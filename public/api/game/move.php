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
require_once __DIR__ . '/../../../backend/controllers/ProfileController.php';
require_once __DIR__ . '/../../../backend/includes/session.php';

// Vérifier si l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    // Nettoyer le tampon avant de répondre
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour effectuer cette action.'
    ]);
    exit;
}

// Récupérer l'ID de l'utilisateur
$user_id = Session::getUserId();

// Mettre à jour l'activité de l'utilisateur
$profileController = new ProfileController();
$profileController->updateActivity();

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données JSON du corps de la requête
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    // Log pour débogage
    error_log("API move.php appelée par l'utilisateur ID: " . $user_id);
    error_log("Données reçues dans move.php: " . json_encode($data));
    
    // Vérifier que toutes les données nécessaires sont présentes
    if (!isset($data['game_id'])) {
        // Nettoyer le tampon avant de répondre
        ob_end_clean();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'ID de partie manquant.'
        ]);
        exit;
    }
    
    $game_id = intval($data['game_id']);
    
    error_log("move.php - Avant création du GameController");
    try {
        $gameController = new GameController();
        error_log("move.php - GameController créé avec succès");
    } catch (Exception $e) {
        error_log("move.php - ERREUR lors de la création du GameController: " . $e->getMessage());
        error_log("move.php - Trace: " . $e->getTraceAsString());
        
        // Nettoyer le tampon avant de répondre
        ob_end_clean();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Erreur interne du serveur: ' . $e->getMessage()
        ]);
        exit;
    }
    
    // Vérifier si c'est une action d'abandon
    if (isset($data['resign']) && $data['resign'] === true) {
        error_log("Action d'abandon détectée pour la partie " . $game_id);
        
        // Récupérer les détails de la partie
        error_log("move.php - Avant appel à getGame() pour la partie " . $game_id);
        $gameResult = $gameController->getGame($game_id);
        error_log("move.php - Après appel à getGame() - Résultat success: " . ($gameResult['success'] ? 'true' : 'false'));
        if (!$gameResult['success']) {
            // Nettoyer le tampon avant de répondre
            ob_end_clean();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Partie introuvable.'
            ]);
            exit;
        }
        
        $game = $gameResult['game'];
        
        // Déterminer le gagnant (l'adversaire du joueur qui abandonne)
        $winner_id = ($user_id == $game['player1_id']) ? $game['player2_id'] : $game['player1_id'];
        
        // Si l'adversaire est un bot (player2_id = 0)
        if ($game['player2_id'] == 0) {
            error_log("Abandon contre bot détecté: joueur " . $user_id . " abandonne contre l'IA");
            // Dans le cas d'un abandon contre un bot, l'IA gagne
            $winner_id = 0;
        }
        
        // Appel à endGame avec le loser_id explicite pour les parties contre l'IA
        $loser_id = ($game['player2_id'] == 0) ? $user_id : null;
        $result = $gameController->endGame($game_id, $winner_id, $loser_id);
        
        // Log détaillé du résultat de l'abandon pour débogage
        error_log("Résultat de l'abandon : " . ($result ? 'succès' : 'échec'));
        
        // Nettoyer le tampon avant de répondre
        ob_end_clean();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Vous avez abandonné la partie.' : 'Erreur lors de l\'abandon de la partie.'
        ]);
        
        exit;
    }
    
    // Vérifier que toutes les données pour un mouvement sont présentes
    if (!isset($data['from_row']) || !isset($data['from_col']) || !isset($data['to_row']) || !isset($data['to_col'])) {
        // Nettoyer le tampon avant de répondre
        ob_end_clean();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Données de mouvement incomplètes.'
        ]);
        exit;
    }
    
    // Convertir les positions en entiers
    $from_row = intval($data['from_row']);
    $from_col = intval($data['from_col']);
    $to_row = intval($data['to_row']);
    $to_col = intval($data['to_col']);
    
    // Récupérer les détails de la partie
    error_log("move.php - Avant appel à getGame() pour vérifier le tour (partie ID: " . $game_id . ")");
    $gameResult = $gameController->getGame($game_id);
    error_log("move.php - Après appel à getGame() - Résultat success: " . ($gameResult['success'] ? 'true' : 'false'));
    
    if (!$gameResult['success']) {
        // Nettoyer le tampon avant de répondre
        ob_end_clean();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Partie introuvable.'
        ]);
        exit;
    }
    
    $game = $gameResult['game'];
    
    // Déterminer si l'utilisateur est le joueur 1 ou 2
    $is_player1 = ($user_id == $game['player1_id']);
    $player_number = $is_player1 ? 1 : 2;
    
    // Vérifier si c'est au tour du joueur
    if ($game['current_player'] != $player_number) {
        // Nettoyer le tampon avant de répondre
        ob_end_clean();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Ce n\'est pas votre tour.'
        ]);
        exit;
    }
    
    // Effectuer le mouvement
    error_log("move.php - Avant appel à makeMove() - Déplacement de [$from_row,$from_col] vers [$to_row,$to_col] pour joueur $player_number");
    $moveResult = $gameController->makeMove($game_id, $from_row, $from_col, $to_row, $to_col, $player_number);
    error_log("move.php - Après appel à makeMove() - Résultat success: " . ($moveResult['success'] ? 'true' : 'false'));
    
    // Log du résultat pour débogage
    error_log("Résultat du mouvement: " . json_encode($moveResult));
    
    // Nettoyer le tampon avant de répondre
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode($moveResult);
    
} else {
    // Nettoyer le tampon avant de répondre
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée. Utilisez POST.'
    ]);
}
?> 