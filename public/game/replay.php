<?php
// Start output buffering to prevent any previous output
ob_start();

// Set display_errors for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include configuration files
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/includes/session.php';
require_once __DIR__ . '/../../backend/controllers/ProfileController.php';

// Check if user is logged in, if not redirect to login
if (!Session::isLoggedIn()) {
    header('Location: /auth/login.php');
    exit;
}

// Get current user's ID
$userId = Session::getUserId();

// Update user activity
$profileController = new ProfileController();
$profileController->updateActivity();

// Get game ID from URL
$gameId = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;

if (!$gameId) {
    header('Location: /game/history.php');
    exit;
}

// Use the API endpoint instead of direct controller call
$apiUrl = "/api/game/get_game_moves.php?game_id=" . $gameId;
$apiResponse = file_get_contents($_SERVER['DOCUMENT_ROOT'] . $apiUrl);

if (!$apiResponse) {
    header('Location: /game/history.php');
    exit;
}

$gameData = json_decode($apiResponse, true);

// Check if the API call was successful
if (!$gameData['success']) {
    header('Location: /game/history.php');
    exit;
}

// Extract game information
$game = $gameData['game'];
$boardState = json_decode($game['board_state'] ?? '[]', true);
if (empty($boardState)) {
    // Fallback to a default board state if none is provided by the API
    $boardState = [];
    for ($i = 0; $i < 8; $i++) {
        $boardState[$i] = array_fill(0, 8, null);
    }
}
$moves = $gameData['moves'] ?? [];

// Determine player names
$player1Name = $game['player1_name'] ?: 'Joueur 1';
$player2Name = $game['player2_name'] ?: ($game['player2_id'] == 0 ? 'IA' : 'Joueur 2');

// Determine if current user is player 1 or 2
$isPlayer1 = $game['player1_id'] == $userId;

// Format game date
$gameDate = new DateTime($game['created_at']);
$formattedDate = $gameDate->format('d/m/Y H:i');

// Determine game result information
$statusText = "";
$resultText = "";
$resultClass = "";

switch ($game['status']) {
    case 'completed':
    case 'finished':
        $statusText = "Terminée";
        
        // Utiliser le champ result pour déterminer le résultat
        if ($game['result'] === 'draw') {
            $resultText = "Match nul";
            $resultClass = "text-yellow-500";
        } else if (($isPlayer1 && $game['result'] === 'player1_won') || (!$isPlayer1 && $game['result'] === 'player2_won')) {
            $resultText = "Victoire";
            $resultClass = "text-green-600";
        } else {
            $resultText = "Défaite";
            $resultClass = "text-red-600";
        }
        break;
        
    case 'in_progress':
        $statusText = "En cours";
        $resultText = "Partie non terminée";
        $resultClass = "text-blue-600";
        break;
        
    case 'cancelled':
        $statusText = "Annulée";
        $resultText = "Partie annulée";
        $resultClass = "text-red-600";
        break;
        
    default:
        $statusText = "Inconnu";
        $resultText = "Statut inconnu";
        $resultClass = "text-gray-600";
}

// Set the title of the page
$pageTitle = "Replay de partie #" . $gameId;

// Include the header
include_once __DIR__ . '/../../backend/includes/header.php';
?>

