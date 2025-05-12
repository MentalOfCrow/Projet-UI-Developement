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
require_once __DIR__ . '/../../../backend/controllers/GameController.php';
require_once __DIR__ . '/../../../backend/includes/session.php';
require_once __DIR__ . '/../../../backend/db/Database.php';
require_once __DIR__ . '/../../../backend/db/JsonDatabase.php';

// Vérifier si une requête POST a été faite
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Seules les requêtes POST sont autorisées.'
    ]);
    exit;
}

// Récupérer les données envoyées
$data = json_decode(file_get_contents('php://input'), true);

// Vérifier si l'ID de la partie est fourni
if (!isset($data['game_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'L\'ID de la partie est requis.'
    ]);
    exit;
}

// Récupérer l'ID de la partie
$game_id = intval($data['game_id']);

// Récupérer la partie de la base de données
$db = Database::getInstance()->getConnection();
$query = "SELECT player1_id, player2_id, status FROM games WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérifier si la partie existe et est terminée
if (!$game) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'La partie spécifiée n\'existe pas.'
    ]);
    exit;
}

if ($game['status'] !== 'finished') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'La partie n\'est pas encore terminée.'
    ]);
    exit;
}

// Obtenir une instance de JsonDatabase
$jsonDb = JsonDatabase::getInstance();

// Synchroniser les statistiques des joueurs
$player1_id = $game['player1_id'];
$jsonDb->synchronizeUserStats($player1_id);

// Si le joueur 2 n'est pas un bot (player2_id = 0), synchroniser ses statistiques aussi
if ($game['player2_id'] > 0) {
    $player2_id = $game['player2_id'];
    $jsonDb->synchronizeUserStats($player2_id);
}

// Succès
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Les statistiques ont été synchronisées avec succès.'
]);
exit; 