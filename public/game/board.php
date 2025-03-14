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

// Récupérer l'ID de la partie depuis l'URL
$game_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$game_id) {
    // Nettoyer le buffer avant de rediriger
    ob_end_clean();
    header('Location: /game/play.php');
    exit;
}

// Récupérer les données de la partie
$gameController = new GameController();
$gameData = $gameController->getGame($game_id);

// Vérifier si la partie existe et si l'utilisateur est autorisé à y accéder
if (!$gameData['success'] || 
    ($gameData['game']['player1_id'] != Session::getUserId() && 
     $gameData['game']['player2_id'] != Session::getUserId())) {
    // Nettoyer le buffer avant de rediriger
    ob_end_clean();
    header('Location: /game/play.php');
    exit;
}

// Déterminer si l'utilisateur est le joueur 1 ou 2
$isPlayer1 = $gameData['game']['player1_id'] == Session::getUserId();
$currentUserId = Session::getUserId();
$user_number = $isPlayer1 ? 1 : 2;
$opponent_number = $isPlayer1 ? 2 : 1;

// Déterminer si c'est au tour de l'utilisateur
$isUserTurn = $gameData['game']['current_player'] == $user_number;

// Récupérer les informations sur l'adversaire
$opponentId = $isPlayer1 ? $gameData['game']['player2_id'] : $gameData['game']['player1_id'];
$opponentIsBot = $opponentId === 0; // ID 0 indique un bot

// Récupérer l'état du plateau
$boardState = json_decode($gameData['game']['board_state'], true);