<div class="container px-4 py-8 max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-purple-900 mb-4 sm:mb-0">
            <i class="fas fa-redo mr-2"></i>Replay de la partie #<?php echo htmlspecialchars($gameId); ?>
        </h1>
        <a href="/game/history.php" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md hover:shadow-lg transition flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Retour à l'historique
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Plateau et contrôles -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="bg-purple-100 px-4 py-3 border-b border-purple-200 flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-purple-900">Replay</h2>
                    <span class="px-3 py-1 rounded-full text-sm font-semibold <?php echo $resultClass; ?>">
                        <?php echo htmlspecialchars($resultText); ?>
                    </span>
                </div>
                <div class="p-4">
                    <div class="mb-6 flex justify-center">
                        <div class="relative inline-block">
                            <canvas id="checkerboard" width="640" height="640" class="rounded-lg shadow-md max-w-full h-auto"></canvas>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-3 flex-wrap gap-2">
                            <button id="btn-first-move" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-3 rounded flex items-center text-sm">
                                <i class="fas fa-step-backward mr-1"></i> Premier
                            </button>
                            <button id="btn-prev-move" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-3 rounded flex items-center text-sm">
                                <i class="fas fa-chevron-left mr-1"></i> Précédent
                            </button>
                            <button id="btn-play-pause" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded flex items-center text-sm">
                                <i class="fas fa-play mr-1"></i> Lecture
                            </button>
                            <button id="btn-next-move" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-3 rounded flex items-center text-sm">
                                <i class="fas fa-chevron-right mr-1"></i> Suivant
                            </button>
                            <button id="btn-last-move" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-3 rounded flex items-center text-sm">
                                <i class="fas fa-step-forward mr-1"></i> Dernier
                            </button>
                        </div>
                        <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div id="progress-bar" class="h-full bg-purple-600 rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Informations sur la partie -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
                <div class="bg-purple-100 px-4 py-3 border-b border-purple-200">
                    <h2 class="text-xl font-semibold text-purple-900">Informations</h2>
                </div>
                <div class="p-4">
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <i class="far fa-calendar-alt w-6 text-purple-600"></i>
                            <span class="font-semibold mr-2">Date :</span>
                            <span><?php echo htmlspecialchars($formattedDate); ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-chess-pawn w-6 text-black"></i>
                            <span class="font-semibold mr-2">Joueur 1 :</span>
                            <span><?php echo htmlspecialchars($player1Name); ?> (Noir)</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-chess-pawn w-6 text-gray-300"></i>
                            <span class="font-semibold mr-2">Joueur 2 :</span>
                            <span><?php echo htmlspecialchars($player2Name); ?> (Blanc)</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-info-circle w-6 text-purple-600"></i>
                            <span class="font-semibold mr-2">Statut :</span>
                            <span><?php echo htmlspecialchars($statusText); ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-trophy w-6 text-purple-600"></i>
                            <span class="font-semibold mr-2">Résultat :</span>
                            <span class="<?php echo $resultClass; ?>"><?php echo htmlspecialchars($resultText); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="bg-purple-100 px-4 py-3 border-b border-purple-200">
                    <h2 class="text-xl font-semibold text-purple-900">Historique des mouvements</h2>
                </div>
                <div class="p-4">
                    <div class="max-h-96 overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joueur</th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mouvement</th>
                                </tr>
                            </thead>
                            <tbody id="moves-list" class="bg-white divide-y divide-gray-200">
                                <?php if (empty($moves)): ?>
                                    <tr>
                                        <td colspan="3" class="px-3 py-2 text-center text-sm text-gray-500">Aucun mouvement enregistré</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Game data from PHP
const gameData = <?php echo json_encode([
    'id' => $gameId,
    'boardState' => $boardState,
    'moves' => $moves,
    'player1_id' => $game['player1_id'],
    'player2_id' => $game['player2_id']
]); ?>;

// Board drawing variables
const canvas = document.getElementById('checkerboard');
const ctx = canvas.getContext('2d');
const boardSize = canvas.width;
const cellSize = boardSize / 8;

// Player colors
const colors = {
    board: {
        dark: '#8B4513', // Dark brown
        light: '#F5DEB3' // Wheat
    },
    pieces: {
        player1: '#000000', // Black
        player1King: '#333333', // Dark gray
        player2: '#FFFFFF', // White
        player2King: '#EEEEEE'  // Light gray
    }
};

// Replay variables
let currentMoveIndex = -1;
let isPlaying = false;
let playInterval = null;
let boardStates = [];

// Initialize the board states array with the initial state
function initializeBoardStates() {
    // Create a deep copy of the initial board state
    const initialState = JSON.parse(JSON.stringify(gameData.boardState));
    boardStates = [initialState];
    
    // Calculate board state after each move
    if (gameData.moves && gameData.moves.length > 0) {
        let currentBoard = initialState;
        
        gameData.moves.forEach(move => {
            // Get move positions from the move data
            const fromRow = move.from_row !== undefined ? move.from_row : parseInt(move.from_position.split(',')[0]);
            const fromCol = move.from_col !== undefined ? move.from_col : parseInt(move.from_position.split(',')[1]);
            const toRow = move.to_row !== undefined ? move.to_row : parseInt(move.to_position.split(',')[0]);
            const toCol = move.to_col !== undefined ? move.to_col : parseInt(move.to_position.split(',')[1]);
            
            // Create a deep copy of the current board
            const newBoard = JSON.parse(JSON.stringify(currentBoard));
            
            // Move the piece
            newBoard[toRow][toCol] = newBoard[fromRow][fromCol];
            newBoard[fromRow][fromCol] = null;
            
            // Handle captures
            if (move.is_capture || move.captured == 1) {
                // Calculate capture position (middle point between from and to)
                const captureRow = fromRow + Math.sign(toRow - fromRow);
                const captureCol = fromCol + Math.sign(toCol - fromCol);
                
                // Remove the captured piece
                newBoard[captureRow][captureCol] = null;
            }
            
            // Handle promotion to king
            if ((newBoard[toRow][toCol].player === 1 && toRow === 7) || 
                (newBoard[toRow][toCol].player === 2 && toRow === 0)) {
                newBoard[toRow][toCol].type = 'king';
            }
            
            // Add new board state to the array
            boardStates.push(newBoard);
            
            // Update current board for the next move
            currentBoard = newBoard;
        });
    }
    
    // Update the moves list
    updateMovesList();
}

