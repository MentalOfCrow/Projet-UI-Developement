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

// Récupérer l'ID de l'utilisateur et de la partie
$user_id = Session::getUserId();
$username = Session::getUsername() ? Session::getUsername() : 'Joueur';
$game_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$game_id) {
    // Nettoyer le buffer avant de rediriger
    ob_end_clean();
    header('Location: /game/history.php');
    exit;
}

// Récupérer les informations de la partie
$gameController = new GameController();
$gameResult = $gameController->getGame($game_id, true); // true pour inclure l'historique des mouvements

if (!$gameResult['success'] || $gameResult['game']['status'] !== 'finished') {
    // Nettoyer le buffer avant de rediriger
    ob_end_clean();
    header('Location: /game/history.php');
    exit;
}

$game = $gameResult['game'];
$moves = isset($game['moves']) ? $game['moves'] : [];

// Vérifier que l'utilisateur a participé à cette partie
if ($game['player1_id'] != $user_id && $game['player2_id'] != $user_id) {
    // Nettoyer le buffer avant de rediriger
    ob_end_clean();
    header('Location: /game/history.php');
    exit;
}

// Déterminer si l'utilisateur est le joueur 1 ou 2
$isPlayer1 = $game['player1_id'] == $user_id;

// Déterminer l'adversaire
$opponentName = $isPlayer1 ? $game['player2_name'] : $game['player1_name'];
if ($game['player2_id'] === '0' || $game['player2_id'] === 0) {
    $opponentName = 'Intelligence Artificielle';
}

// Déterminer le résultat
$resultClass = "bg-blue-100 text-blue-800"; // Match nul par défaut
$resultText = "Match nul";
$resultIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';

if ($game['winner_id'] !== null) {
    if ($game['winner_id'] == $user_id) {
        $resultClass = "bg-green-100 text-green-800";
        $resultText = "Victoire";
        $resultIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
    } else {
        $resultClass = "bg-red-100 text-red-800";
        $resultText = "Défaite";
        $resultIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
    }
}

// Formater les dates
$created_at = new DateTime($game['created_at']);
$updated_at = new DateTime($game['updated_at']);
$formattedCreatedDate = $created_at->format('d/m/Y H:i');
$formattedUpdatedDate = $updated_at->format('d/m/Y H:i');
$duration = $created_at->diff($updated_at)->format('%H:%I:%S');

$pageTitle = "Replay de la partie #" . $game_id . " - " . APP_NAME;
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<link rel="stylesheet" href="/assets/css/style.css">

