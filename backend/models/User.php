<?php
require_once __DIR__ . '/../db/Database.php';

/**
 * Modèle User
 * Gère les opérations liées aux utilisateurs (inscription, connexion, etc.)
 */
class User {
    // Propriétés de la base de données
    private $conn;
    private $table = "users";
    
    // Propriétés de l'utilisateur
    public $id;
    public $username;
    public $email;
    public $password;
    public $created_at;
    public $last_login;
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Obtenir la connexion à la base de données
        $database = Database::getInstance();
        $this->conn = $database->getConnection();
    }
    
    /**
     * Crée un nouvel utilisateur
     * @return bool Succès de l'opération
     */
    public function create() {
        try {
            // Vérifier si l'utilisateur existe déjà
            if ($this->usernameExists() || $this->emailExists()) {
                return false;
            }
            
            // Hacher le mot de passe
            $password_hash = password_hash($this->password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            
            // Préparer la requête
            $query = "INSERT INTO " . $this->table . " (username, email, password) VALUES (:username, :email, :password)";
            $stmt = $this->conn->prepare($query);
            
            // Nettoyer les données
            $this->username = htmlspecialchars(strip_tags($this->username));
            $this->email = htmlspecialchars(strip_tags($this->email));
            
            // Lier les paramètres
            $stmt->bindParam(':username', $this->username);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':password', $password_hash);
            
            // Exécuter la requête
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                
                // Créer une entrée dans la table des statistiques
                $query = "INSERT INTO stats (user_id) VALUES (:user_id)";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $this->id);
                $stmt->execute();
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Erreur lors de la création de l'utilisateur: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Vérifie si un nom d'utilisateur existe déjà
     * @return bool True si le nom d'utilisateur existe, false sinon
     */
    public function usernameExists() {
        try {
            $query = "SELECT id, password FROM " . $this->table . " WHERE username = :username LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $this->username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->id = $row['id'];
                $this->password = $row['password'];
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification du nom d'utilisateur: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Vérifie si une adresse e-mail existe déjà
     * @return bool True si l'adresse e-mail existe, false sinon
     */
    public function emailExists() {
        try {
            $query = "SELECT id FROM " . $this->table . " WHERE email = :email LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $this->email);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification de l'email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lit les informations d'un utilisateur par son ID
     * @return bool Succès de l'opération
     */
    public function readOne() {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $this->username = $row['username'];
                $this->email = $row['email'];
                $this->created_at = $row['created_at'];
                $this->last_login = $row['last_login'];
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Erreur lors de la lecture de l'utilisateur: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Met à jour la date de dernière connexion
     * @return bool Succès de l'opération
     */
    public function updateLastLogin() {
        try {
            $query = "UPDATE " . $this->table . " SET last_login = NOW() WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour de la dernière connexion: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtient les statistiques d'un utilisateur
     * @return array Statistiques de l'utilisateur
     */
    public function getStats() {
        try {
            $query = "SELECT * FROM stats WHERE user_id = :user_id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $this->id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Si aucune statistique n'est trouvée, renvoyer des valeurs par défaut
            return [
                'games_played' => 0,
                'games_won' => 0,
                'games_lost' => 0
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
            return [
                'games_played' => 0,
                'games_won' => 0,
                'games_lost' => 0
            ];
        }
    }
    
    /**
     * Obtient tous les utilisateurs
     * @return array Liste des utilisateurs
     */
    public function readAll() {
        try {
            $query = "SELECT id, username, email, created_at, last_login FROM " . $this->table;
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la lecture de tous les utilisateurs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Met à jour les informations d'un utilisateur
     * @return bool Succès de l'opération
     */
    public function update() {
        try {
            $query = "UPDATE " . $this->table . " SET ";
            $params = [];
            
            if (!empty($this->username)) {
                $query .= "username = :username, ";
                $params[':username'] = htmlspecialchars(strip_tags($this->username));
            }
            
            if (!empty($this->email)) {
                $query .= "email = :email, ";
                $params[':email'] = htmlspecialchars(strip_tags($this->email));
            }
            
            if (!empty($this->password)) {
                $query .= "password = :password, ";
                $password_hash = password_hash($this->password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
                $params[':password'] = $password_hash;
            }
            
            // Supprimer la virgule finale
            $query = rtrim($query, ", ");
            
            // Ajouter la condition
            $query .= " WHERE id = :id";
            $params[':id'] = $this->id;
            
            // Préparer et exécuter la requête
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour de l'utilisateur: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Supprime un utilisateur
     * @return bool Succès de l'opération
     */
    public function delete() {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erreur lors de la suppression de l'utilisateur: " . $e->getMessage());
            return false;
        }
    }
}
