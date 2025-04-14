<?php
// Pour le développement seulement
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuration du logging
$logFile = __DIR__ . '/../../../backend/logs/api_errors.log';
if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0777, true);
}
ini_set('log_errors', 1);
ini_set('error_log', $logFile);

// Headers requis
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Inclure les fichiers nécessaires
require_once __DIR__ . '/../../../backend/includes/config.php';
require_once __DIR__ . '/../../../backend/db/Database.php';
require_once __DIR__ . '/../../../backend/controllers/ProfileController.php';
require_once __DIR__ . '/../../../backend/includes/session.php';

// Vérifier si l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour modifier votre mot de passe.']);
    exit;
}

// Récupérer l'ID utilisateur de la session
$userId = Session::getUserId();

// Vérifier que la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Utilisez POST.']);
    exit;
}

// Récupérer les données envoyées
$data = json_decode(file_get_contents("php://input"), true);

// Vérifier que les mots de passe sont fournis
if (!isset($data['current_password']) || !isset($data['new_password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le mot de passe actuel et le nouveau mot de passe sont requis.']);
    exit;
}

$currentPassword = $data['current_password'];
$newPassword = $data['new_password'];

try {
    // Créer une instance du contrôleur de profil
    $profileController = new ProfileController();
    
    // Mettre à jour le mot de passe
    $result = $profileController->updatePassword($userId, $currentPassword, $newPassword);
    
    // Mettre à jour l'activité de l'utilisateur
    $profileController->updateActivity();
    
    // Envoyer la réponse
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    echo json_encode($result);
} catch (Exception $e) {
    error_log("Erreur dans update_password.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?> 