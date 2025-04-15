<?php
// Activation du rapport d'erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclusion des fichiers nécessaires
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../db/Database.php';

try {
    // Obtenir une connexion à la base de données
    $db = Database::getInstance()->getConnection();
    
    // 1. Réinitialiser la table des statistiques
    $db->query("TRUNCATE TABLE stats");
    echo "✓ Table des statistiques réinitialisée<br>";
    
    // 2. Initialiser les statistiques pour tous les utilisateurs
    $db->query("
        INSERT INTO stats (user_id, games_played, games_won, games_lost, draws, last_game)
        SELECT id, 0, 0, 0, 0, NULL FROM users
    ");
    echo "✓ Statistiques initialisées pour tous les utilisateurs<br>";
    
    // 3. Appliquer le nouveau trigger
    $sqlFile = file_get_contents(__DIR__ . '/../db/fix_trigger.sql');
    $queries = explode('DELIMITER //', $sqlFile);
    
    foreach ($queries as $query) {
        if (empty(trim($query))) continue;
        
        // Nettoyer la requête
        $query = str_replace('DELIMITER ;', '', $query);
        $query = trim($query);
        
        if (!empty($query)) {
            $db->exec($query);
        }
    }
    echo "✓ Nouveau trigger installé<br>";
    
    // 4. Récupérer toutes les parties terminées
    $stmt = $db->query("
        SELECT 
            id,
            player1_id,
            player2_id,
            winner_id,
            status,
            updated_at
        FROM games 
        WHERE status = 'finished'
        ORDER BY updated_at ASC
    ");
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Traitement de " . count($games) . " parties terminées...<br>";
    
    // 5. Mettre à jour les statistiques pour chaque partie
    foreach ($games as $game) {
        // Partie contre un bot
        if ($game['player2_id'] == 0) {
            $stmt = $db->prepare("
                UPDATE stats SET
                    games_played = games_played + 1,
                    games_won = games_won + IF(? = ?, 1, 0),
                    games_lost = games_lost + IF(? = 0 OR ? IS NULL, 1, 0),
                    last_game = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $game['winner_id'],
                $game['player1_id'],
                $game['winner_id'],
                $game['winner_id'],
                $game['updated_at'],
                $game['player1_id']
            ]);
        }
        // Partie entre deux joueurs
        else {
            // Match nul
            if ($game['winner_id'] === null) {
                $stmt = $db->prepare("
                    UPDATE stats SET
                        games_played = games_played + 1,
                        draws = draws + 1,
                        last_game = ?
                    WHERE user_id IN (?, ?)
                ");
                $stmt->execute([
                    $game['updated_at'],
                    $game['player1_id'],
                    $game['player2_id']
                ]);
            }
            // Victoire/Défaite
            else {
                // Mettre à jour le gagnant
                $stmt = $db->prepare("
                    UPDATE stats SET
                        games_played = games_played + 1,
                        games_won = games_won + 1,
                        last_game = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $game['updated_at'],
                    $game['winner_id']
                ]);
                
                // Mettre à jour le perdant
                $loser_id = ($game['winner_id'] == $game['player1_id']) 
                    ? $game['player2_id'] 
                    : $game['player1_id'];
                
                $stmt = $db->prepare("
                    UPDATE stats SET
                        games_played = games_played + 1,
                        games_lost = games_lost + 1,
                        last_game = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $game['updated_at'],
                    $loser_id
                ]);
            }
        }
    }
    
    echo "✓ Statistiques recalculées avec succès<br>";
    
    // 6. Vérifier les résultats
    $stmt = $db->query("
        SELECT u.username, s.* 
        FROM users u 
        JOIN stats s ON u.id = s.user_id 
        ORDER BY s.games_played DESC
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Statistiques mises à jour :</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>
            <th>Joueur</th>
            <th>Parties jouées</th>
            <th>Victoires</th>
            <th>Défaites</th>
            <th>Matchs nuls</th>
            <th>Dernière partie</th>
          </tr>";
    
    foreach ($stats as $stat) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($stat['username']) . "</td>";
        echo "<td>" . $stat['games_played'] . "</td>";
        echo "<td>" . $stat['games_won'] . "</td>";
        echo "<td>" . $stat['games_lost'] . "</td>";
        echo "<td>" . $stat['draws'] . "</td>";
        echo "<td>" . ($stat['last_game'] ?? 'Jamais') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p>✓ Correction terminée avec succès !</p>";
    echo "<p><a href='/game/history.php'>Retourner à l'historique des parties</a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red'>Erreur :</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}