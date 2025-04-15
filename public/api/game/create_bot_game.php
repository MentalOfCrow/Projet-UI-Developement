<?php
// Activer la mise en tampon de sortie
ob_start();

// Activer l'affichage des erreurs en développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enregistrer toutes les erreurs dans un fichier log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../backend/logs/php_errors.log');

require_once __DIR__ . '/../../../backend/includes/config.php';
require_once __DIR__ . '/../../../backend/controllers/GameController.php';
require_once __DIR__ . '/../../../backend/controllers/ProfileController.php';
require_once __DIR__ . '/../../../backend/includes/session.php';

// Vérifier si l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à cette fonctionnalité.'
    ]);
    exit;
}

// Récupérer l'ID de l'utilisateur
$user_id = Session::getUserId();

// Mettre à jour l'activité de l'utilisateur
$profileController = new ProfileController();
$profileController->updateActivity();

// Log pour débogage
error_log("API create_bot_game.php appelée par l'utilisateur ID: " . Session::getUserId());

try {
    // Créer une instance de GameController
    error_log("create_bot_game.php - Avant création du GameController");
    $gameController = new GameController();
    error_log("create_bot_game.php - GameController créé avec succès");
    
    // Log pour débogage
    error_log("Création d'une partie contre un bot pour l'utilisateur ID: " . $user_id);
    
    // Créer une partie contre un bot
    error_log("create_bot_game.php - Avant appel à createBotGame()");
    $result = $gameController->createBotGame($user_id);
    error_log("create_bot_game.php - Après appel à createBotGame() - Résultat success: " . ($result['success'] ? 'true' : 'false'));
    
    // Vérifier que le fichier board.php existe
    $boardFilePath = __DIR__ . '/../../game/board.php';
    error_log("Vérification de l'existence du fichier board.php: " . ($boardFilePath));
    error_log("Le fichier board.php existe: " . (file_exists($boardFilePath) ? 'Oui' : 'Non'));
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    // Retourner le résultat en JSON
    header('Content-Type: application/json');
    echo json_encode($result);
} catch (Exception $e) {
    // En cas d'erreur, retourner un message d'erreur détaillé
    error_log("Erreur dans create_bot_game.php: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
exit; 