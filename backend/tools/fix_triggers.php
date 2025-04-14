<?php
// Script pour réparer les triggers et procédures stockées
// Exécuter ce script après l'initialisation de la base de données

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Charger les dépendances
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../db/Database.php';

// Se connecter à la base de données
$db = Database::getInstance()->getConnection();

echo "<h1>Réparation des triggers de la base de données</h1>";

try {
    // Supprimer les triggers existants (s'ils existent)
    $db->exec("DROP TRIGGER IF EXISTS after_game_finished");
    echo "<p>Ancien trigger supprimé avec succès (s'il existait).</p>";
    
    // Créer le trigger after_game_finished correctement
    $triggerSQL = "
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
    END;
    ";
    
    $db->exec($triggerSQL);
    echo "<p style='color:green'>✓ Trigger after_game_finished créé avec succès!</p>";
    
    // Vérifier que le trigger a bien été créé
    $stmt = $db->query("SHOW TRIGGERS WHERE `Table` = 'games'");
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($triggers) > 0) {
        echo "<h2>Triggers disponibles sur la table games</h2>";
        echo "<ul>";
        foreach ($triggers as $trigger) {
            echo "<li>" . $trigger['Trigger'] . "</li>";
        }
        echo "</ul>";
        echo "<p style='color:green'>✓ Les triggers ont été correctement réparés!</p>";
    } else {
        echo "<p style='color:red'>✗ Aucun trigger n'a été trouvé après la réparation. Veuillez vérifier les erreurs.</p>";
    }
    
} catch (PDOException $e) {
    echo "<h2 style='color:red'>Erreur lors de la réparation des triggers:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

echo "<p><a href='check_games.php'>Vérifier à nouveau les jeux</a></p>";
echo "<p><a href='/'>Retourner à l'accueil</a></p>";
?> 