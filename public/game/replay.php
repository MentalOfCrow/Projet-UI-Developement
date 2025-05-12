<?php
// -----------------------------------------------------------------------------
//  REPLAY.PHP – VERSION MANUELLE
// -----------------------------------------------------------------------------
//  • Affiche l'état initial du plateau avant tout mouvement
//  • Permet de visualiser manuellement chaque coup (cliquer sur un coup pour le voir)
//  • Style visuel identique à board.php
//  • Plateau dézoomer pour voir l'ensemble du jeu
//  • Affichage correct des résultats (Victoire/Défaite/Match nul)
// -----------------------------------------------------------------------------

use function htmlspecialchars as h;

ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/includes/session.php';
require_once __DIR__ . '/../../backend/db/JsonDatabase.php';
require_once __DIR__ . '/../../backend/controllers/GameController.php';
require_once __DIR__ . '/../../backend/controllers/ProfileController.php';

// Auth
if (!Session::isLoggedIn()) {
    header('Location: /auth/login.php');
    exit;
}
$userId = Session::getUserId();
(new ProfileController())->updateActivity();

// Game ID
$gameId = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
if ($gameId <= 0) {
    header('Location: /game/history.php');
    exit;
}

$jsonDb = JsonDatabase::getInstance();
$game   = $jsonDb->getGameById($gameId);
$moves  = [];

if ($game === null) {
    // Fallback MySQL puis ré-export JSON pour le prochain appel
    $gc = new GameController();
    $res = $gc->getGame($gameId, true);
    if (!$res['success']) {
    header('Location: /game/history.php');
    exit;
}
    $game  = $res['game'];
    $moves = $game['moves'] ?? [];
    $jsonDb->saveGame($game); // persiste
} else {
    $moves = $game['moves'] ?? [];
}

// Sécurité : l'utilisateur doit être joueur 1 ou 2
if ($game['player1_id'] != $userId && $game['player2_id'] != $userId) {
    header('Location: /game/history.php');
    exit;
}

// Normalisation
if ($game['status'] !== 'finished') {
    $game['status'] = 'finished';
}
if (!isset($game['result'])) {
    if ($game['winner_id'] === null) {
        $game['result'] = 'draw';
    } elseif ($game['winner_id'] == $game['player1_id']) {
        $game['result'] = 'player1_won';
    } else {
        $game['result'] = 'player2_won';
    }
}

$player1Name = $game['player1_name'] ?? 'Joueur 1';
$player2Name = $game['player2_name'] ?? (($game['player2_id'] == 0) ? 'IA' : 'Joueur 2');
$isPlayer1   = ($game['player1_id'] == $userId);

// Décodage board_state
$initialBoard = [];
if (!empty($game['board_state'])) {
    $initialBoard = json_decode($game['board_state'], true) ?: [];
}
// Plateau par défaut si vide OU si des coups existent (pour garantir l'état initial)
if (empty($initialBoard) || count($moves) > 0) {
    $initialBoard = [];
    for ($r = 0; $r < 8; $r++) {
        for ($c = 0; $c < 8; $c++) {
            if (($r + $c) % 2 == 1) {
                if ($r < 3)      $initialBoard[$r][$c] = ['type' => 'pawn', 'player' => 1];
                elseif ($r > 4)  $initialBoard[$r][$c] = ['type' => 'pawn', 'player' => 2];
                else             $initialBoard[$r][$c] = null;
        } else {
                $initialBoard[$r][$c] = null;
}
        }
    }
}

