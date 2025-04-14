<?php
// Start output buffering to prevent any previous output
ob_start();

// Set display_errors for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set the content type to JSON
header('Content-Type: application/json');

// Log errors to a file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../backend/logs/api_errors.log');

// Include configuration files
require_once __DIR__ . '/../../../backend/includes/config.php';
require_once __DIR__ . '/../../../backend/includes/session.php';
require_once __DIR__ . '/../../../backend/controllers/NotificationController.php';
require_once __DIR__ . '/../../../backend/controllers/ProfileController.php';

// Check if user is logged in
if (!Session::isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour effectuer cette action.'
    ]);
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée.'
    ]);
    exit;
}

// Get JSON data from the request
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'Données JSON invalides.'
    ]);
    exit;
}

// Get user ID
$userId = Session::getUserId();

// Update user activity
$profileController = new ProfileController();
$profileController->updateActivity();

// Create notification controller
$notificationController = new NotificationController();

try {
    // Check if we need to mark all notifications as read
    if (isset($data['mark_all']) && $data['mark_all'] === true) {
        // Mark all notifications as read
        $result = $notificationController->markAllAsRead();
        
        // Return the result directly as it already has the required format
        echo json_encode($result);
    } else if (isset($data['notification_id'])) {
        // Mark single notification as read
        $notificationId = $data['notification_id'];
        
        // Verify that the notification belongs to the user
        if (!$notificationController->belongsToUser($notificationId, $userId)) {
            echo json_encode([
                'success' => false,
                'message' => 'Cette notification ne vous appartient pas.'
            ]);
            exit;
        }
        
        // Mark notification as read and get result
        $result = $notificationController->markAsRead($notificationId);
        
        // Return the result directly
        echo json_encode($result);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Les données sont incomplètes.'
        ]);
    }
} catch (Exception $e) {
    // Log the error
    error_log('Error in mark_as_read.php: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur serveur est survenue.'
    ]);
}
?> 