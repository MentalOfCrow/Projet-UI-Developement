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
require_once __DIR__ . '/../../../backend/controllers/ProfileController.php';
require_once __DIR__ . '/../../../backend/includes/session.php';

// Récupérer l'ID de l'utilisateur à afficher
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Si aucun ID n'est fourni et que l'utilisateur est connecté, utiliser son propre ID
if ($userId === 0 && Session::isLoggedIn()) {
    $userId = Session::getUserId();
}

// Si aucun ID valide n'est disponible
if ($userId === 0) {
    // Nettoyer le tampon avant de répondre
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'ID utilisateur non valide.'
    ]);
    exit;
}

try {
    // Créer une instance de ProfileController
    $profileController = new ProfileController();
    
    // Récupérer le profil
    $result = $profileController->getProfile($userId);
    
    // Mettre à jour l'activité de l'utilisateur actuel
    if (Session::isLoggedIn()) {
        $profileController->updateActivity();
    }
    
    // Nettoyer le tampon avant de répondre
    ob_end_clean();
    
    // Retourner le résultat en JSON
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
    // En cas d'erreur, retourner un message d'erreur détaillé
    error_log("Erreur dans get_profile.php: " . $e->getMessage());
    
    // Nettoyer le tampon avant de répondre
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}

exit; 