$pageTitle = "Replay de la partie #{$gameId}";
include __DIR__ . '/../../backend/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-6">
    <h1 class="text-3xl font-bold text-purple-900 mb-4">Replay de la partie #<?= h($gameId) ?></h1>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Colonne plateau -->
        <div class="lg:col-span-2 flex flex-col items-center">
            <!-- Panneau d'information du haut -->
            <div class="w-full bg-white rounded-t-xl shadow p-4 mb-1 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-6 h-6 rounded-full bg-black mr-2"></div>
                    <span class="font-medium"><?= h($player1Name) ?></span>
                </div>
                <div class="font-bold">VS</div>
                <div class="flex items-center">
                    <span class="font-medium"><?= h($player2Name) ?></span>
                    <div class="w-6 h-6 rounded-full bg-white border-2 border-black ml-2"></div>
                </div>
                        </div>
            
            <!-- Plateau -->
            <div class="w-full bg-white rounded-b-xl shadow p-4 flex flex-col items-center">
                <div id="board-container" class="w-full aspect-square max-w-md mx-auto relative mb-4">
                    <canvas id="boardCanvas" width="400" height="400" class="w-full h-full rounded shadow"></canvas>
                    
                    <!-- Affichage du tour actuel -->
                    <div id="move-indicator" class="absolute bottom-2 right-2 bg-white/80 px-3 py-1 rounded-full text-sm font-bold shadow">
                        Coup 0 / <?= count($moves) ?>
                    </div>
                        </div>

                <!-- Contrôles -->
                <div class="w-full flex flex-col space-y-2">
                    <!-- Contrôles principaux -->
                    <div class="flex justify-center items-center space-x-3">
                        <button id="btnFirst" class="control-btn bg-purple-600 text-white">⏮ Début</button>
                        <button id="btnPrev" class="control-btn bg-purple-600 text-white">◀ Précédent</button>
                        <button id="btnPlay" class="control-btn bg-green-600 text-white px-4">▶ Lecture</button>
                        <button id="btnNext" class="control-btn bg-purple-600 text-white">Suivant ▶</button>
                        <button id="btnLast" class="control-btn bg-purple-600 text-white">Fin ⏭</button>
                        </div>
                    
                    <!-- Slider -->
                    <div class="flex items-center space-x-2">
                        <input id="moveSlider" type="range" min="0" max="<?= count($moves) ?>" value="0" 
                               class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Colonne infos -->
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <!-- Informations de la partie -->
            <div class="p-4 border-b">
                <h2 class="text-xl font-semibold text-purple-900 mb-3">Informations</h2>
                <p class="mb-2"><span class="font-semibold">Date :</span> <?= h(date('d/m/Y H:i', strtotime($game['created_at']))) ?></p>
                <p class="mb-2 flex items-center">
                            <span class="font-semibold mr-2">Joueur 1 :</span>
                    <span class="flex items-center"><?= h($player1Name) ?> <span class="ml-1 inline-block w-3 h-3 bg-black rounded-full"></span></span>
                </p>
                <p class="mb-2 flex items-center">
                            <span class="font-semibold mr-2">Joueur 2 :</span>
                    <span class="flex items-center"><?= h($player2Name) ?> <span class="ml-1 inline-block w-3 h-3 bg-white border border-black rounded-full"></span></span>
                </p>
                <p class="mb-2"><span class="font-semibold">Résultat :</span>
                    <?php
                        $txt = ['player1_won'=>'Victoire','player2_won'=>'Défaite','draw'=>'Match nul'][$game['result']];
                        $color = ['player1_won'=>'text-green-600','player2_won'=>'text-red-600','draw'=>'text-yellow-600'][$game['result']];
                        if (!$isPlayer1) {
                            $txt = ['player1_won'=>'Défaite','player2_won'=>'Victoire','draw'=>'Match nul'][$game['result']];
                            $color = ['player1_won'=>'text-red-600','player2_won'=>'text-green-600','draw'=>'text-yellow-600'][$game['result']];
                        }
                    ?>
                    <span class="<?= $color ?> font-bold"><?= $txt ?></span>
                </p>
            </div>
            
            <!-- Liste des coups -->
                <div class="p-4">
                <h2 class="text-xl font-semibold text-purple-900 mb-3">Coups (<?= count($moves) ?>)</h2>
                <div class="overflow-y-auto max-h-[350px] border border-gray-100 rounded p-1">
                    <div id="movesList" class="divide-y divide-gray-100">
                        <!-- Les coups seront insérés ici par JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.control-btn {
    padding: 0.4rem 0.75rem;
    border-radius: 0.375rem;
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.2s;
}
.control-btn:hover {
    opacity: 0.9;
}
.control-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.move-item {
    padding: 0.5rem;
    cursor: pointer;
    transition: all 0.15s;
}
.move-item:hover {
    background-color: #f3f4f6;
}
.move-item.active {
    background-color: #e0e7ff;
    color: #4f46e5;
    font-weight: 600;
}
</style>

