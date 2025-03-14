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

// Récupérer les données du mouvement
$data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si les données sont envoyées en JSON
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $data = json_decode($input, true);
    } else {
        // Si les données sont envoyées en POST classique
        $data = $_POST;
    }
} else {
    // Si ce n'est pas une requête POST
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée. Utilisez POST.'
    ]);
    exit;
}

// Vérifier les données obligatoires
if (!isset($data['game_id']) || !isset($data['from_row']) || !isset($data['from_col']) || !isset($data['to_row']) || !isset($data['to_col'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Données incomplètes. Veuillez fournir game_id, from_row, from_col, to_row et to_col.'
    ]);
    exit;
}

// Instancier le contrôleur de jeu
$gameController = new GameController();

// Effectuer le mouvement
$result = $gameController->makeMove($data);

// Retourner le résultat en JSON
header('Content-Type: application/json');
echo json_encode($result);
exit; 