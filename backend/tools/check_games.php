<?php
// Script pour vérifier les parties dans la base de données
// Ce script affiche toutes les parties et leurs statuts

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Charger les dépendances
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/db/Database.php';

// Se connecter à la base de données
$db = Database::getInstance()->getConnection();

echo "<h1>Vérification des parties de jeu</h1>";

// 1. Vérifier la structure de la table games
try {
    $stmt = $db->query("DESCRIBE games");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Structure de la table games</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color:red'>Erreur lors de la lecture de la structure de la table games: " . $e->getMessage() . "</p>";
}

// 2. Compter les parties par statut
try {
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM games GROUP BY status");
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Nombre de parties par statut</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Statut</th><th>Nombre</th></tr>";
    
    foreach ($statusCounts as $statusCount) {
        echo "<tr>";
        echo "<td>" . $statusCount['status'] . "</td>";
        echo "<td>" . $statusCount['count'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color:red'>Erreur lors du comptage des parties par statut: " . $e->getMessage() . "</p>";
}

// 3. Vérifier les parties contre des bots
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM games WHERE player2_id = 0");
    $botGamesCount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>Parties contre des bots</h2>";
    echo "<p>Nombre total de parties contre des bots: " . $botGamesCount['count'] . "</p>";
    
    // Détails des parties contre des bots
    if ($botGamesCount['count'] > 0) {
        $stmt = $db->query("SELECT g.*, u.username FROM games g JOIN users u ON g.player1_id = u.id WHERE g.player2_id = 0 ORDER BY g.created_at DESC LIMIT 20");
        $botGames = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Dernières parties contre des bots</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr>
                <th>ID</th>
                <th>Joueur</th>
                <th>Statut</th>
                <th>Gagnant</th>
                <th>Créée le</th>
                <th>Mise à jour le</th>
            </tr>";
            
        foreach ($botGames as $game) {
            $winnerInfo = "N/A";
            if ($game['winner_id'] !== null) {
                if ($game['winner_id'] == $game['player1_id']) {
                    $winnerInfo = $game['username'] . " (humain)";
                } else if ($game['winner_id'] == 0) {
                    $winnerInfo = "IA";
                } else {
                    $winnerInfo = "ID: " . $game['winner_id'] . " (inconnu)";
                }
            }
            
            echo "<tr>";
            echo "<td>" . $game['id'] . "</td>";
            echo "<td>" . $game['username'] . " (ID: " . $game['player1_id'] . ")</td>";
            echo "<td>" . $game['status'] . "</td>";
            echo "<td>" . $winnerInfo . "</td>";
            echo "<td>" . $game['created_at'] . "</td>";
            echo "<td>" . $game['updated_at'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>Erreur lors de la vérification des parties contre des bots: " . $e->getMessage() . "</p>";
}

// 4. Vérifier les statistiques des joueurs
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM stats");
    $statsCount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>Statistiques des joueurs</h2>";
    echo "<p>Nombre d'entrées dans la table stats: " . $statsCount['count'] . "</p>";
    
    // Détails des statistiques des joueurs
    if ($statsCount['count'] > 0) {
        $stmt = $db->query("SELECT s.*, u.username FROM stats s JOIN users u ON s.user_id = u.id ORDER BY s.games_played DESC LIMIT 20");
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Statistiques des joueurs les plus actifs</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr>
                <th>Joueur</th>
                <th>Parties jouées</th>
                <th>Victoires</th>
                <th>Défaites</th>
                <th>% Victoires</th>
                <th>Dernière partie</th>
            </tr>";
            
        foreach ($stats as $stat) {
            $winPercentage = $stat['games_played'] > 0 ? round(($stat['games_won'] / $stat['games_played']) * 100, 2) : 0;
            
            echo "<tr>";
            echo "<td>" . $stat['username'] . " (ID: " . $stat['user_id'] . ")</td>";
            echo "<td>" . $stat['games_played'] . "</td>";
            echo "<td>" . $stat['games_won'] . "</td>";
            echo "<td>" . $stat['games_lost'] . "</td>";
            echo "<td>" . $winPercentage . "%</td>";
            echo "<td>" . $stat['last_game'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>Erreur lors de la vérification des statistiques des joueurs: " . $e->getMessage() . "</p>";
}

// 5. Vérifier le trigger
try {
    $stmt = $db->query("SHOW TRIGGERS WHERE `Table` = 'games'");
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Triggers sur la table games</h2>";
    
    if (count($triggers) > 0) {
        echo "<ul>";
        foreach ($triggers as $trigger) {
            echo "<li>" . $trigger['Trigger'] . " - " . $trigger['Statement'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>Aucun trigger trouvé pour la table games!</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>Erreur lors de la vérification des triggers: " . $e->getMessage() . "</p>";
}

// 6. Suggestions de correction
echo "<h2>Suggestions de correction</h2>";
echo "<ul>";
echo "<li>Si vous avez des parties qui ne s'affichent pas dans l'historique, vérifiez leurs statuts : ils doivent être 'in_progress' ou 'finished'.</li>";
echo "<li>Pour les parties contre des bots, vérifiez que player2_id est bien défini à 0 et que les données sont correctement enregistrées.</li>";
echo "<li>Si les statistiques ne sont pas mises à jour, vérifiez la présence du trigger 'after_game_finished' ou exécutez le script init_stats.php.</li>";
echo "<li>Si vous voyez des parties mais qu'elles ne s'affichent pas correctement dans l'interface, redémarrez votre session de navigation.</li>";
echo "</ul>";

echo "<p><a href='init_stats.php'>Initialiser/Réparer les statistiques des joueurs</a></p>";
?> 