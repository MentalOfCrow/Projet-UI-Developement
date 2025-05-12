<?php
require_once __DIR__ . '/../../../backend/includes/session.php';
require_once __DIR__ . '/../../../backend/controllers/GameController.php';

header('Content-Type: application/json');

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$gameId = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;
if ($gameId <= 0) {
    echo json_encode(['success' => false, 'message' => 'game_id manquant']);
    exit;
}

$sinceId = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

try {
    $controller = new GameController();
    $gameInfo = $controller->getGame($gameId, true);
    if (!$gameInfo['success']) {
        echo json_encode(['success' => false, 'message' => $gameInfo['message']]);
        exit;
    }

    $moves = $gameInfo['game']['moves'] ?? [];
    if ($sinceId > 0) {
        $moves = array_filter($moves, fn($m) => $m['id'] > $sinceId);
    }

    echo json_encode(['success' => true, 'moves' => array_values($moves)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 