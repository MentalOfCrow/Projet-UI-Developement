<?php
/**
 * Classe Database
 * Gère la connexion à la base de données à l'aide du pattern Singleton
 */
class Database {
    // Instance unique de la classe
    private static $instance = null;
    
    // Connexion PDO
    private $conn;
    
    /**
     * Constructeur privé pour empêcher l'instanciation directe
     */
    private function __construct() {
        // Récupérer les paramètres de connexion depuis les constantes de configuration
        $host = defined('DB_HOST') ? DB_HOST : "localhost";
        $dbname = defined('DB_NAME') ? DB_NAME : "checkers_game";
        $username = defined('DB_USER') ? DB_USER : "root";
        $password = defined('DB_PASS') ? DB_PASS : "";
        
        try {
            $this->conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
            
            // Configurer PDO pour lever des exceptions en cas d'erreur
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Configurer PDO pour retourner les résultats sous forme de tableau associatif
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Utiliser l'UTF-8 pour les communications avec MySQL
            $this->conn->exec("SET NAMES utf8");
            
            // Vérifier si APP_ENV est défini avant de l'utiliser
            // Si APP_ENV n'est pas défini ou est en mode développement, afficher les erreurs
            if (defined('APP_ENV') && APP_ENV === 'development') {
                $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                ini_set('display_errors', 1);
                ini_set('display_startup_errors', 1);
                error_reporting(E_ALL);
            }
        } catch (PDOException $e) {
            // Journaliser l'erreur au lieu de l'afficher directement (sécurité)
            error_log("Erreur de connexion à la base de données : " . $e->getMessage());
            
            // En développement, on peut afficher l'erreur
            if (defined('APP_ENV') && APP_ENV === 'development') {
                echo "Erreur de connexion : " . $e->getMessage();
            } else {
                echo "Une erreur est survenue lors de la connexion à la base de données.";
            }
            exit;
        }
    }
    
    /**
     * Méthode statique pour récupérer l'instance unique
     * @return Database Instance de la classe Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Récupère la connexion à la base de données
     * @return PDO Connexion PDO
     */
    public function getConnection() {
        if (!isset($this->conn) || $this->conn === null) {
            error_log("ALERTE: Tentative d'obtenir une connexion non initialisée dans Database");
            // Tenter de réinitialiser la connexion
            try {
                $host = defined('DB_HOST') ? DB_HOST : "localhost";
                $dbname = defined('DB_NAME') ? DB_NAME : "checkers_game";
                $username = defined('DB_USER') ? DB_USER : "root";
                $password = defined('DB_PASS') ? DB_PASS : "";
                
                $this->conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->conn->exec("SET NAMES utf8");
                
                error_log("Connexion à la base de données réinitialisée avec succès");
            } catch (PDOException $e) {
                error_log("Échec de la réinitialisation de connexion à la base de données : " . $e->getMessage());
            }
        }
        return $this->conn;
    }
    
    /**
     * Empêche le clonage de l'instance
     */
    private function __clone() {}
    
    /**
     * Empêche la désérialisation de l'instance
     */
    public function __wakeup() {}
}
