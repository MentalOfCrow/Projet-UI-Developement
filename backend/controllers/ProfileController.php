<?php
/**
 * Contrôleur pour la gestion des profils utilisateurs
 * Gère l'affichage et la modification des profils
 */

require_once __DIR__ . '/../db/Database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/FriendController.php';

class ProfileController {
    private $db;
    private $friendController;
    private $user;
    
    public function __construct() {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
        $this->friendController = new FriendController();
        $this->user = new User();
    }
    
    /**
     * Récupère le profil d'un utilisateur
     * Récupère les informations de profil d'un utilisateur
     * @param int $userId ID de l'utilisateur
     * @return array Informations du profil
     */
    public function getProfile($userId) {
        try {
            $query = "SELECT id, username, email, created_at, last_login, 
                     COALESCE(privacy_setting, 'friends') as privacy_level,
                     TIMESTAMPDIFF(MINUTE, last_activity, NOW()) < 5 as is_online,
                     last_activity 
                     FROM users WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId]);
            
            if ($stmt->rowCount() > 0) {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                // Assurons-nous que le résultat a toujours id, username, etc.
                $result['id'] = $result['id'] ?? $userId;
                $result['username'] = $result['username'] ?? 'Utilisateur inconnu';
                $result['email'] = $result['email'] ?? '';
                $result['created_at'] = $result['created_at'] ?? date('Y-m-d H:i:s');
                $result['privacy_level'] = $result['privacy_level'] ?? 'friends';
                return $result;
            }
            
