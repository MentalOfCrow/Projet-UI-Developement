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

class AuthController {
    private $user;

    public function __construct() {
        $this->user = new User();
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
            
            // Vérification si l'utilisateur existe déjà
            $this->user->username = $username;
            if ($this->user->usernameExists()) {
                return ["success" => false, "message" => "Ce nom d'utilisateur est déjà pris."];
            }
            
            // Création de l'utilisateur
            $this->user->password = $password;
            $this->user->email = $email;
            
            if ($this->user->create()) {
                return ["success" => true, "message" => "Inscription réussie. Vous pouvez maintenant vous connecter."];
            } else {
                return ["success" => false, "message" => "Une erreur est survenue. Veuillez réessayer."];
            }
        }
        
        return ["success" => false, "message" => "Méthode non autorisée."];
    }

    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Récupération des données du formulaire
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            // Validation des données
            if (empty($username) || empty($password)) {
                return ["success" => false, "message" => "Tous les champs sont obligatoires."];
            }
            
            // Vérification si l'utilisateur existe
            $this->user->username = $username;
            if ($this->user->usernameExists() && password_verify($password, $this->user->password)) {
                // Création de la session
                Session::set('user_id', $this->user->id);
                Session::set('username', $this->user->username);
                
                return ["success" => true, "message" => "Connexion réussie.", "redirect" => "/game/play.php"];
            } else {
                return ["success" => false, "message" => "Nom d'utilisateur ou mot de passe incorrect."];
            }
        }
        
        return ["success" => false, "message" => "Méthode non autorisée."];
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
