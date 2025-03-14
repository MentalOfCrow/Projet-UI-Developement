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
    $gameController = new GameController();
    
    // Vérifier si c'est une action d'abandon
    if (isset($data['resign']) && $data['resign'] === true) {
        error_log("Action d'abandon détectée pour la partie " . $game_id);
        
        // Récupérer les détails de la partie
        $gameResult = $gameController->getGame($game_id);
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
        
        // Si l'adversaire est un bot (player2_id = 0), définir le gagnant à 0
        if ($winner_id == 0) {
            $winner_id = 0;
        }
        
        // Mettre fin à la partie
        $result = $gameController->endGame($game_id, $winner_id);
        
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
    $gameResult = $gameController->getGame($game_id);
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
    $moveResult = $gameController->makeMove($game_id, $from_row, $from_col, $to_row, $to_col, $player_number);
    
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