            // Au lieu de retourner false, retourner un tableau avec la propriété 'exists' à false
            return [
                'id' => $userId,
                'username' => 'Utilisateur inconnu',
                'email' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'privacy_level' => 'friends',
                'exists' => false
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération du profil: " . $e->getMessage());
            // Retourner un tableau avec message d'erreur au lieu de false
            return [
                'id' => $userId,
                'username' => 'Utilisateur inconnu',
                'email' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'privacy_level' => 'friends',
                'error' => true,
                'error_message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Met à jour les informations du profil utilisateur
     * @param array $data Données à mettre à jour
     * @return array Résultat de l'opération
     */
    public function updateProfile($data) {
        if (!Session::isLoggedIn()) {
            return ['success' => false, 'message' => 'Vous devez être connecté pour modifier votre profil.'];
        }
        
        $userId = Session::getUserId();
        $allowedFields = ['username', 'email', 'privacy_setting', 'friend_requests_setting', 'appear_offline'];
        $updateData = [];
        $params = [];
        
        // Filtrer les champs autorisés
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updateData[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updateData)) {
            return ['success' => false, 'message' => 'Aucune donnée valide à mettre à jour.'];
        }
        
        // Ajouter l'ID utilisateur aux paramètres
        $params[] = $userId;
        
        try {
            $updateQuery = "UPDATE users SET " . implode(', ', $updateData) . " WHERE id = ?";
            $stmt = $this->db->prepare($updateQuery);
            $stmt->execute($params);
            
            // Si le username a été modifié, mettre à jour la session
            if (isset($data['username'])) {
                $_SESSION['username'] = $data['username'];
            }
            
            return ['success' => true, 'message' => 'Profil mis à jour avec succès.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour du profil: ' . $e->getMessage()];
        }
    }
    
    /**
     * Change le mot de passe de l'utilisateur
     * @param string $currentPassword Mot de passe actuel
     * @param string $newPassword Nouveau mot de passe
     * @return array Résultat de l'opération
     */
    public function updatePassword($userId, $currentPassword, $newPassword) {
        if (!Session::isLoggedIn()) {
            return ['success' => false, 'message' => 'Vous devez être connecté pour changer votre mot de passe.'];
        }
        
        // Vérifier le mot de passe actuel
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Le mot de passe actuel est incorrect.'];
        }
        
        // Vérifier la complexité du nouveau mot de passe
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => 'Le nouveau mot de passe doit contenir au moins 6 caractères.'];
        }
        
        // Hasher et enregistrer le nouveau mot de passe
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
            
            return ['success' => true, 'message' => 'Mot de passe changé avec succès.'];
        } catch (PDOException $e) {
            error_log("Erreur lors du changement de mot de passe: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors du changement de mot de passe: ' . $e->getMessage()];
        }
    }
    
    /**
     * Télécharge et définit l'avatar de l'utilisateur
     * @param array $fileData Données du fichier téléchargé ($_FILES['avatar'])
     * @return array Résultat de l'opération
     */
    public function uploadAvatar($fileData) {
        if (!Session::isLoggedIn()) {
            return ['success' => false, 'message' => 'Vous devez être connecté pour changer votre avatar.'];
        }
        
        $userId = Session::getUserId();
        
        // Vérifier si un fichier a été téléchargé
        if (!isset($fileData) || $fileData['error'] != UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Erreur lors du téléchargement du fichier.'];
        }
        
        // Vérifier le type de fichier
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($fileData['type'], $allowedTypes)) {
            return ['success' => false, 'message' => 'Type de fichier non autorisé. Seuls les formats JPEG, PNG et GIF sont acceptés.'];
        }
        
        // Vérifier la taille du fichier (max 2 Mo)
        if ($fileData['size'] > 2 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Le fichier est trop volumineux. La taille maximale est de 2 Mo.'];
        }
        
        // Créer le répertoire des avatars s'il n'existe pas
        $uploadDir = BASE_PATH . '/public/assets/avatars/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Générer un nom de fichier unique
        $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $filename;
        
        // Déplacer le fichier téléchargé vers le répertoire cible
        if (!move_uploaded_file($fileData['tmp_name'], $targetPath)) {
            return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement du fichier.'];
        }
        
        // Supprimer l'ancien avatar s'il existe
        $stmt = $this->db->prepare("SELECT avatar_path FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!empty($user['avatar_path'])) {
            $oldAvatarPath = BASE_PATH . $user['avatar_path'];
            if (file_exists($oldAvatarPath)) {
                unlink($oldAvatarPath);
            }
        }
        
        // Mettre à jour le chemin de l'avatar dans la base de données
        $relativePath = '/assets/avatars/' . $filename;
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
            $stmt->execute([$relativePath, $userId]);
            
            return [
                'success' => true, 
                'message' => 'Avatar téléchargé avec succès.',
                'avatar_path' => $relativePath
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour de l\'avatar: ' . $e->getMessage()];
        }
    }
    
    /**
     * Génère un avatar par défaut basé sur l'initiale du pseudo
     * @return array Résultat de l'opération
     */
    public function generateDefaultAvatar() {
        if (!Session::isLoggedIn()) {
            return ['success' => false, 'message' => 'Vous devez être connecté pour générer un avatar.'];
        }
        
        $userId = Session::getUserId();
        
        // Récupérer le nom d'utilisateur
        $stmt = $this->db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Utilisateur non trouvé.'];
        }
        
        // Générer un avatar basé sur l'initiale
        $initial = strtoupper(substr($user['username'], 0, 1));
        $colors = [
            'A' => '#FF5733', 'B' => '#33FF57', 'C' => '#3357FF', 'D' => '#F3FF33',
            'E' => '#33FFF3', 'F' => '#F333FF', 'G' => '#FF33A8', 'H' => '#A833FF',
            'I' => '#33FFA8', 'J' => '#FF8333', 'K' => '#8333FF', 'L' => '#33FF83',
            'M' => '#FF3383', 'N' => '#3383FF', 'O' => '#FF3333', 'P' => '#33FF33',
            'Q' => '#3333FF', 'R' => '#FFFF33', 'S' => '#33FFFF', 'T' => '#FF33FF',
            'U' => '#FFAA33', 'V' => '#33FFAA', 'W' => '#AA33FF', 'X' => '#FF33AA',
            'Y' => '#33AAFF', 'Z' => '#AAFF33'
        ];
        
        $bgColor = isset($colors[$initial]) ? $colors[$initial] : '#CCCCCC';
        
        // Créer le répertoire des avatars s'il n'existe pas
        $uploadDir = BASE_PATH . '/public/assets/avatars/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Créer une image
        $image = imagecreatetruecolor(200, 200);
        $bg = imagecolorallocate($image, hexdec(substr($bgColor, 1, 2)), hexdec(substr($bgColor, 3, 2)), hexdec(substr($bgColor, 5, 2)));
        $textColor = imagecolorallocate($image, 255, 255, 255);
        
        // Remplir l'arrière-plan
        imagefill($image, 0, 0, $bg);
        
        // Ajouter l'initiale
        $font = 5; // Font interne de GD
        $fontWidth = imagefontwidth($font);
        $fontHeight = imagefontheight($font);
        $textX = (200 - $fontWidth * strlen($initial)) / 2;
        $textY = (200 - $fontHeight) / 2;
        
        imagestring($image, $font, $textX, $textY, $initial, $textColor);
        
        // Générer un nom de fichier unique
        $filename = 'avatar_default_' . $userId . '_' . time() . '.png';
        $targetPath = $uploadDir . $filename;
        
        // Enregistrer l'image
        imagepng($image, $targetPath);
        imagedestroy($image);
        
        // Supprimer l'ancien avatar s'il existe
        $stmt = $this->db->prepare("SELECT avatar_path FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userAvatar = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!empty($userAvatar['avatar_path'])) {
            $oldAvatarPath = BASE_PATH . $userAvatar['avatar_path'];
            if (file_exists($oldAvatarPath)) {
                unlink($oldAvatarPath);
            }
        }
        
        // Mettre à jour le chemin de l'avatar dans la base de données
        $relativePath = '/assets/avatars/' . $filename;
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
            $stmt->execute([$relativePath, $userId]);
            
            return [
                'success' => true, 
                'message' => 'Avatar généré avec succès.',
                'avatar_path' => $relativePath
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour de l\'avatar: ' . $e->getMessage()];
        }
    }
    
    /**
     * Met à jour les paramètres de confidentialité
     * @param array $settings Paramètres à mettre à jour
     * @return array Résultat de l'opération
     */
    public function updatePrivacySettings($settings) {
        if (!Session::isLoggedIn()) {
            return ['success' => false, 'message' => 'Vous devez être connecté pour modifier vos paramètres de confidentialité.'];
        }
        
        $userId = Session::getUserId();
        $allowedSettings = ['privacy_setting', 'friend_requests_setting', 'appear_offline'];
        $updateData = [];
        $params = [];
        
        // Filtrer les paramètres autorisés
        foreach ($settings as $key => $value) {
            if (in_array($key, $allowedSettings)) {
                $updateData[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updateData)) {
            return ['success' => false, 'message' => 'Aucun paramètre valide à mettre à jour.'];
        }
        
        // Ajouter l'ID utilisateur aux paramètres
        $params[] = $userId;
        
        try {
            $updateQuery = "UPDATE users SET " . implode(', ', $updateData) . " WHERE id = ?";
            $stmt = $this->db->prepare($updateQuery);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Paramètres de confidentialité mis à jour avec succès.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour des paramètres: ' . $e->getMessage()];
        }
    }
    
    /**
     * Met à jour l'activité de l'utilisateur
     * @return void
     */
    public function updateActivity() {
        if (!Session::isLoggedIn()) {
            return;
        }
        
        $userId = Session::getUserId();
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log('Erreur lors de la mise à jour de l\'activité: ' . $e->getMessage());
        }
    }
    
    /**
     * Met à jour les paramètres de confidentialité de l'utilisateur
     * @param int $userId ID de l'utilisateur
     * @param string $privacyLevel Niveau de confidentialité ('public', 'friends', 'private')
     * @return array Résultat de l'opération
     */
    public function updatePrivacy($userId, $privacyLevel) {
        if (!Session::isLoggedIn()) {
            return ['success' => false, 'message' => 'Vous devez être connecté pour modifier vos paramètres de confidentialité.'];
        }
        
        // Vérifier que le niveau de confidentialité est valide
        $validLevels = ['public', 'friends', 'private'];
        if (!in_array($privacyLevel, $validLevels)) {
            return ['success' => false, 'message' => 'Niveau de confidentialité invalide.'];
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET privacy_level = ? WHERE id = ?");
            $stmt->execute([$privacyLevel, $userId]);
            
            return ['success' => true, 'message' => 'Paramètres de confidentialité mis à jour avec succès.'];
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour des paramètres de confidentialité: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour des paramètres: ' . $e->getMessage()];
        }
    }
} 