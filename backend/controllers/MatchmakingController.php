<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../db/Database.php';
require_once __DIR__ . '/../controllers/GameController.php';
require_once __DIR__ . '/../includes/session.php';

/**
 * Contrôleur pour gérer le matchmaking des parties
 */
class MatchmakingController {
    private $db;
    private $gameController;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->gameController = new GameController();
    }
    
    /**
     * Ajoute un utilisateur à la file d'attente
     * @param array $data Données de la requête
     * @return array Résultat de l'opération
     */
    public function joinQueue($data = []) {
        Session::requireLogin();
        $user_id = Session::getUserId();
        
        try {
            // Vérifier si l'utilisateur est déjà dans la file d'attente
            $stmt = $this->db->prepare("SELECT * FROM queue WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Vous êtes déjà dans la file d\'attente.'
                ];
            }
            
            // Ajouter l'utilisateur à la file d'attente
            $stmt = $this->db->prepare("INSERT INTO queue (user_id, joined_at) VALUES (?, NOW())");
            $stmt->execute([$user_id]);
            
            // Chercher un adversaire
            $opponent = $this->findOpponent($user_id);
            
            if ($opponent) {
                // Retirer les deux joueurs de la file d'attente
                $this->removeFromQueue($user_id);
                $this->removeFromQueue($opponent['user_id']);
                
                // Créer une nouvelle partie
                $gameResult = $this->gameController->createGame([
                    'player1_id' => $user_id,
                    'player2_id' => $opponent['user_id']
                ]);
                
                if ($gameResult['success']) {
                    return [
                        'success' => true,
                        'match_found' => true,
                        'game_id' => $gameResult['game_id'],
                        'message' => 'Adversaire trouvé ! Redirection vers le plateau de jeu...'
                    ];
                }
            }
            
            return [
                'success' => true,
                'match_found' => false,
                'message' => 'Vous avez rejoint la file d\'attente. En attente d\'un adversaire...'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'ajout à la file d\'attente: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Retire un utilisateur de la file d'attente
     * @param array $data Données de la requête
     * @return array Résultat de l'opération
     */
    public function leaveQueue($data = []) {
        Session::requireLogin();
        $user_id = Session::getUserId();
        
        try {
            $this->removeFromQueue($user_id);
            
            return [
                'success' => true,
                'message' => 'Vous avez quitté la file d\'attente.'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors du retrait de la file d\'attente: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Vérifie si un match a été trouvé pour l'utilisateur
     * @param array $data Données de la requête
     * @return array Résultat de l'opération
     */
    public function checkQueue($data = []) {
        Session::requireLogin();
        $user_id = Session::getUserId();
        
        try {
            // Vérifier si l'utilisateur est toujours dans la file d'attente
            $stmt = $this->db->prepare("SELECT * FROM queue WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            if ($stmt->rowCount() == 0) {
                // L'utilisateur n'est plus dans la file, vérifier s'il a une partie en cours
                $stmt = $this->db->prepare("
                    SELECT id FROM games 
                    WHERE (player1_id = ? OR player2_id = ?) 
                    AND status = 'in_progress'
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$user_id, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $game = $stmt->fetch(PDO::FETCH_ASSOC);
                    return [
                        'success' => true,
                        'match_found' => true,
                        'game_id' => $game['id'],
                        'message' => 'Adversaire trouvé ! Redirection vers le plateau de jeu...'
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => 'Vous n\'êtes plus dans la file d\'attente.'
                ];
            }
            
            // Chercher un adversaire
            $opponent = $this->findOpponent($user_id);
            
            if ($opponent) {
                // Retirer les deux joueurs de la file d'attente
                $this->removeFromQueue($user_id);
                $this->removeFromQueue($opponent['user_id']);
                
                // Créer une nouvelle partie
                $gameResult = $this->gameController->createGame([
                    'player1_id' => $user_id,
                    'player2_id' => $opponent['user_id']
                ]);
                
                if ($gameResult['success']) {
                    return [
                        'success' => true,
                        'match_found' => true,
                        'game_id' => $gameResult['game_id'],
                        'message' => 'Adversaire trouvé ! Redirection vers le plateau de jeu...'
                    ];
                }
            }
            
            return [
                'success' => true,
                'match_found' => false,
                'message' => 'En attente d\'un adversaire...'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification de la file d\'attente: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Recherche un adversaire dans la file d'attente
     * @param int $user_id ID de l'utilisateur
     * @return array|false Données de l'adversaire ou false si aucun trouvé
     */
    private function findOpponent($user_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM queue 
            WHERE user_id != ? 
            ORDER BY joined_at ASC 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return false;
    }
    
    /**
     * Retire un utilisateur de la file d'attente
     * @param int $user_id ID de l'utilisateur
     */
    private function removeFromQueue($user_id) {
        $stmt = $this->db->prepare("DELETE FROM queue WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
}