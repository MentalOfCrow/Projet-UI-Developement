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

// Get user ID
$userId = Session::getUserId();

// Get pagination parameters from query string
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Limit the maximum number of notifications to prevent abuse
if ($limit > 50) {
    $limit = 50;
}

// Update user activity
$profileController = new ProfileController();
$profileController->updateActivity();

// Create notification controller
$notificationController = new NotificationController();

try {
    // Get notifications
    $notifications = $notificationController->getNotifications($userId, $limit, $offset);
    
    // Get unread count
    $unreadCount = $notificationController->countUnread($userId);
    
    // Return the notifications
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
        'meta' => [
            'limit' => $limit,
            'offset' => $offset,
            'count' => count($notifications)
        ]
    ]);
} catch (Exception $e) {
    // Log the error
    error_log('Error in get_notifications.php: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur serveur est survenue.'
    ]);
}
?> 