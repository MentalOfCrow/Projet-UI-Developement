<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/controllers/MatchmakingController.php';
require_once __DIR__ . '/../../backend/includes/session.php';

// Vérifier si l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à cette fonctionnalité.'
    ]);
    exit;
}

// Récupérer l'action demandée (join, leave, check)
$action = $_GET['action'] ?? '';

// Instancier le contrôleur de matchmaking
$matchmakingController = new MatchmakingController();

// Traiter l'action demandée
switch ($action) {
    case 'join':
        $result = $matchmakingController->joinQueue();
        break;
    
    case 'leave':
        $result = $matchmakingController->leaveQueue();
        break;
    
    case 'check':
        $result = $matchmakingController->checkQueue();
        break;
    
    default:
        $result = [
            'success' => false,
            'message' => 'Action non valide. Utilisez join, leave ou check.'
        ];
}

// Retourner le résultat en JSON
header('Content-Type: application/json');
echo json_encode($result);
exit; 