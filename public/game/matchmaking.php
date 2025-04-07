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
                    <svg class="animate-spin h-10 w-10 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <p class="text-lg font-medium mb-2">En attente d'un adversaire...</p>
                <p class="text-gray-600">Temps d'attente: <span id="wait-time">0</span> seconds</p>
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
    let waitTimeCounter = 0;
    let waitInterval;
    
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
                    
                    // Démarrer le compteur de temps
                    waitTimeCounter = 0;
                    document.getElementById('wait-time').textContent = waitTimeCounter;
                    waitInterval = setInterval(function() {
                        waitTimeCounter++;
                        document.getElementById('wait-time').textContent = waitTimeCounter;
                    }, 1000);
                    
                    // Vérifier la file d'attente
                    if (!data.matched) {
                        checkQueue();
                    } else {
                        // Si un adversaire a été trouvé immédiatement
                        window.location.href = '/game/board.php?id=' + data.game_id;
                    }
                } else {
                    alert(data.message);
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
                    clearInterval(waitInterval);
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
                    // Rediriger vers la nouvelle partie
                    window.location.href = '/game/board.php?id=' + data.game_id;
                } else {
                    // Mettre à jour le temps d'attente affiché
                    document.getElementById('wait-time').textContent = data.wait_time || waitTimeCounter;
                    
                    // Continuer à vérifier
                    setTimeout(checkQueue, 3000); // Vérifie toutes les 3 secondes au lieu de 5
                }
            })
            .catch(error => {
                console.error('Erreur lors de la vérification de la file d\'attente:', error);
                setTimeout(checkQueue, 3000);
            });
    }
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?> 