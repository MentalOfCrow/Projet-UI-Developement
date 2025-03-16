<?php
require_once __DIR__ . '/../../../backend/includes/config.php';
require_once __DIR__ . '/../../../backend/controllers/MatchmakingController.php';
require_once __DIR__ . '/../../../backend/includes/session.php';

// Vérifier que l'utilisateur est authentifié
Session::requireLogin();

// Initialiser le contrôleur de matchmaking
$matchmakingController = new MatchmakingController();

// Récupérer l'action demandée
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Traiter l'action
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
            'message' => 'Action non reconnue'
        ];
}

// Envoyer la réponse en JSON
header('Content-Type: application/json');
echo json_encode($result); 