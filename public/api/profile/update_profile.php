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
require_once __DIR__ . '/../../../backend/controllers/ProfileController.php';
require_once __DIR__ . '/../../../backend/includes/session.php';

// Vérifier si l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour modifier votre profil.'
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

// Récupérer les données du formulaire
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

// Valider les données
if (empty($username)) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Le nom d\'utilisateur ne peut pas être vide.'
    ]);
    exit;
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'L\'adresse email n\'est pas valide.'
    ]);
    exit;
}

try {
    // Créer une instance de ProfileController
    $profileController = new ProfileController();
    
    // Mettre à jour le profil de l'utilisateur
    $result = $profileController->updateProfile([
        'username' => $username,
        'email' => $email
    ]);
    
    // Mettre à jour la session si le nom d'utilisateur a été modifié
    if ($result['success']) {
        $_SESSION['username'] = $username;
    }
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    // Retourner le résultat
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
    // Log l'erreur
    error_log("Erreur lors de la mise à jour du profil: " . $e->getMessage());
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    // Retourner une erreur
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la mise à jour du profil.'
    ]);
}
exit; 