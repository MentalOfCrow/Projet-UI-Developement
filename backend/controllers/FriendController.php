<?php
/**
 * Contrôleur pour la gestion des amis
 * Gère les demandes d'amis, la liste d'amis et les interactions sociales
 */

require_once __DIR__ . '/../db/Database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/NotificationController.php';

class FriendController {
    private $db;
    private $notificationController;
    
    public function __construct() {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
        $this->notificationController = new NotificationController();
    }
    
    /**
     * Envoie une demande d'ami à un utilisateur
     * @param int $receiverId ID de l'utilisateur destinataire
     * @return array Résultat de l'opération
     */
    public function sendFriendRequest($receiverId) {
        if (!Session::isLoggedIn()) {
            return [
                'success' => false,
                'message' => 'Vous devez être connecté pour envoyer une demande d\'ami.'
            ];
        }
        
        $senderId = Session::getUserId();
        
        // Ne pas permettre d'envoyer une demande à soi-même
        if ($senderId == $receiverId) {
            return [
                'success' => false,
                'message' => 'Vous ne pouvez pas vous envoyer une demande d\'ami à vous-même.'
            ];
        }
        
        try {
            // Vérifier si le destinataire existe
            $stmt = $this->db->prepare("SELECT id, username, privacy_setting, friend_requests_setting FROM users WHERE id = ?");
            $stmt->execute([$receiverId]);
            
            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur destinataire non trouvé.'
                ];
            }
            
            $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Vérifier les paramètres de confidentialité
            if ($receiver['friend_requests_setting'] === 'none') {
                return [
                    'success' => false,
                    'message' => 'Cet utilisateur n\'accepte pas les demandes d\'ami.'
                ];
            }
            
            if ($receiver['friend_requests_setting'] === 'friends_of_friends') {
                // Vérifier si un ami commun existe
                $hasMutualFriend = $this->hasMutualFriends($senderId, $receiverId);
                
                if (!$hasMutualFriend) {
                    return [
                        'success' => false,
                        'message' => 'Cet utilisateur n\'accepte que les demandes d\'ami des amis de ses amis.'
                    ];
                }
            }
            
