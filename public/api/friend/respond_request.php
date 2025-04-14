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
        'message' => 'Vous devez être connecté pour répondre à une demande d\'ami.'
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

// Récupérer l'ID de l'utilisateur qui a envoyé la demande
$sender_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

// Récupérer l'action à effectuer (accept ou reject)
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

// Valider les données
if ($sender_id <= 0) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Identifiant d\'utilisateur invalide.'
    ]);
    exit;
}

if ($action !== 'accept' && $action !== 'reject') {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Action non reconnue. Utilisez "accept" ou "reject".'
    ]);
    exit;
}

try {
    // Créer une instance de FriendController
    $friendController = new FriendController();
    
    // Obtenir les demandes d'amis en attente
    $pendingRequests = $friendController->getPendingFriendRequests();
    
    // Vérifier si une demande de cet utilisateur existe
    $requestFound = false;
    $requestId = 0;
    
    if ($pendingRequests['success']) {
        foreach ($pendingRequests['requests'] as $request) {
            if ($request['sender_id'] == $sender_id) {
                $requestFound = true;
                $requestId = $request['id'];
                break;
            }
        }
    }
    
    if (!$requestFound) {
        // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
        ob_end_clean();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Aucune demande d\'ami en attente trouvée.'
        ]);
        exit;
    }
    
    // Répondre à la demande d'ami
    $result = $friendController->respondToFriendRequest($requestId, $action);
    
    // Si la demande a été acceptée, créer une notification
    if ($result['success'] && $action === 'accept') {
        $notificationController = new NotificationController();
        $notificationController->createNotification(
            $sender_id,
            'friend_accepted',
            Session::getUsername() . ' a accepté votre demande d\'ami.',
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
    error_log("Erreur lors de la réponse à une demande d'ami: " . $e->getMessage());
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    // Retourner une erreur
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la réponse à la demande d\'ami.'
    ]);
}
exit; 