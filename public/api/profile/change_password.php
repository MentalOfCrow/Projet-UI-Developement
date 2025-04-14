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

// Vérifier que la méthode est POST
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

// Récupérer les données du formulaire
$input = json_decode(file_get_contents('php://input'), true);
if ($input === null) {
    $input = $_POST;
}

$currentPassword = $input['currentPassword'] ?? '';
$newPassword = $input['newPassword'] ?? '';

if (empty($currentPassword) || empty($newPassword)) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Le mot de passe actuel et le nouveau mot de passe sont requis.'
    ]);
    exit;
}

// Vérifier que le nouveau mot de passe répond aux exigences
if (strlen($newPassword) < 6) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Le nouveau mot de passe doit contenir au moins 6 caractères.'
    ]);
    exit;
}

try {
    // Créer une instance de ProfileController
    $profileController = new ProfileController();
    
    // Changer le mot de passe
    $result = $profileController->updatePassword(Session::getUserId(), $currentPassword, $newPassword);
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    // Retourner le résultat en JSON
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
    // En cas d'erreur, retourner un message d'erreur détaillé
    error_log("Erreur dans change_password.php: " . $e->getMessage());
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
exit; 