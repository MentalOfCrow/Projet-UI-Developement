-- Script d'initialisation complète de la base de données pour le jeu de dames
-- Ce script supprime la base de données existante si elle existe, puis la recrée avec toutes les tables nécessaires

-- Suppression de la base de données si elle existe déjà
-- Note: nous supprimons également "checkers_games" (avec un 's') pour nettoyer d'anciennes installations si elles existent
DROP DATABASE IF EXISTS checkers_games;
DROP DATABASE IF EXISTS checkers_game;

-- Création de la base de données avec le bon encodage
-- Nous utilisons désormais uniquement "checkers_game" (sans 's') de façon cohérente
CREATE DATABASE IF NOT EXISTS checkers_game DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Utilisation de la base de données
USE checkers_game;

-- Désactiver temporairement les vérifications de clés étrangères
SET FOREIGN_KEY_CHECKS=0;

-- D'abord créer toutes les tables sans contraintes de clés étrangères
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    privacy_level ENUM('public', 'friends', 'private') DEFAULT 'public',
    is_admin TINYINT(1) DEFAULT 0,
    last_login DATETIME DEFAULT NULL,
    last_activity DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Garantir l'insertion de l'utilisateur IA (ID 0) pour les parties contre l'IA
-- Cette configuration permet de forcer l'ID à 0 même avec AUTO_INCREMENT
SET @ORIGINAL_SQL_MODE = @@SQL_MODE;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- Insérer l'utilisateur IA avec ID 0 (utilisé pour les parties contre le bot)
-- En utilisant IGNORE, cette commande ne génère pas d'erreur même si l'utilisateur existe déjà
INSERT IGNORE INTO users (id, username, email, password, is_admin)
VALUES (0, 'AI_Bot', 'ia@bot.local', 'no-password', 0);

-- Restaurer le mode SQL original
SET SQL_MODE = @ORIGINAL_SQL_MODE;

