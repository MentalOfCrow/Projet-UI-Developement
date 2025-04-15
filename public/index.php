<?php
// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enregistrer les erreurs dans un fichier log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../backend/logs/php_errors.log');

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

// Démarrer la session
Session::start();

// Charger les classes nécessaires
require_once __DIR__ . '/../backend/includes/session.php';
require_once __DIR__ . '/../backend/db/Database.php';

// Définir le titre de la page
$pageTitle = "Accueil - " . APP_NAME;

// Vérifier si nous sommes connectés
$isLoggedIn = Session::isLoggedIn();

// Inclure l'en-tête du site
include_once __DIR__ . '/../backend/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-indigo-600 mb-3">Bienvenue sur <?php echo APP_NAME; ?></h1>
            <p class="text-lg text-gray-600">Le jeu de dames en ligne simple et accessible</p>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-semibold text-indigo-700 mb-4">Commencer à jouer</h2>
            
            <?php if ($isLoggedIn): ?>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="/game/play.php" class="bg-indigo-600 text-white py-3 px-6 rounded-lg hover:bg-indigo-700 transition text-center">
                        Jouer maintenant
                    </a>
                    <a href="/leaderboard.php" class="bg-purple-600 text-white py-3 px-6 rounded-lg hover:bg-purple-700 transition text-center">
                        Voir le classement
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center">
                    <p class="mb-4 text-gray-700">Pour commencer à jouer, vous devez vous connecter ou créer un compte</p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="/auth/login.php" class="bg-indigo-600 text-white py-3 px-6 rounded-lg hover:bg-indigo-700 transition text-center">
                            Se connecter
                        </a>
                        <a href="/auth/register.php" class="bg-purple-600 text-white py-3 px-6 rounded-lg hover:bg-purple-700 transition text-center">
                            Créer un compte
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-semibold text-indigo-700 mb-2">Jeu de Dames International</h3>
                <p class="text-gray-600 mb-4">Jouez selon les règles internationales sur un plateau de 10x10 cases avec 20 pions par joueur.</p>
                <ul class="list-disc list-inside text-gray-700 space-y-1">
                    <li>Déplacement en diagonale</li>
                    <li>Prise obligatoire</li>
                    <li>Prise maximum obligatoire</li>
                    <li>Promotion en dame à la dernière rangée</li>
                </ul>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-semibold text-indigo-700 mb-2">Caractéristiques</h3>
                <ul class="list-disc list-inside text-gray-700 space-y-2">
                    <li>Jouez contre l'ordinateur ou d'autres joueurs</li>
                    <li>Système de matchmaking pour trouver des adversaires</li>
                    <li>Suivez vos statistiques et progressez dans le classement</li>
                    <li>Interface simple et intuitive</li>
                    <li>Accessible sur tous les appareils</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../backend/includes/footer.php'; ?> 