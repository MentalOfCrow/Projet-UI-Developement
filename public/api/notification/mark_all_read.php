<?php
// Activer la mise en tampon de sortie
ob_start();

// Définir l'affichage des erreurs pour le développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurer le chemin du journal des erreurs
$logFile = __DIR__ . '/../../../backend/logs/php_errors.log';
if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0777, true);
}
ini_set('error_log', $logFile);

// Définir le type de contenu comme JSON
header('Content-Type: application/json');

// Inclure les fichiers nécessaires
require_once __DIR__ . '/../../../backend/includes/config.php';
require_once __DIR__ . '/../../../backend/controllers/NotificationController.php';
require_once __DIR__ . '/../../../backend/controllers/ProfileController.php';
require_once __DIR__ . '/../../../backend/includes/session.php';

// Vérifier si l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à cette fonctionnalité.'
    ]);
    exit;
}

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée. Utilisez POST.'
    ]);
    exit;
}

$userId = Session::getUserId();

try {
    // Créer une instance du contrôleur de notifications
    $notificationController = new NotificationController();
    
    // Mettre à jour l'activité de l'utilisateur
    $profileController = new ProfileController();
    $profileController->updateActivity();
    
    // Marquer toutes les notifications comme lues
    $result = $notificationController->markAllAsRead();
    
    // Répondre avec le résultat
    echo json_encode($result);
} catch (Exception $e) {
    // Journaliser l'erreur
    error_log('Erreur dans mark_all_read.php: ' . $e->getMessage());
    
    // Renvoyer une réponse d'erreur
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur s\'est produite lors du marquage de toutes les notifications comme lues.'
    ]);
}

// Vider le tampon de sortie et l'envoyer au navigateur
ob_end_flush(); 