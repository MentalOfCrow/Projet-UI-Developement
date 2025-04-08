<?php
// Script pour mettre à jour le trigger after_game_finished
// Ce script améliore la gestion des abandons contre l'IA

// Activer l'affichage des erreurs en développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enregistrer toutes les erreurs dans un fichier log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../backend/logs/php_errors.log');

require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/db/Database.php';
require_once __DIR__ . '/../../backend/includes/session.php';

// Vérifier si l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour exécuter ce script'
    ]);
    exit;
}

// Vérifier si l'utilisateur est admin (si nécessaire)
// Cette partie est optionnelle, mais recommandée pour la sécurité
$user_id = Session::getUserId();
if ($user_id != 1) { // Remplacer par un vrai système de vérification d'admin
    echo json_encode([
        'success' => false,
        'message' => 'Vous n\'avez pas les droits pour exécuter ce script'
    ]);
    exit;
}

try {
    // Connexion à la base de données
    $db = Database::getInstance()->getConnection();
    
    // Supprimer le trigger existant
    $db->exec("DROP TRIGGER IF EXISTS after_game_finished");
    
    // Créer le nouveau trigger avec une meilleure gestion des abandons contre l'IA
    $db->exec("
    CREATE TRIGGER after_game_finished
    AFTER UPDATE ON games
    FOR EACH ROW
    BEGIN
        IF NEW.status = 'finished' AND OLD.status != 'finished' THEN
            -- Mise à jour des statistiques du joueur 1
            INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game)
            VALUES (NEW.player1_id, 1, 
                    IF(NEW.winner_id = NEW.player1_id, 1, 0), 
                    IF((NEW.winner_id != NEW.player1_id AND NEW.winner_id IS NOT NULL) OR (NEW.player2_id = 0 AND NEW.winner_id IS NULL), 1, 0), 
                    NOW())
            ON DUPLICATE KEY UPDATE
                games_played = games_played + 1,
                games_won = games_won + IF(NEW.winner_id = NEW.player1_id, 1, 0),
                games_lost = games_lost + IF((NEW.winner_id != NEW.player1_id AND NEW.winner_id IS NOT NULL) OR (NEW.player2_id = 0 AND NEW.winner_id IS NULL), 1, 0),
                last_game = NOW();
            
            -- Mise à jour des statistiques du joueur 2 (seulement s'il n'est pas un bot)
            IF NEW.player2_id != 0 THEN
                INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game)
                VALUES (NEW.player2_id, 1, 
                        IF(NEW.winner_id = NEW.player2_id, 1, 0), 
                        IF(NEW.winner_id != NEW.player2_id AND NEW.winner_id IS NOT NULL, 1, 0), 
                        NOW())
                ON DUPLICATE KEY UPDATE
                    games_played = games_played + 1,
                    games_won = games_won + IF(NEW.winner_id = NEW.player2_id, 1, 0),
                    games_lost = games_lost + IF(NEW.winner_id != NEW.player2_id AND NEW.winner_id IS NOT NULL, 1, 0),
                    last_game = NOW();
            END IF;
        END IF;
    END
    ");
    
    echo json_encode([
        'success' => true,
        'message' => 'Trigger mis à jour avec succès. Les abandons contre l\'IA seront maintenant correctement comptabilisés comme des défaites.'
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la mise à jour du trigger: ' . $e->getMessage()
    ]);
    error_log('Erreur lors de la mise à jour du trigger: ' . $e->getMessage());
}
?> 