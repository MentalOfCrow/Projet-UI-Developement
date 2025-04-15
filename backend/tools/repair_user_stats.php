<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/db/Database.php';

echo "<h1>Réparation des statistiques des utilisateurs</h1>";

try {
    $db = Database::getInstance()->getConnection();
    
    // Récupérer tous les utilisateurs
    $userQuery = "SELECT id, username FROM users";
    $userStmt = $db->query($userQuery);
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Recalcul des statistiques pour " . count($users) . " utilisateurs...</p>";
    
    foreach ($users as $user) {
        $userId = $user['id'];
        $username = $user['username'];
        
        echo "<h2>Utilisateur: {$username} (ID: {$userId})</h2>";
        
        // Réinitialiser les statistiques
        $resetStatsQuery = "INSERT INTO stats (user_id, games_played, games_won, games_lost, draws, last_game) 
                            VALUES (?, 0, 0, 0, 0, NULL)
                            ON DUPLICATE KEY UPDATE 
                            games_played = 0,
                            games_won = 0,
                            games_lost = 0,
                            draws = 0,
                            last_game = NULL";
        
        $resetStatsStmt = $db->prepare($resetStatsQuery);
        $resetStatsStmt->execute([$userId]);
        
        echo "<p>Statistiques réinitialisées</p>";
        
        // Récupérer toutes les parties de l'utilisateur
        $gamesQuery = "SELECT * FROM games 
                      WHERE (player1_id = ? OR player2_id = ?) 
                      AND status = 'finished'
                      ORDER BY updated_at DESC";
        
        $gamesStmt = $db->prepare($gamesQuery);
        $gamesStmt->execute([$userId, $userId]);
        $games = $gamesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>Nombre de parties terminées trouvées: " . count($games) . "</p>";
        
        // Statistiques
        $gamesPlayed = 0;
        $gamesWon = 0;
        $gamesLost = 0;
        $draws = 0;
        $lastGameDate = null;
        
        foreach ($games as $game) {
            $gamesPlayed++;
            
            // Déterminer le résultat
            $isPlayer1 = ($game['player1_id'] == $userId);
            $isAgainstAI = ($isPlayer1 && $game['player2_id'] == 0) || (!$isPlayer1 && $game['player1_id'] == 0);
            
            // Mettre à jour la dernière date de jeu
            if ($lastGameDate === null || strtotime($game['updated_at']) > strtotime($lastGameDate)) {
                $lastGameDate = $game['updated_at'];
            }
            
            // Vérifier le résultat de la partie
            if ($game['result'] === 'draw') {
                // Contre l'IA, un match nul est en réalité une défaite
                if ($isAgainstAI) {
                    $gamesLost++;
                    echo "<p>Partie ID {$game['id']}: Défaite contre l'IA (était marquée comme match nul)</p>";
                    
                    // Corriger le résultat dans la base de données
                    if ($isPlayer1) {
                        $updateGameQuery = "UPDATE games SET result = 'player2_won', winner_id = 0 WHERE id = ?";
                        $updateGameStmt = $db->prepare($updateGameQuery);
                        $updateGameStmt->execute([$game['id']]);
                    } else {
                        $updateGameQuery = "UPDATE games SET result = 'player1_won', winner_id = ? WHERE id = ?";
                        $updateGameStmt = $db->prepare($updateGameQuery);
                        $updateGameStmt->execute([$game['player1_id'], $game['id']]);
                    }
                } else {
                    // Un vrai match nul entre joueurs humains
                    $draws++;
                    echo "<p>Partie ID {$game['id']}: Match nul</p>";
                }
            } else {
                $isWinner = ($isPlayer1 && $game['result'] === 'player1_won') || 
                          (!$isPlayer1 && $game['result'] === 'player2_won');
                
                if ($isWinner) {
                    $gamesWon++;
                    echo "<p>Partie ID {$game['id']}: Victoire</p>";
                } else {
                    $gamesLost++;
                    echo "<p>Partie ID {$game['id']}: Défaite</p>";
                }
            }
        }
        
        // Mettre à jour les statistiques avec les valeurs correctes
        $updateStatsQuery = "UPDATE stats SET 
                            games_played = ?,
                            games_won = ?,
                            games_lost = ?,
                            draws = ?,
                            last_game = ?
                            WHERE user_id = ?";
        
        $updateStatsStmt = $db->prepare($updateStatsQuery);
        $updateStatsStmt->execute([$gamesPlayed, $gamesWon, $gamesLost, $draws, $lastGameDate, $userId]);
        
        echo "<p style='color:green'>✓ Statistiques mises à jour: {$gamesPlayed} parties, {$gamesWon} victoires, {$gamesLost} défaites, {$draws} matchs nuls</p>";
    }
    
    echo "<h2 style='color:green'>Réparation des statistiques terminée avec succès!</h2>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red'>Erreur lors de la réparation :</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
} 