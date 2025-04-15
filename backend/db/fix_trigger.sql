-- Suppression du trigger existant s'il existe
DROP TRIGGER IF EXISTS after_game_finished;

-- Suppression de la procédure existante
DROP PROCEDURE IF EXISTS update_player_stats_after_game;

-- Création de la nouvelle procédure stockée
DELIMITER //
CREATE PROCEDURE update_player_stats_after_game(IN p_game_id INT)
BEGIN
    DECLARE p1_id INT;
    DECLARE p2_id INT;
    DECLARE winner_id INT;
    DECLARE game_status VARCHAR(20);
    DECLARE is_draw BOOLEAN;
    
    -- Récupérer les informations de la partie
    SELECT player1_id, player2_id, winner_id, status,
           CASE WHEN status = 'finished' AND winner_id IS NULL THEN TRUE ELSE FALSE END
    INTO p1_id, p2_id, winner_id, game_status, is_draw
    FROM games 
    WHERE id = p_game_id;
    
    -- Ne mettre à jour que si la partie est terminée
    IF game_status = 'finished' THEN
        -- Mettre à jour les statistiques du joueur 1
        INSERT INTO stats (user_id, games_played, games_won, games_lost, draws, last_game)
        VALUES (p1_id, 1, 
                IF(winner_id = p1_id, 1, 0),
                IF(winner_id IS NOT NULL AND winner_id != p1_id, 1, 0),
                IF(is_draw, 1, 0),
                NOW())
        ON DUPLICATE KEY UPDATE
            games_played = games_played + 1,
            games_won = games_won + IF(winner_id = p1_id, 1, 0),
            games_lost = games_lost + IF(winner_id IS NOT NULL AND winner_id != p1_id, 1, 0),
            draws = draws + IF(is_draw, 1, 0),
            last_game = NOW();
        
        -- Mettre à jour les statistiques du joueur 2 (seulement si ce n'est pas un bot)
        IF p2_id IS NOT NULL AND p2_id != 0 THEN
            INSERT INTO stats (user_id, games_played, games_won, games_lost, draws, last_game)
            VALUES (p2_id, 1,
                    IF(winner_id = p2_id, 1, 0),
                    IF(winner_id IS NOT NULL AND winner_id != p2_id, 1, 0),
                    IF(is_draw, 1, 0),
                    NOW())
            ON DUPLICATE KEY UPDATE
                games_played = games_played + 1,
                games_won = games_won + IF(winner_id = p2_id, 1, 0),
                games_lost = games_lost + IF(winner_id IS NOT NULL AND winner_id != p2_id, 1, 0),
                draws = draws + IF(is_draw, 1, 0),
                last_game = NOW();
        END IF;
    END IF;
END //
DELIMITER ;

-- Création du nouveau trigger
DELIMITER //
CREATE TRIGGER after_game_finished
AFTER UPDATE ON games
FOR EACH ROW
BEGIN
    -- Mise à jour des statistiques uniquement si le statut passe à 'finished'
    IF NEW.status = 'finished' AND OLD.status != 'finished' THEN
        -- Cas d'une partie contre un bot (player2_id = 0)
        IF NEW.player2_id = 0 THEN
            -- Mise à jour uniquement pour le joueur humain
            UPDATE stats SET
                games_played = games_played + 1,
                games_won = games_won + IF(NEW.winner_id = NEW.player1_id, 1, 0),
                games_lost = games_lost + IF(NEW.winner_id = 0 OR NEW.winner_id IS NULL, 1, 0),
                last_game = NOW()
            WHERE user_id = NEW.player1_id;
        ELSE
            -- Cas d'une partie entre deux joueurs
            -- Match nul
            IF NEW.winner_id IS NULL THEN
                -- Mettre à jour les deux joueurs pour un match nul
                UPDATE stats SET
                    games_played = games_played + 1,
                    draws = draws + 1,
                    last_game = NOW()
                WHERE user_id IN (NEW.player1_id, NEW.player2_id);
            ELSE
                -- Mettre à jour le gagnant
                UPDATE stats SET
                    games_played = games_played + 1,
                    games_won = games_won + 1,
                    last_game = NOW()
                WHERE user_id = NEW.winner_id;
                
                -- Mettre à jour le perdant
                UPDATE stats SET
                    games_played = games_played + 1,
                    games_lost = games_lost + 1,
                    last_game = NOW()
                WHERE user_id = IF(NEW.winner_id = NEW.player1_id, NEW.player2_id, NEW.player1_id);
            END IF;
        END IF;
    END IF;
END //
DELIMITER ; 