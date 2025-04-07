<?php
// Activer l'affichage des erreurs en développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Définir le fuseau horaire
date_default_timezone_set('Europe/Paris');

// Essayer de charger le fichier de configuration s'il existe
if (file_exists(__DIR__ . '/../backend/includes/config.php')) {
    require_once __DIR__ . '/../backend/includes/config.php';
} else {
    // Définir les constantes seulement si config.php n'existe pas
    if (!defined('APP_NAME')) define('APP_NAME', 'Jeu de Dames en Ligne');
    if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
    if (!defined('APP_URL')) define('APP_URL', 'http://localhost:8000');
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_NAME')) define('DB_NAME', 'jeu_dames');
    if (!defined('DB_USER')) define('DB_USER', 'root');
    if (!defined('DB_PASS')) define('DB_PASS', '');
}

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Charger les classes nécessaires
require_once __DIR__ . '/../backend/includes/session.php';
require_once __DIR__ . '/../backend/db/Database.php';

// Page d'accueil directement dans index.php
if ($_SERVER['REQUEST_URI'] == '/' || $_SERVER['REQUEST_URI'] == '/index.php') {
    $pageTitle = "Accueil - " . APP_NAME;
    
    // Inclure l'en-tête
    include __DIR__ . '/../backend/includes/header.php';
    
    // Contenu de la page d'accueil
    ?>
    <div class="bg-purple-700 text-white py-16">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-6">Bienvenue sur <?php echo APP_NAME; ?></h1>
            <p class="text-xl mb-8 max-w-3xl mx-auto">
                Jouez aux dames en ligne contre d'autres joueurs. Rejoignez notre communauté et améliorez vos compétences !
            </p>
            
            <?php if (!Session::isLoggedIn()): ?>
                <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                    <a href="/auth/register.php" class="bg-white text-purple-700 px-6 py-3 rounded-lg font-semibold hover:bg-purple-100 transition">
                        Créer un compte
                    </a>
                    <a href="/auth/login.php" class="bg-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-purple-500 transition">
                        Se connecter
                    </a>
                </div>
            <?php else: ?>
                <div class="flex justify-center">
                    <a href="/game/play.php" class="bg-white text-purple-700 px-8 py-4 rounded-lg font-semibold text-xl hover:bg-purple-100 transition">
                        Jouer maintenant
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Espace blanc explicite -->
    <div class="h-16 bg-gray-50"></div>

    <div class="container mx-auto px-4 py-16 bg-gray-50">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <div class="bg-purple-100 text-purple-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold mb-2">Créez votre compte</h2>
                <p class="text-gray-600">
                    Inscrivez-vous en quelques secondes pour commencer à jouer et suivre vos statistiques.
                </p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <div class="bg-purple-100 text-purple-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold mb-2">Trouvez un adversaire</h2>
                <p class="text-gray-600">
                    Notre système vous met en relation avec un adversaire de votre niveau en quelques secondes.
                </p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <div class="bg-purple-100 text-purple-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold mb-2">Suivez vos progrès</h2>
                <p class="text-gray-600">
                    Consultez vos statistiques de jeu et améliorez votre classement au fil du temps.
                </p>
            </div>
        </div>
    </div>
    <?php
    
    // Inclure le pied de page
    include __DIR__ . '/../backend/includes/footer.php';
    exit;
}

// Récupérer l'URL demandée
$request_uri = $_SERVER['REQUEST_URI'];

// Supprimer les paramètres de l'URL pour obtenir uniquement le chemin
$uri = explode('?', $request_uri)[0];

// Vérifier si c'est une requête API
if (strpos($uri, '/api/') === 0) {
    // Construire le chemin vers le fichier API
    $api_file = __DIR__ . $uri . '.php';
    
    // Vérifier si le fichier API existe
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        // Retourner une erreur 404 en JSON pour les requêtes API
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'API endpoint not found']);
        exit;
    }
}

// Page de profil
if ($uri == '/profile.php') {
    require_once __DIR__ . '/profile.php';
    exit;
}

// Pages d'authentification
if ($uri == '/auth/login.php') {
    require_once __DIR__ . '/auth/login.php';
    exit;
} elseif ($uri == '/auth/register.php') {
    require_once __DIR__ . '/auth/register.php';
    exit;
} elseif ($uri == '/auth/logout.php') {
    require_once __DIR__ . '/auth/logout.php';
    exit;
}

// Pages du jeu
if ($uri == '/game/play.php') {
    require_once __DIR__ . '/game/play.php';
    exit;
} elseif ($uri == '/game/board.php') {
    error_log("Requête vers /game/board.php détectée dans index.php");
    require_once __DIR__ . '/game/board.php';
    exit;
}

// Pages informatives
if ($uri == '/pages/about.php') {
    require_once __DIR__ . '/pages/about.php';
    exit;
} elseif ($uri == '/pages/faq.php') {
    require_once __DIR__ . '/pages/faq.php';
    exit;
} elseif ($uri == '/pages/help.php') {
    require_once __DIR__ . '/pages/help.php';
    exit;
}

// Si aucune correspondance n'est trouvée, afficher la page 404 de l'utilisateur
header("HTTP/1.0 404 Not Found");
require_once __DIR__ . '/errors/404.php';
exit; 