-- Script de création de la base de données pour le jeu de dames
-- Exécuter ce script pour initialiser la base de données

-- Création de la base de données
CREATE DATABASE IF NOT EXISTS checkers_game DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE checkers_game;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nom d''utilisateur unique',
    email VARCHAR(100) NOT NULL UNIQUE COMMENT 'Adresse email unique',
    password VARCHAR(255) NOT NULL COMMENT 'Mot de passe haché',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création du compte',
    last_login TIMESTAMP NULL COMMENT 'Date de dernière connexion'
) COMMENT 'Table des utilisateurs du jeu';

-- Table de la file d'attente pour le matchmaking
CREATE TABLE IF NOT EXISTS queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'ID de l''utilisateur dans la file',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date d''entrée dans la file',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) COMMENT 'File d''attente pour le matchmaking';

-- Table des parties
CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player1_id INT NOT NULL COMMENT 'ID du joueur 1',
    player2_id INT NOT NULL COMMENT 'ID du joueur 2',
    current_player INT NOT NULL COMMENT 'ID du joueur dont c''est le tour',
    status ENUM('waiting', 'in_progress', 'finished') NOT NULL DEFAULT 'waiting' COMMENT 'État de la partie',
    winner_id INT NULL COMMENT 'ID du gagnant (NULL si match nul)',
    board_state JSON NOT NULL COMMENT 'État du plateau au format JSON',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date de dernière mise à jour',
    FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (player2_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL
) COMMENT 'Table des parties de jeu';

-- Table des mouvements de jeu
CREATE TABLE IF NOT EXISTS moves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL COMMENT 'ID de la partie',
    user_id INT NOT NULL COMMENT 'ID du joueur qui a effectué le mouvement',
    from_position VARCHAR(10) NOT NULL COMMENT 'Position de départ (ligne,colonne)',
    to_position VARCHAR(10) NOT NULL COMMENT 'Position d''arrivée (ligne,colonne)',
    captured BOOLEAN DEFAULT FALSE COMMENT 'Indique si une pièce a été capturée',
    move_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date et heure du mouvement',
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) COMMENT 'Historique des mouvements de jeu';

-- Table des statistiques des joueurs
CREATE TABLE IF NOT EXISTS stats (
    user_id INT PRIMARY KEY,
    games_played INT DEFAULT 0 COMMENT 'Nombre total de parties jouées',
    games_won INT DEFAULT 0 COMMENT 'Nombre de parties gagnées',
    games_lost INT DEFAULT 0 COMMENT 'Nombre de parties perdues',
    last_game TIMESTAMP NULL COMMENT 'Date de la dernière partie',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) COMMENT 'Statistiques des joueurs';

-- Création des index pour optimiser les performances
CREATE INDEX idx_queue_user_id ON queue(user_id);
CREATE INDEX idx_games_player1_id ON games(player1_id);
CREATE INDEX idx_games_player2_id ON games(player2_id);
CREATE INDEX idx_games_status ON games(status);
CREATE INDEX idx_moves_game_id ON moves(game_id);
CREATE INDEX idx_moves_user_id ON moves(user_id);

-- Procédure pour mettre à jour les statistiques après une partie
DELIMITER //
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
    
    -- Mettre à jour les statistiques du joueur 2
    INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game)
    VALUES (p2_id, 1, IF(winner_id = p2_id, 1, 0), IF(winner_id != p2_id AND winner_id IS NOT NULL, 1, 0), NOW())
    ON DUPLICATE KEY UPDATE
        games_played = games_played + 1,
        games_won = games_won + IF(winner_id = p2_id, 1, 0),
        games_lost = games_lost + IF(winner_id != p2_id AND winner_id IS NOT NULL, 1, 0),
        last_game = NOW();
END //
DELIMITER ;

-- Trigger pour mettre à jour les statistiques lorsqu'une partie se termine
DELIMITER //
CREATE TRIGGER after_game_finished
AFTER UPDATE ON games
FOR EACH ROW
BEGIN
    IF NEW.status = 'finished' AND OLD.status != 'finished' THEN
        CALL update_player_stats_after_game(NEW.id);
    END IF;
END //
DELIMITER ;

-- Données de test (à commenter en production)
-- INSERT INTO users (username, email, password) VALUES 
-- ('joueur1', 'joueur1@example.com', '$2y$10$8I5Qz9Z9X4XQl6U1W1W1WeKxUJf.WZ1W1W1W1W1W1W1W1W1W1W'),
-- ('joueur2', 'joueur2@example.com', '$2y$10$8I5Qz9Z9X4XQl6U1W1W1WeKxUJf.WZ1W1W1W1W1W1W1W1W1W1W');