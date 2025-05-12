<?php
// Supprimer tout output buffering existant et en démarrer un nouveau
while (ob_get_level()) ob_end_clean();
ob_start();

// Activer l'affichage des erreurs en développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enregistrer les erreurs dans un fichier log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../backend/logs/php_errors.log');

error_log("Début du chargement de play.php");

require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/controllers/ProfileController.php';
require_once __DIR__ . '/../../backend/includes/session.php';
require_once __DIR__ . '/../../backend/db/Database.php';

// Rediriger si l'utilisateur n'est pas connecté
if (!Session::isLoggedIn()) {
    // Nettoyer le buffer avant de rediriger
    ob_end_clean();
    header('Location: /auth/login.php');
    exit;
}

// Récupérer l'ID de l'utilisateur
$user_id = Session::getUserId();
$username = Session::getUsername() ? Session::getUsername() : 'Joueur';

// Inclure le contrôleur de jeu une seule fois
error_log("Inclusion du GameController");
require_once __DIR__ . '/../../backend/controllers/GameController.php';

// Mettre à jour l'activité de l'utilisateur
$profileController = new ProfileController();
$profileController->updateActivity();

// Récupérer les parties actives de l'utilisateur
error_log("play.php - Tentative de création de GameController");
if (!class_exists('GameController')) {
    error_log("ERREUR: La classe GameController n'existe pas après inclusion");
    die("Impossible de charger la classe GameController");
} else {
    error_log("La classe GameController existe");
}

try {
    error_log("play.php - Avant création du GameController");
    $gameController = new GameController();
    error_log("play.php - GameController créé avec succès");
    
    error_log("play.php - Avant appel à getActiveGames() pour user_id: " . $user_id);
    $activeGames = $gameController->getActiveGames($user_id);
    error_log("play.php - Après appel à getActiveGames() - ActiveGames récupérés avec succès");
} catch (Exception $e) {
    error_log("Exception lors de la création de GameController: " . $e->getMessage());
    error_log("play.php - Trace: " . $e->getTraceAsString());
    echo "Erreur: " . $e->getMessage();
}

// Historique des parties temporairement désactivé
$gameHistory = null;

// Récupérer un message éventuel de redirection
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';

$pageTitle = "Jouer - " . APP_NAME;
error_log("Fin du chargement de play.php");
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<link rel="stylesheet" href="/assets/css/style.css">

<div class="container mx-auto px-4 py-8 mt-2 pt-4">
    <h1 class="text-3xl font-bold text-center text-purple-600 mb-8">Choisissez votre mode de jeu</h1>
    
    <?php if ($message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
        <!-- Option Jouer contre l'IA -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Jouer contre l'IA</h2>
                </div>
                
                <p class="text-gray-600 mb-4">Entraînez-vous contre notre intelligence artificielle</p>
                
                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <h3 class="text-md font-semibold text-purple-600 mb-2 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Avantages
                    </h3>
                    <ul class="space-y-2">
                        <li class="flex items-center text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Disponible 24h/24, 7j/7
                        </li>
                        <li class="flex items-center text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Commencez immédiatement
                        </li>
                        <li class="flex items-center text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Parfait pour s'entraîner
                        </li>
                    </ul>
                </div>
                
                <button id="play-bot" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg shadow transition duration-200 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                    </svg>
                    Commencer une partie contre l'IA
                </button>
            </div>
        </div>
        
        <!-- Section matchmaking -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Jouer contre un joueur</h2>
                </div>
                
                <p class="text-gray-600 mb-4">Affrontez d'autres joueurs en ligne en temps réel</p>
                
                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <h3 class="text-md font-semibold text-purple-600 mb-2 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Avantages
                    </h3>
                    <ul class="space-y-2">
                        <li class="flex items-center text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Matchmaking intelligent
                        </li>
                        <li class="flex items-center text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Compétitif et stimulant
                        </li>
                        <li class="flex items-center text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Rejoignez notre communauté
                        </li>
                    </ul>
                </div>
                
                <a href="/game/matchmaking.php" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg shadow transition duration-200 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                    </svg>
                    Trouver un adversaire
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Lien direct vers l'historique des parties -->
<div class="container mx-auto flex justify-center my-4">
    <a href="/game/history.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Voir l'historique des parties
    </a>
</div>

<!-- Modal de chargement pour IA -->
<div id="loading-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold text-indigo-600 mb-4">Création de la partie...</h3>
        <div class="flex items-center justify-center mb-6">
            <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-indigo-500"></div>
        </div>
        <p class="text-gray-700 text-center">Veuillez patienter pendant que nous préparons votre partie contre l'IA.</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variables pour la gestion de la partie contre l'IA
    const playBotBtn = document.getElementById('play-bot');
    const loadingModal = document.getElementById('loading-modal');
    
    // Afficher automatiquement l'historique si l'URL contient #history-section
    if (window.location.hash === '#history-section') {
    const historySection = document.getElementById('history-section');
        if (historySection) {
            historySection.classList.remove('hidden');
            // Faire défiler jusqu'à la section
            historySection.scrollIntoView({ behavior: 'smooth' });
        }
    }
    
    // Fonction pour créer une partie contre l'IA
    function playAgainstBot() {
        console.log('Clic sur le bouton IA détecté');
        loadingModal.classList.remove('hidden');
        
        fetch('/api/game/create_bot_game.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({})
        })
        .then(response => {
            console.log('Réponse reçue:', response);
            return response.json();
        })
        .then(data => {
            console.log('Données reçues:', data);
            
            if (data.success) {
                // Rediriger vers la partie
                window.location.href = '/game/board.php?id=' + data.game_id;
            } else {
                loadingModal.classList.add('hidden');
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            loadingModal.classList.add('hidden');
            alert('Une erreur est survenue lors de la création de la partie.');
        });
    }
    
    // Ajouter l'écouteur d'événement
    if (playBotBtn) {
        playBotBtn.addEventListener('click', playAgainstBot);
        console.log('Écouteur configuré pour le bouton IA');
    } else {
        console.error('Bouton IA non trouvé');
    }
});
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>