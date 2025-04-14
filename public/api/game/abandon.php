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
        'message' => 'Vous devez être connecté pour abandonner une partie.'
    ]);
    exit;
}

// Vérifier que la méthode est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée. Utilisez POST.'
    ]);
    exit;
}

// Récupérer l'ID de la partie
$input = json_decode(file_get_contents('php://input'), true);
if ($input === null) {
    $input = $_POST;
}

$game_id = isset($input['game_id']) ? intval($input['game_id']) : 0;

if ($game_id <= 0) {
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'ID de partie invalide.'
    ]);
    exit;
}

// Récupérer l'ID de l'utilisateur
$user_id = Session::getUserId();

// Mettre à jour l'activité de l'utilisateur
$profileController = new ProfileController();
$profileController->updateActivity();

try {
    // Créer une instance de GameController
    $gameController = new GameController();
    
    // Log pour déboguer les données reçues
    error_log("abandon.php - Données reçues: game_id=" . $game_id . ", user_id=" . $user_id);
    
    // Récupérer les détails de la partie
    $gameResult = $gameController->getGame($game_id);
    if (!$gameResult['success']) {
        // Nettoyer le tampon avant de répondre
        ob_end_clean();
        
        error_log("abandon.php - Partie introuvable: " . $game_id);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Partie introuvable.'
        ]);
        exit;
    }
    
    $game = $gameResult['game'];
    
    // Log des informations de la partie
    error_log("abandon.php - Infos partie: " . json_encode($game));
    
    // Vérifier que l'utilisateur est un joueur de cette partie
    if ($game['player1_id'] != $user_id && $game['player2_id'] != $user_id) {
        // Nettoyer le tampon avant de répondre
        ob_end_clean();
        
        error_log("abandon.php - Utilisateur non autorisé: user_id=" . $user_id . ", player1_id=" . $game['player1_id'] . ", player2_id=" . $game['player2_id']);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Vous n\'êtes pas un joueur de cette partie.'
        ]);
        exit;
    }
    
    // Vérifier que la partie est en cours
    if ($game['status'] !== 'in_progress') {
        // Nettoyer le tampon avant de répondre
        ob_end_clean();
        
        error_log("abandon.php - Partie non abandonnée car statut '" . $game['status'] . "' au lieu de 'in_progress'");
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Cette partie ne peut pas être abandonnée car elle est déjà ' . ($game['status'] === 'finished' ? 'terminée' : 'dans un état incompatible')
        ]);
        exit;
    }
    
    // Déterminer le gagnant (l'adversaire du joueur qui abandonne)
    $winner_id = ($user_id == $game['player1_id']) ? $game['player2_id'] : $game['player1_id'];
    
    // Si l'adversaire est un bot (player2_id = 0), le bot gagne
    if ($game['player2_id'] == 0) {
        error_log("abandon.php - Abandon contre bot détecté: joueur " . $user_id . " abandonne contre l'IA");
        $winner_id = 0;
    }
    
    // Appel à endGame avec le loser_id explicite pour les parties contre l'IA
    $loser_id = $user_id;
    error_log("abandon.php - Appel à endGame: game_id=" . $game_id . ", winner_id=" . $winner_id . ", loser_id=" . $loser_id);
    
    // Essayer d'abandonner la partie
    $result = $gameController->endGame($game_id, $winner_id, $loser_id);
    error_log("abandon.php - Résultat de l'abandon : " . ($result ? 'succès' : 'échec'));
    
    // Vérification supplémentaire: si endGame échoue, mettre à jour directement la base de données
    if (!$result) {
        error_log("abandon.php - Échec de endGame, tentative de mise à jour directe");
        
        try {
            $db = Database::getInstance()->getConnection();
            $updateQuery = "UPDATE games SET status = 'finished', winner_id = :winner_id, updated_at = NOW() WHERE id = :game_id";
            $stmt = $db->prepare($updateQuery);
            $stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
            $stmt->bindParam(':winner_id', $winner_id, PDO::PARAM_INT);
            $success = $stmt->execute();
            
            error_log("abandon.php - Mise à jour directe: " . ($success ? 'succès' : 'échec'));
            
            if (!$success) {
                // Nettoyer le tampon avant de répondre
                ob_end_clean();
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de l\'abandon de la partie.'
                ]);
                exit;
            }
        } catch (Exception $e) {
            error_log("abandon.php - Erreur lors de la mise à jour directe: " . $e->getMessage());
            
            // Nettoyer le tampon avant de répondre
            ob_end_clean();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de l\'abandon de la partie: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    // Nettoyer le tampon avant de répondre
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Vous avez abandonné la partie.'
    ]);
    
} catch (Exception $e) {
    // En cas d'erreur, retourner un message d'erreur détaillé
    error_log("Erreur dans abandon.php: " . $e->getMessage());
    
    // Nettoyer le tampon avant d'envoyer l'en-tête et les données JSON
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
exit; 