// Draw the board
function drawBoard() {
    // Draw the checkerboard pattern
    for (let row = 0; row < 8; row++) {
        for (let col = 0; col < 8; col++) {
            const isLightSquare = (row + col) % 2 === 0;
            ctx.fillStyle = isLightSquare ? colors.board.light : colors.board.dark;
            ctx.fillRect(col * cellSize, row * cellSize, cellSize, cellSize);
        }
    }
}

// Draw a piece
function drawPiece(row, col, player, isKing) {
    const x = col * cellSize + cellSize / 2;
    const y = row * cellSize + cellSize / 2;
    const radius = cellSize * 0.4;
    
    // Draw the piece body
    ctx.beginPath();
    ctx.arc(x, y, radius, 0, Math.PI * 2);
    ctx.fillStyle = player === 1 ? 
        (isKing ? colors.pieces.player1King : colors.pieces.player1) : 
        (isKing ? colors.pieces.player2King : colors.pieces.player2);
    ctx.fill();
    
    // Add a border
    ctx.strokeStyle = player === 1 ? '#555555' : '#888888';
    ctx.lineWidth = 2;
    ctx.stroke();
    
    // Add a crown for kings
    if (isKing) {
        ctx.beginPath();
        ctx.fillStyle = player === 1 ? '#DDDDDD' : '#333333';
        const crownSize = radius * 0.6;
        
        // Draw a simple crown
        ctx.font = `bold ${crownSize}px Arial`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('♔', x, y);
    }
}

// Draw the current board state
function drawBoardState(boardState) {
    // Clear the canvas
    ctx.clearRect(0, 0, boardSize, boardSize);
    
    // Draw the board
    drawBoard();
    
    // Draw the pieces
    for (let row = 0; row < 8; row++) {
        for (let col = 0; col < 8; col++) {
            const piece = boardState[row][col];
            if (piece !== null) {
                const isKing = piece.type === 'king';
                drawPiece(row, col, piece.player, isKing);
            }
        }
    }
}

// Update the list of moves in the UI
function updateMovesList() {
    const movesList = document.getElementById('moves-list');
    movesList.innerHTML = '';
    
    if (gameData.moves && gameData.moves.length > 0) {
        gameData.moves.forEach((move, index) => {
            // Get move positions from the move data
            const fromRow = move.from_row !== undefined ? move.from_row : parseInt(move.from_position.split(',')[0]);
            const fromCol = move.from_col !== undefined ? move.from_col : parseInt(move.from_position.split(',')[1]);
            const toRow = move.to_row !== undefined ? move.to_row : parseInt(move.to_position.split(',')[0]);
            const toCol = move.to_col !== undefined ? move.to_col : parseInt(move.to_position.split(',')[1]);
            
            const tr = document.createElement('tr');
            tr.className = index === currentMoveIndex ? 'bg-purple-100' : 'hover:bg-purple-50 cursor-pointer';
            tr.id = `move-${index}`;
            
            // Convert row/col to chess-like notation (a8, b6, etc.)
            const fromNotation = String.fromCharCode(97 + fromCol) + (8 - fromRow);
            const toNotation = String.fromCharCode(97 + toCol) + (8 - toRow);
            
            tr.innerHTML = `
                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">${index + 1}</td>
                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">${move.player_id == gameData.player1_id ? 'Joueur 1' : 'Joueur 2'}</td>
                <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                    ${fromNotation} → ${toNotation}
                    ${(move.is_capture || move.captured == 1) ? '<span class="text-red-500">(capture)</span>' : ''}
                </td>
            `;
            
            // Add click event to jump to this move
            tr.addEventListener('click', () => {
                goToMove(index);
            });
            
            movesList.appendChild(tr);
        });
    } else {
        movesList.innerHTML = '<tr><td colspan="3" class="px-3 py-2 text-center text-sm text-gray-500">Aucun mouvement enregistré</td></tr>';
    }
    
    // Update progress bar
    updateProgressBar();
}