$pageTitle = "Partie #" . $game_id . " - " . APP_NAME;
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row gap-8">
        <!-- Panneau d'information -->
        <div class="w-full md:w-1/4">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-indigo-600 mb-4">Informations</h2>
                
                <div class="mb-4">
                    <h3 class="font-semibold text-gray-700">Partie #<?php echo $game_id; ?></h3>
                    <p class="text-gray-600">
                        Statut: 
                        <span class="font-medium <?php 
                            echo $gameData['game']['status'] === 'in_progress' ? 'text-green-600' : 
                                 ($gameData['game']['status'] === 'finished' ? 'text-red-600' : 'text-yellow-600'); 
                        ?>">
                            <?php 
                                echo $gameData['game']['status'] === 'in_progress' ? 'En cours' : 
                                     ($gameData['game']['status'] === 'finished' ? 'Terminée' : 'En attente'); 
                            ?>
                        </span>
                    </p>
                </div>
                
                <div class="mb-4">
                    <h3 class="font-semibold text-gray-700">Joueurs</h3>
                    <div class="flex items-center justify-between mt-2 p-2 bg-indigo-50 rounded">
                        <span class="flex items-center">
                            <span class="w-4 h-4 bg-black rounded-full mr-2"></span>
                            <span class="font-medium"><?php echo $isPlayer1 ? 'Vous' : ($opponentIsBot ? 'IA' : 'Adversaire'); ?></span>
                        </span>
                        <span class="text-xs bg-indigo-200 px-2 py-1 rounded">Joueur 1</span>
                    </div>
                    <div class="flex items-center justify-between mt-2 p-2 bg-indigo-50 rounded">
                        <span class="flex items-center">
                            <span class="w-4 h-4 bg-white border border-gray-300 rounded-full mr-2"></span>
                            <span class="font-medium"><?php echo !$isPlayer1 ? 'Vous' : ($opponentIsBot ? 'IA' : 'Adversaire'); ?></span>
                        </span>
                        <span class="text-xs bg-indigo-200 px-2 py-1 rounded">Joueur 2</span>
                    </div>
                </div>
                
                <div id="game-status" class="mb-4">
                    <h3 class="font-semibold text-gray-700">Tour actuel</h3>
                    <p class="mt-2 p-2 bg-indigo-50 rounded text-center font-medium">
                        <?php if ($gameData['game']['status'] === 'finished'): ?>
                            Partie terminée
                        <?php else: ?>
                            <?php if ($isUserTurn): ?>
                                <span class="text-green-600">À vous de jouer</span>
                            <?php else: ?>
                                <span class="text-orange-600">Au tour de l'adversaire</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-indigo-600 mb-4">Actions</h2>
                
                <div class="space-y-3">
                    <a href="/game/play.php" class="block w-full py-2 px-4 bg-indigo-100 text-indigo-700 text-center rounded hover:bg-indigo-200 transition">
                        Retour à mes parties
                    </a>
                    <?php if ($gameData['game']['status'] !== 'finished'): ?>
                        <button id="resign-button" class="block w-full py-2 px-4 bg-red-100 text-red-700 text-center rounded hover:bg-red-200 transition">
                            Abandonner la partie
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Plateau de jeu -->
        <div class="w-full md:w-3/4">
            <div class="bg-white rounded-lg shadow-md p-6" id="game-container">
                <h2 class="text-xl font-bold text-indigo-600 mb-4">Plateau de jeu</h2>
                
                <div class="mb-4 p-3 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700" id="game-message">
                    <?php if ($gameData['game']['status'] === 'finished'): ?>
                        La partie est terminée. 
                        <?php if ($gameData['game']['winner_id'] == $currentUserId): ?>
                            Vous avez gagné !
                        <?php elseif ($gameData['game']['winner_id'] == null): ?>
                            Match nul !
                        <?php else: ?>
                            Vous avez perdu.
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($isUserTurn): ?>
                            C'est à votre tour. Sélectionnez une pièce pour la déplacer.
                        <?php else: ?>
                            C'est au tour de l'adversaire. Veuillez patienter...
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Plateau de jeu -->
                <div class="relative mx-auto" style="width: 100%; max-width: 640px;">
                    <div class="aspect-w-1 aspect-h-1 w-full">
                        <div id="checkerboard" class="grid grid-cols-8 grid-rows-8 border-2 border-gray-800 select-none">
                            <!-- Le plateau sera généré par JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Données du jeu
    const gameId = <?php echo $game_id; ?>;
    const isUserTurn = <?php echo $isUserTurn ? 'true' : 'false'; ?>;
    const isPlayer1 = <?php echo $isPlayer1 ? 'true' : 'false'; ?>;
    const gameStatus = "<?php echo $gameData['game']['status']; ?>";
    const opponentIsBot = <?php echo $opponentIsBot ? 'true' : 'false'; ?>;
    let boardState = <?php echo $gameData['game']['board_state']; ?>;
    let selectedPiece = null;
    let possibleMoves = [];
    
    // Éléments DOM
    const checkerboard = document.getElementById('checkerboard');
    const gameMessage = document.getElementById('game-message');
    const gameStatus_el = document.getElementById('game-status');
    const resignButton = document.getElementById('resign-button');
    
    // Fonction pour initialiser le plateau
    function initBoard() {
        checkerboard.innerHTML = '';
        
        for (let row = 0; row < 8; row++) {
            for (let col = 0; col < 8; col++) {
                const isBlackSquare = (row + col) % 2 === 1;
                const square = document.createElement('div');
                square.className = `square ${isBlackSquare ? 'bg-gray-800' : 'bg-gray-200'} relative`;
                square.dataset.row = row;
                square.dataset.col = col;
                
                // Ajouter un événement de clic pour les cases
                square.addEventListener('click', function() {
                    handleSquareClick(row, col);
                });
                
                checkerboard.appendChild(square);
                
                // Ajouter une pièce si nécessaire
                if (isBlackSquare && boardState[row] && boardState[row][col]) {
                    const piece = boardState[row][col];
                    if (piece) {
                        addPiece(row, col, piece.player, piece.type === 'king');
                    }
                }
            }
        }
    }
    
    // Fonction pour ajouter une pièce au plateau
    function addPiece(row, col, player, isKing = false) {
        const square = getSquare(row, col);
        if (!square) return;
        
        const piece = document.createElement('div');
        piece.className = `piece absolute inset-0 m-auto rounded-full border-2 ${player === 1 ? 'bg-black border-gray-600' : 'bg-white border-gray-300'} w-4/5 h-4/5 transform transition-transform`;
        piece.dataset.player = player;
        
        // Ajouter une couronne pour les dames
        if (isKing) {
            const crown = document.createElement('div');
            crown.className = `absolute inset-0 flex items-center justify-center text-${player === 1 ? 'white' : 'black'} font-bold text-lg`;
            crown.textContent = '♔';
            piece.appendChild(crown);
        }
        
        square.appendChild(piece);
    }
    
    // Fonction pour obtenir une case à partir des coordonnées
    function getSquare(row, col) {
        return document.querySelector(`.square[data-row="${row}"][data-col="${col}"]`);
    }
    
    // Fonction pour gérer le clic sur une case
    function handleSquareClick(row, col) {
        // Si le jeu est terminé ou ce n'est pas le tour du joueur, ne rien faire
        if (gameStatus === 'finished' || !isUserTurn) return;
        
        const square = getSquare(row, col);
        const piece = square.querySelector('.piece');
        
        // Si aucune pièce n'est sélectionnée et qu'il y a une pièce sur la case
        if (!selectedPiece && piece) {
            const piecePlayer = parseInt(piece.dataset.player);
            const isUserPiece = (isPlayer1 && piecePlayer === 1) || (!isPlayer1 && piecePlayer === 2);
            
            // Vérifier si c'est une pièce du joueur
            if (isUserPiece) {
                selectPiece(row, col);
            }
        } 
        // Si une pièce est sélectionnée et que la case cliquée est une destination possible
        else if (selectedPiece) {
            const fromRow = parseInt(selectedPiece.dataset.row);
            const fromCol = parseInt(selectedPiece.dataset.col);
            
            // Vérifier si c'est un mouvement valide
            const validMove = possibleMoves.find(move => 
                move.toRow === row && move.toCol === col
            );
            
            if (validMove) {
                makeMove(fromRow, fromCol, row, col);
            } else {
                // Désélectionner si on clique ailleurs
                unselectPiece();
            }
        }
    }
    
    // Fonction pour sélectionner une pièce
    function selectPiece(row, col) {
        // Désélectionner la pièce précédente si elle existe
        unselectPiece();
        
        const square = getSquare(row, col);
        const piece = square.querySelector('.piece');
        
        // Marquer la pièce comme sélectionnée
        piece.classList.add('ring-4', 'ring-yellow-400', 'scale-110', 'z-10');
        selectedPiece = { element: piece, dataset: { row, col } };
        
        // Calculer et afficher les mouvements possibles
        calculatePossibleMoves(row, col);
    }
    
    // Fonction pour désélectionner une pièce
    function unselectPiece() {
        if (selectedPiece) {
            selectedPiece.element.classList.remove('ring-4', 'ring-yellow-400', 'scale-110', 'z-10');
            selectedPiece = null;
            
            // Supprimer les indicateurs de mouvement possible
            document.querySelectorAll('.move-indicator').forEach(indicator => {
                indicator.remove();
            });
            
            possibleMoves = [];
        }
    }
    
    // Fonction pour calculer les mouvements possibles
    function calculatePossibleMoves(row, col) {
        const piecePlayer = parseInt(selectedPiece.element.dataset.player);
        const isKing = selectedPiece.element.querySelector('.crown') !== null;
        
        // Direction du mouvement selon le joueur
        const directions = isKing ? [
            { dr: -1, dc: -1 }, { dr: -1, dc: 1 }, { dr: 1, dc: -1 }, { dr: 1, dc: 1 }
        ] : (
            piecePlayer === 1 ? 
            [{ dr: 1, dc: -1 }, { dr: 1, dc: 1 }] :  // Pion noir (vers le bas)
            [{ dr: -1, dc: -1 }, { dr: -1, dc: 1 }]  // Pion blanc (vers le haut)
        );
        
        // Récupérer les mouvements possibles via l'API
        fetch(`/api/game/status.php?game_id=${gameId}&check_moves=1&from_row=${row}&from_col=${col}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.possible_moves) {
                    possibleMoves = data.possible_moves;
                    
                    // Afficher les mouvements possibles
                    possibleMoves.forEach(move => {
                        const targetSquare = getSquare(move.toRow, move.toCol);
                        const indicator = document.createElement('div');
                        indicator.className = 'move-indicator absolute inset-0 m-auto w-1/4 h-1/4 bg-green-500 rounded-full opacity-60 z-5 cursor-pointer';
                        targetSquare.appendChild(indicator);
                    });
                }
            })
            .catch(error => {
                console.error('Erreur lors de la récupération des mouvements possibles:', error);
            });
    }
    
    // Fonction pour effectuer un mouvement
    function makeMove(fromRow, fromCol, toRow, toCol) {
        // Désactiver les interactions pendant l'envoi
        document.body.classList.add('cursor-wait');
        gameMessage.textContent = "Traitement de votre mouvement...";
        
        // Envoyer le mouvement au serveur
        fetch('/api/game/move.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                game_id: gameId,
                from_row: fromRow,
                from_col: fromCol,
                to_row: toRow,
                to_col: toCol
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour le plateau
                boardState = data.board_state;
                updateBoard();
                
                // Mettre à jour les messages et statuts
                gameMessage.textContent = data.message || "Mouvement effectué avec succès.";
                
                // Si c'est une partie contre un bot, attendre la réponse du bot
                if (opponentIsBot && data.game_status === 'in_progress') {
                    gameMessage.textContent = "L'IA réfléchit à son prochain coup...";
                    
                    // Attendre un peu puis récupérer l'état du jeu (mouvement du bot)
                    setTimeout(() => {
                        checkGameStatus();
                    }, 1000);
                }
                
                // Si le jeu est terminé
                if (data.game_status === 'finished') {
                    handleGameOver(data.winner_id);
                }
            } else {
                gameMessage.textContent = data.message || "Erreur lors de l'exécution du mouvement.";
                unselectPiece();
            }
        })
        .catch(error => {
            console.error('Erreur lors de l\'envoi du mouvement:', error);
            gameMessage.textContent = "Erreur de connexion. Veuillez réessayer.";
            unselectPiece();
        })
        .finally(() => {
            document.body.classList.remove('cursor-wait');
        });
    }
    
    // Fonction pour vérifier l'état du jeu
    function checkGameStatus() {
        fetch(`/api/game/status.php?game_id=${gameId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mettre à jour le plateau si nécessaire
                    if (JSON.stringify(boardState) !== JSON.stringify(data.game.board_state)) {
                        boardState = data.game.board_state;
                        updateBoard();
                    }
                    
                    // Mettre à jour le statut du tour
                    const newIsUserTurn = (data.game.current_player === (isPlayer1 ? 1 : 2));
                    if (newIsUserTurn !== isUserTurn) {
                        gameStatus_el.innerHTML = `
                            <h3 class="font-semibold text-gray-700">Tour actuel</h3>
                            <p class="mt-2 p-2 bg-indigo-50 rounded text-center font-medium">
                                <span class="text-green-600">À vous de jouer</span>
                            </p>
                        `;
                        gameMessage.textContent = "C'est à votre tour. Sélectionnez une pièce pour la déplacer.";
                        window.location.reload(); // Recharger pour mettre à jour tous les états
                    }
                    
                    // Si le jeu est terminé
                    if (data.game.status === 'finished') {
                        handleGameOver(data.game.winner_id);
                    }
                }
            })
            .catch(error => {
                console.error('Erreur lors de la vérification de l\'état du jeu:', error);
            });
    }
    
    // Fonction pour mettre à jour le plateau
    function updateBoard() {
        // Supprimer toutes les pièces
        document.querySelectorAll('.piece').forEach(piece => {
            piece.remove();
        });
        
        // Ajouter les pièces selon l'état actuel
        for (let row = 0; row < 8; row++) {
            for (let col = 0; col < 8; col++) {
                if (boardState[row] && boardState[row][col]) {
                    const piece = boardState[row][col];
                    if (piece) {
                        addPiece(row, col, piece.player, piece.type === 'king');
                    }
                }
            }
        }
        
        // Désélectionner toute pièce
        unselectPiece();
    }
    
    // Fonction pour gérer la fin de partie
    function handleGameOver(winnerId) {
        const currentUserId = <?php echo $currentUserId; ?>;
        
        if (winnerId === currentUserId) {
            gameMessage.textContent = "Félicitations ! Vous avez gagné la partie !";
            gameMessage.className = "mb-4 p-3 bg-green-50 border-l-4 border-green-400 text-green-700";
        } else if (winnerId === null) {
            gameMessage.textContent = "La partie s'est terminée par un match nul.";
            gameMessage.className = "mb-4 p-3 bg-blue-50 border-l-4 border-blue-400 text-blue-700";
        } else {
            gameMessage.textContent = "Vous avez perdu la partie.";
            gameMessage.className = "mb-4 p-3 bg-red-50 border-l-4 border-red-400 text-red-700";
        }
        
        gameStatus_el.innerHTML = `
            <h3 class="font-semibold text-gray-700">Statut</h3>
            <p class="mt-2 p-2 bg-indigo-50 rounded text-center font-medium">
                <span class="text-red-600">Partie terminée</span>
            </p>
        `;
    }
    
    // Fonction pour abandonner la partie
    function resignGame() {
        if (confirm("Êtes-vous sûr de vouloir abandonner cette partie ? Cette action est irréversible.")) {
            fetch('/api/game/move.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    game_id: gameId,
                    resign: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || "Erreur lors de l'abandon de la partie.");
                }
            })
            .catch(error => {
                console.error('Erreur lors de l\'abandon de la partie:', error);
                alert("Erreur de connexion. Veuillez réessayer.");
            });
        }
    }
    
    // Attacher l'événement au bouton d'abandon
    if (resignButton) {
        resignButton.addEventListener('click', resignGame);
    }
    
    // Vérifier périodiquement l'état du jeu si ce n'est pas le tour de l'utilisateur
    if (!isUserTurn && gameStatus === 'in_progress') {
        const checkInterval = setInterval(() => {
            checkGameStatus();
            
            // Arrêter la vérification si la page est fermée
            window.addEventListener('beforeunload', () => {
                clearInterval(checkInterval);
            });
        }, 5000); // Vérifier toutes les 5 secondes
    }
    
    // Initialiser le plateau
    initBoard();
});
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>