<?php
// Préparation des données nécessaires au JavaScript
$gameDataJson = json_encode([
    'initial' => $initialBoard,
    'moves'   => $moves,
    'player1' => [
        'id' => $game['player1_id'],
        'name' => $player1Name
    ],
    'player2' => [
        'id' => $game['player2_id'],
        'name' => $player2Name
    ],
]);
?>

<!-- Script placé à la fin du document -->
<script>
// Configuration et données
const gameData = <?= $gameDataJson ?>;
const ANIMATION_SPEED = 300; // ms pour l'animation
const CELL_COLORS = {
    dark: '#B58863',   // Retour au marron
    light: '#F0D9B5'   // Retour au beige
};
const PIECE_COLORS = {
    player1: '#000',
    player2: '#FFF',
    player1Stroke: '#444',
    player2Stroke: '#444'
};

// État du jeu
let boardStates = [];
let currentMoveIndex = 0;
let animationInProgress = false;
let playInterval = null;

// Éléments DOM
const canvas = document.getElementById('boardCanvas');
const ctx = canvas.getContext('2d');
const moveIndicator = document.getElementById('move-indicator');
const slider = document.getElementById('moveSlider');
const movesList = document.getElementById('movesList');
const btnPlay = document.getElementById('btnPlay');

// Fonction d'initialisation
function init() {
    console.log("Initialisation du replay...");
    buildBoardStates();
    createMovesList();
    renderBoard(0);
    setupEventListeners();
}

// Construction des états successifs du plateau
function buildBoardStates() {
    console.log("Construction des états du plateau...");
    
    // État initial - la première rangée commence avec une case claire
    boardStates = [deepClone(gameData.initial)];
    
    // Pour chaque coup, calculer l'état résultant
    let currentState = deepClone(gameData.initial);
        
    gameData.moves.forEach((move, index) => {
        try {
            // Extraction des coordonnées
            const fromRow = getCoordinate(move, 'from_row', 'from_position', 0);
            const fromCol = getCoordinate(move, 'from_col', 'from_position', 1);
            const toRow = getCoordinate(move, 'to_row', 'to_position', 0);
            const toCol = getCoordinate(move, 'to_col', 'to_position', 1);
            
            // Clonage de l'état actuel
            const newState = deepClone(currentState);
            
            // Déplacement de la pièce
            newState[toRow][toCol] = newState[fromRow][fromCol];
            newState[fromRow][fromCol] = null;
            
            // Gestion des captures
            if (move.captured || move.is_capture) {
                // Calcul des coordonnées de la pièce capturée
                const capturedRow = fromRow + Math.sign(toRow - fromRow);
                const capturedCol = fromCol + Math.sign(toCol - fromCol);
                newState[capturedRow][capturedCol] = null;
            }
            
            // Promotion en dame
            if (newState[toRow][toCol]) {
                if ((newState[toRow][toCol].player === 1 && toRow === 7) || 
                    (newState[toRow][toCol].player === 2 && toRow === 0)) {
                    newState[toRow][toCol].type = 'king';
                }
            }
            
            // Ajout de l'état au tableau
            boardStates.push(newState);
            currentState = newState;
        } catch (e) {
            console.error(`Erreur au coup ${index}:`, e, move);
        }
    });
    
    console.log(`${boardStates.length} états de plateau générés.`);
}

