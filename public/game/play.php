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

require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/controllers/GameController.php';
require_once __DIR__ . '/../../backend/includes/session.php';

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

// Récupérer les parties actives de l'utilisateur
$gameController = new GameController();
$activeGames = $gameController->getActiveGames($user_id);

// Récupérer les parties terminées (historique)
$gameHistory = $gameController->readGameHistory($user_id);

// Récupérer un message éventuel de redirection
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';

$pageTitle = "Jouer - " . APP_NAME;
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<link rel="stylesheet" href="/assets/css/style.css">

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center text-indigo-600 mb-8">Choisissez votre mode de jeu</h1>
    
    <?php if ($message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Option Jouer contre l'IA -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Jouer contre l'IA</h2>
                </div>
                
                <p class="text-gray-600 mb-4">Entraînez-vous contre notre intelligence artificielle</p>
                
                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <h3 class="text-md font-semibold text-indigo-600 mb-2 flex items-center">
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
                
                <button id="play-bot" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow transition duration-200 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                    </svg>
                    Commencer une partie contre l'IA
                </button>
            </div>
        </div>
        
        <!-- Option Jouer contre un joueur -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Jouer contre un joueur</h2>
                </div>
                
                <p class="text-gray-600 mb-4">Affrontez d'autres joueurs en ligne</p>
                
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
                            Jouez contre de vrais adversaires
                        </li>
                        <li class="flex items-center text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Améliorez votre classement
                        </li>
                        <li class="flex items-center text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                            </svg>
                            Temps d'attente variable
                        </li>
                    </ul>
                </div>
                
                <button id="join-queue" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg shadow transition duration-200 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    Rejoindre la file d'attente
                </button>
            </div>
        </div>
        
        <!-- Vos parties en cours -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                                            <a href="/game/board.php?id=<?php echo $game['id']; ?>" class="text-indigo-600 hover:text-indigo-900">
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
                
                <?php if ($gameHistory && $gameHistory->rowCount() > 0): ?>
                    <div class="border-t pt-4">
                        <h3 class="text-md font-semibold text-gray-700 mb-2">Historique des parties</h3>
                        <button id="show-history" class="w-full py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg transition duration-200 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                            Voir l'historique (<?php echo $gameHistory->rowCount(); ?> parties)
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Historique des parties (caché par défaut) -->
    <?php if ($gameHistory && $gameHistory->rowCount() > 0): ?>
        <div id="history-section" class="mt-8 bg-white shadow-md rounded-lg overflow-hidden hidden">
            <div class="p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Historique de vos parties</h2>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Partie</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adversaire</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Résultat</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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
                                    $resultClass = 'text-green-600';
                                } elseif ($game['winner_id'] == null) {
                                    $result = 'Match nul';
                                    $resultClass = 'text-yellow-600';
                                } else {
                                    $result = 'Défaite';
                                    $resultClass = 'text-red-600';
                                }
                                
                                // Formater la date
                                $date = new DateTime($game['created_at']);
                                $formattedDate = $date->format('d/m/Y H:i');
                                ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        #<?php echo $game['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($opponentName); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $formattedDate; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $resultClass; ?>">
                                            <?php echo $result; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="/game/board.php?id=<?php echo $game['id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                            Voir
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de file d'attente -->
<div id="queue-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold text-indigo-600 mb-4">Recherche d'adversaire...</h3>
        <div class="flex items-center justify-center mb-6">
            <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-indigo-500"></div>
        </div>
        <p class="text-gray-700 mb-6 text-center">Veuillez patienter pendant que nous recherchons un adversaire pour vous.</p>
        <div id="queue-status" class="text-center mb-4 text-sm font-medium text-indigo-600">
            En recherche depuis 0 secondes...
        </div>
        <div class="flex justify-center">
            <button id="cancel-queue" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">Annuler</button>
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
    // Variables pour la gestion de la file d'attente
    const queueModal = document.getElementById('queue-modal');
    const queueStatus = document.getElementById('queue-status');
    const cancelQueueBtn = document.getElementById('cancel-queue');
    const joinQueueBtn = document.getElementById('join-queue');
    
    // Variables pour la gestion de la partie contre l'IA
    const playBotBtn = document.getElementById('play-bot');
    const loadingModal = document.getElementById('loading-modal');
    
    // Variables pour l'historique
    const showHistoryBtn = document.getElementById('show-history');
    const historySection = document.getElementById('history-section');
    
    let queueStartTime = 0;
    let queueInterval = null;
    let checkMatchInterval = null;
    
    // Fonction pour mettre à jour le temps d'attente
    function updateQueueTime() {
        const elapsedSeconds = Math.floor((Date.now() - queueStartTime) / 1000);
        queueStatus.innerText = `En recherche depuis ${elapsedSeconds} seconde${elapsedSeconds > 1 ? 's' : ''}...`;
    }
    
    // Fonction pour rejoindre la file d'attente
    function joinQueue() {
        queueModal.classList.remove('hidden');
        queueStartTime = Date.now();
        
        // Mettre à jour le temps d'attente toutes les secondes
        queueInterval = setInterval(updateQueueTime, 1000);
        
        // Appeler l'API pour rejoindre la file d'attente
        fetch('/api/game/queue.php?action=join')
            .then(response => response.json())
            .then(data => {
                console.log('Rejoindre la file:', data);
                
                if (data.success) {
                    // Vérifier toutes les 3 secondes si un match a été trouvé
                    checkMatchInterval = setInterval(checkMatch, 3000);
                } else {
                    alert('Erreur: ' + data.message);
                    quitQueue();
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de la connexion au serveur.');
                quitQueue();
            });
    }
    
    // Fonction pour vérifier si un match a été trouvé
    function checkMatch() {
        fetch('/api/game/queue.php?action=check')
            .then(response => response.json())
            .then(data => {
                console.log('Vérification de match:', data);
                
                if (data.success) {
                    if (data.game_found && data.game_id) {
                        // Match trouvé, rediriger vers la partie
                        clearInterval(queueInterval);
                        clearInterval(checkMatchInterval);
                        window.location.href = '/game/board.php?id=' + data.game_id;
                    }
                } else {
                    alert('Erreur: ' + data.message);
                    quitQueue();
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de la vérification du match.');
                quitQueue();
            });
    }
    
    // Fonction pour quitter la file d'attente
    function quitQueue() {
        queueModal.classList.add('hidden');
        clearInterval(queueInterval);
        clearInterval(checkMatchInterval);
        
        // Appeler l'API pour quitter la file d'attente
        fetch('/api/game/queue.php?action=leave')
            .then(response => response.json())
            .then(data => {
                console.log('Quitter la file:', data);
            })
            .catch(error => {
                console.error('Erreur:', error);
            });
    }
    
    // Fonction pour créer une partie contre l'IA
    function playAgainstBot() {
        loadingModal.classList.remove('hidden');
        
        fetch('/api/game/create_bot_game.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({})
        })
        .then(response => response.json())
        .then(data => {
            console.log('Création de partie contre IA:', data);
            
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
    
    // Ajouter les écouteurs d'événements
    joinQueueBtn.addEventListener('click', joinQueue);
    cancelQueueBtn.addEventListener('click', quitQueue);
    playBotBtn.addEventListener('click', playAgainstBot);
    
    // Gestion de l'historique
    if (showHistoryBtn) {
        showHistoryBtn.addEventListener('click', function() {
            if (historySection.classList.contains('hidden')) {
                historySection.classList.remove('hidden');
                this.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                    Masquer l'historique
                `;
            } else {
                historySection.classList.add('hidden');
                this.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Voir l'historique (<?php echo $gameHistory->rowCount(); ?> parties)
                `;
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>