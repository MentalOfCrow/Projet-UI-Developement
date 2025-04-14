<?php
// Activer la mise en tampon de sortie
ob_start();

// Activer l'affichage des erreurs en développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enregistrer toutes les erreurs dans un fichier log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../../backend/logs/php_errors.log');

require_once __DIR__ . '/../../../../backend/includes/config.php';
require_once __DIR__ . '/../../../../backend/controllers/FriendController.php';
require_once __DIR__ . '/../../../../backend/controllers/ProfileController.php';
require_once __DIR__ . '/../../../../backend/includes/session.php';

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

// Récupérer l'ID de l'utilisateur dont on veut la liste d'amis
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : Session::getUserId();

// Si aucun ID n'est spécifié, utiliser l'ID de l'utilisateur connecté
if ($user_id <= 0) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'ID utilisateur invalide.'
    ]);
    exit;
}

try {
    // Créer une instance de FriendController
    $friendController = new FriendController();
    
    // Récupérer la liste des amis
    $result = $friendController->getFriendsList($user_id);
    
    // Si l'utilisateur est connecté, mettre à jour son activité
    if (Session::isLoggedIn()) {
        $profileController = new ProfileController();
        $profileController->updateActivity();
    }
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    // Retourner le résultat en JSON
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
    // En cas d'erreur, retourner un message d'erreur détaillé
    error_log("Erreur dans get_friends.php: " . $e->getMessage());
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
exit; 