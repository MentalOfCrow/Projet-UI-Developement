<?php
// Supprimer tout output buffering existant et en démarrer un nouveau
while (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/controllers/AuthController.php';
require_once __DIR__ . '/../../backend/controllers/GameController.php';
require_once __DIR__ . '/../../backend/controllers/MatchmakingController.php';
require_once __DIR__ . '/../../backend/includes/session.php';

// Vérifier si l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    // Nettoyer le buffer avant de rediriger
    ob_end_clean();
    header('Location: /auth/login.php');
    exit();
}

$authController = new AuthController();
$currentUser = $authController->getCurrentUser();
$gameController = new GameController();
$matchmakingController = new MatchmakingController();

$pageTitle = "Jouer - " . APP_NAME;
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-indigo-600 mb-8 text-center">Trouvez un adversaire</h1>
    
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-8" x-data="matchmaking">
        <!-- Statut du matchmaking -->
        <div class="text-center mb-6">
            <template x-if="!inQueue && !message">
                <p class="text-gray-600">Rejoignez la file d'attente pour trouver un adversaire.</p>
            </template>
            
            <template x-if="inQueue">
                <div>
                    <div class="flex justify-center items-center mb-4">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-indigo-600 font-semibold">Recherche d'un adversaire...</p>
                    </div>
                    <p class="text-gray-600 text-sm">Cette opération peut prendre quelques instants.</p>
                </div>
            </template>
            
            <template x-if="message">
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
                    <p x-text="message"></p>
                </div>
            </template>
        </div>
        
        <!-- Boutons d'action -->
        <div class="flex justify-center">
            <template x-if="!inQueue">
                <button @click="joinQueue" class="bg-indigo-600 text-white py-2 px-6 rounded-lg font-semibold hover:bg-indigo-700 transition duration-200">
                    Rejoindre la file d'attente
                </button>
            </template>
            
            <template x-if="inQueue">
                <button @click="leaveQueue" class="bg-red-600 text-white py-2 px-6 rounded-lg font-semibold hover:bg-red-700 transition duration-200">
                    Quitter la file
                </button>
            </template>
        </div>
    </div>
    
    <div class="max-w-md mx-auto mt-8">
        <h2 class="text-xl font-semibold text-indigo-600 mb-4">Comment ça marche ?</h2>
        <ol class="list-decimal pl-6 space-y-2 text-gray-600">
            <li>Rejoignez la file d'attente en cliquant sur le bouton ci-dessus.</li>
            <li>Le système cherchera automatiquement un adversaire disponible.</li>
            <li>Une fois un adversaire trouvé, vous serez automatiquement redirigé vers le plateau de jeu.</li>
            <li>En cas d'attente prolongée, vous pouvez quitter la file et réessayer plus tard.</li>
        </ol>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('matchmaking', () => ({
        inQueue: false,
        message: '',
        checkInterval: null,
        
        joinQueue() {
            this.message = '';
            this.inQueue = true;
            
            fetch('/api/game/queue.php?action=join', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Vérifier régulièrement si un match a été trouvé
                    this.checkInterval = setInterval(() => this.checkQueue(), 3000);
                } else {
                    this.message = data.message;
                    this.inQueue = false;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                this.message = "Une erreur est survenue lors de la connexion au serveur.";
                this.inQueue = false;
            });
        },
        
        leaveQueue() {
            fetch('/api/game/queue.php?action=leave', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                this.message = data.message;
                this.inQueue = false;
                clearInterval(this.checkInterval);
            })
            .catch(error => {
                console.error('Erreur:', error);
                this.message = "Une erreur est survenue lors de la déconnexion du serveur.";
                this.inQueue = false;
                clearInterval(this.checkInterval);
            });
        },
        
        checkQueue() {
            fetch('/api/game/queue.php?action=check', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.match_found) {
                        clearInterval(this.checkInterval);
                        window.location.href = `/game/board.php?id=${data.game_id}`;
                    }
                } else {
                    this.message = data.message;
                    this.inQueue = false;
                    clearInterval(this.checkInterval);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                this.message = "Une erreur est survenue lors de la vérification de la file d'attente.";
                this.inQueue = false;
                clearInterval(this.checkInterval);
            });
        }
    }));
});
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>