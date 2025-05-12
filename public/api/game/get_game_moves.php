<?php
// Start output buffering
ob_start();

// Display errors for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set log file
ini_set('error_log', __DIR__ . '/../../../backend/logs/php_errors.log');

// Include required files
require_once __DIR__ . '/../../../backend/includes/config.php';
require_once __DIR__ . '/../../../backend/models/Game.php';
require_once __DIR__ . '/../../../backend/models/User.php';
require_once __DIR__ . '/../../../backend/controllers/GameController.php';
require_once __DIR__ . '/../../../backend/db/JsonDatabase.php';

// Log function for debugging
function logDebug($message) {
    error_log("[get_game_moves.php] " . $message);
}

logDebug("API appelée avec game_id: " . ($_GET['game_id'] ?? 'non défini'));

// Set header content type to JSON
header('Content-Type: application/json');

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    logDebug("Utilisateur non connecté - Accès refusé");
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à l\'historique des coups.'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
logDebug("Utilisateur connecté: ID=" . $user_id);

// Check if game_id is provided
if (!isset($_GET['game_id']) || empty($_GET['game_id'])) {
    logDebug("Identifiant de partie manquant");
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Identifiant de partie manquant.'
    ]);
    exit();
}

$game_id = intval($_GET['game_id']);
logDebug("Récupération des informations pour la partie ID=" . $game_id);

// Tentative de récupération via la base JSON (préférée pour un fonctionnement sans MySQL)
$jsonDb = JsonDatabase::getInstance();
$jsonGame = $jsonDb->getGameById($game_id);

if ($jsonGame !== null) {
    // Vérifier que l'utilisateur a participé à la partie
    if ($jsonGame['player1_id'] !== $user_id && $jsonGame['player2_id'] !== $user_id) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Vous n\'avez pas participé à cette partie.'
        ]);
        exit();
    }

    // Préparer la réponse au même format que précédemment
    $responseData = [
        'success' => true,
        'game'    => $jsonGame,
        'moves'   => $jsonGame['moves'] ?? []
    ];

    ob_end_clean();
    echo json_encode($responseData);
    exit();
}

// Create GameController instance
$gameController = new GameController();

// Get game details
try {
    $result = $gameController->getGame($game_id);
    
    logDebug("Résultat getGame: success=" . ($result['success'] ? 'true' : 'false'));
    
    if (!$result['success'] || !isset($result['game'])) {
        logDebug("Partie introuvable: " . json_encode($result));
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Partie introuvable.'
        ]);
        exit();
    }
    
    // Récupérer les détails de la partie
    $game = $result['game'];
    logDebug("Détails de la partie récupérés. Clés: " . json_encode(array_keys($game)));
    
    // Vérification supplémentaire des champs attendus
    $requiredFields = ['id', 'player1_id', 'player2_id', 'status'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($game[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        logDebug("ATTENTION: Champs manquants dans les données de jeu: " . implode(', ', $missingFields));
    }
    
    // Vérification de la structure des données
    logDebug("Structure du jeu - player1_id: " . ($game['player1_id'] ?? 'non défini') . 
             ", player2_id: " . ($game['player2_id'] ?? 'non défini') . 
             ", status: " . ($game['status'] ?? 'non défini'));
    
    // Check if the user participated in the game
    if ($game['player1_id'] !== $user_id && $game['player2_id'] !== $user_id) {
        logDebug("L'utilisateur n'a pas participé à cette partie");
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Vous n\'avez pas participé à cette partie.'
        ]);
        exit();
    }
    
    // Get moves for the game
    // Utilisez getGame avec le paramètre history=true pour récupérer les coups
    logDebug("Récupération des coups pour la partie");
    $gameWithMoves = $gameController->getGame($game_id, true);
    
    if (!isset($gameWithMoves['game']['moves'])) {
        logDebug("ERREUR: Aucun mouvement trouvé dans la réponse de getGame avec history=true");
        logDebug("Structure de gameWithMoves: " . json_encode(array_keys($gameWithMoves)));
        if (isset($gameWithMoves['game'])) {
            logDebug("Structure de gameWithMoves['game']: " . json_encode(array_keys($gameWithMoves['game'])));
        }
    } else {
        logDebug("Coups récupérés: " . count($gameWithMoves['game']['moves']) . " mouvements");
    }
    
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
        try {
            // Vérification de la structure des mouvements
            if (!isset($move['from_position']) || !isset($move['to_position'])) {
                logDebug("ERREUR: Structure de mouvement incorrecte: " . json_encode($move));
                continue;
            }
            
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
                    'captured_pieces' => [],  // Champ réservé pour une future amélioration
                    'timestamp' => $move['move_time'] ?? date('Y-m-d H:i:s'),
                    'player_name' => ($move['user_id'] == $game['player1_id']) ? $player1Name : $player2Name,
                    'color' => ($move['user_id'] == $game['player1_id']) ? 'white' : 'black'
                ];
            } else {
                logDebug("ERREUR: Format de position invalide: from=" . $move['from_position'] . ", to=" . $move['to_position']);
            }
        } catch (Exception $ex) {
            logDebug("ERREUR lors du traitement d'un mouvement: " . $ex->getMessage());
        }
    }
    
    // Récupération des dates de début et fin
    // S'assurer que ces valeurs existent, sinon utiliser des valeurs par défaut
    $startedAt = $game['created_at'] ?? date('Y-m-d H:i:s');
    $finishedAt = $game['updated_at'] ?? null;
    
    // Journal des dates pour débogage
    logDebug("Date de début: " . $startedAt . ", Date de fin: " . ($finishedAt ?? 'non définie'));
    
    // Préparer les informations sur le vainqueur
    $winnerId = $game['winner_id'] ?? null;
    
    logDebug("Préparation de la réponse JSON");
    
    // Préparer la réponse JSON
    $responseData = [
        'success' => true,
        'game' => [
            'id' => $game['id'],
            'status' => $game['status'],
            'created_at' => $startedAt,  // Date de début
            'updated_at' => $finishedAt, // Date de fin
            'player1_id' => $game['player1_id'],
            'player2_id' => $game['player2_id'],
            'player1_name' => $player1Name,
            'player2_name' => $player2Name,
            'user_color' => $userColor,
            'opponent_color' => $opponentColor,
            'winner_id' => $winnerId
        ],
        'moves' => $formattedMoves,
        'total_moves' => count($formattedMoves)
    ];
    
    logDebug("Envoi de la réponse JSON: " . count($formattedMoves) . " mouvements");
    
    // Nettoyage du buffer avant d'envoyer la réponse JSON
    ob_end_clean();
    echo json_encode($responseData);
    
} catch (Exception $e) {
    logDebug("ERREUR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des coups: ' . $e->getMessage()
    ]);
}

// End script
logDebug("Script terminé");
exit(); 