<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/controllers/GameController.php';
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

// Récupérer l'ID de la partie depuis la requête GET ou POST
$gameId = $_GET['game_id'] ?? $_POST['game_id'] ?? null;

if (!$gameId) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'ID de partie manquant.'
    ]);
    exit;
}

// Instancier le contrôleur de jeu
$gameController = new GameController();

// Récupérer le statut de la partie
$result = $gameController->getGameStatus(['game_id' => $gameId]);

// Retourner le résultat en JSON
header('Content-Type: application/json');
echo json_encode($result);
exit; 