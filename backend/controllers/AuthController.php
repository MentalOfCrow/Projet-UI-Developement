<?php
// Activer l'affichage des erreurs en développement uniquement si ce n'est pas déjà fait
if (!ini_get('display_errors')) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Définir le fuseau horaire si ce n'est pas déjà fait
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Europe/Paris');
}

// Définir les constantes uniquement si elles n'existent pas déjà
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'jeu_dames');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// Constantes de l'application
if (!defined('APP_NAME')) define('APP_NAME', 'Jeu de Dames en Ligne');
if (!defined('APP_URL')) define('APP_URL', 'http://localhost:8000');

// Configuration de session - démarrer seulement si pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../db/Database.php';
require_once __DIR__ . '/../db/JsonDatabase.php';

class AuthController {
    private $user;
    private $db;

    public function __construct() {
        $this->user = new User();
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }

    public function register() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Récupération des données du formulaire
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            
            // Validation des données
            if (empty($username) || empty($email) || empty($password)) {
                return ["success" => false, "message" => "Tous les champs sont obligatoires."];
            }
            
            if (strlen($password) < 6) {
                return ["success" => false, "message" => "Le mot de passe doit contenir au moins 6 caractères."];
            }
            
            // Obtenir l'instance de JsonDatabase
            $db = JsonDatabase::getInstance();
            
            // Vérification si l'utilisateur existe déjà
            if ($db->getUserByUsername($username)) {
                return ["success" => false, "message" => "Ce nom d'utilisateur est déjà pris."];
            }
            
            // Création de l'utilisateur
            $userData = [
                'username' => $username,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if ($db->saveUser($userData)) {
                return ["success" => true, "message" => "Inscription réussie. Vous pouvez maintenant vous connecter."];
            } else {
                return ["success" => false, "message" => "Une erreur est survenue. Veuillez réessayer."];
            }
        }
        
        return ["success" => false, "message" => "Méthode non autorisée."];
    }

    public function login() {
        if (!isset($_POST['username']) || !isset($_POST['password'])) {
            return [
                'success' => false,
                'message' => 'Veuillez remplir tous les champs.'
            ];
        }
        
        $input = $_POST['username']; // Peut être un email ou un nom d'utilisateur
        $password = $_POST['password'];
        
        // Vérifier si l'entrée est un email
        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            // Recherche par email
            $sql = "SELECT * FROM users WHERE email = :input";
        } else {
            // Recherche par nom d'utilisateur (pour compatibilité)
            $sql = "SELECT * FROM users WHERE username = :input";
        }
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':input', $input);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['logged_in'] = true;
                    
                    return [
                        'success' => true,
                        'message' => 'Connexion réussie !',
                        'redirect' => '/game/play.php'
                    ];
                }
            }
            
            return [
                'success' => false,
                'message' => 'Email/nom d\'utilisateur ou mot de passe incorrect.'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur de connexion: ' . $e->getMessage()
            ];
        }
    }

    public function logout() {
        Session::logout();
        return ["success" => true, "message" => "Déconnexion réussie.", "redirect" => "/index.php"];
    }

    public function isLoggedIn() {
        return Session::isLoggedIn();
    }

    public function getCurrentUser() {
        if (!Session::isLoggedIn()) {
            return null;
        }
        
        $userId = Session::getUserId();
        $this->user->id = $userId;
        
        if ($this->user->readOne()) {
            return $this->user;
        }
        
        return null;
    }
    
    /**
     * Récupère les informations d'un utilisateur par son ID
     * @param int $userId ID de l'utilisateur à récupérer
     * @return User|null L'utilisateur trouvé ou null si non trouvé
     */
    public function getUserById($userId) {
        if (!$userId) {
            return null;
        }
        
        $user = new User();
        $user->id = $userId;
        
        if ($user->readOne()) {
            return $user;
        }
        
        return null;
    }
}
?>
