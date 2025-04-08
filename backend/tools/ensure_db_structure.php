<?php
// Script pour vérifier et mettre à jour la structure de la base de données
// Assure que les tables, triggers et procédures stockées sont correctement configurés

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

echo "Vérification et mise à jour de la structure de la base de données...\n";
$startTime = microtime(true);

try {
    // Obtenir une connexion à la base de données
    $db = Database::getInstance()->getConnection();
    
    // Vérifier les tables essentielles
    $requiredTables = ['users', 'games', 'moves', 'stats', 'queue'];
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        $checkTableQuery = "SHOW TABLES LIKE '$table'";
        $checkTableStmt = $db->query($checkTableQuery);
        
        if ($checkTableStmt->rowCount() == 0) {
            $missingTables[] = $table;
        }
    }
    
    if (!empty($missingTables)) {
        echo "Tables manquantes détectées: " . implode(', ', $missingTables) . "\n";
        echo "Exécution du script d'initialisation complet de la base de données...\n";
        
        // Exécuter le script SQL complet
        $sqlFile = file_get_contents(__DIR__ . '/../db/db.sql');
        $db->exec($sqlFile);
        
        echo "Structure de base de données recréée avec succès.\n";
    } else {
        echo "Toutes les tables requises existent.\n";
        
        // Vérifier la procédure stockée update_player_stats_after_game
        $checkProcedureQuery = "SHOW PROCEDURE STATUS WHERE Name = 'update_player_stats_after_game'";
        $procedureExists = $db->query($checkProcedureQuery)->rowCount() > 0;
        
        if (!$procedureExists) {
            echo "Création de la procédure stockée update_player_stats_after_game...\n";
            
            // Supprimer la procédure si elle existe déjà
            $db->exec("DROP PROCEDURE IF EXISTS update_player_stats_after_game");
            
            // Créer la procédure
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
                    
                    -- Mettre à jour les statistiques du joueur 2 (seulement s'il n'est pas un bot)
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
            
            echo "Procédure stockée créée avec succès.\n";
        } else {
            echo "La procédure stockée update_player_stats_after_game existe déjà.\n";
        }
        
        // Vérifier le trigger after_game_finished
        $checkTriggerQuery = "SHOW TRIGGERS LIKE 'after_game_finished'";
        $triggerExists = $db->query($checkTriggerQuery)->rowCount() > 0;
        
        if (!$triggerExists) {
            echo "Création du trigger after_game_finished...\n";
            
            // Supprimer le trigger s'il existe déjà
            $db->exec("DROP TRIGGER IF EXISTS after_game_finished");
            
            // Créer le trigger
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
            
            echo "Trigger créé avec succès.\n";
        } else {
            echo "Le trigger after_game_finished existe déjà.\n";
        }
    }
    
    // Vérifier la structure de la table stats
    echo "Vérification de la structure de la table stats...\n";
    
    $columnsQuery = "SHOW COLUMNS FROM stats";
    $columnsStmt = $db->query($columnsQuery);
    $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = ['user_id', 'games_played', 'games_won', 'games_lost', 'last_game'];
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (!empty($missingColumns)) {
        echo "Colonnes manquantes dans la table stats: " . implode(', ', $missingColumns) . "\n";
        
        // Recréer la table stats
        $db->exec("DROP TABLE IF EXISTS stats");
        $db->exec("
            CREATE TABLE stats (
                user_id INT PRIMARY KEY,
                games_played INT DEFAULT 0,
                games_won INT DEFAULT 0,
                games_lost INT DEFAULT 0,
                last_game TIMESTAMP NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        echo "Table stats recréée avec succès.\n";
    } else {
        echo "La structure de la table stats est correcte.\n";
    }
    
    // Vérifier la clé primaire et la contrainte de clé étrangère sur la table stats
    $foreignKeyQuery = "
        SELECT * FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_NAME = 'stats' 
        AND CONSTRAINT_NAME LIKE 'stats_ibfk_%'
    ";
    $foreignKeyStmt = $db->query($foreignKeyQuery);
    
    if ($foreignKeyStmt->rowCount() == 0) {
        echo "Contrainte de clé étrangère manquante sur la table stats. Recréation...\n";
        
        // Recréer la table stats avec contrainte de clé étrangère
        $db->exec("DROP TABLE IF EXISTS stats");
        $db->exec("
            CREATE TABLE stats (
                user_id INT PRIMARY KEY,
                games_played INT DEFAULT 0,
                games_won INT DEFAULT 0,
                games_lost INT DEFAULT 0,
                last_game TIMESTAMP NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        echo "Table stats recréée avec clé étrangère.\n";
    } else {
        echo "Contrainte de clé étrangère correcte sur la table stats.\n";
    }
    
    $elapsedTime = round(microtime(true) - $startTime, 4);
    echo "Vérification de la structure de la base de données terminée en {$elapsedTime} secondes.\n";
    echo "La base de données est maintenant correctement configurée.\n";
    
} catch (PDOException $e) {
    echo "Erreur lors de la mise à jour de la structure de la base de données: " . $e->getMessage() . "\n";
    exit(1);
} 