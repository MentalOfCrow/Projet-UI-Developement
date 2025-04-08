<?php 
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/includes/session.php';

// Vérifier que l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    header('Location: /auth/login.php');
    exit;
}

$pageTitle = "Recherche de partie - " . APP_NAME;
include __DIR__ . '/../../backend/includes/header.php';
?>

<!-- Espace blanc en haut de la page -->
<div class="h-16 bg-gray-50"></div>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-indigo-600 mb-6 text-center">
            Recherche de partie
        </h1>
        
        <div id="queue-status" class="mb-6 text-center">
            <div id="waiting" class="hidden">
                <div class="flex flex-col items-center mb-4">
                    <div class="queue-status bg-indigo-600 text-white px-6 py-3 rounded-lg shadow-lg mb-4">
                        <span class="pulse-dot"></span>
                        <span class="ml-2 font-medium">En file d'attente</span>
                    </div>
                    
                    <div class="queue-timer text-3xl font-bold text-indigo-600">
                        <span id="minutes">0</span>:<span id="seconds">00</span>
                    </div>
                </div>
                <p id="countdown-message" class="text-lg font-medium mb-2">Recherche d'un adversaire...</p>
            </div>
            <div id="not-waiting" class="">
                <p class="mb-4">Affrontez d'autres joueurs en ligne pour tester vos compétences!</p>
                <ul class="text-left mb-4 text-gray-700">
                    <li class="flex items-center mb-2">
                        <svg class="h-5 w-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Jouez contre de vrais adversaires
                    </li>
                    <li class="flex items-center mb-2">
                        <svg class="h-5 w-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Améliorez votre classement
                    </li>
                    <li class="flex items-center">
                        <svg class="h-5 w-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Affinez votre stratégie
                    </li>
                </ul>
            </div>
        </div>
        
        <style>
            .queue-status {
                display: flex;
                align-items: center;
                animation: pulseBackground 2s infinite;
            }
            
            .pulse-dot {
                display: inline-block;
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background-color: white;
                animation: pulse 1.5s infinite;
            }
            
            .queue-timer {
                font-family: 'Roboto', sans-serif;
                letter-spacing: 1px;
            }
            
            @keyframes pulse {
                0% {
                    transform: scale(0.8);
                    opacity: 0.8;
                }
                50% {
                    transform: scale(1.2);
                    opacity: 1;
                }
                100% {
                    transform: scale(0.8);
                    opacity: 0.8;
                }
            }
            
            @keyframes pulseBackground {
                0% {
                    background-color: rgba(79, 70, 229, 0.9);
                }
                50% {
                    background-color: rgba(99, 102, 241, 1);
                }
                100% {
                    background-color: rgba(79, 70, 229, 0.9);
                }
            }
        </style>
        
        <div class="flex justify-center">
            <button id="join-queue-btn" class="bg-indigo-600 text-white py-3 px-6 rounded-lg hover:bg-indigo-700 transition duration-200">
                Rejoindre la file d'attente
            </button>
            <button id="leave-queue-btn" class="hidden bg-red-600 text-white py-3 px-6 rounded-lg hover:bg-red-700 transition duration-200">
                Quitter la file d'attente
            </button>
        </div>
    </div>
</div>

<script>
    // Définir les constantes pour la file d'attente
    let waitTimeCounter = 0;
    let timerInterval = null;
    
    function startTimer() {
        clearInterval(timerInterval);
        waitTimeCounter = 0;
        
        // Mettre à jour l'affichage initial
        updateTimerDisplay(0);
        document.getElementById("countdown-message").textContent = "Recherche d'un adversaire...";
        
        timerInterval = setInterval(() => {
            waitTimeCounter++;
            updateTimerDisplay(waitTimeCounter);
        }, 1000);
    }
    
    function updateTimerDisplay(totalSeconds) {
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        
        // Afficher avec un zéro devant si les secondes sont inférieures à 10
        document.getElementById("minutes").textContent = minutes;
        document.getElementById("seconds").textContent = seconds < 10 ? `0${seconds}` : seconds;
    }
    
    function checkQueue() {
        console.log('Vérification de la file d\'attente...');
        fetch('/api/game/queue.php?action=check')
            .then(response => response.json())
            .then(data => {
                console.log('Réponse reçue:', data);
                
                if (data.matched) {
                    console.log('Match trouvé! Redirection vers:', '/game/board.php?id=' + data.game_id);
                    
                    // Arrêter le timer avant de rediriger
                    clearInterval(timerInterval);
                    
                    // Rediriger vers la nouvelle partie
                    window.location.href = '/game/board.php?id=' + data.game_id;
                } else {
                    // Continuer à vérifier
                    setTimeout(checkQueue, 3000); // Vérifie toutes les 3 secondes
                }
            })
            .catch(error => {
                console.error('Erreur lors de la vérification de la file d\'attente:', error);
                setTimeout(checkQueue, 3000);
            });
    }
    
    document.getElementById('join-queue-btn').addEventListener('click', function() {
        // Rejoindre la file d'attente
        fetch('/api/game/queue.php?action=join')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Afficher l'interface d'attente
                    document.getElementById('waiting').classList.remove('hidden');
                    document.getElementById('not-waiting').classList.add('hidden');
                    document.getElementById('join-queue-btn').classList.add('hidden');
                    document.getElementById('leave-queue-btn').classList.remove('hidden');
                    
                    // Démarrer le timer avec animation
                    startTimer();
                    
                    // Vérifier la file d'attente
                    if (!data.matched) {
                        checkQueue();
                    } else {
                        // Si un adversaire a été trouvé immédiatement
                        window.location.href = '/game/board.php?id=' + data.game_id;
                    }
                } else {
                    // Au lieu d'une alerte, montrer un message plus convivial
                    if (data.message === "Vous êtes déjà dans la file d'attente.") {
                        // Afficher l'interface d'attente
                        document.getElementById('waiting').classList.remove('hidden');
                        document.getElementById('not-waiting').classList.add('hidden');
                        document.getElementById('join-queue-btn').classList.add('hidden');
                        document.getElementById('leave-queue-btn').classList.remove('hidden');
                        
                        // Démarrer la vérification
                        checkQueue();
                        startTimer();
                    } else {
                        alert(data.message);
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue. Veuillez réessayer.');
            });
    });
    
    document.getElementById('leave-queue-btn').addEventListener('click', function() {
        // Quitter la file d'attente
        fetch('/api/game/queue.php?action=leave')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Afficher l'interface initiale
                    document.getElementById('waiting').classList.add('hidden');
                    document.getElementById('not-waiting').classList.remove('hidden');
                    document.getElementById('join-queue-btn').classList.remove('hidden');
                    document.getElementById('leave-queue-btn').classList.add('hidden');
                    
                    // Arrêter le compteur
                    clearInterval(timerInterval);
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue. Veuillez réessayer.');
            });
    });
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?> 