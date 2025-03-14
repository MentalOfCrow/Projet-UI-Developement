<?php
// Supprimer tout output buffering existant et en démarrer un nouveau
while (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/controllers/AuthController.php';

$authController = new AuthController();
$result = $authController->logout();

// Nettoyer le buffer avant de rediriger
ob_end_clean();
// Redirection
header('Location: ' . ($result['redirect'] ?? '/'));
exit();
?>