<div class="container mx-auto px-4 py-8">
    <h2 class="text-2xl font-bold mb-4">Replay de la partie #<?php echo $game_id; ?></h2>
    
    <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Informations de la partie</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">Détails et résultat</p>
        </div>
        <div class="border-t border-gray-200">
            <dl>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Résultat</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <span class="px-3 py-1 inline-flex items-center text-sm leading-5 font-semibold rounded-full <?php echo $resultClass; ?>">
                            <?php echo $resultIcon; ?>
                            <?php echo $resultText; ?>
                        </span>
                    </dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Adversaire</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <?php echo htmlspecialchars($opponentName); ?>
                    </dd>
                </div>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Date de début</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <?php echo date('d/m/Y H:i', strtotime($game['created_at'])); ?>
                    </dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Date de fin</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <?php echo date('d/m/Y H:i', strtotime($game['updated_at'])); ?>
                    </dd>
                </div>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Durée</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <?php echo $duration; ?>
                    </dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Nombre de coups</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <?php echo count($moves); ?>
                    </dd>
                </div>
            </dl>
        </div>
    </div>
    
    <div class="flex flex-col md:flex-row gap-6">
        <!-- Plateau de jeu -->
        <div class="flex-1">
            <div class="bg-white shadow rounded-lg p-4 mb-4">
                <h3 class="text-lg font-medium mb-4">Plateau de jeu</h3>
                <div id="game-board" class="w-full max-w-md mx-auto"></div>
            </div>
        </div>
        
        <!-- Contrôles de replay -->
        <div class="w-full md:w-64">
            <div class="bg-white shadow rounded-lg p-4 mb-4">
                <h3 class="text-lg font-medium mb-4">Contrôles</h3>
                
                <div class="flex flex-col space-y-4">
                    <div class="flex items-center justify-between space-x-2">
                        <button id="btn-first" class="p-2 rounded hover:bg-gray-100 transition-colors duration-200" title="Premier mouvement">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                            </svg>
                        </button>
                        <button id="btn-prev" class="p-2 rounded hover:bg-gray-100 transition-colors duration-200" title="Mouvement précédent">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <button id="btn-play" class="p-2 rounded hover:bg-gray-100 transition-colors duration-200 flex-grow bg-blue-50" title="Lecture/Pause">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>
                        <button id="btn-next" class="p-2 rounded hover:bg-gray-100 transition-colors duration-200" title="Mouvement suivant">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <button id="btn-last" class="p-2 rounded hover:bg-gray-100 transition-colors duration-200" title="Dernier mouvement">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                    
                    <div class="text-center text-sm bg-gray-50 py-2 rounded">
                        Coup <span id="current-move" class="font-bold">0</span> / <span id="total-moves" class="font-bold">0</span>
                    </div>
                    
                    <div class="flex flex-col space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Vitesse:</span>
                            <span class="text-sm font-medium bg-blue-50 px-2 py-1 rounded">x<span id="speed-value">1</span></span>
                        </div>
                        <input type="range" id="replay-speed" min="0.5" max="3" step="0.5" value="1" class="w-full accent-blue-600">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Coups joués -->
    <div class="bg-white shadow rounded-lg p-4 mt-6">
        <h3 class="text-lg font-medium mb-4">Historique des coups</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N°</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joueur</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">De</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vers</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="moves-list">
                    <!-- Les coups seront ajoutés dynamiquement ici -->
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-6">
        <a href="/game/history.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
            </svg>
            Retour à l'historique
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration du plateau
    const boardSize = 8;
    const cellSize = 50;
    const boardElement = document.getElementById('game-board');
    
    // État du replay
    let currentMoveIndex = 0;
    let isPlaying = false;
    let playInterval = null;
    let speed = 1; // secondes
    let moves = [];
    let gameInfo = {};
    
    // Éléments DOM
    const btnFirst = document.getElementById('btn-first');
    const btnPrev = document.getElementById('btn-prev');
    const btnPlay = document.getElementById('btn-play');
    const btnNext = document.getElementById('btn-next');
    const btnLast = document.getElementById('btn-last');
    const currentMoveElement = document.getElementById('current-move');
    const totalMovesElement = document.getElementById('total-moves');
    const replaySpeedInput = document.getElementById('replay-speed');
    const speedValueElement = document.getElementById('speed-value');
    const movesList = document.getElementById('moves-list');
    
    // Plateau de jeu
    const board = [];
    
    // Changer la valeur affichée de la vitesse quand on ajuste le curseur
    replaySpeedInput.addEventListener('input', function() {
        speedValueElement.textContent = this.value;
    });
    
    // Charger les mouvements depuis l'API
    function loadGameMoves() {
        fetch(`/api/game/get_game_moves.php?game_id=<?php echo $game_id; ?>`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    gameInfo = data.game;
                    moves = data.moves;
                    
                    // Initialiser le plateau et l'affichage
                    initBoard();
                    generateMovesList();
                    totalMovesElement.textContent = moves.length;
                    currentMoveElement.textContent = "0";
                    
                    // Appliquer le mouvement initial (position de départ)
                    applyMove(0);
                } else {
                    console.error('Erreur lors du chargement des mouvements:', data.message);
                    alert('Erreur lors du chargement des mouvements: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur lors de la requête API:', error);
                alert('Erreur de connexion au serveur. Veuillez réessayer plus tard.');
            });
    }
    
    // Initialisation du plateau
    function initBoard() {
        boardElement.innerHTML = '';
        boardElement.style.width = `${boardSize * cellSize}px`;
        boardElement.style.height = `${boardSize * cellSize}px`;
        boardElement.style.display = 'grid';
        boardElement.style.gridTemplateColumns = `repeat(${boardSize}, ${cellSize}px)`;
        boardElement.style.gridTemplateRows = `repeat(${boardSize}, ${cellSize}px)`;
        
        // Créer les cellules du plateau
        for (let row = 0; row < boardSize; row++) {
            board[row] = [];
            for (let col = 0; col < boardSize; col++) {
                const cell = document.createElement('div');
                cell.className = `cell ${(row + col) % 2 === 0 ? 'bg-gray-200' : 'bg-gray-700'}`;
                cell.dataset.row = row;
                cell.dataset.col = col;
                
                // Style de la cellule
                cell.style.width = `${cellSize}px`;
                cell.style.height = `${cellSize}px`;
                cell.style.display = 'flex';
                cell.style.alignItems = 'center';
                cell.style.justifyContent = 'center';
                
                board[row][col] = {
                    element: cell,
                    piece: null
                };
                
                boardElement.appendChild(cell);
            }
        }
        
        // Position initiale des pièces
        setupInitialPosition();
    }
    
    // Configuration de la position initiale
    function setupInitialPosition() {
        for (let row = 0; row < boardSize; row++) {
            for (let col = 0; col < boardSize; col++) {
                // Cellules noires uniquement
                if ((row + col) % 2 !== 0) {
                    // Pièces noires (rangées 0-2)
                    if (row < 3) {
                        addPiece(row, col, 'black');
                    }
                    // Pièces blanches (rangées 5-7)
                    else if (row > 4) {
                        addPiece(row, col, 'white');
                    }
                }
            }
        }
    }
    
    // Ajouter une pièce
    function addPiece(row, col, color, isKing = false) {
        const cell = board[row][col];
        
        // Supprimer la pièce existante si nécessaire
        if (cell.piece) {
            cell.element.removeChild(cell.piece.element);
            cell.piece = null;
        }
        
        // Créer la nouvelle pièce
        const pieceElement = document.createElement('div');
        pieceElement.className = `piece ${color}`;
        pieceElement.style.width = `${cellSize * 0.8}px`;
        pieceElement.style.height = `${cellSize * 0.8}px`;
        pieceElement.style.borderRadius = '50%';
        pieceElement.style.backgroundColor = color;
        pieceElement.style.border = '2px solid #333';
        pieceElement.style.boxShadow = '0 3px 5px rgba(0,0,0,0.3)';
        
        // Marquer comme roi si nécessaire
        if (isKing) {
            const crownElement = document.createElement('div');
            crownElement.className = 'crown';
            crownElement.style.textAlign = 'center';
            crownElement.style.color = color === 'black' ? 'white' : 'black';
            crownElement.style.fontWeight = 'bold';
            crownElement.innerHTML = '&#9813;'; // Symbole de couronne
            pieceElement.appendChild(crownElement);
        }
        
        cell.element.appendChild(pieceElement);
        cell.piece = { element: pieceElement, color, isKing };
    }
    
    // Déplacer une pièce
    function movePiece(fromRow, fromCol, toRow, toCol) {
        const fromCell = board[fromRow][fromCol];
        const toCell = board[toRow][toCol];
        
        if (!fromCell.piece) {
            console.error('Aucune pièce à déplacer');
            return;
        }
        
        // Ajouter la pièce à la nouvelle position
        addPiece(toRow, toCol, fromCell.piece.color, fromCell.piece.isKing);
        
        // Supprimer la pièce de l'ancienne position
        fromCell.element.removeChild(fromCell.piece.element);
        fromCell.piece = null;
    }
    
    // Appliquer un mouvement
    function applyMove(moveIndex) {
        // Réinitialiser le plateau
        initBoard();
        
        // S'il n'y a pas de mouvements, sortir
        if (moves.length === 0) return;
        
        // Appliquer tous les mouvements jusqu'à l'index actuel
        for (let i = 0; i <= moveIndex && i < moves.length; i++) {
            const move = moves[i];
            
            const fromRow = parseInt(move.from_row);
            const fromCol = parseInt(move.from_col);
            const toRow = parseInt(move.to_row);
            const toCol = parseInt(move.to_col);
            
            // Supprimer la pièce capturée si nécessaire
            if (move.is_capture && move.captured_pieces && move.captured_pieces.length > 0) {
                move.captured_pieces.forEach(capturedPiece => {
                    const capturedRow = parseInt(capturedPiece.row);
                    const capturedCol = parseInt(capturedPiece.col);
                    
                    if (board[capturedRow][capturedCol].piece) {
                        board[capturedRow][capturedCol].element.removeChild(board[capturedRow][capturedCol].piece.element);
                        board[capturedRow][capturedCol].piece = null;
                    }
                });
            } else if (move.is_capture) {
                // Fallback simple si pas de détails sur les pièces capturées
                const capturedRow = Math.floor((fromRow + toRow) / 2);
                const capturedCol = Math.floor((fromCol + toCol) / 2);
                
                if (board[capturedRow][capturedCol].piece) {
                    board[capturedRow][capturedCol].element.removeChild(board[capturedRow][capturedCol].piece.element);
                    board[capturedRow][capturedCol].piece = null;
                }
            }
            
            // Déplacer la pièce
            movePiece(fromRow, fromCol, toRow, toCol);
            
            // Vérifier si la pièce devient un roi
            if ((move.color === 'white' && toRow === 0) || (move.color === 'black' && toRow === 7)) {
                board[toRow][toCol].piece.isKing = true;
                const crownElement = document.createElement('div');
                crownElement.className = 'crown';
                crownElement.style.textAlign = 'center';
                crownElement.style.color = move.color === 'black' ? 'white' : 'black';
                crownElement.style.fontWeight = 'bold';
                crownElement.innerHTML = '&#9813;'; // Symbole de couronne
                board[toRow][toCol].piece.element.appendChild(crownElement);
            }
        }
        
        // Mettre à jour l'affichage du mouvement actuel
        currentMoveElement.textContent = moveIndex;
        
        // Mettre en évidence le mouvement dans la liste
        const moveRows = movesList.querySelectorAll('tr');
        moveRows.forEach((row, index) => {
            if (index === moveIndex && index > 0) {
                row.classList.add('bg-blue-100');
            } else {
                row.classList.remove('bg-blue-100');
            }
        });
    }
    
    // Générer la liste des mouvements
    function generateMovesList() {
        movesList.innerHTML = '';
        
        // Ajouter la position initiale comme "mouvement 0"
        const initialRow = document.createElement('tr');
        initialRow.className = 'hover:bg-gray-50 cursor-pointer';
        initialRow.dataset.index = 0;
        
        initialRow.innerHTML = `
            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">0</td>
            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-900">Position initiale</td>
            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">-</td>
            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">-</td>
        `;
        
        initialRow.addEventListener('click', () => {
            currentMoveIndex = 0;
            applyMove(currentMoveIndex);
            stopPlayback();
        });
        
        movesList.appendChild(initialRow);
        
        // Ajouter chaque mouvement
        moves.forEach((move, index) => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 cursor-pointer';
            row.dataset.index = index + 1;
            
            row.innerHTML = `
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">${index + 1}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-900">${move.player_name}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">${move.from_row},${move.from_col}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">${move.to_row},${move.to_col}</td>
            `;
            
            row.addEventListener('click', () => {
                currentMoveIndex = index + 1;
                applyMove(currentMoveIndex);
                stopPlayback();
            });
            
            movesList.appendChild(row);
        });
    }
    
    // Contrôle du replay
    function startPlayback() {
        isPlaying = true;
        btnPlay.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        `;
        
        // Récupérer la vitesse actuelle depuis le slider (0.5 à 3)
        const currentSpeed = parseFloat(replaySpeedInput.value);
        
        // Calculer l'intervalle en millisecondes
        // - Un intervalle plus petit = replay plus rapide
        // - La vitesse de base (speed=1) correspond à 1000ms (1 seconde par coup)
        // - Quand currentSpeed = 2, l'intervalle est de 500ms (2 coups par seconde)
        const interval = Math.round(1000 / currentSpeed);
        
        // Lancer l'intervalle pour avancer dans les coups automatiquement
        playInterval = setInterval(() => {
            if (currentMoveIndex < moves.length) {
                currentMoveIndex++;
                applyMove(currentMoveIndex);
            } else {
                stopPlayback();
            }
        }, interval);
    }
    
    function stopPlayback() {
        isPlaying = false;
        btnPlay.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        `;
        
        if (playInterval) {
            clearInterval(playInterval);
            playInterval = null;
        }
    }
    
    // Événements des boutons
    btnFirst.addEventListener('click', () => {
        currentMoveIndex = 0;
        applyMove(currentMoveIndex);
        stopPlayback();
    });
    
    btnPrev.addEventListener('click', () => {
        if (currentMoveIndex > 0) {
            currentMoveIndex--;
            applyMove(currentMoveIndex);
        }
        stopPlayback();
    });
    
    btnPlay.addEventListener('click', () => {
        if (isPlaying) {
            stopPlayback();
        } else {
            if (currentMoveIndex >= moves.length) {
                currentMoveIndex = 0;
                applyMove(currentMoveIndex);
            }
            startPlayback();
        }
    });
    
    btnNext.addEventListener('click', () => {
        if (currentMoveIndex < moves.length) {
            currentMoveIndex++;
            applyMove(currentMoveIndex);
        }
        stopPlayback();
    });
    
    btnLast.addEventListener('click', () => {
        currentMoveIndex = moves.length;
        applyMove(currentMoveIndex);
        stopPlayback();
    });
    
    // Contrôle de la vitesse
    replaySpeedInput.addEventListener('change', () => {
        speed = 1;
        if (isPlaying) {
            stopPlayback();
            startPlayback();
        }
    });
    
    // Charger les données du jeu
    loadGameMoves();
});
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?> 