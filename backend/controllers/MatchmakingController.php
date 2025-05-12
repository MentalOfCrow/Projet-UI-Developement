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
        try {
            // Récupérer l'identifiant du joueur
            $user_id = isset($data['user_id']) ? $data['user_id'] : Session::getUserId();
            
            // Vérifier si le joueur est déjà dans la file d'attente
            $query = "SELECT * FROM queue WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => false,
                    'message' => 'Vous êtes déjà dans la file d\'attente.'
                ];
            }
            
            // Ajouter le joueur à la file d'attente
            $query = "INSERT INTO queue (user_id, joined_at) VALUES (:user_id, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Chercher immédiatement un adversaire
            $opponent = $this->findOpponent($user_id);
            
            if ($opponent) {
                // Créer une nouvelle partie entre les deux joueurs
                $gameController = new GameController();
                $gameData = [
                    'player1_id' => $user_id,
                    'player2_id' => $opponent
                ];
                
                $result = $gameController->createGame($gameData);
                
                if ($result['success']) {
                    // Retirer les deux joueurs de la file d'attente
                    $this->removeFromQueue($user_id);
                    $this->removeFromQueue($opponent);
                    
                    return [
                        'success' => true,
                        'message' => 'Adversaire trouvé! Partie créée.',
                        'game_id' => $result['game_id'],
                        'matched' => true
                    ];
                }
            }
            
            // Aucun adversaire trouvé pour l'instant
            return [
                'success' => true,
                'message' => 'Vous avez rejoint la file d\'attente. En attente d\'un adversaire...',
                'matched' => false
            ];
        } catch (Exception $e) {
            error_log('Erreur dans joinQueue: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Une erreur est survenue.'
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
                        'matched' => true,
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
                $this->removeFromQueue($opponent);
                
                // Créer une nouvelle partie
                $gameResult = $this->gameController->createGame([
                    'player1_id' => $user_id,
                    'player2_id' => $opponent
                ]);
                
                if ($gameResult['success']) {
                    return [
                        'success' => true,
                        'matched' => true,
                        'game_id' => $gameResult['game_id'],
                        'message' => 'Adversaire trouvé ! Redirection vers le plateau de jeu...'
                    ];
                }
            }
            
            // Ajouter le temps d'attente pour le client
            $stmt = $this->db->prepare("SELECT TIMESTAMPDIFF(SECOND, joined_at, NOW()) as wait_time FROM queue WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $waitInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            // -------------------------------------------------------
            // Si l'attente dépasse 15 secondes, lancer une partie vs IA
            // -------------------------------------------------------
            if (($waitInfo['wait_time'] ?? 0) >= 15) {
                // Retirer le joueur de la file d'attente avant de créer la partie
                $this->removeFromQueue($user_id);

                // Créer une partie contre un bot (player2_id = 0)
                $botGame = $this->gameController->createBotGame($user_id);

                if ($botGame['success']) {
                    return [
                        'success' => true,
                        'matched' => true,
                        'game_id' => $botGame['game_id'],
                        'message' => 'Adversaire trouvé ! Redirection vers le plateau de jeu...'
                    ];
                }
                // Si la création échoue, continuer comme si aucun adversaire n'était trouvé
            }

            return [
                'success' => true,
                'matched' => false,
                'wait_time' => $waitInfo['wait_time'] ?? 0,
                'message' => 'En attente d\'un adversaire...'
            ];
            
        } catch (Exception $e) {
            error_log('Erreur dans checkQueue: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification de la file d\'attente: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Recherche un adversaire dans la file d'attente
     * @param int $user_id ID de l'utilisateur
     * @return int|null ID de l'adversaire ou null si aucun trouvé
     */
    private function findOpponent($user_id) {
        try {
            // Rechercher le joueur en attente depuis le plus longtemps (sauf soi-même)
            $query = "SELECT user_id FROM queue WHERE user_id != :user_id ORDER BY joined_at ASC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row['user_id'];
            }
            
            return null;
        } catch (Exception $e) {
            error_log('Erreur dans findOpponent: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Retire un utilisateur de la file d'attente
     * @param int $user_id ID de l'utilisateur
     */
    private function removeFromQueue($user_id) {
        $stmt = $this->db->prepare("DELETE FROM queue WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
    
    /**
     * Gère les actions de la file d'attente via un paramètre action
     * @param array $data Données de la requête
     * @return array Résultat de l'opération
     */
    public function handleQueueAction($data = []) {
        // Récupérer l'action demandée
        $action = isset($data['action']) ? $data['action'] : '';
        
        error_log("handleQueueAction appelée avec l'action: " . $action);
        
        // Traiter l'action
        switch ($action) {
            case 'join':
                return $this->joinQueue($data);
            case 'leave':
                return $this->leaveQueue($data);
            case 'check':
                return $this->checkQueue($data);
            default:
                return [
                    'success' => false,
                    'message' => 'Action non reconnue'
                ];
        }
    }
}