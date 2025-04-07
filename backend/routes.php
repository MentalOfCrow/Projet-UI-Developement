<?php
/**
 * Système de routage simple pour le jeu de dames
 * Ce fichier gère les redirections vers les contrôleurs appropriés
 */

// Chemin de base de l'application
define('BASE_PATH', __DIR__ . '/..');

// Récupération de l'URL demandée
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Supprimer les paramètres de l'URL
$uri = explode('?', $request_uri)[0];

// Routes définies
$routes = [
    // Pages principales
    '/' => ['file' => BASE_PATH . '/public/index.php'],
    '/index.php' => ['file' => BASE_PATH . '/public/index.php'],
    
    // Pages d'authentification
    '/auth/login.php' => ['file' => BASE_PATH . '/views/auth/login.php'],
    '/auth/logout.php' => ['file' => BASE_PATH . '/views/auth/logout.php'],
    '/auth/register.php' => ['file' => BASE_PATH . '/views/auth/register.php'],
    
    // Pages de jeu
    '/game/play.php' => ['file' => BASE_PATH . '/views/game/play.php'],
    '/game/board.php' => ['file' => BASE_PATH . '/views/game/board.php'],
    
    // Pages informatives
    '/pages/about.php' => ['file' => BASE_PATH . '/views/pages/about.php'],
    '/pages/faq.php' => ['file' => BASE_PATH . '/views/pages/faq.php'],
    '/pages/help.php' => ['file' => BASE_PATH . '/views/pages/help.php'],
    
    // API pour le matchmaking
    '/api/matchmaking/join.php' => [
        'controller' => 'MatchmakingController',
        'method' => 'joinQueue',
        'ajax' => true
    ],
    '/api/matchmaking/leave.php' => [
        'controller' => 'MatchmakingController',
        'method' => 'leaveQueue',
        'ajax' => true
    ],
    '/api/matchmaking/check.php' => [
        'controller' => 'MatchmakingController',
        'method' => 'checkQueue',
        'ajax' => true
    ],
    
    // API pour le jeu
    '/api/game/move.php' => [
        'controller' => 'GameController',
        'method' => 'makeMove',
        'ajax' => true
    ],
    '/api/game/status.php' => [
        'controller' => 'GameController',
        'method' => 'getGameStatus',
        'ajax' => true
    ],
    
    // Nouvelle route pour queue.php
    '/api/game/queue.php' => [
        'controller' => 'MatchmakingController',
        'method' => 'handleQueueAction',
        'ajax' => true
    ]
];

// Recherche de la route correspondante
if (array_key_exists($uri, $routes)) {
    $route = $routes[$uri];
    
    // Si c'est un fichier direct
    if (isset($route['file'])) {
        if (file_exists($route['file'])) {
            require_once $route['file'];
            exit;
        }
    }
    
    // Si c'est une route vers un contrôleur
    if (isset($route['controller']) && isset($route['method'])) {
        $controller_name = $route['controller'];
        $method_name = $route['method'];
        
        // Charger le contrôleur
        require_once __DIR__ . '/controllers/' . $controller_name . '.php';
        $controller = new $controller_name();
        
        // Vérifier si la méthode existe
        if (method_exists($controller, $method_name)) {
            // Traiter les requêtes AJAX
            if (isset($route['ajax']) && $route['ajax']) {
                header('Content-Type: application/json');
                
                // Récupérer les données de la requête
                $data = [];
                if ($request_method === 'POST') {
                    $input = file_get_contents('php://input');
                    if (!empty($input)) {
                        $data = json_decode($input, true);
                    } else {
                        $data = $_POST;
                    }
                } elseif ($request_method === 'GET') {
                    $data = $_GET;
                }
                
                // Appeler la méthode du contrôleur
                $result = $controller->$method_name($data);
                echo json_encode($result);
                exit;
            }
            
            // Pour les requêtes non-AJAX
            $controller->$method_name();
            exit;
        }
    }
}

// Si aucune route ne correspond, afficher la page 404
header("HTTP/1.0 404 Not Found");
require_once BASE_PATH . '/views/errors/404.php';
exit;
