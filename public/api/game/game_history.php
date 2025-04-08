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

// Vérifier si l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    // Nettoyer le tampon avant de répondre
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour accéder à cette API.'
    ]);
    exit;
}

// Récupérer l'ID de l'utilisateur
$user_id = Session::getUserId();

try {
    // Créer une instance de GameController
    $gameController = new GameController();
    
    // Log pour débogage
    error_log("API game_history.php appelée par l'utilisateur ID: " . $user_id);
    
    // Récupérer l'historique des parties
    $db = Database::getInstance()->getConnection();
    
    // Récupérer directement les parties terminées
    $query = "SELECT g.*, 
            u1.username as player1_name, 
            u2.username as player2_name
            FROM games g
            LEFT JOIN users u1 ON g.player1_id = u1.id
            LEFT JOIN users u2 ON g.player2_id = u2.id
            WHERE (g.player1_id = ? OR g.player2_id = ?) 
            AND g.status = 'finished'
            ORDER BY g.updated_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $user_id]);
    
    $games = [];
    $totalGames = $stmt->rowCount();
    
    while ($game = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Déterminer si l'utilisateur est le joueur 1 ou 2
        $isPlayer1 = $game['player1_id'] == $user_id;
        
        // Déterminer l'adversaire
        $opponentName = $isPlayer1 ? ($game['player2_name'] ?? 'Inconnu') : ($game['player1_name'] ?? 'Inconnu');
        if ($game['player2_id'] === '0' || $game['player2_id'] === 0) {
            $opponentName = 'Intelligence Artificielle';
        }
        
        // Déterminer le résultat
        $result = "Match nul";
        
        // Un match nul est défini uniquement lorsque le gagnant est null ET qu'aucun des deux joueurs n'a perdu
        // Cas particulier: partie contre l'IA avec winner_id null, c'est toujours une défaite pour le joueur
        if (($game['player2_id'] === 0 || $game['player2_id'] === '0') && $game['winner_id'] === null) {
            $result = "Défaite";
        } 
        // Sinon, vérifier si un gagnant est défini
        else if ($game['winner_id'] !== null) {
            if ($game['winner_id'] == $user_id) {
                $result = "Victoire";
            } else {
                $result = "Défaite";
            }
        } else {
            // Si winner_id est null et ce n'est pas contre un bot, c'est un match nul légitime
            $result = "Match nul";
        }
        
        // Formater les données
        $games[] = [
            'id' => $game['id'],
            'opponent_name' => $opponentName,
            'opponent_id' => $isPlayer1 ? $game['player2_id'] : $game['player1_id'],
            'date' => $game['updated_at'],
            'formatted_date' => date('d/m/Y H:i', strtotime($game['updated_at'])),
            'result' => $result,
            'is_player1' => $isPlayer1,
            'winner_id' => $game['winner_id'],
            'has_won' => $game['winner_id'] == $user_id
        ];
    }
    
    // Nettoyer le tampon avant de répondre
    ob_end_clean();
    
    // Retourner le résultat en JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'total_games' => $totalGames,
        'games' => $games
    ]);
    
} catch (Exception $e) {
    // En cas d'erreur, retourner un message d'erreur détaillé
    error_log("Erreur dans game_history.php: " . $e->getMessage());
    
    // Nettoyer le tampon avant de répondre
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}

exit; 