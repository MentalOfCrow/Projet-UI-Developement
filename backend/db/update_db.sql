-- Vérifier si la colonne result existe dans la table games
SET @columnExists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'games'
    AND COLUMN_NAME = 'result'
);

-- Ajouter la colonne si elle n'existe pas
SET @query = IF(@columnExists = 0,
    'ALTER TABLE games ADD COLUMN result ENUM("player1_won", "player2_won", "draw") DEFAULT NULL AFTER winner_id',
    'SELECT "Column result already exists"');

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout de la colonne draws dans la table leaderboard
SET @columnExists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'leaderboard'
    AND COLUMN_NAME = 'draws'
);

SET @query = IF(@columnExists = 0,
    'ALTER TABLE leaderboard ADD COLUMN draws INT DEFAULT 0 AFTER wins',
    'SELECT "Column draws already exists"');

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mettre à jour les parties existantes pour définir le résultat
UPDATE games 
SET result = CASE
    WHEN winner_id IS NULL AND status = 'finished' THEN 'draw'
    WHEN winner_id = player1_id THEN 'player1_won'
    WHEN winner_id = player2_id OR winner_id = 0 THEN 'player2_won'
    ELSE NULL
END
WHERE status = 'finished' AND result IS NULL;

-- Vérifier si le trigger existe et le supprimer
DROP TRIGGER IF EXISTS after_game_finished;

-- Créer le trigger mis à jour
DELIMITER //
CREATE TRIGGER after_game_finished
AFTER UPDATE ON games
FOR EACH ROW
BEGIN
    IF NEW.status = 'finished' AND OLD.status != 'finished' THEN

        -- Déterminer le résultat
        UPDATE games
        SET result = CASE
            WHEN NEW.winner_id IS NULL THEN 'draw'
            WHEN NEW.winner_id = NEW.player1_id THEN 'player1_won'
            WHEN NEW.winner_id = NEW.player2_id OR NEW.winner_id = 0 THEN 'player2_won'
            ELSE NULL
        END
        WHERE id = NEW.id;

        -- Statistiques joueur 1
        INSERT INTO stats (user_id, games_played, games_won, games_lost, draws, last_game)
        VALUES (
            NEW.player1_id,
            1,
            IF(NEW.winner_id = NEW.player1_id, 1, 0),
            IF(NEW.winner_id != NEW.player1_id AND NEW.winner_id IS NOT NULL, 1, 0),
            IF(NEW.winner_id IS NULL, 1, 0),
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            games_played = games_played + 1,
            games_won = games_won + IF(NEW.winner_id = NEW.player1_id, 1, 0),
            games_lost = games_lost + IF(NEW.winner_id != NEW.player1_id AND NEW.winner_id IS NOT NULL, 1, 0),
            draws = draws + IF(NEW.winner_id IS NULL, 1, 0),
            last_game = NOW();

        -- Statistiques joueur 2 (si pas un bot)
        IF NEW.player2_id != 0 THEN
            INSERT INTO stats (user_id, games_played, games_won, games_lost, draws, last_game)
            VALUES (
                NEW.player2_id,
                1,
                IF(NEW.winner_id = NEW.player2_id, 1, 0),
                IF(NEW.winner_id != NEW.player2_id AND NEW.winner_id IS NOT NULL, 1, 0),
                IF(NEW.winner_id IS NULL, 1, 0),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                games_played = games_played + 1,
                games_won = games_won + IF(NEW.winner_id = NEW.player2_id, 1, 0),
                games_lost = games_lost + IF(NEW.winner_id != NEW.player2_id AND NEW.winner_id IS NOT NULL, 1, 0),
                draws = draws + IF(NEW.winner_id IS NULL, 1, 0),
                last_game = NOW();
        END IF;
    END IF;
END //
DELIMITER ;

-- Exécuter une commande pour vérifier le nombre de lignes mises à jour
SELECT CONCAT('Updated ', ROW_COUNT(), ' games with missing result values') AS update_info; 