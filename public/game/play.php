<?php
// Supprimer tout output buffering existant et en démarrer un nouveau
while (ob_get_level()) ob_end_clean();
ob_start();

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
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Joueur';

// Récupérer les parties actives de l'utilisateur
$gameController = new GameController();
$activeGames = $gameController->getActiveGames($user_id);

$pageTitle = "Jouer - " . APP_NAME;
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-center text-indigo-700 mb-8">Choisissez votre mode de jeu</h1>
    
    <div class="flex flex-col md:flex-row gap-8">
        <!-- Modes de jeu -->
        <div class="w-full md:w-2/3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Jouer contre l'IA -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-indigo-100">
                    <div class="p-6">
                        <div class="flex items-start mb-4">
                            <div class="bg-indigo-100 rounded-full p-3 mr-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">Jouer contre l'IA</h2>
                                <p class="text-gray-600">Entraînez-vous contre notre intelligence artificielle</p>
                            </div>
                        </div>
                        
                        <div class="bg-indigo-50 rounded-lg p-4 mb-4">
                            <h3 class="text-sm font-medium text-indigo-800 flex items-center mb-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Avantages
                            </h3>
                            <ul class="space-y-2">
                                <li class="flex items-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-gray-700">Disponible 24h/24, 7j/7</span>
                                </li>
                                <li class="flex items-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-gray-700">Commencez immédiatement</span>
                                </li>
                                <li class="flex items-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-gray-700">Parfait pour s'entraîner</span>
                                </li>
                            </ul>
                        </div>
                        
                        <button id="start-bot-game" class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-md flex items-center justify-center transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Commencer une partie contre l'IA
                        </button>
                        <div id="bot-game-status" class="mt-2 hidden"></div>
                    </div>
                </div>
                
                <!-- Jouer contre un joueur -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-indigo-100">
                    <div class="p-6">
                        <div class="flex items-start mb-4">
                            <div class="bg-purple-100 rounded-full p-3 mr-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">Jouer contre un joueur</h2>
                                <p class="text-gray-600">Affrontez d'autres joueurs en ligne</p>
                            </div>
                        </div>
                        
                        <div class="bg-purple-50 rounded-lg p-4 mb-4">
                            <h3 class="text-sm font-medium text-purple-800 flex items-center mb-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Avantages
                            </h3>
                            <ul class="space-y-2">
                                <li class="flex items-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-gray-700">Jouez contre de vrais adversaires</span>
                                </li>
                                <li class="flex items-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-gray-700">Améliorez votre classement</span>
                                </li>
                                <li class="flex items-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <span class="text-gray-700">Temps d'attente variable</span>
                                </li>
                            </ul>
                        </div>
                        
                        <button id="join-queue" class="w-full py-3 px-4 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-md flex items-center justify-center transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                            Rejoindre la file d'attente
                        </button>
                        <div id="queue-status" class="mt-2 hidden"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Parties actives -->
        <div class="w-full md:w-1/3">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
                    <h2 class="text-lg font-semibold text-white">Vos parties en cours</h2>
                </div>
                
                <div class="p-4">
                    <?php if ($activeGames->rowCount() > 0): ?>
                        <ul class="divide-y divide-gray-200">
                            <?php while ($game = $activeGames->fetch(PDO::FETCH_ASSOC)): ?>
                                <?php
                                $isPlayer1 = $game['player1_id'] == $user_id;
                                $opponentId = $isPlayer1 ? $game['player2_id'] : $game['player1_id'];
                                $opponentName = $isPlayer1 ? $game['player2_name'] : $game['player1_name'];
                                
                                // Si l'adversaire est le bot (ID négatif)
                                if ($opponentId < 0) {
                                    $opponentName = "IA";
                                }
                                
                                $isUserTurn = $game['current_player'] == $user_id;
                                ?>
                                <li class="py-3">
                                    <a href="/game/board.php?id=<?php echo $game['id']; ?>" class="block hover:bg-gray-50 transition p-2 rounded-md">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm font-medium text-indigo-600">Partie #<?php echo $game['id']; ?></p>
                                                <p class="text-sm text-gray-700">vs <?php echo htmlspecialchars($opponentName); ?></p>
                                                <p class="text-xs text-gray-500">Créée le <?php echo date('d/m/Y à H:i', strtotime($game['created_at'])); ?></p>
                                            </div>
                                            <div>
                                                <?php if ($isUserTurn): ?>
                                                    <span class="bg-green-100 text-green-800 text-xs font-medium py-1 px-2 rounded-full">Votre tour</span>
                                                <?php else: ?>
                                                    <span class="bg-orange-100 text-orange-800 text-xs font-medium py-1 px-2 rounded-full">Tour adverse</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-6">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p class="mt-2 text-gray-600">Vous n'avez aucune partie en cours.</p>
                            <p class="text-sm text-gray-500">Commencez une nouvelle partie en choisissant un mode de jeu.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Éléments DOM
    const startBotGameBtn = document.getElementById('start-bot-game');
    const botGameStatus = document.getElementById('bot-game-status');
    const joinQueueBtn = document.getElementById('join-queue');
    const queueStatus = document.getElementById('queue-status');
    
    // Commencer une partie contre le bot
    startBotGameBtn.addEventListener('click', function() {
        // Désactiver le bouton et afficher le chargement
        startBotGameBtn.disabled = true;
        startBotGameBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Création de la partie...
        `;
        
        // Appel à l'API pour créer une partie contre l'IA
        fetch('/api/game/create_bot_game.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => {
            console.log("Réponse status:", response.status);
            return response.json();
        })
        .then(data => {
            console.log("Données reçues:", data);
            
            if (data.success) {
                botGameStatus.textContent = "Partie créée avec succès !";
                botGameStatus.className = "mt-2 text-sm text-green-600";
                botGameStatus.classList.remove('hidden');
                
                // Rediriger vers la page du plateau de jeu
                window.location.href = `/game/board.php?id=${data.game_id}`;
            } else {
                // Afficher l'erreur
                botGameStatus.textContent = data.message || "Erreur lors de la création de la partie.";
                botGameStatus.className = "mt-2 text-sm text-red-600";
                botGameStatus.classList.remove('hidden');
                
                // Réactiver le bouton
                startBotGameBtn.disabled = false;
                startBotGameBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Commencer une partie contre l'IA
                `;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            
            // Afficher l'erreur
            botGameStatus.textContent = "Erreur de connexion. Veuillez réessayer.";
            botGameStatus.className = "mt-2 text-sm text-red-600";
            botGameStatus.classList.remove('hidden');
            
            // Réactiver le bouton
            startBotGameBtn.disabled = false;
            startBotGameBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Commencer une partie contre l'IA
            `;
        });
    });
    
    // File d'attente pour jouer contre un joueur
    let queueInterval;
    
    joinQueueBtn.addEventListener('click', function() {
        // Vérifier si on est déjà dans la file d'attente
        const isInQueue = joinQueueBtn.dataset.inQueue === 'true';
        
        if (isInQueue) {
            // Quitter la file d'attente
            leaveQueue();
        } else {
            // Rejoindre la file d'attente
            joinQueue();
        }
    });
    
    function joinQueue() {
        // Désactiver le bouton et afficher le chargement
        joinQueueBtn.disabled = true;
        joinQueueBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Recherche d'adversaire...
        `;
        
        // Appel à l'API pour rejoindre la file d'attente
        fetch('/api/game/queue.php?action=join', {
            method: 'GET'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour le statut et le bouton
                queueStatus.textContent = "En attente d'un adversaire...";
                queueStatus.className = "mt-2 text-sm text-blue-600";
                queueStatus.classList.remove('hidden');
                
                joinQueueBtn.disabled = false;
                joinQueueBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Quitter la file d'attente
                `;
                joinQueueBtn.dataset.inQueue = 'true';
                
                // Démarrer la vérification périodique de la file d'attente
                queueInterval = setInterval(checkQueue, 3000);
            } else {
                // Afficher l'erreur
                queueStatus.textContent = data.message || "Erreur lors de la jointure à la file d'attente.";
                queueStatus.className = "mt-2 text-sm text-red-600";
                queueStatus.classList.remove('hidden');
                
                // Réactiver le bouton
                joinQueueBtn.disabled = false;
                joinQueueBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                    Rejoindre la file d'attente
                `;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            
            // Afficher l'erreur
            queueStatus.textContent = "Erreur de connexion. Veuillez réessayer.";
            queueStatus.className = "mt-2 text-sm text-red-600";
            queueStatus.classList.remove('hidden');
            
            // Réactiver le bouton
            joinQueueBtn.disabled = false;
            joinQueueBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
                Rejoindre la file d'attente
            `;
        });
    }
    
    function leaveQueue() {
        // Désactiver le bouton et afficher le chargement
        joinQueueBtn.disabled = true;
        joinQueueBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Sortie de la file...
        `;
        
        // Arrêter la vérification périodique
        clearInterval(queueInterval);
        
        // Appel à l'API pour quitter la file d'attente
        fetch('/api/game/queue.php?action=leave', {
            method: 'GET'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour le statut et le bouton
                queueStatus.textContent = "Vous avez quitté la file d'attente.";
                queueStatus.className = "mt-2 text-sm text-orange-600";
                
                setTimeout(() => {
                    queueStatus.classList.add('hidden');
                }, 3000);
                
                joinQueueBtn.disabled = false;
                joinQueueBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                    Rejoindre la file d'attente
                `;
                joinQueueBtn.dataset.inQueue = 'false';
            } else {
                // Afficher l'erreur
                queueStatus.textContent = data.message || "Erreur lors de la sortie de la file d'attente.";
                queueStatus.className = "mt-2 text-sm text-red-600";
                
                // Réactiver le bouton
                joinQueueBtn.disabled = false;
                joinQueueBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Quitter la file d'attente
                `;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            
            // Afficher l'erreur
            queueStatus.textContent = "Erreur de connexion. Veuillez réessayer.";
            queueStatus.className = "mt-2 text-sm text-red-600";
            
            // Réactiver le bouton
            joinQueueBtn.disabled = false;
            joinQueueBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Quitter la file d'attente
            `;
        });
    }
    
    function checkQueue() {
        fetch('/api/game/queue.php?action=check', {
            method: 'GET'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.match_found) {
                    // Arrêter la vérification périodique
                    clearInterval(queueInterval);
                    
                    // Mettre à jour le statut
                    queueStatus.textContent = "Match trouvé ! Redirection...";
                    queueStatus.className = "mt-2 text-sm text-green-600";
                    
                    // Rediriger vers la page du plateau de jeu
                    window.location.href = `/game/board.php?id=${data.game_id}`;
                } else {
                    // Mettre à jour le temps d'attente
                    const waitTime = data.wait_time || 0;
                    queueStatus.textContent = `En attente d'un adversaire... (${waitTime}s)`;
                }
            } else if (data.error === 'not_in_queue') {
                // Arrêter la vérification périodique
                clearInterval(queueInterval);
                
                // Mettre à jour le statut et le bouton
                queueStatus.textContent = "Vous n'êtes plus dans la file d'attente.";
                queueStatus.className = "mt-2 text-sm text-orange-600";
                
                joinQueueBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                    Rejoindre la file d'attente
                `;
                joinQueueBtn.dataset.inQueue = 'false';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
    }
});
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>