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
        'message' => 'Vous devez être connecté pour gérer vos amis.'
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

// Récupérer l'ID de l'ami à supprimer
$friend_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

// Valider les données
if ($friend_id <= 0) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Identifiant d\'ami invalide.'
    ]);
    exit;
}

try {
    // Créer une instance de FriendController
    $friendController = new FriendController();
    
    // Vérifier que l'utilisateur est bien ami avec cet utilisateur
    if (!$friendController->areFriends($user_id, $friend_id)) {
        // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
        ob_end_clean();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Cet utilisateur n\'est pas dans votre liste d\'amis.'
        ]);
        exit;
    }
    
    // Supprimer l'ami
    $result = $friendController->removeFriend($friend_id);
    
    // Créer une notification pour l'autre utilisateur
    if ($result['success']) {
        $notificationController = new NotificationController();
        $notificationController->createNotification(
            $friend_id,
            'friend_removed',
            Session::getUsername() . ' vous a retiré de sa liste d\'amis.',
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
    error_log("Erreur lors de la suppression d'un ami: " . $e->getMessage());
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    // Retourner une erreur
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la suppression de l\'ami.'
    ]);
}
exit; 