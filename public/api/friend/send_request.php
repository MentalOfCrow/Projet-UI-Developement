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
require_once __DIR__ . '/../../../backend/controllers/FriendController.php';
require_once __DIR__ . '/../../../backend/controllers/NotificationController.php';
require_once __DIR__ . '/../../../backend/includes/session.php';

// Vérifier si l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour envoyer une demande d\'ami.'
    ]);
    exit;
}

// Vérifier si la méthode de requête est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée.'
    ]);
    exit;
}

// Récupérer l'ID de l'utilisateur connecté
$user_id = Session::getUserId();

// Récupérer l'ID de l'utilisateur à qui envoyer la demande
$target_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

// Valider les données
if ($target_user_id <= 0) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Identifiant d\'utilisateur invalide.'
    ]);
    exit;
}

// Vérifier qu'on n'essaie pas d'envoyer une demande à soi-même
if ($user_id === $target_user_id) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Vous ne pouvez pas vous envoyer une demande d\'ami à vous-même.'
    ]);
    exit;
}

try {
    // Créer une instance de FriendController
    $friendController = new FriendController();
    
    // Envoyer la demande d'ami
    $result = $friendController->sendFriendRequest($target_user_id);
    
    // Si la demande a été envoyée avec succès, créer une notification
    if ($result['success']) {
        $notificationController = new NotificationController();
        $notificationController->createNotification(
            $target_user_id,
            'friend_request',
            Session::getUsername() . ' vous a envoyé une demande d\'ami.',
            ['user_id' => $user_id]
        );
    }
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    // Retourner le résultat
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
    // Log l'erreur
    error_log("Erreur lors de l'envoi d'une demande d'ami: " . $e->getMessage());
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    // Retourner une erreur
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de l\'envoi de la demande d\'ami.'
    ]);
}
exit; 