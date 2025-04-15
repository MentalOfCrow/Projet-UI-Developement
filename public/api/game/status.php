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
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à cette fonctionnalité.'
    ]);
    exit;
}

// Récupérer l'ID de l'utilisateur
$user_id = Session::getUserId();

// Mettre à jour l'activité de l'utilisateur
$profileController = new ProfileController();
$profileController->updateActivity();

// Log pour débogage
error_log("API status.php appelée par l'utilisateur ID: " . $user_id);

// Vérifier si l'ID de la partie est fourni
$game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : 
           (isset($_POST['game_id']) ? intval($_POST['game_id']) : 0);

if (!$game_id) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'ID de partie manquant.'
    ]);
    exit;
}

try {
    // Créer une instance de GameController
    $gameController = new GameController();
    
    // Récupérer le statut de la partie
    $result = $gameController->getGame($game_id);
    
    // Log du résultat
    error_log("Résultat de getGameStatus: " . json_encode($result));
    
    if (!$result['success']) {
        // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
        ob_end_clean();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Partie non trouvée.'
        ]);
        exit;
    }

    // Récupérer l'état de la partie
    $game = $result['game'];

    // Déterminer si c'est au tour du joueur connecté
    $is_player1 = ($user_id == $game['player1_id']);
    $is_player2 = ($user_id == $game['player2_id'] || $game['player2_id'] == 0);
    $is_user_turn = ($is_player1 && $game['current_player'] == 1) || ($is_player2 && $game['current_player'] == 2);

    // Ajuster la réponse en fonction de qui est l'utilisateur connecté
    $response = [
        'success' => true,
        'game' => [
            'id' => $game['id'],
            'status' => $game['status'],
            'current_player' => intval($game['current_player']),
            'is_your_turn' => $is_user_turn,
            'player1_id' => $game['player1_id'],
            'player2_id' => $game['player2_id'],
            'player1_name' => $game['player1_name'],
            'player2_name' => $game['player2_name'],
            'updated_at' => $game['updated_at']
        ]
    ];

    // Si la partie est terminée, ajouter les informations sur le gagnant
    if ($game['status'] === 'finished') {
        $response['game']['winner_id'] = $game['winner_id'];
        $response['game']['you_won'] = ($game['winner_id'] == $user_id);
    }

    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    // Retourner le résultat en JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    // En cas d'erreur, retourner un message d'erreur détaillé
    error_log("Erreur dans status.php: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
exit; 