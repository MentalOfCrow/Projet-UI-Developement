<?php
// Script de réparation des statistiques (version CLI)
// À exécuter depuis le dossier backend/tools avec php repair_stats.php

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enregistrer toutes les erreurs dans un fichier log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../db/Database.php';

// Se connecter à la base de données
$db = Database::getInstance()->getConnection();

echo "Réparation des statistiques des utilisateurs...\n\n";

// 1. Vérifier que la table stats existe
try {
    $db->exec("CREATE TABLE IF NOT EXISTS stats (
        user_id INT PRIMARY KEY,
        games_played INT DEFAULT 0 COMMENT 'Nombre total de parties jouées',
        games_won INT DEFAULT 0 COMMENT 'Nombre de parties gagnées',
        games_lost INT DEFAULT 0 COMMENT 'Nombre de parties perdues',
        last_game TIMESTAMP NULL COMMENT 'Date de la dernière partie',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Table stats vérifiée/créée avec succès.\n";
} catch (PDOException $e) {
    echo "Erreur lors de la vérification/création de la table stats: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Récupérer tous les utilisateurs
try {
    $users = $db->query("SELECT id, username FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "Trouvé " . count($users) . " utilisateurs.\n\n";
} catch (PDOException $e) {
    echo "Erreur lors de la récupération des utilisateurs: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Réinitialiser les statistiques (optionnel)
echo "Réinitialisation des statistiques...\n";
try {
    $db->exec("TRUNCATE TABLE stats");
    echo "Statistiques réinitialisées avec succès.\n\n";
} catch (PDOException $e) {
    echo "Erreur lors de la réinitialisation des statistiques: " . $e->getMessage() . "\n";
    // Continuer malgré l'erreur
}

// 4. Recalculer les statistiques pour chaque utilisateur
foreach ($users as $user) {
    $user_id = $user['id'];
    $username = $user['username'];
    
    echo "Traitement des statistiques pour {$username} (ID: {$user_id})...\n";
    
    try {
        // Récupérer toutes les parties terminées pour cet utilisateur
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
        $gamesStmt->execute([$user_id, $user_id]);
        
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
            if ($game['winner_id'] == $user_id) {
                $gamesWon++;
            } 
            // Vérifier si le joueur a perdu (seulement si un gagnant est défini)
            else if ($game['winner_id'] !== null && $game['winner_id'] != $user_id) {
                $gamesLost++;
            }
            // Si winner_id est null et c'est contre un bot, c'est une défaite
            else if ($game['winner_id'] === null && 
                    (($game['player1_id'] == $user_id && $game['player2_id'] == 0) || 
                      ($game['player2_id'] == $user_id && $game['player1_id'] == 0))) {
                $gamesLost++;
            }
        }
        
        echo "  - Parties jouées: {$gamesPlayed}\n";
        echo "  - Victoires: {$gamesWon}\n";
        echo "  - Défaites: {$gamesLost}\n";
        
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
                $user_id, $gamesPlayed, $gamesWon, $gamesLost, $lastGame,
                $gamesPlayed, $gamesWon, $gamesLost, $lastGame
            ]);
            
            echo "  - Statistiques mises à jour avec succès.\n";
        } else {
            echo "  - Aucune partie trouvée pour cet utilisateur.\n";
        }
    } catch (PDOException $e) {
        echo "  - Erreur lors de la mise à jour des statistiques: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// 5. Vérifier/créer le trigger pour les mises à jour futures
echo "Vérification du trigger pour les mises à jour futures...\n";
try {
    // Vérifier si le trigger existe
    $triggerExists = $db->query("SHOW TRIGGERS LIKE 'after_game_finished'")->rowCount() > 0;
    
    if (!$triggerExists) {
        echo "Création du trigger after_game_finished...\n";
        
        // Supprimer le trigger s'il existe
        $db->exec("DROP TRIGGER IF EXISTS after_game_finished");
        
        // Créer le trigger
        $db->exec("
        CREATE TRIGGER after_game_finished
        AFTER UPDATE ON games
        FOR EACH ROW
        BEGIN
            IF NEW.status = 'finished' AND OLD.status != 'finished' THEN
                -- Mise à jour des statistiques du joueur 1
                INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game)
                VALUES (NEW.player1_id, 1, IF(NEW.winner_id = NEW.player1_id, 1, 0), IF(NEW.winner_id != NEW.player1_id AND NEW.winner_id IS NOT NULL, 1, 0), NOW())
                ON DUPLICATE KEY UPDATE
                    games_played = games_played + 1,
                    games_won = games_won + IF(NEW.winner_id = NEW.player1_id, 1, 0),
                    games_lost = games_lost + IF(NEW.winner_id != NEW.player1_id AND NEW.winner_id IS NOT NULL, 1, 0),
                    last_game = NOW();
                
                -- Mise à jour des statistiques du joueur 2 (seulement s'il n'est pas un bot)
                IF NEW.player2_id != 0 THEN
                    INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game)
                    VALUES (NEW.player2_id, 1, IF(NEW.winner_id = NEW.player2_id, 1, 0), IF(NEW.winner_id != NEW.player2_id AND NEW.winner_id IS NOT NULL, 1, 0), NOW())
                    ON DUPLICATE KEY UPDATE
                        games_played = games_played + 1,
                        games_won = games_won + IF(NEW.winner_id = NEW.player2_id, 1, 0),
                        games_lost = games_lost + IF(NEW.winner_id != NEW.player2_id AND NEW.winner_id IS NOT NULL, 1, 0),
                        last_game = NOW();
                END IF;
            END IF;
        END
        ");
        
        echo "Trigger créé avec succès.\n";
    } else {
        echo "Le trigger after_game_finished existe déjà.\n";
    }
} catch (PDOException $e) {
    echo "Erreur lors de la vérification/création du trigger: " . $e->getMessage() . "\n";
}

echo "\nRéparation des statistiques terminée avec succès.\n";

// Fonction pour réparer les statistiques d'un utilisateur spécifique
function repairUserStats($userId) {
    if (empty($userId) || !is_numeric($userId)) {
        error_log("ID utilisateur non valide: " . print_r($userId, true));
        return false;
    }
    
    try {
        // Connexion à la base de données
        require_once __DIR__ . '/../db/Database.php';
        $db = Database::getInstance()->getConnection();
        
        // Vérifier si l'utilisateur existe
        $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            error_log("Utilisateur non trouvé: ID = " . $userId);
            return false;
        }
        
        // Vérifier si la table stats existe, sinon la créer
        $tableExists = false;
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'stats'");
            $tableExists = ($stmt->rowCount() > 0);
        } catch (Exception $e) {
            error_log("Erreur lors de la vérification de la table stats: " . $e->getMessage());
        }
        
        if (!$tableExists) {
            // Créer la table stats
            $sql = "CREATE TABLE IF NOT EXISTS stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL UNIQUE,
                games_played INT DEFAULT 0,
                games_won INT DEFAULT 0,
                games_lost INT DEFAULT 0,
                last_game DATETIME,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )";
            $db->exec($sql);
            error_log("Table stats créée avec succès");
        }
        
        // Statistiques calculées
        $gamesPlayed = 0;
        $gamesWon = 0;
        $gamesLost = 0;
        $lastGame = null;
        
        // Récupérer toutes les parties terminées de cet utilisateur
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
        $gamesPlayed = count($games);
        
        foreach ($games as $game) {
            // Mise à jour de la date de dernière partie
            if ($lastGame === null || strtotime($game['updated_at']) > strtotime($lastGame)) {
                $lastGame = $game['updated_at'];
            }
            
            // Vérifier si le joueur a gagné
            if ($game['winner_id'] == $userId) {
                $gamesWon++;
            } 
            // Vérifier si le joueur a perdu
            else if ($game['winner_id'] !== null && $game['winner_id'] != $userId) {
                $gamesLost++;
            }
            // Cas spécial: partie contre l'IA avec winner_id null = défaite
            else if ($game['winner_id'] === null && 
                    (($game['player1_id'] == $userId && ($game['player2_id'] == 0 || $game['player2_id'] === '0')) || 
                     ($game['player2_id'] == $userId && ($game['player1_id'] == 0 || $game['player1_id'] === '0')))) {
                $gamesLost++;
            }
            // Sinon c'est un match nul (winner_id === null et pas une partie contre l'IA)
        }
        
        // Mettre à jour ou insérer les statistiques
        try {
            // Vérifier si l'utilisateur a déjà des statistiques
            $stmt = $db->prepare("SELECT id FROM stats WHERE user_id = ?");
            $stmt->execute([$userId]);
            $statsExist = ($stmt->rowCount() > 0);
            
            if ($statsExist) {
                // Mettre à jour les statistiques existantes
                $stmt = $db->prepare("
                    UPDATE stats 
                    SET games_played = ?, games_won = ?, games_lost = ?, last_game = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([$gamesPlayed, $gamesWon, $gamesLost, $lastGame, $userId]);
                error_log("Statistiques mises à jour pour l'utilisateur ID " . $userId . ": " . $gamesPlayed . " parties, " . $gamesWon . " victoires, " . $gamesLost . " défaites");
            } else {
                // Insérer de nouvelles statistiques
                $stmt = $db->prepare("
                    INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $gamesPlayed, $gamesWon, $gamesLost, $lastGame]);
                error_log("Statistiques créées pour l'utilisateur ID " . $userId . ": " . $gamesPlayed . " parties, " . $gamesWon . " victoires, " . $gamesLost . " défaites");
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour des statistiques: " . $e->getMessage());
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Erreur lors de la réparation des statistiques: " . $e->getMessage());
        return false;
    }
}

// Si exécuté directement depuis la ligne de commande
if (isset($argv) && basename(__FILE__) === basename($argv[0])) {
    // Vérifier si un ID utilisateur est fourni
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $userId = intval($argv[1]);
        echo "Réparation des statistiques pour l'utilisateur ID: $userId\n";
        
        if (repairUserStats($userId)) {
            echo "Statistiques réparées avec succès.\n";
            exit(0);
        } else {
            echo "Échec de la réparation des statistiques.\n";
            exit(1);
        }
    } else {
        echo "Usage: php repair_stats.php [user_id]\n";
        exit(1);
    }
}
?> 