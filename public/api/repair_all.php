<?php
// Point d'entrée pour réparation complète du système
// 1. Correction du trigger pour l'abandon des parties
// 2. Correction des matchs nuls contre l'IA (doivent être des défaites)
// 3. Recalcul complet des statistiques des utilisateurs

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/db/Database.php';
require_once __DIR__ . '/../../backend/includes/session.php';

// Vérifier si l'utilisateur est connecté et a les droits d'admin
if (!Session::isLoggedIn() || Session::getUserId() != 1) {
    echo "<h2>Accès refusé</h2>";
    echo "<p>Vous devez être connecté comme administrateur pour accéder à cette fonctionnalité.</p>";
    exit;
}

echo "<h1>Réparation complète du système</h1>";

try {
    $db = Database::getInstance()->getConnection();
    
    // Étape 1: Correction du trigger pour l'abandon des parties
    echo "<h2>1. Correction du trigger pour l'abandon des parties</h2>";
    
    // Supprimer l'ancien trigger s'il existe
    $db->exec("DROP TRIGGER IF EXISTS after_game_finished");
    
    // Créer le nouveau trigger
    $triggerQuery = "
        CREATE TRIGGER after_game_finished
        AFTER UPDATE ON games
        FOR EACH ROW
        BEGIN
            IF NEW.status = 'finished' AND OLD.status != 'finished' THEN
                -- Mise à jour des statistiques du joueur 1
                INSERT INTO stats (user_id, games_played, games_won, games_lost, draws, last_game)
                VALUES (
                    NEW.player1_id, 
                    1, 
                    IF(NEW.result = 'player1_won', 1, 0),
                    IF(NEW.result = 'player2_won', 1, 0),
                    IF(NEW.result = 'draw', 1, 0),
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    games_played = games_played + 1,
                    games_won = games_won + IF(NEW.result = 'player1_won', 1, 0),
                    games_lost = games_lost + IF(NEW.result = 'player2_won', 1, 0),
                    draws = draws + IF(NEW.result = 'draw', 1, 0),
                    last_game = NOW();
                
                -- Mise à jour des statistiques du joueur 2 (seulement s'il n'est pas un bot)
                IF NEW.player2_id != 0 THEN
                    INSERT INTO stats (user_id, games_played, games_won, games_lost, draws, last_game)
                    VALUES (
                        NEW.player2_id, 
                        1, 
                        IF(NEW.result = 'player2_won', 1, 0),
                        IF(NEW.result = 'player1_won', 1, 0),
                        IF(NEW.result = 'draw', 1, 0),
                        NOW()
                    )
                    ON DUPLICATE KEY UPDATE
                        games_played = games_played + 1,
                        games_won = games_won + IF(NEW.result = 'player2_won', 1, 0),
                        games_lost = games_lost + IF(NEW.result = 'player1_won', 1, 0),
                        draws = draws + IF(NEW.result = 'draw', 1, 0),
                        last_game = NOW();
                END IF;
            END IF;
        END
    ";
    
    $db->exec($triggerQuery);
    echo "<p style='color:green'>✓ Trigger corrigé avec succès</p>";
    
    // Étape 2: Correction des matchs nuls contre l'IA
    echo "<h2>2. Correction des matchs nuls contre l'IA</h2>";
    
    // Récupérer toutes les parties terminées
    $gamesQuery = "SELECT * FROM games WHERE status = 'finished' AND result = 'draw'";
    $gamesStmt = $db->query($gamesQuery);
    $games = $gamesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fixedCount = 0;
    
    foreach ($games as $game) {
        // Vérifier s'il s'agit d'une partie contre l'IA
        $isAgainstAI = ($game['player2_id'] == 0);
        
        // Si c'est une partie contre l'IA avec match nul, elle devrait être une défaite pour le joueur humain
        if ($isAgainstAI) {
            // Mettre à jour le résultat de la partie
            $updateGameQuery = "UPDATE games SET result = 'player2_won', winner_id = 0 WHERE id = ?";
            $updateGameStmt = $db->prepare($updateGameQuery);
            $updateGameStmt->execute([$game['id']]);
            $fixedCount++;
            
            echo "<p>Partie ID {$game['id']}: Match nul contre l'IA corrigé en défaite pour le joueur {$game['player1_id']}</p>";
        }
    }
    
    echo "<p style='color:green'>✓ {$fixedCount} parties corrigées</p>";
    
    // Étape 3: Recalcul complet des statistiques
    echo "<h2>3. Recalcul des statistiques des utilisateurs</h2>";
    
    // Récupérer tous les utilisateurs
    $userQuery = "SELECT id, username FROM users";
    $userStmt = $db->query($userQuery);
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Recalcul des statistiques pour " . count($users) . " utilisateurs...</p>";
    
    foreach ($users as $user) {
        $userId = $user['id'];
        $username = $user['username'];
        
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
        
        // Récupérer toutes les parties de l'utilisateur
        $gamesQuery = "SELECT * FROM games 
                      WHERE (player1_id = ? OR player2_id = ?) 
                      AND status = 'finished'
                      ORDER BY updated_at DESC";
        
        $gamesStmt = $db->prepare($gamesQuery);
        $gamesStmt->execute([$userId, $userId]);
        $games = $gamesStmt->fetchAll(PDO::FETCH_ASSOC);
        
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
                } else {
                    // Un vrai match nul entre joueurs humains
                    $draws++;
                }
            } else {
                $isWinner = ($isPlayer1 && $game['result'] === 'player1_won') || 
                          (!$isPlayer1 && $game['result'] === 'player2_won');
                
                if ($isWinner) {
                    $gamesWon++;
                } else {
                    $gamesLost++;
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
        
        echo "<p>Utilisateur {$username} (ID: {$userId}): {$gamesPlayed} parties, {$gamesWon} victoires, {$gamesLost} défaites, {$draws} matchs nuls</p>";
    }
    
    echo "<p style='color:green'>✓ Statistiques recalculées avec succès pour tous les utilisateurs</p>";
    
    echo "<h2 style='color:green'>Réparation complète terminée avec succès!</h2>";
    echo "<p>Les problèmes suivants ont été corrigés:</p>";
    echo "<ul>";
    echo "<li>Le trigger d'abandon des parties a été corrigé</li>";
    echo "<li>Les matchs nuls contre l'IA ont été correctement marqués comme des défaites</li>";
    echo "<li>Les statistiques de tous les utilisateurs ont été recalculées</li>";
    echo "</ul>";
    echo "<p>Vous pouvez maintenant <a href='/'>retourner au jeu</a>.</p>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red'>Erreur lors de la réparation :</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
} 