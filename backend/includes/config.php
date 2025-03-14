<?php
/**
 * Fichier de configuration
 * Définit les constantes et paramètres globaux de l'application
 */

// Activer l'affichage des erreurs en développement
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Définir le fuseau horaire
date_default_timezone_set('Europe/Paris');

// Paramètres de l'application
define('APP_NAME', 'Jeu de Dames');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development'); // 'development' ou 'production'

// Chemins de l'application
define('BASE_PATH', realpath(__DIR__ . '/../../'));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('VIEWS_PATH', BASE_PATH . '/views');
define('BACKEND_PATH', BASE_PATH . '/backend');

// Paramètres de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'checkers_game');
define('DB_USER', 'root');
define('DB_PASS', '');

// Paramètres de sécurité
define('HASH_COST', 10); // Coût de hachage pour bcrypt

// Chargement des classes et fonctions essentielles
require_once __DIR__ . '/session.php';
Session::start();

// Fonction pour charger automatiquement les classes
spl_autoload_register(function ($class_name) {
    $paths = [
        BACKEND_PATH . '/models/',
        BACKEND_PATH . '/controllers/',
        BACKEND_PATH . '/db/'
    ];
    
    foreach ($paths as $path) {
        $file = $path . $class_name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Fonction pour échapper les sorties HTML
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Fonction pour rediriger
function redirect($path) {
    header('Location: ' . $path);
    exit();
}

// Démarrer la session
Session::start();