CREATE TABLE IF NOT EXISTS queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('waiting', 'matched', 'cancelled') DEFAULT 'waiting'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player1_id INT NOT NULL,
    player2_id INT NOT NULL,
    current_player TINYINT(1) DEFAULT 1,
    board_state JSON NOT NULL,
    status ENUM('in_progress', 'finished') DEFAULT 'in_progress',
    winner_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS moves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    user_id INT NOT NULL,
    from_row INT NOT NULL,
    from_col INT NOT NULL,
    to_row INT NOT NULL,
    to_col INT NOT NULL,
    captured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stats (
    user_id INT PRIMARY KEY,
    games_played INT DEFAULT 0,
    games_won INT DEFAULT 0,
    games_lost INT DEFAULT 0,
    draws INT DEFAULT 0,
    win_streak INT DEFAULT 0,
    longest_win_streak INT DEFAULT 0,
    games_vs_bots INT DEFAULT 0,
    games_vs_players INT DEFAULT 0,
    bot_games_won INT DEFAULT 0,
    player_games_won INT DEFAULT 0,
    last_game DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    read_status TINYINT(1) DEFAULT 0,
    data JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS friends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'blocked') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_friendship (sender_id, receiver_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS leaderboard (
    user_id INT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    rank_position INT NOT NULL,
    games_played INT DEFAULT 0,
    wins INT DEFAULT 0,
    win_percentage DECIMAL(5,2) DEFAULT 0.00,
    last_game DATETIME DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Créer un utilisateur de test
INSERT INTO users (username, email, password) 
VALUES ('testuser', 'test@example.com', '$2y$10$9XmjyRPV/kZHZBwIWRm2S.5s4P1l5xEgMsZSbBNq6JOJw1oBUXH1S');

-- Ajouter les utilisateurs test supplémentaires pour le développement
INSERT INTO users (username, email, password) 
VALUES ('player1', 'player1@example.com', '$2y$10$9XmjyRPV/kZHZBwIWRm2S.5s4P1l5xEgMsZSbBNq6JOJw1oBUXH1S');

INSERT INTO users (username, email, password) 
VALUES ('player2', 'player2@example.com', '$2y$10$9XmjyRPV/kZHZBwIWRm2S.5s4P1l5xEgMsZSbBNq6JOJw1oBUXH1S');

-- Initialiser les statistiques pour les utilisateurs
INSERT INTO stats (user_id, games_played, games_won, games_lost, draws)
SELECT id, 0, 0, 0, 0 FROM users
ON DUPLICATE KEY UPDATE games_played=games_played;

-- Maintenant, ajouter les contraintes de clés étrangères
ALTER TABLE queue
ADD CONSTRAINT fk_queue_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Vérifier et supprimer la contrainte fk_games_player1 si elle existe
SET @constraintName = (
    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'games'
    AND CONSTRAINT_NAME = 'fk_games_player1'
    LIMIT 1
);

SET @dropFkPlayer1 = IF(@constraintName IS NOT NULL,
    CONCAT('ALTER TABLE games DROP FOREIGN KEY ', @constraintName),
    'SELECT 1');
PREPARE stmt FROM @dropFkPlayer1;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Vérifier et supprimer la contrainte fk_games_winner si elle existe
SET @constraintName = (
    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'games'
    AND CONSTRAINT_NAME = 'fk_games_winner'
    LIMIT 1
);

SET @dropFkWinner = IF(@constraintName IS NOT NULL,
    CONCAT('ALTER TABLE games DROP FOREIGN KEY ', @constraintName),
    'SELECT 1');
PREPARE stmt FROM @dropFkWinner;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE games
ADD CONSTRAINT fk_games_player1
FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE CASCADE;

-- Ensuite, modifier la contrainte de clé étrangère
ALTER TABLE games
ADD CONSTRAINT fk_games_winner
FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE moves
ADD CONSTRAINT fk_moves_game
FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_moves_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE stats
ADD CONSTRAINT fk_stats_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE notifications
ADD CONSTRAINT fk_notifications_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE friends
ADD CONSTRAINT fk_friends_sender
FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_friends_receiver
FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE leaderboard
ADD CONSTRAINT fk_leaderboard_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Réactiver les vérifications de clés étrangères
SET FOREIGN_KEY_CHECKS=1;

-- Procédures stockées pour le classement
DELIMITER //

-- Procédure pour gérer en toute sécurité la suppression d'index
CREATE PROCEDURE drop_index_if_exists(
    IN idx_name VARCHAR(64),
    IN tbl_name VARCHAR(64)
)
BEGIN
    DECLARE idx_exists INT DEFAULT 0;
    SELECT COUNT(1) INTO idx_exists
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE table_schema = DATABASE()
      AND table_name = tbl_name
      AND index_name = idx_name;

    IF idx_exists > 0 THEN
        SET @sql := CONCAT('DROP INDEX ', idx_name, ' ON ', tbl_name);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //

-- Procédure pour supprimer une colonne si elle existe
CREATE PROCEDURE drop_column_if_exists(
    IN tbl_name VARCHAR(64),
    IN col_name VARCHAR(64)
)
BEGIN
    DECLARE col_exists INT DEFAULT 0;
    SELECT COUNT(1) INTO col_exists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE table_schema = DATABASE()
      AND table_name = tbl_name
      AND column_name = col_name;

    IF col_exists > 0 THEN
        SET @sql := CONCAT('ALTER TABLE ', tbl_name, ' DROP COLUMN ', col_name);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //

-- Procédure pour recalculer les rangs du leaderboard
CREATE PROCEDURE recalculate_leaderboard_ranks()
BEGIN
    -- Mettre à jour les positions de classement (basé sur pourcentage de victoire, puis nombre de victoires)
    SET @rank := 0;
    
    UPDATE leaderboard
    SET rank_position = 0
    WHERE games_played = 0;
    
    UPDATE leaderboard lb
    JOIN (
        SELECT user_id, (@rank := @rank + 1) AS new_rank
        FROM leaderboard
        WHERE games_played > 0
        ORDER BY win_percentage DESC, games_won DESC
    ) r ON lb.user_id = r.user_id
    SET lb.rank_position = r.new_rank;
    
    -- Remettre à zéro les rangs des joueurs sans partie
    UPDATE leaderboard
    SET rank_position = 0 
    WHERE games_played = 0;
END //

-- Procédure pour initialiser le leaderboard à partir des stats existantes
CREATE PROCEDURE initialize_leaderboard()
BEGIN
    -- Vider le leaderboard actuel
    DELETE FROM leaderboard;
    
    -- Insérer tous les joueurs avec leurs statistiques
    INSERT INTO leaderboard (user_id, games_played, games_won, games_lost, win_percentage)
    SELECT 
        user_id,
        games_played,
        games_won,
        games_lost,
        CASE 
            WHEN games_played > 0 THEN ROUND((games_won / games_played) * 100, 2)
            ELSE 0.00
        END as win_percentage
    FROM stats;
    
    -- Mettre à jour les positions de classement
    CALL recalculate_leaderboard_ranks();
END //

-- Trigger pour mettre à jour le classement après modification des stats
CREATE TRIGGER after_stats_update
AFTER UPDATE ON stats
FOR EACH ROW
BEGIN
    -- Mettre à jour ou insérer l'entrée dans le classement
    INSERT INTO leaderboard (user_id, games_played, games_won, games_lost, win_percentage)
    VALUES (
        NEW.user_id,
        NEW.games_played,
        NEW.games_won,
        NEW.games_lost,
        CASE 
            WHEN NEW.games_played > 0 THEN ROUND((NEW.games_won / NEW.games_played) * 100, 2)
            ELSE 0.00
        END
    )
    ON DUPLICATE KEY UPDATE
        games_played = NEW.games_played,
        games_won = NEW.games_won,
        games_lost = NEW.games_lost,
        win_percentage = CASE 
            WHEN NEW.games_played > 0 THEN ROUND((NEW.games_won / NEW.games_played) * 100, 2)
            ELSE 0.00
        END;
    
    -- Recalculer les rangs
    CALL recalculate_leaderboard_ranks();
END //

-- Trigger pour mettre à jour les stats après fin d'une partie
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
END //

-- Procédure pour créer un index s'il n'existe pas
CREATE PROCEDURE create_index_if_not_exists(
    IN index_name VARCHAR(64),
    IN table_name VARCHAR(64),
    IN column_name VARCHAR(64)
)
BEGIN
    DECLARE idx_exists INT DEFAULT 0;
    
    SELECT COUNT(1) INTO idx_exists
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE table_schema = DATABASE()
      AND table_name = table_name
      AND index_name = index_name;
      
    IF idx_exists = 0 THEN
        SET @sql = CONCAT('CREATE INDEX ', index_name, ' ON ', table_name, '(', column_name, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //

DELIMITER ;

-- Gérer les index de manière sécurisée
CALL drop_index_if_exists('idx_queue_user_id', 'queue');
CALL drop_index_if_exists('idx_games_player1_id', 'games');
CALL drop_index_if_exists('idx_games_player2_id', 'games');
CALL drop_index_if_exists('idx_games_status', 'games');
CALL drop_index_if_exists('idx_moves_game_id', 'moves');
CALL drop_index_if_exists('idx_moves_user_id', 'moves');
CALL drop_index_if_exists('idx_notifications_user_id', 'notifications');
CALL drop_index_if_exists('idx_notifications_read_status', 'notifications');
CALL drop_index_if_exists('idx_friends_sender_id', 'friends');
CALL drop_index_if_exists('idx_friends_receiver_id', 'friends');
CALL drop_index_if_exists('idx_friends_status', 'friends');

-- Créer les index pour optimiser les requêtes
CREATE INDEX idx_queue_user_id ON queue(user_id);
CREATE INDEX idx_games_player1_id ON games(player1_id);
CREATE INDEX idx_games_player2_id ON games(player2_id);
CREATE INDEX idx_games_status ON games(status);
CREATE INDEX idx_moves_game_id ON moves(game_id);
CREATE INDEX idx_moves_user_id ON moves(user_id);
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_read_status ON notifications(read_status);
CREATE INDEX idx_friends_sender_id ON friends(sender_id);
CREATE INDEX idx_friends_receiver_id ON friends(receiver_id);
CREATE INDEX idx_friends_status ON friends(status);

-- Supprimer la colonne win_rate obsolète si elle existe (en utilisant la procédure stockée)
CALL drop_column_if_exists('leaderboard', 'win_rate');

-- D'abord, créer l'utilisateur AI s'il n'existe pas
-- Forcer l'insertion avec id = 0
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- Ajouter l'utilisateur IA
INSERT IGNORE INTO users (id, username, email, password)
VALUES (0, 'AI_Bot', 'ia@bot.local', 'no-password');

-- Vérifier et supprimer la contrainte fk_games_player2 si elle existe
SET @fk_name_p2 = (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'games'
      AND COLUMN_NAME = 'player2_id'
      AND REFERENCED_TABLE_NAME = 'users'
    LIMIT 1
);

SET @sql_drop_fk_p2 = IF(@fk_name_p2 IS NOT NULL,
    CONCAT('ALTER TABLE games DROP FOREIGN KEY ', @fk_name_p2),
    'SELECT 1');

PREPARE stmt FROM @sql_drop_fk_p2;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Réajouter la contrainte modifiée pour player2_id
ALTER TABLE games
ADD CONSTRAINT fk_games_player2
FOREIGN KEY (player2_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- Code commenté pour valider l'installation
-- Ces lignes n'affectent pas la base mais permettent de vérifier dans les logs
-- que tout s'est bien passé, notamment la création de l'utilisateur IA

-- Vérifier que l'IA (id=0) existe bien dans la table users
SELECT 'Test de cohérence - Vérification utilisateur IA:' AS message_log;
SELECT id, username, email FROM users WHERE id = 0;

-- Vérifier les contraintes de clé étrangère sur la table games
SELECT 'Test de cohérence - Vérification contraintes:' AS message_log;
SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'games'
  AND REFERENCED_TABLE_NAME = 'users';