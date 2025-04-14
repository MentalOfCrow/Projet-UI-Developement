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

// Vérifier si un fichier a été envoyé
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Aucun fichier valide n\'a été envoyé.'
    ]);
    exit;
}

// Vérifier le type MIME du fichier
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$fileInfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedType = finfo_file($fileInfo, $_FILES['avatar']['tmp_name']);
finfo_close($fileInfo);

if (!in_array($detectedType, $allowedTypes)) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Type de fichier non autorisé. Seuls JPEG, PNG et GIF sont acceptés.'
    ]);
    exit;
}

// Vérifier la taille du fichier (max 2 Mo)
$maxFileSize = 2 * 1024 * 1024; // 2 Mo en octets
if ($_FILES['avatar']['size'] > $maxFileSize) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Le fichier est trop volumineux. La taille maximale est de 2 Mo.'
    ]);
    exit;
}

try {
    // Créer une instance de ProfileController
    $profileController = new ProfileController();
    
    // Télécharger l'avatar
    $result = $profileController->uploadAvatar($_FILES['avatar']);
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    // Retourner le résultat en JSON
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
    // En cas d'erreur, retourner un message d'erreur détaillé
    error_log("Erreur dans upload_avatar.php: " . $e->getMessage());
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
exit; 