<?php
// Script pour réparer les statistiques des joueurs
// À exécuter manuellement en cas de problème avec la mise à jour des statistiques

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enregistrer toutes les erreurs dans un fichier log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Inclure les fichiers nécessaires
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../db/Database.php';

echo "Début de la réparation des statistiques...\n";

try {
    // Obtenir une connexion à la base de données
    $db = Database::getInstance()->getConnection();
    
    // 1. Créer la table stats si elle n'existe pas
    $db->exec("CREATE TABLE IF NOT EXISTS stats (
        user_id INT PRIMARY KEY,
        games_played INT DEFAULT 0,
        games_won INT DEFAULT 0,
        games_lost INT DEFAULT 0,
        last_game TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    echo "Table stats vérifiée/créée avec succès\n";
    
    // 2. Vérifier que le trigger existe
    $checkTriggerQuery = "SHOW TRIGGERS LIKE 'after_game_finished'";
    $triggerExists = $db->query($checkTriggerQuery)->rowCount() > 0;
    
    if (!$triggerExists) {
        echo "Création du trigger manquant...\n";
        
        // Supprimer le trigger s'il existe déjà
        $db->exec("DROP TRIGGER IF EXISTS after_game_finished");
        
        $db->exec("
            CREATE TRIGGER after_game_finished
            AFTER UPDATE ON games
            FOR EACH ROW
            BEGIN
                IF NEW.status = 'finished' AND OLD.status != 'finished' THEN
                    CALL update_player_stats_after_game(NEW.id);
                END IF;
            END
        ");
        
        echo "Trigger créé avec succès\n";
    } else {
        echo "Le trigger existe déjà\n";
    }
    
    // 3. Vérifier que la procédure stockée existe
    $checkProcedureQuery = "SHOW PROCEDURE STATUS WHERE Name = 'update_player_stats_after_game'";
    $procedureExists = $db->query($checkProcedureQuery)->rowCount() > 0;
    
    if (!$procedureExists) {
        echo "Création de la procédure stockée manquante...\n";
        
        // Supprimer la procédure si elle existe déjà
        $db->exec("DROP PROCEDURE IF EXISTS update_player_stats_after_game");
        
        $db->exec("
            CREATE PROCEDURE update_player_stats_after_game(IN p_game_id INT)
            BEGIN
                DECLARE p1_id INT;
                DECLARE p2_id INT;
                DECLARE winner_id INT;
                
                -- Récupérer les informations de la partie
                SELECT player1_id, player2_id, winner_id 
                INTO p1_id, p2_id, winner_id
                FROM games 
                WHERE id = p_game_id;
                
                -- Mettre à jour les statistiques du joueur 1
                INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game)
                VALUES (p1_id, 1, IF(winner_id = p1_id, 1, 0), IF(winner_id != p1_id AND winner_id IS NOT NULL, 1, 0), NOW())
                ON DUPLICATE KEY UPDATE
                    games_played = games_played + 1,
                    games_won = games_won + IF(winner_id = p1_id, 1, 0),
                    games_lost = games_lost + IF(winner_id != p1_id AND winner_id IS NOT NULL, 1, 0),
                    last_game = NOW();
                
                -- Mettre à jour les statistiques du joueur 2 (s'il n'est pas un bot)
                IF p2_id IS NOT NULL AND p2_id != 0 THEN
                    INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game)
                    VALUES (p2_id, 1, IF(winner_id = p2_id, 1, 0), IF(winner_id != p2_id AND winner_id IS NOT NULL, 1, 0), NOW())
                    ON DUPLICATE KEY UPDATE
                        games_played = games_played + 1,
                        games_won = games_won + IF(winner_id = p2_id, 1, 0),
                        games_lost = games_lost + IF(winner_id != p2_id AND winner_id IS NOT NULL, 1, 0),
                        last_game = NOW();
                END IF;
            END
        ");
        
        echo "Procédure stockée créée avec succès\n";
    } else {
        echo "La procédure stockée existe déjà\n";
    }
    
    // 4. Réinitialiser les statistiques
    echo "Réinitialisation des statistiques...\n";
    $db->exec("TRUNCATE TABLE stats");
    
    // 5. Recalculer toutes les statistiques à partir des parties terminées
    echo "Recalcul des statistiques à partir des parties terminées...\n";
    
    // Récupérer tous les joueurs
    $usersQuery = "SELECT id FROM users";
    $usersStmt = $db->query($usersQuery);
    $users = $usersStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $totalUpdated = 0;
    
    foreach ($users as $userId) {
        // Pour chaque joueur, récupérer ses parties terminées
        $gamesQuery = "SELECT 
                          id, 
                          player1_id, 
                          player2_id, 
                          winner_id,
                          updated_at
                      FROM games 
                      WHERE (player1_id = ? OR player2_id = ?) 
                      AND status = 'finished'";
        
        $gamesStmt = $db->prepare($gamesQuery);
        $gamesStmt->execute([$userId, $userId]);
        
        $games = $gamesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $gamesPlayed = 0;
        $gamesWon = 0;
        $gamesLost = 0;
        $lastGame = null;
        
        foreach ($games as $game) {
            $gamesPlayed++;
            
            // Mise à jour de la date de dernière partie
            if ($lastGame === null || strtotime($game['updated_at']) > strtotime($lastGame)) {
                $lastGame = $game['updated_at'];
            }
            
            // Vérifier si le joueur a gagné
            if ($game['winner_id'] == $userId) {
                $gamesWon++;
            } 
            // Vérifier si le joueur a perdu (seulement si un gagnant est défini)
            else if ($game['winner_id'] !== null && $game['winner_id'] != $userId) {
                $gamesLost++;
            }
            // Si winner_id est null et c'est contre un bot, c'est une défaite
            else if ($game['winner_id'] === null && 
                    (($game['player1_id'] == $userId && $game['player2_id'] == 0) || 
                     ($game['player2_id'] == $userId && $game['player1_id'] == 0))) {
                $gamesLost++;
            }
        }
        
        if ($gamesPlayed > 0) {
            // Mettre à jour les statistiques de ce joueur
            $updateQuery = "INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game) 
                          VALUES (?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE 
                              games_played = ?, 
                              games_won = ?, 
                              games_lost = ?, 
                              last_game = ?";
            
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->execute([
                $userId, $gamesPlayed, $gamesWon, $gamesLost, $lastGame,
                $gamesPlayed, $gamesWon, $gamesLost, $lastGame
            ]);
            
            $totalUpdated++;
            
            echo "Joueur ID $userId: $gamesPlayed parties, $gamesWon victoires, $gamesLost défaites\n";
        }
    }
    
    echo "Terminé! $totalUpdated joueurs mis à jour.\n";
    
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
} 