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
        
        <!-- Vos parties en cours -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden mb-8">
            <div class="p-6">
                <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Vos parties en cours</h2>
                </div>
                
                <div class="mb-4 overflow-auto max-h-80">
                    <?php if ($activeGames && $activeGames->rowCount() > 0): ?>
                        <ul class="divide-y divide-gray-200">
                            <?php while ($game = $activeGames->fetch(PDO::FETCH_ASSOC)): ?>
                                <?php
                                // Déterminer si l'utilisateur est le joueur 1 ou 2
                                $isPlayer1 = $game['player1_id'] == $user_id;
                                
                                // Déterminer l'adversaire
                                $opponentName = $isPlayer1 ? $game['player2_name'] : $game['player1_name'];
                                if ($game['player2_id'] === '0' || $game['player2_id'] === 0) {
                                    $opponentName = 'IA';
                                }
                                
                                // Déterminer si c'est au tour de l'utilisateur
                                $isUserTurn = ($isPlayer1 && $game['current_player'] == 1) || (!$isPlayer1 && $game['current_player'] == 2);
                                ?>
                                <li class="py-3">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <span class="font-medium">Partie #<?php echo $game['id']; ?></span>
                                            <p class="text-sm text-gray-600">
                                                Contre <?php echo htmlspecialchars($opponentName); ?>
                                            </p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <?php if ($isUserTurn): ?>
                                                <span class="px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full">Votre tour</span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full">Tour de l'adversaire</span>
                                            <?php endif; ?>
                                        <a href="/game/board.php?id=<?php echo $game['id']; ?>" class="text-purple-600 hover:text-purple-900">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center py-8">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p class="text-gray-500 text-center">Vous n'avez aucune partie en cours.</p>
                            <p class="text-gray-500 text-center text-sm mt-1">Commencez une nouvelle partie en choisissant un mode de jeu.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bouton pour afficher l'historique -->
<div class="container mx-auto flex justify-center my-4">
    <button id="show-history-btn" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Voir l'historique des parties
    </button>
</div>

<!-- Historique des parties -->
<div id="history-section" class="bg-white shadow-md rounded-lg overflow-hidden mb-8 hidden">
    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-800">Historique des parties</h2>
            </div>
            <button id="hide-history" class="text-gray-500 hover:text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        
        <div class="mb-4 overflow-auto max-h-96">
            <?php if ($gameHistory && $gameHistory->rowCount() > 0): ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Partie</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adversaire</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Résultat</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($game = $gameHistory->fetch(PDO::FETCH_ASSOC)): ?>
                            <?php
                            // Déterminer si l'utilisateur est le joueur 1 ou 2
                            $isPlayer1 = $game['player1_id'] == $user_id;
                            
                            // Déterminer l'adversaire
                            $opponentName = $isPlayer1 ? $game['player2_name'] : $game['player1_name'];
                            if ($game['player2_id'] === '0' || $game['player2_id'] === 0) {
                                $opponentName = 'IA';
                            }
                            
                            // Déterminer le résultat
                            $result = '';
                            $resultClass = '';
                            
                            if ($game['winner_id'] == $user_id) {
                                $result = 'Victoire';
                                $resultClass = 'bg-green-100 text-green-800';
                            } elseif ($game['winner_id'] == '0' && $game['status'] == 'draw') {
                                $result = 'Match nul';
                                $resultClass = 'bg-yellow-100 text-yellow-800';
                            } else {
                                $result = 'Défaite';
                                $resultClass = 'bg-red-100 text-red-800';
                            }
                            
                            // Formater la date
                            $date = new DateTime($game['updated_at']);
                            $formattedDate = $date->format('d/m/Y H:i');
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">Partie #<?php echo $game['id']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($opponentName); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $resultClass; ?>">
                                        <?php echo $result; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $formattedDate; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="flex flex-col items-center justify-center py-8">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-gray-500 text-center">Vous n'avez aucune partie terminée.</p>
                    <p class="text-gray-500 text-center text-sm mt-1">Jouez quelques parties pour voir votre historique ici.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
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
    
    // Gestion du bouton d'affichage de l'historique
    const showHistoryBtn = document.getElementById('show-history-btn');
    if (showHistoryBtn) {
        showHistoryBtn.addEventListener('click', function() {
            const historySection = document.getElementById('history-section');
            if (historySection) {
                historySection.classList.toggle('hidden');
                if (!historySection.classList.contains('hidden')) {
                    historySection.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    }
    
    // Gestion du bouton pour masquer l'historique
    const hideHistoryBtn = document.getElementById('hide-history');
    if (hideHistoryBtn) {
        hideHistoryBtn.addEventListener('click', function() {
            const historySection = document.getElementById('history-section');
            if (historySection) {
                historySection.classList.add('hidden');
            }
        });
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