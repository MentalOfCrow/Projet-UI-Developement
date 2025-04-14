<?php
/**
 * Contrôleur pour la gestion des notifications
 * Gère les notifications des utilisateurs (demandes d'amis, invitations de partie, etc.)
 */

require_once __DIR__ . '/../db/Database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/User.php';

class NotificationController {
    private $db;
    
    public function __construct() {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }
    
    /**
     * Ajoute une notification pour un utilisateur
     * @param int $userId ID de l'utilisateur destinataire
     * @param string $type Type de notification (friend_request, game_invite, game_turn, message)
     * @param string $message Contenu de la notification
     * @param mixed $data Données supplémentaires (JSON)
     * @return bool Succès de l'opération
     */
    public function addNotification($userId, $type, $message, $data = null) {
        try {
            $stmt = $this->db->prepare("INSERT INTO notifications (user_id, type, message, data, created_at) 
                VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$userId, $type, $message, json_encode($data)]);
            return true;
        } catch (PDOException $e) {
            error_log("Erreur dans NotificationController::addNotification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupère les notifications non lues d'un utilisateur
     * @param int $userId ID de l'utilisateur
     * @return array Tableau des notifications
     */
    public function getUnreadNotifications($userId) {
        $notifications = [];
        try {
            $stmt = $this->db->prepare("SELECT id, type, message, data, created_at 
                FROM notifications 
                WHERE user_id = ? AND read_status = 0 
                ORDER BY created_at DESC");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $notifications;
        } catch (PDOException $e) {
            error_log("Erreur dans NotificationController::getUnreadNotifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère toutes les notifications d'un utilisateur
     * @param int $page Numéro de page
     * @param int $perPage Nombre d'éléments par page
     * @return array Notifications de l'utilisateur
     */
    public function getAllNotifications($page = 1, $perPage = 20) {
        if (!Session::isLoggedIn()) {
            return [
                'success' => false,
                'message' => 'Vous devez être connecté pour voir vos notifications.'
            ];
        }
        
        $userId = Session::getUserId();
        $offset = ($page - 1) * $perPage;
        
        try {
            // Récupérer le nombre total de notifications
            $countStmt = $this->db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
            $countStmt->execute([$userId]);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Récupérer les notifications paginées
            $stmt = $this->db->prepare("SELECT id, type, message, data, read_status, created_at 
                FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?");
            $stmt->execute([$userId, $perPage, $offset]);
            
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'notifications' => $notifications,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage)
                ]
            ];
        } catch (PDOException $e) {
            error_log("Erreur dans NotificationController::getAllNotifications: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des notifications.'
            ];
        }
    }
    
    /**
     * Marque une notification comme lue
     * @param int $notificationId ID de la notification
     * @return array Résultat de l'opération
     */
    public function markAsRead($notificationId) {
        if (!Session::isLoggedIn()) {
            return [
                'success' => false,
                'message' => 'Vous devez être connecté pour marquer une notification comme lue.'
            ];
        }
        
        $userId = Session::getUserId();
        
        try {
            $stmt = $this->db->prepare("UPDATE notifications SET read_status = 1 
                WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $userId]);
            
            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'Notification non trouvée ou vous n\'êtes pas autorisé à la modifier.'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Notification marquée comme lue.'
            ];
        } catch (PDOException $e) {
            error_log("Erreur dans NotificationController::markAsRead: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors du marquage de la notification.'
            ];
        }
    }
    
    /**
     * Marque toutes les notifications d'un utilisateur comme lues
     * @return array Résultat de l'opération
     */
    public function markAllAsRead() {
        if (!Session::isLoggedIn()) {
            return [
                'success' => false,
                'message' => 'Vous devez être connecté pour marquer vos notifications comme lues.'
            ];
        }
        
        $userId = Session::getUserId();
        
        try {
            $stmt = $this->db->prepare("UPDATE notifications SET read_status = 1 
                WHERE user_id = ? AND read_status = 0");
            $stmt->execute([$userId]);
            
            $count = $stmt->rowCount();
            
            return [
                'success' => true,
                'message' => $count > 0 ? 
                    $count . ' notification' . ($count > 1 ? 's' : '') . ' marquée' . ($count > 1 ? 's' : '') . ' comme lue' . ($count > 1 ? 's' : '') . '.' :
                    'Aucune nouvelle notification à marquer comme lue.'
            ];
        } catch (PDOException $e) {
            error_log("Erreur dans NotificationController::markAllAsRead: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors du marquage des notifications.'
            ];
        }
    }
    
    /**
     * Supprime une notification
     * @param int $notificationId ID de la notification
     * @return array Résultat de l'opération
     */
    public function deleteNotification($notificationId) {
        if (!Session::isLoggedIn()) {
            return [
                'success' => false,
                'message' => 'Vous devez être connecté pour supprimer une notification.'
            ];
        }
        
        $userId = Session::getUserId();
        
        try {
            $stmt = $this->db->prepare("DELETE FROM notifications 
                WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $userId]);
            
            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'Notification non trouvée ou vous n\'êtes pas autorisé à la supprimer.'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Notification supprimée.'
            ];
        } catch (PDOException $e) {
            error_log("Erreur dans NotificationController::deleteNotification: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression de la notification.'
            ];
        }
    }
    
    /**
     * Supprime toutes les notifications d'un utilisateur
     * @return array Résultat de l'opération
     */
    public function deleteAllNotifications() {
        if (!Session::isLoggedIn()) {
            return [
                'success' => false,
                'message' => 'Vous devez être connecté pour supprimer vos notifications.'
            ];
        }
        
        $userId = Session::getUserId();
        
        try {
            $stmt = $this->db->prepare("DELETE FROM notifications WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            $count = $stmt->rowCount();
            
            return [
                'success' => true,
                'message' => $count > 0 ? 
                    $count . ' notification' . ($count > 1 ? 's' : '') . ' supprimée' . ($count > 1 ? 's' : '') . '.' :
                    'Aucune notification à supprimer.'
            ];
        } catch (PDOException $e) {
            error_log("Erreur dans NotificationController::deleteAllNotifications: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression des notifications.'
            ];
        }
    }

    /**
     * Vérifie si une notification appartient à un utilisateur
     * @param int $notificationId ID de la notification
     * @param int $userId ID de l'utilisateur
     * @return bool True si la notification appartient à l'utilisateur, false sinon
     */
    public function belongsToUser($notificationId, $userId) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count 
                FROM notifications 
                WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $userId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Erreur dans NotificationController::belongsToUser: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crée une notification avec données supplémentaires
     * @param int $user_id ID de l'utilisateur destinataire
     * @param string $type Type de notification
     * @param string $message Contenu textuel de la notification
     * @param array $data Données additionnelles (converties en JSON)
     * @return bool|int ID de la notification créée ou false en cas d'erreur
     */
    public function createNotification($user_id, $type, $message, $data = []) {
        try {
            $query = "INSERT INTO notifications (user_id, type, message, data, created_at) 
                      VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $user_id,
                $type,
                $message,
                json_encode($data)
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur dans NotificationController::createNotification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupère les notifications d'un utilisateur
     * 
     * @param int $user_id ID de l'utilisateur
     * @param int $limit Nombre maximal de notifications à récupérer
     * @param int $offset Décalage pour la pagination
     * @return array Liste des notifications
     */
    public function getNotifications($user_id, $limit = 10, $offset = 0) {
        try {
            $query = "SELECT id, type, message, data, read_status, created_at 
                      FROM notifications 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC, id DESC
                      LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$user_id, $limit, $offset]);
            
            $notifications = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['data'] = json_decode($row['data'], true);
                $notifications[] = $row;
            }
            
            return $notifications;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Compte le nombre de notifications non lues pour un utilisateur
     * 
     * @param int $user_id ID de l'utilisateur
     * @return int Nombre de notifications non lues
     */
    public function countUnread($user_id) {
        try {
            $query = "SELECT COUNT(*) as count 
                      FROM notifications 
                      WHERE user_id = ? AND read_status = 0";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$user_id]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['count'];
        } catch (PDOException $e) {
            error_log("Erreur lors du comptage des notifications non lues: " . $e->getMessage());
            return 0;
        }
    }
} 