// Update the progress bar
function updateProgressBar() {
    const progressBar = document.getElementById('progress-bar');
    const totalMoves = gameData.moves.length;
    
    if (totalMoves > 0) {
        const progress = ((currentMoveIndex + 1) / totalMoves) * 100;
        progressBar.style.width = `${progress}%`;
    } else {
        progressBar.style.width = '0%';
    }
}

// Go to a specific move
function goToMove(index) {
    // Ensure index is within bounds
    index = Math.max(-1, Math.min(index, boardStates.length - 2));
    
    // Update current move index
    currentMoveIndex = index;
    
    // Draw the board state
    drawBoardState(boardStates[index + 1]); // +1 because boardStates[0] is the initial state
    
    // Update the moves list (highlight current move)
    const moveElements = document.querySelectorAll('#moves-list tr');
    moveElements.forEach((el, i) => {
        if (i === index) {
            el.className = 'bg-purple-100';
        } else {
            el.className = 'hover:bg-purple-50 cursor-pointer';
        }
    });
    
    // Scroll to the current move
    if (index >= 0) {
        const currentMoveEl = document.getElementById(`move-${index}`);
        if (currentMoveEl) {
            currentMoveEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
    
    // Update progress bar
    updateProgressBar();
}

// Button event handlers
document.getElementById('btn-first-move').addEventListener('click', () => {
    stopPlayback();
    goToMove(-1); // Initial state
});

document.getElementById('btn-prev-move').addEventListener('click', () => {
    stopPlayback();
    goToMove(currentMoveIndex - 1);
});

document.getElementById('btn-next-move').addEventListener('click', () => {
    stopPlayback();
    goToMove(currentMoveIndex + 1);
});

document.getElementById('btn-last-move').addEventListener('click', () => {
    stopPlayback();
    goToMove(boardStates.length - 2); // Last move
});

document.getElementById('btn-play-pause').addEventListener('click', () => {
    if (isPlaying) {
        stopPlayback();
    } else {
        startPlayback();
    }
});

// Start auto-playback
function startPlayback() {
    if (isPlaying) return;
    
    isPlaying = true;
    
    // Update button appearance
    const button = document.getElementById('btn-play-pause');
    button.innerHTML = '<i class="fas fa-pause mr-1"></i> Pause';
    button.classList.remove('bg-green-600', 'hover:bg-green-700');
    button.classList.add('bg-yellow-600', 'hover:bg-yellow-700');
    
    // Start from the beginning if at the end
    if (currentMoveIndex >= boardStates.length - 2) {
        goToMove(-1);
    }
    
    // Start the playback interval
    playInterval = setInterval(() => {
        if (currentMoveIndex < boardStates.length - 2) {
            goToMove(currentMoveIndex + 1);
        } else {
            stopPlayback();
        }
    }, 1000); // 1 second between moves
}

// Stop auto-playback
function stopPlayback() {
    if (!isPlaying) return;
    
    isPlaying = false;
    
    // Update button appearance
    const button = document.getElementById('btn-play-pause');
    button.innerHTML = '<i class="fas fa-play mr-1"></i> Lecture';
    button.classList.remove('bg-yellow-600', 'hover:bg-yellow-700');
    button.classList.add('bg-green-600', 'hover:bg-green-700');
    
    // Clear the playback interval
    if (playInterval) {
        clearInterval(playInterval);
        playInterval = null;
    }
}

// Initialize the replay
function initReplay() {
    // Make sure the board state is properly initialized
    if (!gameData.boardState || gameData.boardState.length === 0) {
        console.error("Erreur: État du plateau non disponible");
        
        // Create a default empty board
        gameData.boardState = [];
        for (let i = 0; i < 8; i++) {
            gameData.boardState[i] = [];
            for (let j = 0; j < 8; j++) {
                gameData.boardState[i][j] = null;
            }
        }
    }
    
    initializeBoardStates();
    drawBoardState(boardStates[0]); // Draw initial state
    goToMove(-1); // Start at initial state
}

// Initialize when the page loads
window.addEventListener('load', initReplay);
</script>

<?php
// Include the footer
include_once __DIR__ . '/../../backend/includes/footer.php';
?> 