            // Vérifier si les utilisateurs sont déjà amis
            $stmt = $this->db->prepare("SELECT id FROM friendships 
                WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
            $stmt->execute([$senderId, $receiverId, $receiverId, $senderId]);
            
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => false,
                    'message' => 'Vous êtes déjà ami avec cet utilisateur.'
                ];
            }
            
            // Vérifier s'il y a déjà une demande en attente
            $stmt = $this->db->prepare("SELECT id, status FROM friend_requests 
                WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
            $stmt->execute([$senderId, $receiverId, $receiverId, $senderId]);
            
            if ($stmt->rowCount() > 0) {
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($request['status'] === 'pending') {
                    // Si la demande a été envoyée par l'autre utilisateur, accepter la demande
                    $stmt = $this->db->prepare("SELECT sender_id FROM friend_requests WHERE id = ?");
                    $stmt->execute([$request['id']]);
                    $sender = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($sender['sender_id'] == $receiverId) {
                        return $this->respondToFriendRequest($request['id'], 'accept');
                    }
                    
                    return [
                        'success' => false,
                        'message' => 'Vous avez déjà envoyé une demande d\'ami à cet utilisateur.'
                    ];
                } elseif ($request['status'] === 'rejected') {
                    // Mettre à jour la demande rejetée
                    $stmt = $this->db->prepare("UPDATE friend_requests SET status = 'pending', created_at = NOW() 
                        WHERE id = ?");
                    $stmt->execute([$request['id']]);
                    
                    // Envoyer une notification
                    $this->notificationController->addNotification(
                        $receiverId,
                        'friend_request',
                        "Vous avez reçu une demande d'ami de " . Session::getUsername(),
                        $request['id']
                    );
                    
                    return [
                        'success' => true,
                        'message' => 'Demande d\'ami envoyée avec succès.'
                    ];
                }
            }
            
            // Créer une nouvelle demande
            $stmt = $this->db->prepare("INSERT INTO friend_requests (sender_id, receiver_id, status, created_at) 
                VALUES (?, ?, 'pending', NOW())");
            $stmt->execute([$senderId, $receiverId]);
            $requestId = $this->db->lastInsertId();
            
            // Envoyer une notification
            $this->notificationController->addNotification(
                $receiverId,
                'friend_request',
                "Vous avez reçu une demande d'ami de " . Session::getUsername(),
                $requestId
            );
            
            return [
                'success' => true,
                'message' => 'Demande d\'ami envoyée avec succès.'
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur dans FriendController::sendFriendRequest: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de la demande d\'ami.'
            ];
        }
    }
    
    /**
     * Répond à une demande d'ami (accepte ou refuse)
     * @param int $requestId ID de la demande d'ami
     * @param string $action Action à effectuer ('accept' ou 'reject')
     * @return array Résultat de l'opération
     */
    public function respondToFriendRequest($requestId, $action) {
        if (!Session::isLoggedIn()) {
            return [
                'success' => false,
                'message' => 'Vous devez être connecté pour répondre à une demande d\'ami.'
            ];
        }
        
        $userId = Session::getUserId();
        
        if (!in_array($action, ['accept', 'reject'])) {
            return [
                'success' => false,
                'message' => 'Action non valide. Utilisez "accept" ou "reject".'
            ];
        }
        
        try {
            // Vérifier si la demande existe et si l'utilisateur est le destinataire
            $stmt = $this->db->prepare("SELECT id, sender_id, receiver_id, status 
                FROM friend_requests 
                WHERE id = ? AND receiver_id = ? AND status = 'pending'");
            $stmt->execute([$requestId, $userId]);
            
            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'Demande d\'ami non trouvée ou vous n\'êtes pas le destinataire.'
                ];
            }
            
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            $senderId = $request['sender_id'];
            
            // Obtenir le nom d'utilisateur de l'expéditeur
            $stmt = $this->db->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$senderId]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            $senderUsername = $sender['username'];
            
            if ($action === 'accept') {
                // Mettre à jour le statut de la demande
                $stmt = $this->db->prepare("UPDATE friend_requests SET status = 'accepted', updated_at = NOW() 
                    WHERE id = ?");
                $stmt->execute([$requestId]);
                
                // Créer une relation d'amitié
                $stmt = $this->db->prepare("INSERT INTO friendships (user1_id, user2_id, created_at) 
                    VALUES (?, ?, NOW())");
                $stmt->execute([$senderId, $userId]);
                
                // Envoyer une notification à l'expéditeur
                $this->notificationController->addNotification(
                    $senderId,
                    'friend_request',
                    Session::getUsername() . " a accepté votre demande d'ami",
                    null
                );
                
                return [
                    'success' => true,
                    'message' => 'Demande d\'ami acceptée. ' . $senderUsername . ' a été ajouté à votre liste d\'amis.'
                ];
            } else {
                // Mettre à jour le statut de la demande
                $stmt = $this->db->prepare("UPDATE friend_requests SET status = 'rejected', updated_at = NOW() 
                    WHERE id = ?");
                $stmt->execute([$requestId]);
                
                return [
                    'success' => true,
                    'message' => 'Demande d\'ami refusée.'
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Erreur dans FriendController::respondToFriendRequest: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors du traitement de la demande d\'ami.'
            ];
        }
    }
    
    /**
     * Supprime un ami de la liste d'amis
     * @param int $friendId ID de l'ami à supprimer
     * @return array Résultat de l'opération
     */
    public function removeFriend($friendId) {
        if (!Session::isLoggedIn()) {
            return [
                'success' => false,
                'message' => 'Vous devez être connecté pour supprimer un ami.'
            ];
        }
        
        $userId = Session::getUserId();
        
        try {
            // Vérifier si la relation d'amitié existe
            $stmt = $this->db->prepare("SELECT id FROM friendships 
                WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
            $stmt->execute([$userId, $friendId, $friendId, $userId]);
            
            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'Cet utilisateur n\'est pas dans votre liste d\'amis.'
                ];
            }
            
            // Obtenir le nom d'utilisateur de l'ami
            $stmt = $this->db->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$friendId]);
            $friend = $stmt->fetch(PDO::FETCH_ASSOC);
            $friendUsername = $friend['username'];
            
            // Supprimer la relation d'amitié
            $stmt = $this->db->prepare("DELETE FROM friendships 
                WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
            $stmt->execute([$userId, $friendId, $friendId, $userId]);
            
            // Supprimer également les demandes d'ami entre ces utilisateurs
            $stmt = $this->db->prepare("DELETE FROM friend_requests 
                WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
            $stmt->execute([$userId, $friendId, $friendId, $userId]);
            
            return [
                'success' => true,
                'message' => $friendUsername . ' a été retiré de votre liste d\'amis.'
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur dans FriendController::removeFriend: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'ami.'
            ];
        }
    }
    
    /**
     * Récupère la liste des amis d'un utilisateur
     * @param int $userId ID de l'utilisateur
     * @return array Résultat de l'opération avec la liste des amis
     */
    public function getFriendsList($userId) {
        try {
            // Récupérer les amis
            $stmt = $this->db->prepare("
                SELECT u.id, u.username, u.avatar_path, u.last_activity, u.appear_offline
                FROM friendships f
                JOIN users u ON (f.user1_id = u.id OR f.user2_id = u.id)
                WHERE (f.user1_id = ? OR f.user2_id = ?) AND u.id != ?
                ORDER BY u.username ASC
            ");
            $stmt->execute([$userId, $userId, $userId]);
            
            $friends = [];
            $currentTime = time();
            
            while ($friend = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Déterminer le statut en ligne
                $status = 'offline';
                
                if (!$friend['appear_offline']) {
                    if ($friend['last_activity']) {
                        $lastActivity = strtotime($friend['last_activity']);
                        $timeDiff = $currentTime - $lastActivity;
                        
                        // Considérer comme en ligne si l'activité date de moins de 5 minutes
                        if ($timeDiff < 300) { // 5 minutes = 300 secondes
                            $status = 'online';
                        }
                    }
                }
                
                $friends[] = [
                    'id' => $friend['id'],
                    'username' => $friend['username'],
                    'avatar_path' => $friend['avatar_path'],
                    'status' => $status
                ];
            }
            
            return [
                'success' => true,
                'friends' => $friends,
                'count' => count($friends)
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur dans FriendController::getFriendsList: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération de la liste d\'amis.'
            ];
        }
    }
    
    /**
     * Récupère les demandes d'ami en attente pour l'utilisateur connecté
     * @return array Résultat de l'opération avec les demandes reçues et envoyées
     */
    public function getPendingFriendRequests() {
        if (!Session::isLoggedIn()) {
            return [
                'success' => false,
                'message' => 'Vous devez être connecté pour voir vos demandes d\'ami.'
            ];
        }
        
        $userId = Session::getUserId();
        
        try {
            // Récupérer les demandes reçues
            $receivedStmt = $this->db->prepare("
                SELECT fr.id, fr.sender_id, fr.created_at, u.username, u.avatar_path
                FROM friend_requests fr
                JOIN users u ON fr.sender_id = u.id
                WHERE fr.receiver_id = ? AND fr.status = 'pending'
                ORDER BY fr.created_at DESC
            ");
            $receivedStmt->execute([$userId]);
            
            $receivedRequests = [];
            while ($request = $receivedStmt->fetch(PDO::FETCH_ASSOC)) {
                $receivedRequests[] = [
                    'id' => $request['id'],
                    'sender_id' => $request['sender_id'],
                    'username' => $request['username'],
                    'avatar_path' => $request['avatar_path'],
                    'created_at' => $request['created_at']
                ];
            }
            
            // Récupérer les demandes envoyées
            $sentStmt = $this->db->prepare("
                SELECT fr.id, fr.receiver_id, fr.created_at, u.username, u.avatar_path
                FROM friend_requests fr
                JOIN users u ON fr.receiver_id = u.id
                WHERE fr.sender_id = ? AND fr.status = 'pending'
                ORDER BY fr.created_at DESC
            ");
            $sentStmt->execute([$userId]);
            
            $sentRequests = [];
            while ($request = $sentStmt->fetch(PDO::FETCH_ASSOC)) {
                $sentRequests[] = [
                    'id' => $request['id'],
                    'receiver_id' => $request['receiver_id'],
                    'username' => $request['username'],
                    'avatar_path' => $request['avatar_path'],
                    'created_at' => $request['created_at']
                ];
            }
            
            return [
                'success' => true,
                'received' => $receivedRequests,
                'sent' => $sentRequests
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur dans FriendController::getPendingFriendRequests: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des demandes d\'ami.'
            ];
        }
    }
    
    /**
     * Vérifie si deux utilisateurs ont des amis en commun
     * @param int $user1Id ID du premier utilisateur
     * @param int $user2Id ID du deuxième utilisateur
     * @return bool True s'ils ont des amis en commun, false sinon
     */
    private function hasMutualFriends($user1Id, $user2Id) {
        try {
            // Récupérer les amis du premier utilisateur
            $stmt = $this->db->prepare("
                SELECT IF(user1_id = ?, user2_id, user1_id) as friend_id
                FROM friendships
                WHERE user1_id = ? OR user2_id = ?
            ");
            $stmt->execute([$user1Id, $user1Id, $user1Id]);
            
            $user1Friends = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $user1Friends[] = $row['friend_id'];
            }
            
            if (empty($user1Friends)) {
                return false;
            }
            
            // Récupérer les amis du deuxième utilisateur
            $stmt = $this->db->prepare("
                SELECT IF(user1_id = ?, user2_id, user1_id) as friend_id
                FROM friendships
                WHERE user1_id = ? OR user2_id = ?
            ");
            $stmt->execute([$user2Id, $user2Id, $user2Id]);
            
            $user2Friends = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $user2Friends[] = $row['friend_id'];
            }
            
            // Vérifier s'il y a des amis en commun
            $mutualFriends = array_intersect($user1Friends, $user2Friends);
            
            return !empty($mutualFriends);
            
        } catch (PDOException $e) {
            error_log("Erreur dans FriendController::hasMutualFriends: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Vérifie si deux utilisateurs sont amis
     * @param int $user1Id ID du premier utilisateur
     * @param int $user2Id ID du deuxième utilisateur
     * @return bool True si les utilisateurs sont amis, false sinon
     */
    public function areFriends($user1Id, $user2Id) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count 
                FROM friendships 
                WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
            $stmt->execute([$user1Id, $user2Id, $user2Id, $user1Id]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Erreur dans FriendController::areFriends: " . $e->getMessage());
            return false;
        }
    }
} 