// Récupère une coordonnée depuis un objet de mouvement
function getCoordinate(move, directProp, positionProp, index) {
    if (move[directProp] !== undefined) {
        return parseInt(move[directProp]);
    } else if (move[positionProp]) {
        const parts = move[positionProp].split(',');
        if (parts.length > index) {
            return parseInt(parts[index]);
        }
    }
    throw new Error(`Impossible de trouver la coordonnée ${directProp}/${positionProp}[${index}]`);
}

// Clone profondément un objet
function deepClone(obj) {
    return JSON.parse(JSON.stringify(obj));
}

// Crée la liste des coups cliquables
function createMovesList() {
    // Coup initial (position de départ)
    const initialItem = document.createElement('div');
    initialItem.className = 'move-item active';
    initialItem.dataset.index = 0;
    initialItem.innerHTML = `<strong>Position initiale</strong>`;
    initialItem.addEventListener('click', () => goToMove(0));
    movesList.appendChild(initialItem);
    
    // Liste des coups
    gameData.moves.forEach((move, index) => {
        const moveItem = document.createElement('div');
        moveItem.className = 'move-item';
        moveItem.dataset.index = index + 1;
        
        // Joueur qui a fait le coup
        const playerId = move.player_id || move.user_id;
        const playerName = playerId == gameData.player1.id ? 
                          gameData.player1.name : gameData.player2.name;
        const playerNumber = playerId == gameData.player1.id ? 1 : 2;
        
        // Position d'origine
        const fromPos = move.from_position || 
                       `${move.from_row},${move.from_col}`;
        
        // Position de destination
        const toPos = move.to_position || 
                     `${move.to_row},${move.to_col}`;
        
        // Marqueur de capture
        const captureMarker = move.captured || move.is_capture ? 
                             ' <span class="text-red-500 font-bold">x</span>' : '';
        
        moveItem.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <span class="inline-block w-2 h-2 rounded-full mr-2 ${playerNumber === 1 ? 'bg-black' : 'bg-white border border-black'}"></span>
                    <span class="font-medium">${playerName}</span>
                </div>
                <div class="text-sm text-gray-700">
                    ${fromPos} → ${toPos}${captureMarker}
                </div>
            </div>
        `;
        
        moveItem.addEventListener('click', () => goToMove(index + 1));
        movesList.appendChild(moveItem);
    });
}

// Configuration des écouteurs d'événements
function setupEventListeners() {
    // Boutons de navigation
    document.getElementById('btnFirst').addEventListener('click', () => goToMove(0));
    document.getElementById('btnPrev').addEventListener('click', () => goToMove(currentMoveIndex - 1));
    document.getElementById('btnNext').addEventListener('click', () => goToMove(currentMoveIndex + 1));
    document.getElementById('btnLast').addEventListener('click', () => goToMove(boardStates.length - 1));
    
    // Slider
    slider.addEventListener('input', (e) => goToMove(parseInt(e.target.value)));
    
    // Bouton Play/Pause
    btnPlay.addEventListener('click', togglePlayPause);
    }
    
// Alterne lecture/pause
function togglePlayPause() {
    if (playInterval) {
        clearInterval(playInterval);
        playInterval = null;
        btnPlay.innerHTML = '▶ Lecture';
        btnPlay.classList.replace('bg-red-600', 'bg-green-600');
    } else {
        // Si on est à la fin, revenir au début
        if (currentMoveIndex >= boardStates.length - 1) {
            goToMove(0);
        }
        
        // Démarrer la lecture automatique
        playInterval = setInterval(() => {
            if (currentMoveIndex < boardStates.length - 1) {
                goToMove(currentMoveIndex + 1);
            } else {
                togglePlayPause(); // S'arrêter à la fin
            }
        }, 1000);
        
        btnPlay.innerHTML = '⏸ Pause';
        btnPlay.classList.replace('bg-green-600', 'bg-red-600');
    }
}

// Va à un mouvement spécifique
function goToMove(index) {
    // Empêcher les actions pendant une animation
    if (animationInProgress) return;
    
    // Bornes
    index = Math.max(0, Math.min(index, boardStates.length - 1));
    
    // Si c'est le même coup, ne rien faire
    if (index === currentMoveIndex) return;
    
    // Mettre à jour l'index actuel
    currentMoveIndex = index;
    
    // Mettre à jour l'interface
    updateUI();
    
    // Rendu du plateau
    renderBoard(index);
}

// Met à jour l'interface utilisateur
function updateUI() {
    // Mise à jour du slider
    slider.value = currentMoveIndex;
    
    // Mise à jour de l'indicateur de coup
    moveIndicator.textContent = `Coup ${currentMoveIndex} / ${boardStates.length - 1}`;
    
    // Mise à jour de la liste des coups
    document.querySelectorAll('#movesList .move-item').forEach(item => {
        if (parseInt(item.dataset.index) === currentMoveIndex) {
            item.classList.add('active');
            // Faire défiler jusqu'à l'élément actif
            item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            item.classList.remove('active');
        }
    });
    
    // Activer/désactiver les boutons selon la position
    document.getElementById('btnFirst').disabled = currentMoveIndex === 0;
    document.getElementById('btnPrev').disabled = currentMoveIndex === 0;
    document.getElementById('btnNext').disabled = currentMoveIndex === boardStates.length - 1;
    document.getElementById('btnLast').disabled = currentMoveIndex === boardStates.length - 1;
}

// Rendu du plateau
function renderBoard(index) {
    const board = boardStates[index];
    const cellSize = canvas.width / 8;
    
    // Effacer le canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Dessiner les cases
    for (let row = 0; row < 8; row++) {
        for (let col = 0; col < 8; col++) {
            drawCell(row, col, cellSize);
            
            // Dessiner la pièce si elle existe
            if (board[row][col]) {
                drawPiece(row, col, board[row][col], cellSize);
            }
        }
    }
}

// Dessine une case du plateau
function drawCell(row, col, cellSize) {
    // Sur un plateau de dames standard, les cases foncées sont en bas à droite
    // quand la rangée + colonne est impaire
    ctx.fillStyle = (row + col) % 2 === 1 ? CELL_COLORS.dark : CELL_COLORS.light;
            ctx.fillRect(col * cellSize, row * cellSize, cellSize, cellSize);
}

// Dessine une pièce sur le plateau
function drawPiece(row, col, piece, cellSize) {
    const x = col * cellSize + cellSize / 2;
    const y = row * cellSize + cellSize / 2;
    const radius = cellSize * 0.4;
    
    // Couleur de la pièce
    ctx.fillStyle = piece.player === 1 ? PIECE_COLORS.player1 : PIECE_COLORS.player2;
    
    // Dessin du cercle
    ctx.beginPath();
    ctx.arc(x, y, radius, 0, Math.PI * 2);
    ctx.fill();
    
    // Bordure
    ctx.strokeStyle = piece.player === 1 ? PIECE_COLORS.player1Stroke : PIECE_COLORS.player2Stroke;
    ctx.lineWidth = 2;
    ctx.stroke();
    
    // Si c'est une dame, ajouter une couronne
    if (piece.type === 'king') {
        ctx.fillStyle = piece.player === 1 ? '#FFF' : '#000';
        ctx.font = `bold ${radius * 0.9}px serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('♔', x, y);
    }
}

// Initialiser le replay quand le DOM est chargé
document.addEventListener('DOMContentLoaded', init);
    
// Sécurité pour s'assurer que le canvas est chargé
setTimeout(() => {
    if (boardStates.length > 0 && currentMoveIndex === 0) {
        renderBoard(0);
    }
}, 100);
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?> 