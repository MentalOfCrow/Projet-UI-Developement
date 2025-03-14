<?php
// Activer l'affichage des erreurs en développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Définir la constante APP_NAME si elle n'est pas déjà définie
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Jeu de Dames en Ligne');
}

// Définir le titre de la page
$pageTitle = "Page non trouvée - " . APP_NAME;

// Inclure l'en-tête
require_once __DIR__ . '/../../backend/includes/header.php';
?>

<div class="container mx-auto px-4 py-16 flex flex-col items-center justify-center">
    <div class="text-6xl font-bold text-indigo-600 mb-4">404</div>
    <h1 class="text-3xl font-bold text-gray-800 mb-8">Page non trouvée</h1>
    <p class="text-xl text-gray-600 mb-8 text-center max-w-md">
        La page que vous recherchez n'existe pas ou a été déplacée.
    </p>
    <a href="/" class="bg-indigo-600 text-white py-2 px-6 rounded hover:bg-indigo-700 transition duration-200">
        Retour à l'accueil
    </a>
</div>

<?php
// Inclure le pied de page
include __DIR__ . '/../../backend/includes/footer.php';
?>