<?php
// Start output buffering to prevent any output before headers are sent
ob_start();

// Enable error display in development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once __DIR__ . '/../backend/includes/config.php';
require_once __DIR__ . '/../backend/includes/session.php';
require_once __DIR__ . '/../backend/db/JsonDatabase.php';

// Vérifier si l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    header('Location: /auth/login.php');
    exit;
}

// Get user ID from URL or use logged-in user's ID
$userId = isset($_GET['id']) ? intval($_GET['id']) : Session::getUserId();

// Obtenir une instance de la base de données JSON
$db = JsonDatabase::getInstance();

// Message à afficher à l'utilisateur
$message = '';
$success = false;

// Synchroniser les statistiques de l'utilisateur
if ($db->synchronizeUserStats($userId)) {
    $message = 'Les statistiques ont été synchronisées avec succès.';
    $success = true;
} else {
    $message = 'Une erreur est survenue lors de la synchronisation des statistiques.';
}

// Définir le titre de la page
$pageTitle = "Synchronisation des statistiques";

// Inclure l'en-tête
include __DIR__ . '/../backend/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Synchronisation des statistiques</h1>
    
    <div class="bg-<?php echo $success ? 'green' : 'red'; ?>-100 border-l-4 border-<?php echo $success ? 'green' : 'red'; ?>-500 text-<?php echo $success ? 'green' : 'red'; ?>-700 p-4 mb-6 rounded" role="alert">
        <p><?php echo htmlspecialchars($message); ?></p>
    </div>
    
    <div class="flex space-x-4">
        <a href="/profile.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
            Retour au profil
        </a>
        <a href="/game/history.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Retour à l'historique des parties
        </a>
    </div>
</div>

<?php
// Inclure le pied de page
include __DIR__ . '/../backend/includes/footer.php';
?> 