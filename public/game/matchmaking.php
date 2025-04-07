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
                <div class="flex justify-center mb-4">
                    <div class="base-timer">
                        <svg class="base-timer__svg" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <g class="base-timer__circle">
                                <circle class="base-timer__path-elapsed" cx="50" cy="50" r="45"></circle>
                                <path
                                    id="base-timer-path-remaining"
                                    stroke-dasharray="283"
                                    class="base-timer__path-remaining green"
                                    d="
                                        M 50, 50
                                        m -45, 0
                                        a 45,45 0 1,0 90,0
                                        a 45,45 0 1,0 -90,0
                                    "
                                ></path>
                            </g>
                        </svg>
                        <span id="base-timer-label" class="base-timer__label">30</span>
                    </div>
                </div>
                <p id="countdown-message" class="text-lg font-medium mb-2">Recherche d'un adversaire...</p>
                <p class="text-gray-600">Temps écoulé: <span id="wait-time">0</span> secondes</p>
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
            .base-timer {
                position: relative;
                width: 150px;
                height: 150px;
            }
            
            .base-timer__svg {
                transform: scaleX(-1);
            }
            
            .base-timer__circle {
                fill: none;
                stroke: none;
            }
            
            .base-timer__path-elapsed {
                stroke-width: 7px;
                stroke: grey;
                opacity: 0.3;
            }
            
            .base-timer__path-remaining {
                stroke-width: 7px;
                stroke-linecap: round;
                transform: rotate(90deg);
                transform-origin: center;
                transition: 1s linear all;
                fill-rule: nonzero;
                stroke: currentColor;
            }
            
            .base-timer__path-remaining.green {
                color: rgb(65, 184, 131);
            }
            
            .base-timer__path-remaining.orange {
                color: orange;
            }
            
            .base-timer__path-remaining.red {
                color: red;
            }
            
            .base-timer__label {
                position: absolute;
                width: 150px;
                height: 150px;
                top: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 48px;
                font-weight: bold;
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
    // Définir les constantes pour l'animation du compteur
    const FULL_DASH_ARRAY = 283;
    // Changer de couleur à ces seuils (en secondes)
    const WARNING_THRESHOLD = 20;
    const ALERT_THRESHOLD = 25;
    // Temps total avant de proposer une partie contre l'IA (en secondes)
    const MAX_WAIT_TIME = 30;

    const COLOR_CODES = {
        info: {
            color: "green"
        },
        warning: {
            color: "orange",
            threshold: WARNING_THRESHOLD
        },
        alert: {
            color: "red",
            threshold: ALERT_THRESHOLD
        }
    };
    
    let waitTimeCounter = 0;
    let waitInterval;
    let timerInterval = null;
    let isWaitingForBot = false;
    
    function setRemainingPathColor(timeLeft) {
        const { alert, warning, info } = COLOR_CODES;
        
        const timeRemaining = MAX_WAIT_TIME - timeLeft;
        
        // Couleur rouge à 5 secondes restantes
        if (timeRemaining <= 5) {
            document
                .getElementById("base-timer-path-remaining")
                .classList.remove(warning.color);
            document
                .getElementById("base-timer-path-remaining")
                .classList.add(alert.color);
        } 
        // Couleur orange à 10 secondes restantes
        else if (timeRemaining <= 10) {
            document
                .getElementById("base-timer-path-remaining")
                .classList.remove(info.color);
            document
                .getElementById("base-timer-path-remaining")
                .classList.add(warning.color);
        }
    }
    
    function calculateTimeFraction(timeElapsed) {
        const timeRemaining = MAX_WAIT_TIME - timeElapsed;
        // Retourne la proportion du temps restant (de 1 à 0)
        return timeRemaining / MAX_WAIT_TIME;
    }
    
    function setCircleDasharray(timeElapsed) {
        const circleDasharray = `${(
            calculateTimeFraction(timeElapsed) * FULL_DASH_ARRAY
        ).toFixed(0)} 283`;
        document
            .getElementById("base-timer-path-remaining")
            .setAttribute("stroke-dasharray", circleDasharray);
    }
    
    function startTimer() {
        clearInterval(timerInterval);
        waitTimeCounter = 0;
        isWaitingForBot = false;
        
        // Mettre à jour l'affichage initial
        document.getElementById("base-timer-label").innerHTML = MAX_WAIT_TIME;
        document.getElementById("wait-time").textContent = "0";
        document.getElementById("countdown-message").textContent = "Recherche d'un adversaire...";
        
        // Réinitialiser les classes CSS
        document.getElementById("base-timer-path-remaining").classList.remove("orange", "red");
        document.getElementById("base-timer-path-remaining").classList.add("green");
        document.getElementById("base-timer-path-remaining").setAttribute("stroke-dasharray", "283 283");
        
        timerInterval = setInterval(() => {
            waitTimeCounter++;
            
            // Afficher le temps restant dans le cercle
            const timeRemaining = Math.max(0, MAX_WAIT_TIME - waitTimeCounter);
            document.getElementById("base-timer-label").innerHTML = timeRemaining;
            
            // Afficher le temps écoulé en dessous
            document.getElementById("wait-time").textContent = waitTimeCounter;
            
            // Mettre à jour la couleur et le message selon le temps restant
            setRemainingPathColor(waitTimeCounter);
            setCircleDasharray(waitTimeCounter);
            
            // Lancer une partie contre l'IA quand le temps est écoulé
            if (waitTimeCounter >= MAX_WAIT_TIME && !isWaitingForBot) {
                isWaitingForBot = true;
                document.getElementById("countdown-message").textContent = "Aucun adversaire trouvé. Lancement d'une partie contre l'IA...";
                
                // Appel pour créer une partie contre l'IA
                setTimeout(() => {
                    createBotGame();
                }, 1500);
            }
            
        }, 1000);
    }
    
    function createBotGame() {
        clearInterval(timerInterval);
        
        // Afficher un message de chargement
        document.getElementById("countdown-message").textContent = "Création d'une partie contre l'IA...";
        
        // Appel à l'API pour créer une partie contre l'IA
        fetch('/api/game/create_bot_game.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirection vers la partie
                    window.location.href = '/game/board.php?id=' + data.game_id;
                } else {
                    alert('Erreur lors de la création de la partie: ' + data.message);
                    // Réinitialiser l'interface
                    document.getElementById('waiting').classList.add('hidden');
                    document.getElementById('not-waiting').classList.remove('hidden');
                    document.getElementById('join-queue-btn').classList.remove('hidden');
                    document.getElementById('leave-queue-btn').classList.add('hidden');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue. Veuillez réessayer.');
                // Réinitialiser l'interface
                document.getElementById('waiting').classList.add('hidden');
                document.getElementById('not-waiting').classList.remove('hidden');
                document.getElementById('join-queue-btn').classList.remove('hidden');
                document.getElementById('leave-queue-btn').classList.add('hidden');
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
                    // Mettre à jour le temps d'attente affiché seulement si provient du serveur
                    if (data.wait_time && data.wait_time > waitTimeCounter) {
                        waitTimeCounter = data.wait_time;
                        document.getElementById("base-timer-label").innerHTML = waitTimeCounter;
                        document.getElementById("wait-time").textContent = waitTimeCounter;
                        setRemainingPathColor(waitTimeCounter);
                        setCircleDasharray(waitTimeCounter);
                    }
                    
                    // Continuer à vérifier
                    setTimeout(checkQueue, 3000); // Vérifie toutes les 3 secondes
                }
            })
            .catch(error => {
                console.error('Erreur lors de la vérification de la file d\'attente:', error);
                setTimeout(checkQueue, 3000);
            });
    }
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?> 