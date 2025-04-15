<?php
// Script pour initialiser ou recalculer les statistiques des joueurs
// Ce script parcourt toutes les parties terminées et met à jour les statistiques

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Charger les dépendances
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/db/Database.php';

// Se connecter à la base de données
$db = Database::getInstance()->getConnection();

echo "<h1>Initialisation/Réparation des statistiques de jeu</h1>";

// 1. Vérifier si la table stats existe
try {
    $db->query("SELECT 1 FROM stats LIMIT 1");
    $statsTableExists = true;
} catch (PDOException $e) {
    $statsTableExists = false;
    echo "<p>La table 'stats' n'existe pas. Création en cours...</p>";
    
    // Créer la table stats
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS stats (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            games_played INT NOT NULL DEFAULT 0,
            games_won INT NOT NULL DEFAULT 0,
            games_lost INT NOT NULL DEFAULT 0,
            last_game TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        echo "<p style='color:green'>Table 'stats' créée avec succès!</p>";
        $statsTableExists = true;
    } catch (PDOException $e) {
        echo "<p style='color:red'>Erreur lors de la création de la table stats: " . $e->getMessage() . "</p>";
        exit;
    }
}

// 2. Récupérer tous les utilisateurs
try {
    $stmt = $db->query("SELECT id FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Nombre d'utilisateurs à traiter: " . count($users) . "</p>";
    
    $stats = [
        'users_processed' => 0,
        'stats_created' => 0,
        'stats_updated' => 0,
        'errors' => 0
    ];
    
    // Pour chaque utilisateur, calculer les statistiques
    foreach ($users as $user) {
        $userId = $user['id'];
        
        // Vérifier si l'utilisateur a déjà des statistiques
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM stats WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userStatsExist = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        // Calculer les parties jouées
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM games 
            WHERE (player1_id = ? OR player2_id = ?) AND status = 'finished'");
        $stmt->execute([$userId, $userId]);
        $gamesPlayed = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Calculer les victoires
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM games 
            WHERE winner_id = ? AND status = 'finished'");
        $stmt->execute([$userId]);
        $gamesWon = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Calculer les défaites
        $gamesLost = $gamesPlayed - $gamesWon;
        
        // Obtenir la date de la dernière partie
        $stmt = $db->prepare("SELECT updated_at FROM games 
            WHERE (player1_id = ? OR player2_id = ?) AND status = 'finished' 
            ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$userId, $userId]);
        $lastGameResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastGame = $lastGameResult ? $lastGameResult['updated_at'] : date('Y-m-d H:i:s');
        
        try {
            if ($userStatsExist) {
                // Mettre à jour les statistiques existantes
                $stmt = $db->prepare("UPDATE stats SET 
                    games_played = ?, 
                    games_won = ?, 
                    games_lost = ?, 
                    last_game = ? 
                    WHERE user_id = ?");
                $stmt->execute([$gamesPlayed, $gamesWon, $gamesLost, $lastGame, $userId]);
                $stats['stats_updated']++;
            } else {
                // Créer de nouvelles statistiques
                $stmt = $db->prepare("INSERT INTO stats 
                    (user_id, games_played, games_won, games_lost, last_game) 
                    VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $gamesPlayed, $gamesWon, $gamesLost, $lastGame]);
                $stats['stats_created']++;
            }
            
            $stats['users_processed']++;
        } catch (PDOException $e) {
            echo "<p style='color:red'>Erreur lors du traitement de l'utilisateur ID " . $userId . ": " . $e->getMessage() . "</p>";
            $stats['errors']++;
        }
    }
    
    echo "<h2>Résumé des opérations</h2>";
    echo "<ul>";
    echo "<li>Utilisateurs traités: " . $stats['users_processed'] . "</li>";
    echo "<li>Statistiques créées: " . $stats['stats_created'] . "</li>";
    echo "<li>Statistiques mises à jour: " . $stats['stats_updated'] . "</li>";
    echo "<li>Erreurs: " . $stats['errors'] . "</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Erreur lors de la récupération des utilisateurs: " . $e->getMessage() . "</p>";
}

// 3. Vérifier la création d'un trigger pour maintenir les statistiques à jour
try {
    $stmt = $db->query("SHOW TRIGGERS WHERE `Table` = 'games'");
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $triggerExists = false;
    
    foreach ($triggers as $trigger) {
        if (strpos($trigger['Trigger'], 'after_game_finished') !== false) {
            $triggerExists = true;
            break;
        }
    }
    
    if (!$triggerExists) {
        echo "<h2>Création du trigger de mise à jour automatique</h2>";
        
        try {
            $db->exec("
                CREATE TRIGGER after_game_finished
                AFTER UPDATE ON games
                FOR EACH ROW
                BEGIN
                    IF NEW.status = 'finished' AND OLD.status != 'finished' THEN
                        -- Mise à jour pour le joueur 1
                        UPDATE stats SET 
                            games_played = games_played + 1,
                            games_won = CASE WHEN NEW.winner_id = NEW.player1_id THEN games_won + 1 ELSE games_won END,
                            games_lost = CASE WHEN NEW.winner_id != NEW.player1_id AND NEW.winner_id IS NOT NULL THEN games_lost + 1 ELSE games_lost END,
                            last_game = NEW.updated_at
                        WHERE user_id = NEW.player1_id;
                        
                        -- Mise à jour pour le joueur 2 (seulement si ce n'est pas un bot)
                        IF NEW.player2_id != 0 THEN
                            UPDATE stats SET 
                                games_played = games_played + 1,
                                games_won = CASE WHEN NEW.winner_id = NEW.player2_id THEN games_won + 1 ELSE games_won END,
                                games_lost = CASE WHEN NEW.winner_id != NEW.player2_id AND NEW.winner_id IS NOT NULL THEN games_lost + 1 ELSE games_lost END,
                                last_game = NEW.updated_at
                            WHERE user_id = NEW.player2_id;
                        END IF;
                    END IF;
                END;
            ");
            echo "<p style='color:green'>Trigger 'after_game_finished' créé avec succès!</p>";
        } catch (PDOException $e) {
            echo "<p style='color:red'>Erreur lors de la création du trigger: " . $e->getMessage() . "</p>";
            echo "<p>Note: Certains hébergeurs ne permettent pas la création de triggers. Si c'est le cas, vous devrez exécuter ce script manuellement après chaque partie pour mettre à jour les statistiques.</p>";
        }
    } else {
        echo "<p style='color:green'>Le trigger 'after_game_finished' existe déjà.</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>Erreur lors de la vérification des triggers: " . $e->getMessage() . "</p>";
}

echo "<h2>Vérification finale des statistiques</h2>";
try {
    $stmt = $db->query("SELECT s.*, u.username FROM stats s JOIN users u ON s.user_id = u.id ORDER BY s.games_played DESC LIMIT 10");
    $finalStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($finalStats) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr>
                <th>Joueur</th>
                <th>Parties jouées</th>
                <th>Victoires</th>
                <th>Défaites</th>
                <th>% Victoires</th>
                <th>Dernière partie</th>
            </tr>";
            
        foreach ($finalStats as $stat) {
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
    } else {
        echo "<p>Aucune statistique trouvée après initialisation.</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>Erreur lors de la vérification finale: " . $e->getMessage() . "</p>";
}

echo "<p><a href='check_games.php'>Retour à la vérification des jeux</a></p>";
echo "<p><a href='/game/history.php'>Aller à l'historique des parties</a></p>";
?> 