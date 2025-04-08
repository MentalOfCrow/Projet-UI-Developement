<?php
// Start output buffering
ob_start();

// Display errors for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set log file
ini_set('error_log', __DIR__ . '/../../../logs/error.log');

// Include required files
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/models/Game.php';
require_once __DIR__ . '/../../../backend/models/User.php';
require_once __DIR__ . '/../../../backend/controllers/GameController.php';

// Set header content type to JSON
header('Content-Type: application/json');

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à l\'historique des coups.'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if game_id is provided
if (!isset($_GET['game_id']) || empty($_GET['game_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Identifiant de partie manquant.'
    ]);
    exit();
}

$game_id = intval($_GET['game_id']);

// Create GameController instance
$gameController = new GameController();

// Get game details
try {
    $result = $gameController->getGame($game_id);
    
    if (!$result['success'] || !isset($result['game'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Partie introuvable.'
        ]);
        exit();
    }
    
    // Récupérer les détails de la partie
    $game = $result['game'];
    
    // Check if the user participated in the game
    if ($game['player1_id'] !== $user_id && $game['player2_id'] !== $user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Vous n\'avez pas participé à cette partie.'
        ]);
        exit();
    }
    
    // Get moves for the game
    // Utilisez getGame avec le paramètre history=true pour récupérer les coups
    $gameWithMoves = $gameController->getGame($game_id, true);
    $moves = isset($gameWithMoves['game']['moves']) ? $gameWithMoves['game']['moves'] : [];
    
    // Get player names
    $player1Name = 'Joueur 1';
    $player2Name = 'Joueur 2';
    
    if ($game['player1_id'] > 0) {
        $player1Name = $game['player1_name'] ?? 'Joueur 1';
    }
    
    if ($game['player2_id'] > 0) {
        $player2Name = $game['player2_name'] ?? 'Joueur 2';
    } else {
        $player2Name = 'Intelligence Artificielle';
    }
    
    // Determine if the user is player 1 or player 2
    $isPlayer1 = ($game['player1_id'] === $user_id);
    $userColor = $isPlayer1 ? 'white' : 'black';
    $opponentColor = $isPlayer1 ? 'black' : 'white';
    
    // Format moves for frontend
    $formattedMoves = [];
    foreach ($moves as $move) {
        // Parse from_position and to_position which are stored as "row,col"
        $fromPos = explode(',', $move['from_position']);
        $toPos = explode(',', $move['to_position']);
        
        if (count($fromPos) == 2 && count($toPos) == 2) {
            $formattedMoves[] = [
                'player_id' => $move['user_id'],
                'from_row' => intval($fromPos[0]),
                'from_col' => intval($fromPos[1]),
                'to_row' => intval($toPos[0]),
                'to_col' => intval($toPos[1]),
                'is_capture' => (bool)$move['captured'],
                'captured_pieces' => [],  // Champ réservé pour une future amélioration - permettra de suivre la position exacte de chaque pièce capturée
                'timestamp' => $move['move_time'],
                'player_name' => ($move['user_id'] == $game['player1_id']) ? $player1Name : $player2Name,
                'color' => ($move['user_id'] == $game['player1_id']) ? 'white' : 'black'
            ];
        }
    }
    
    // Prepare response data
    $responseData = [
        'success' => true,
        'game' => [
            'id' => $game['id'],
            'status' => $game['status'],
            'started_at' => $game['started_at'],
            'finished_at' => $game['finished_at'],
            'winner_id' => $game['winner_id'],
            'player1_id' => $game['player1_id'],
            'player2_id' => $game['player2_id'],
            'player1_name' => $player1Name,
            'player2_name' => $player2Name,
            'user_color' => $userColor,
            'opponent_color' => $opponentColor
        ],
        'moves' => $formattedMoves,
        'total_moves' => count($formattedMoves)
    ];
    
    echo json_encode($responseData);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des coups: ' . $e->getMessage()
    ]);
}

// End script
exit(); 