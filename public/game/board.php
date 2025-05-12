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
require_once __DIR__ . '/../../backend/controllers/ProfileController.php';
require_once __DIR__ . '/../../backend/includes/session.php';

// Rediriger si l'utilisateur n'est pas connecté
if (!Session::isLoggedIn()) {
    // Nettoyer le buffer avant de rediriger
    ob_end_clean();
    error_log("Utilisateur non connecté, redirection vers login.php");
    header('Location: /auth/login.php');
    exit;
}

// Récupérer l'ID de l'utilisateur
$user_id = Session::getUserId();

// Mettre à jour l'activité de l'utilisateur
$profileController = new ProfileController();
$profileController->updateActivity();

// Récupérer l'ID de la partie depuis l'URL
$game_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

error_log("board.php appelé avec l'ID de partie: " . $game_id);

if (!$game_id) {
    // Nettoyer le buffer avant de rediriger
    ob_end_clean();
    error_log("ID de partie non spécifié, redirection vers play.php");
    header('Location: /game/play.php');
    exit;
}

// Récupérer les données de la partie
error_log("board.php - Avant création du GameController");
try {
    $gameController = new GameController();
    error_log("board.php - GameController créé avec succès");
    
    error_log("board.php - Avant appel à getGame() avec ID: " . $game_id);
    $gameData = $gameController->getGame($game_id);
    error_log("board.php - Après appel à getGame() - Résultat success: " . ($gameData['success'] ? 'true' : 'false'));
} catch (Exception $e) {
    error_log("board.php - ERREUR lors de l'utilisation de GameController: " . $e->getMessage());
    error_log("board.php - Trace: " . $e->getTraceAsString());
    // Nettoyer le buffer avant de rediriger
    ob_end_clean();
    header('Location: /game/play.php?error=' . urlencode('Erreur lors du chargement de la partie: ' . $e->getMessage()));
    exit;
}

error_log("Résultat de getGame pour l'ID " . $game_id . ": " . json_encode($gameData));

// Vérifier si la partie existe et si l'utilisateur est autorisé à y accéder
if (!$gameData['success'] || 
    ($gameData['game']['player1_id'] != Session::getUserId() && 
     $gameData['game']['player2_id'] != Session::getUserId() && 
     $gameData['game']['player2_id'] != 0)) {
    // Nettoyer le buffer avant de rediriger
    ob_end_clean();
    error_log("Partie inexistante ou utilisateur non autorisé, redirection vers play.php");
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

// Tout semble bien, continuer avec l'affichage de la page
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<link rel="stylesheet" href="/assets/css/style.css">

<div class="container mx-auto px-4 py-8 max-w-6xl">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Colonne d'informations -->
        <div class="md:col-span-1">
            <div class="bg-white shadow-md rounded-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-indigo-700 mb-4">Informations</h2>
                
                <div class="space-y-4">
                    <div>
                        <p class="text-gray-700">Partie #<?php echo $game_id; ?></p>
                        <p class="text-gray-700">Statut: <span class="font-semibold <?php echo $gameData['game']['status'] === 'in_progress' ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $gameData['game']['status'] === 'in_progress' ? 'En cours' : 'Terminée'; ?>
                        </span></p>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-indigo-600 mb-2">Joueurs</h3>
                        <div class="flex items-center mb-2 bg-gray-100 rounded-lg p-2">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center bg-black mr-2">
                                <div class="w-6 h-6 rounded-full bg-black border-2 border-white"></div>
                            </div>
                            <span class="font-medium <?php echo $isPlayer1 ? 'text-indigo-700' : 'text-gray-700'; ?>">
                                <?php echo htmlspecialchars($gameData['game']['player1_name'] ?? ''); ?>
                            </span>
                            <span class="ml-2 px-2 py-1 text-xs rounded bg-indigo-100 text-indigo-800">
                                Joueur 1
                            </span>
                        </div>
                        
                        <div class="flex items-center bg-gray-100 rounded-lg p-2">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center bg-white border border-gray-300 mr-2">
                                <div class="w-6 h-6 rounded-full bg-white border-2 border-gray-300"></div>
                            </div>
                            <span class="font-medium <?php echo !$isPlayer1 ? 'text-indigo-700' : 'text-gray-700'; ?>">
                                <?php echo htmlspecialchars($gameData['game']['player2_name'] ?? ''); ?>
                            </span>
                            <span class="ml-2 px-2 py-1 text-xs rounded bg-indigo-100 text-indigo-800">
                                <?php echo $opponentIsBot ? 'Joueur' : 'Joueur 2'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="p-4 rounded-lg <?php echo $isUserTurn ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'; ?>">
                        <h3 class="text-lg font-semibold mb-1">Tour actuel</h3>
                        <p class="font-medium">
                            <?php if ($isUserTurn): ?>
                                <span class="text-green-600 font-bold">À vous de jouer</span>
                            <?php else: ?>
                                En attente de l'adversaire
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Actions possibles -->
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-xl font-bold text-indigo-700 mb-4">Actions</h2>
                
                <div class="space-y-3">
                    <a href="/game/play.php" class="block w-full text-center py-3 px-4 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 rounded-lg transition duration-200">
                        Retour à mes parties
                    </a>
                    
                    <button id="abandonBtn" class="w-full bg-red-600 text-white py-2 px-4 rounded hover:bg-red-700 transition duration-200 mb-2" onclick="abandonGame()">
                        Abandonner la partie
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Plateau de jeu -->
        <div class="md:col-span-2">
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-xl font-bold text-indigo-700 mb-4">Plateau de jeu</h2>
                
                <?php if ($gameData['game']['status'] === 'in_progress'): ?>
                    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-r-lg">
                        <p class="font-medium">
                            <?php if ($isUserTurn): ?>
                                C'est à votre tour. Sélectionnez une pièce pour la déplacer.
                            <?php else: ?>
                                Attendez votre tour. Votre adversaire réfléchit...
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php 
                        $result   = $gameData['game']['result'] ?? null;
                        $statusMsg = '';
                        if ($result === 'draw') {
                            $statusMsg = "La partie s'est terminée par un match nul.";
                        } elseif (($result === 'player1_won' && $isPlayer1) || ($result === 'player2_won' && !$isPlayer1)) {
                            $statusMsg = 'Félicitations ! Vous avez gagné cette partie.';
                        } else {
                            $statusMsg = 'Vous avez perdu cette partie. Meilleure chance la prochaine fois !';
                        }
                    ?>
                    <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded-r-lg">
                        <p class="font-medium"><?php echo $statusMsg; ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Plateau centré avec une taille fixe -->
                <div class="flex justify-center items-center">
                    <div id="board" class="relative border-4 border-gray-800 rounded-md shadow-lg" style="width: 480px; height: 480px;">
                        <?php for ($row = 0; $row < 8; $row++): ?>
                            <?php for ($col = 0; $col < 8; $col++): ?>
                                <div class="absolute cell" style="width: 60px; height: 60px; top: <?php echo $row * 60; ?>px; left: <?php echo $col * 60; ?>px; background-color: <?php echo ($row + $col) % 2 === 0 ? '#f0f0f0' : '#1e3a5f'; ?>;"
                                     data-row="<?php echo $row; ?>" data-col="<?php echo $col; ?>">
                                    
                                    <?php if (isset($boardState[$row][$col]) && $boardState[$row][$col] !== null): ?>
                                        <?php 
                                        $piece = $boardState[$row][$col];
                                        $is_player_piece = ($piece['player'] == 1 && $isPlayer1) || ($piece['player'] == 2 && !$isPlayer1);
                                        $piece_color = $piece['player'] == 1 ? 'black' : 'white';
                                        $border_color = $piece['player'] == 1 ? 'border-gray-300' : 'border-gray-400';
                                        $is_king = isset($piece['type']) && $piece['type'] === 'king';
                                        ?>
                                        
                                        <div class="piece absolute inset-0 flex items-center justify-center cursor-<?php echo ($is_player_piece && $isUserTurn) ? 'pointer' : 'default'; ?>"
                                            data-row="<?php echo $row; ?>" 
                                            data-col="<?php echo $col; ?>" 
                                            data-player="<?php echo $piece['player']; ?>"
                                            data-king="<?php echo $is_king ? 'true' : 'false'; ?>">
                                            <div class="w-12 h-12 rounded-full <?php echo $piece_color == 'black' ? 'bg-black' : 'bg-white border-2 '.$border_color; ?> shadow-md flex items-center justify-center">
                                                <?php if ($is_king): ?>
                                                    <div class="text-<?php echo $piece_color == 'black' ? 'yellow-400' : 'yellow-600'; ?> text-2xl font-bold">♛</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        <?php endfor; ?>
                        
                        <!-- Les indicateurs de mouvement seront ajoutés ici dynamiquement par JavaScript -->
                        <div id="moveIndicators"></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Ajout du panneau de chat -->
        <div class="md:col-span-1 md:order-last order-first">
            <div class="bg-white shadow-md rounded-lg p-4 flex flex-col h-[24rem] md:h-[34rem]">
                <h3 class="text-lg font-bold text-indigo-700 mb-2 flex items-center gap-2"><i class="fas fa-comments"></i> Chat</h3>
                <div id="chat-messages" class="relative flex-1 min-h-[10rem] max-h-[18rem] overflow-y-auto mb-3 space-y-2 px-3 py-2 bg-gray-50 rounded border border-gray-200 scroll-smooth"></div>
                <!-- Ligne d'actions rapides -->
                <div class="flex space-x-2 mb-1">
                    <button class="quick-msg bg-gray-200 hover:bg-gray-300 rounded-full w-8 h-8 flex items-center justify-center">👍</button>
                    <button class="quick-msg bg-gray-200 hover:bg-gray-300 rounded-full w-8 h-8 flex items-center justify-center">😂</button>
                    <button class="quick-msg bg-gray-200 hover:bg-gray-300 rounded-full w-8 h-8 flex items-center justify-center">😮</button>
                    <button class="quick-msg bg-gray-200 hover:bg-gray-300 rounded-full px-2 text-xs font-semibold">GG</button>
                </div>
                <form id="chat-form" class="flex">
                    <input type="text" id="chat-input" class="flex-1 border rounded-l-lg px-2 py-1 focus:ring-purple-500 focus:border-purple-500" placeholder="Votre message..." autocomplete="off" />
                    <button class="bg-purple-600 hover:bg-purple-700 text-white px-4 rounded-r-lg">Envoyer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation pour abandonner -->
<div id="abandonModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold text-red-600 mb-4">Abandonner la partie</h3>
        <p class="text-gray-700 mb-6">Êtes-vous sûr de vouloir abandonner cette partie ? Cette action est irréversible et vous serez considéré comme perdant.</p>
        <div id="abandonMessage" class="text-center mb-4 font-semibold"></div>
        <div class="flex justify-end space-x-4">
            <button id="cancelAbandon" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 transition">Annuler</button>
            <button id="confirmAbandon" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">Abandonner</button>
        </div>
    </div>
</div>

<!-- Modal pour fin de partie -->
<div id="gameOverModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full mx-4 text-center">
        <!-- Victoire -->
        <div id="victoryContent" class="hidden">
            <div id="victoryIcon" class="mx-auto w-24 h-24 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-full h-full text-green-500">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-green-600 mb-2">Victoire !</h2>
            <p class="text-gray-700 mb-6">Félicitations, vous avez gagné la partie.</p>
        </div>
        
        <!-- Défaite -->
        <div id="defeatContent" class="hidden">
            <div id="defeatIcon" class="mx-auto w-24 h-24 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-full h-full text-red-500">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-red-600 mb-2">Défaite</h2>
            <p class="text-gray-700 mb-6">Vous avez perdu cette partie. Meilleure chance la prochaine fois !</p>
        </div>
        
        <!-- Match nul -->
        <div id="drawContent" class="hidden">
            <div id="drawIcon" class="mx-auto w-24 h-24 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-full h-full text-blue-500">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-blue-600 mb-2">Match nul</h2>
            <p class="text-gray-700 mb-6">La partie s'est terminée par un match nul.</p>
        </div>
        
        <div class="flex justify-center space-x-4">
            <a href="/game/play.php" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                Retour
            </a>
            <a href="/game/matchmaking.php" class="px-6 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                Nouvelle partie
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log("Le DOM est chargé et prêt pour les interactions !");
    
    const board = document.getElementById('board');
    const moveIndicators = document.getElementById('moveIndicators');
    let selectedPiece = null;
    let possibleMoves = [];
    const game_id = <?php echo $game_id; ?>;
    const isUserTurn = <?php echo $isUserTurn ? 'true' : 'false'; ?>;
    const gameStatus = "<?php echo $gameData['game']['status']; ?>";
    const currentPlayer = <?php echo $gameData['game']['current_player']; ?>;
    const userPlayer = <?php echo $isPlayer1 ? 1 : 2; ?>;
    const gameWinner = <?php echo $gameData['game']['winner_id'] ? $gameData['game']['winner_id'] : 'null'; ?>;
    const currentUserId = <?php echo $currentUserId; ?>;
    
    console.log("État du jeu:", {
        game_id: game_id,
        isUserTurn: isUserTurn,
        gameStatus: gameStatus,
        currentPlayer: currentPlayer,
        userPlayer: userPlayer,
        gameWinner: gameWinner,
        currentUserId: currentUserId
    });
    
    // Mettre à jour l'état de la partie toutes les 5 secondes si ce n'est pas le tour du joueur
    if (gameStatus === 'in_progress' && !isUserTurn) {
        setInterval(checkGameStatus, 5000);
    }
    
    // Ajouter les écouteurs d'événements aux pièces du joueur actuel
    if (gameStatus === 'in_progress' && isUserTurn) {
        const playerPieces = document.querySelectorAll(`.piece[data-player="${userPlayer}"]`);
        console.log(`Nombre de pièces du joueur ${userPlayer} trouvées:`, playerPieces.length);
        
        playerPieces.forEach(piece => {
            piece.addEventListener('click', selectPiece);
            
            // Ajouter une classe visuelle pour montrer que la pièce est sélectionnable
            piece.classList.add('selectable-piece');
            
            // Ajouter un effet de survol pour les pièces jouables
            piece.addEventListener('mouseenter', function() {
                this.classList.add('hover-effect');
            });
            
            piece.addEventListener('mouseleave', function() {
                this.classList.remove('hover-effect');
            });
            
            console.log(`Pièce à la position [${piece.dataset.row},${piece.dataset.col}] prête pour le jeu`);
        });
    }
    
    // Fonctions pour la gestion des mouvements
    function selectPiece(event) {
        console.log("Pièce sélectionnée !");
        if (!isUserTurn) {
            console.log("Ce n'est pas votre tour !");
            return;
        }
        
        // Réinitialiser la pièce précédemment sélectionnée
        if (selectedPiece) {
            console.log("Réinitialisation de la pièce précédemment sélectionnée");
            
            // Enlever la classe de sélection de toutes les pièces
            document.querySelectorAll('.piece').forEach(p => {
                p.classList.remove('selected');
            });
            
            const prevRow = parseInt(selectedPiece.dataset.row);
            const prevCol = parseInt(selectedPiece.dataset.col);
            const prevCell = document.querySelector(`.cell[data-row="${prevRow}"][data-col="${prevCol}"]`);
            
            if (prevCell) {
                prevCell.style.backgroundColor = (prevRow + prevCol) % 2 === 0 ? '#f0f0f0' : '#1e3a5f';
            }
        }
        
        // Effacer les indicateurs de mouvement précédents
        moveIndicators.innerHTML = '';
        
        // Obtenir l'élément HTML de la pièce
        const piece = event.currentTarget;
        
        // Sélectionner la nouvelle pièce
        const row = parseInt(piece.dataset.row);
        const col = parseInt(piece.dataset.col);
        const isKing = piece.dataset.king === 'true';
        
        console.log(`Nouvelle pièce sélectionnée: Position [${row},${col}], Roi: ${isKing}`);
        
        // Ajouter la classe de sélection
        piece.classList.add('selected');
        
        // Afficher la case sélectionnée en surbrillance
        const cell = document.querySelector(`.cell[data-row="${row}"][data-col="${col}"]`);
        if (cell) {
            cell.style.backgroundColor = '#ffeb3b'; // Jaune pour la case sélectionnée
        }
        
        selectedPiece = piece;
        
        // Calculer les mouvements possibles
        calculatePossibleMoves(row, col, isKing);
        
        // Afficher les indicateurs de mouvement
        showMoveIndicators();
    }
    
    function calculatePossibleMoves(row, col, isKing) {
        console.log(`Calcul des mouvements possibles pour la pièce à [${row},${col}]`);
        possibleMoves = [];
        
        let moveDirs = [];
        // Directions de capture (toutes les diagonales pour permettre la prise arrière)
        const captureDirs = [
            {r: 1,  c: -1},
            {r: 1,  c: 1},
            {r: -1, c: -1},
            {r: -1, c: 1}
        ];

        // Pour les dames, elles se déplacent sur toutes les diagonales
        if (isKing) {
            moveDirs = captureDirs;
        } else {
            // Pour les pions, seulement vers l'avant
            if (userPlayer === 1) {
                moveDirs = [{r: 1, c: -1}, {r: 1, c: 1}];
            } else {
                moveDirs = [{r: -1, c: -1}, {r: -1, c: 1}];
            }
        }

        console.log("MoveDirs", moveDirs, "CaptureDirs", captureDirs);

        if (!isKing) {
            // Vérifier chaque direction
            moveDirs.forEach(dir => {
                const newRow = row + dir.r;
                const newCol = col + dir.c;
                
                // Vérifier si la nouvelle position est dans les limites du plateau
                if (newRow >= 0 && newRow < 8 && newCol >= 0 && newCol < 8) {
                    // Vérifier si la case est vide
                    const targetCell = document.querySelector(`.piece[data-row="${newRow}"][data-col="${newCol}"]`);
                    if (!targetCell) {
                        possibleMoves.push({
                            fromRow: row,
                            fromCol: col,
                            toRow: newRow,
                            toCol: newCol,
                            isCapture: false
                        });
                        console.log(`Mouvement simple possible vers [${newRow},${newCol}]`);
                    } else {
                        // Vérifier une capture possible
                        const piecePlayer = parseInt(targetCell.dataset.player);
                        if (piecePlayer !== userPlayer) {
                            const jumpRow = newRow + dir.r;
                            const jumpCol = newCol + dir.c;
                            
                            // Vérifier si la case après la capture est dans les limites et vide
                            if (jumpRow >= 0 && jumpRow < 8 && jumpCol >= 0 && jumpCol < 8) {
                                const jumpCell = document.querySelector(`.piece[data-row="${jumpRow}"][data-col="${jumpCol}"]`);
                                if (!jumpCell) {
                                    possibleMoves.push({
                                        fromRow: row,
                                        fromCol: col,
                                        toRow: jumpRow,
                                        toCol: jumpCol,
                                        isCapture: true,
                                        captureRow: newRow,
                                        captureCol: newCol
                                    });
                                    console.log(`Capture possible vers [${jumpRow},${jumpCol}], en capturant [${newRow},${newCol}]`);
                                }
                            }
                        }
                    }
                }
            });

            // Vérifier captures dans toutes les directions (avant + arrière)
            captureDirs.forEach(dir => {
                const newRow = row + dir.r;
                const newCol = col + dir.c;
                if (newRow >= 0 && newRow < 8 && newCol >= 0 && newCol < 8) {
                    const targetCell = document.querySelector(`.piece[data-row="${newRow}"][data-col="${newCol}"]`);
                    if (targetCell && parseInt(targetCell.dataset.player) !== userPlayer) {
                        const jumpRow = newRow + dir.r;
                        const jumpCol = newCol + dir.c;
                        if (jumpRow >= 0 && jumpRow < 8 && jumpCol >= 0 && jumpCol < 8) {
                            const jumpCell = document.querySelector(`.piece[data-row="${jumpRow}"][data-col="${jumpCol}"]`);
                            if (!jumpCell) {
                                possibleMoves.push({
                                    fromRow: row,
                                    fromCol: col,
                                    toRow: jumpRow,
                                    toCol: jumpCol,
                                    isCapture: true,
                                    captureRow: newRow,
                                    captureCol: newCol
                                });
                            }
                        }
                    }
                }
            });
        } 
        // Pour les dames, mouvements à longue distance
        else {
            // Pour chaque direction, parcourir toute la diagonale
            moveDirs.forEach(dir => {
                let currentRow = row;
                let currentCol = col;
                
                // Déplacements simples (sans capture)
                for (let i = 1; i <= 7; i++) { // Maximum 7 déplacements possibles sur une diagonale
                    const newRow = row + (dir.r * i);
                    const newCol = col + (dir.c * i);
                    
                    // Vérifier si la position est dans les limites
                    if (newRow >= 0 && newRow < 8 && newCol >= 0 && newCol < 8) {
                        // Vérifier si la case est vide
                        const targetCell = document.querySelector(`.piece[data-row="${newRow}"][data-col="${newCol}"]`);
                        if (!targetCell) {
                            // Case vide, on peut s'y déplacer
                            possibleMoves.push({
                                fromRow: row,
                                fromCol: col,
                                toRow: newRow,
                                toCol: newCol,
                                isCapture: false
                            });
                            console.log(`Mouvement dame possible vers [${newRow},${newCol}]`);
                        } else {
                            // Case occupée, on arrête dans cette direction
                            const piecePlayer = parseInt(targetCell.dataset.player);
                            
                            // Si c'est une pièce adverse, vérifier la capture
                            if (piecePlayer !== userPlayer) {
                                // Regarder si la case suivante est libre pour permettre la capture
                                const jumpRow = newRow + dir.r;
                                const jumpCol = newCol + dir.c;
                                
                                if (jumpRow >= 0 && jumpRow < 8 && jumpCol >= 0 && jumpCol < 8) {
                                    const jumpCell = document.querySelector(`.piece[data-row="${jumpRow}"][data-col="${jumpCol}"]`);
                                    if (!jumpCell) {
                                        // On peut capturer la pièce
                                        possibleMoves.push({
                                            fromRow: row,
                                            fromCol: col,
                                            toRow: jumpRow,
                                            toCol: jumpCol,
                                            isCapture: true,
                                            captureRow: newRow,
                                            captureCol: newCol
                                        });
                                        console.log(`Capture dame possible vers [${jumpRow},${jumpCol}], en capturant [${newRow},${newCol}]`);
                                    }
                                }
                            }
                            
                            // Dans tous les cas, on arrête l'exploration dans cette direction
                            break;
                        }
                    } else {
                        // Position hors limites, on arrête dans cette direction
                        break;
                    }
                }
            });
        }
        
        console.log(`Total des mouvements possibles: ${possibleMoves.length}`);
    }
    
    function showMoveIndicators() {
        console.log("Affichage des indicateurs de mouvement");
        
        possibleMoves.forEach(move => {
            // Créer un indicateur de mouvement
            const indicator = document.createElement('div');
            indicator.className = 'move-indicator absolute flex items-center justify-center cursor-pointer';
            indicator.style.width = '60px';
            indicator.style.height = '60px';
            indicator.style.top = `${move.toRow * 60}px`;
            indicator.style.left = `${move.toCol * 60}px`;
            indicator.style.backgroundColor = move.isCapture ? 'rgba(255, 0, 0, 0.3)' : 'rgba(0, 255, 0, 0.3)';
            indicator.style.borderRadius = '50%';
            indicator.style.zIndex = '10';
            
            // Ajouter une flèche pour indiquer la direction
            const arrow = document.createElement('div');
            arrow.innerHTML = '&#x2192;'; // Flèche droite
            arrow.className = 'text-white text-3xl font-bold';
            
            // Ajuster la rotation de la flèche selon la direction
            const angle = Math.atan2(move.toRow - move.fromRow, move.toCol - move.fromCol) * (180 / Math.PI);
            arrow.style.transform = `rotate(${angle}deg)`;
            
            indicator.appendChild(arrow);
            
            // Ajouter l'écouteur d'événement pour effectuer le mouvement
            indicator.addEventListener('click', () => {
                console.log(`Clic sur l'indicateur pour déplacer vers [${move.toRow},${move.toCol}]`);
                makeMove(move);
            });
            
            moveIndicators.appendChild(indicator);
        });
    }
    
    function makeMove(move) {
        console.log(`Tentative de déplacement de [${move.fromRow},${move.fromCol}] vers [${move.toRow},${move.toCol}]`);
        
        // Afficher un indicateur visuel de progression
        const loadingIndicator = document.createElement('div');
        loadingIndicator.className = 'fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50';
        loadingIndicator.innerHTML = '<div class="bg-white p-4 rounded-lg shadow-lg"><p class="text-lg font-bold">Déplacement en cours...</p></div>';
        document.body.appendChild(loadingIndicator);
        
        // Appeler l'API pour effectuer le mouvement
        fetch('/api/game/move.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                game_id: game_id,
                from_row: move.fromRow,
                from_col: move.fromCol,
                to_row: move.toRow,
                to_col: move.toCol
            })
        })
        .then(response => {
            console.log("Réponse reçue du serveur");
            return response.json();
        })
        .then(data => {
            console.log('Résultat du mouvement:', data);
            
            // Supprimer l'indicateur de chargement
            document.body.removeChild(loadingIndicator);
            
            if (data.success) {
                // Si la partie est terminée, afficher la modal de fin de partie
                if (data.game_over) {
                    const gameOverModal = document.getElementById('gameOverModal');
                    const victoryContent = document.getElementById('victoryContent');
                    const defeatContent = document.getElementById('defeatContent');
                    const drawContent = document.getElementById('drawContent');
                    
                    // Afficher la modal correspondante (victoire par défaut car c'est le joueur qui vient de faire le mouvement gagnant)
                    victoryContent.classList.remove('hidden');
                    gameOverModal.classList.remove('hidden');
                    
                    // Ajouter un délai avant redirection pour permettre à l'utilisateur de voir le résultat
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                } else {
                    // Animation de succès avant l'actualisation pour un mouvement normal
                    const successMessage = document.createElement('div');
                    successMessage.className = 'fixed inset-0 bg-green-500 bg-opacity-30 flex items-center justify-center z-50';
                    successMessage.innerHTML = '<div class="bg-white p-4 rounded-lg shadow-lg"><p class="text-lg font-bold text-green-600">Mouvement réussi!</p></div>';
                    document.body.appendChild(successMessage);
                    
                    // Attendre un peu avant d'actualiser la page
                    setTimeout(() => {
                        // Actualiser la page pour afficher le nouvel état du plateau
                        window.location.reload();
                    }, 500);
                }
            } else {
                alert('Erreur lors du mouvement: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            
            // Supprimer l'indicateur de chargement
            document.body.removeChild(loadingIndicator);
            
            alert('Une erreur est survenue lors du mouvement.');
        });
    }
    
    function checkGameStatus() {
        console.log("Vérification du statut de la partie...");
        
        fetch(`/api/game/status.php?game_id=${game_id}`)
        .then(response => response.json())
        .then(data => {
            console.log('Statut de la partie:', data);
            if (data.success && (data.game.current_player === userPlayer || data.game.status !== 'in_progress')) {
                // Actualiser la page si c'est au tour du joueur ou si la partie est terminée
                console.log("C'est à votre tour ou la partie est terminée, actualisation de la page");
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Erreur lors de la vérification du statut:', error);
        });
    }
    
    // Gestion de l'abandon de partie
    const abandonBtn = document.getElementById('abandonBtn');
    const abandonModal = document.getElementById('abandonModal');
    const cancelAbandon = document.getElementById('cancelAbandon');
    const confirmAbandon = document.getElementById('confirmAbandon');
    
    abandonBtn.addEventListener('click', () => {
        console.log("Ouverture du modal d'abandon");
        abandonModal.classList.remove('hidden');
    });
    
    cancelAbandon.addEventListener('click', () => {
        console.log("Annulation de l'abandon");
        abandonModal.classList.add('hidden');
    });
    
    confirmAbandon.addEventListener('click', () => {
        console.log("Confirmation de l'abandon");
        
        // Afficher un indicateur visuel de progression
        const loadingIndicator = document.createElement('div');
        loadingIndicator.className = 'fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50';
        loadingIndicator.innerHTML = '<div class="bg-white p-4 rounded-lg shadow-lg"><p class="text-lg font-bold">Abandon en cours...</p></div>';
        document.body.appendChild(loadingIndicator);
        
        // Appeler l'API pour abandonner la partie
        fetch('/api/game/abandon.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                game_id: game_id
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Résultat de l\'abandon:', data);
            
            // Supprimer l'indicateur de chargement
            document.body.removeChild(loadingIndicator);
            
            if (data.success) {
                // Message de confirmation
                const confirmationMessage = document.createElement('div');
                confirmationMessage.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
                confirmationMessage.innerHTML = `
                    <div class="bg-white p-6 rounded-lg shadow-lg max-w-md">
                        <h3 class="text-xl font-bold text-red-600 mb-4">Partie abandonnée</h3>
                        <p class="mb-4">Vous avez abandonné cette partie.</p>
                        <p class="mb-6">Redirection vers la page des parties...</p>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-red-600 h-2.5 rounded-full" style="width: 0%" id="progress-bar"></div>
                        </div>
                    </div>
                `;
                document.body.appendChild(confirmationMessage);
                
                // Animation de la barre de progression
                const progressBar = document.getElementById('progress-bar');
                let progress = 0;
                const interval = setInterval(() => {
                    progress += 5;
                    if (progressBar) progressBar.style.width = progress + '%';
                    if (progress >= 100) {
                        clearInterval(interval);
                        // Redirection vers la page des parties
                        window.location.href = '/game/play.php';
                    }
                }, 50);
            } else {
                alert('Erreur lors de l\'abandon de la partie: ' + data.message);
                // Masquer la modal d'abandon
                abandonModal.classList.add('hidden');
            }
        })
        .catch(error => {
            console.error('Erreur lors de l\'abandon:', error);
            
            // Supprimer l'indicateur de chargement
            document.body.removeChild(loadingIndicator);
            
            alert('Une erreur est survenue lors de l\'abandon de la partie.');
            // Masquer la modal d'abandon
            abandonModal.classList.add('hidden');
        });
    });
    
    // Fermer le modal si on clique en dehors
    abandonModal.addEventListener('click', (e) => {
        if (e.target === abandonModal) {
            abandonModal.classList.add('hidden');
        }
    });
    
    // Ajouter des gestionnaires d'erreurs globaux
    window.addEventListener('error', function(e) {
        console.error('Erreur JavaScript:', e.message, 'à', e.filename, ':', e.lineno);
    });

    // Vérifier si la partie est terminée et afficher le résultat
    if (gameStatus === 'finished') {
        const gameOverModal = document.getElementById('gameOverModal');
        const victoryContent = document.getElementById('victoryContent');
        const defeatContent = document.getElementById('defeatContent');
        const drawContent = document.getElementById('drawContent');
        
        if (gameWinner === currentUserId) {
            victoryContent.classList.remove('hidden');
        } else if (gameWinner === null) {
            drawContent.classList.remove('hidden');
        } else {
            defeatContent.classList.remove('hidden');
        }
        
        gameOverModal.classList.remove('hidden');
    }

    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');
    let lastTimestamp = 0;

    function appendMessage(msg) {
        const wrapper = document.createElement('div');
        const isMine = parseInt(msg.user_id) === currentUserId;
        wrapper.className = `flex ${isMine ? 'justify-end' : 'justify-start'}`;
        const bubble = document.createElement('div');
        bubble.className = `relative max-w-[80%] px-4 py-2.5 rounded-lg text-base shadow leading-normal ${isMine ? 'bg-purple-600 text-white rounded-br-none after:border-l-purple-600' : 'bg-gray-200 text-gray-800 rounded-bl-none after:border-r-gray-200'}`;
        bubble.innerHTML = `<span class='font-semibold mr-1'>${msg.username}</span>${msg.message}`;
        bubble.style.wordWrap = 'break-word';
        // Petite flèche
        bubble.classList.add('after:absolute','after:top-2.5','after:border-8','after:border-transparent');
        if(isMine){
            bubble.classList.add('after:right-[-16px]','after:border-l-8');
        }else{
            bubble.classList.add('after:left-[-16px]','after:border-r-8');
        }
        wrapper.appendChild(bubble);
        chatMessages.appendChild(wrapper);
        if(nearBottom()){
            chatMessages.scrollTop = chatMessages.scrollHeight;
        } else {
            scrollBtn.classList.remove('hidden');
        }
    }

    function fetchChat() {
        fetch(`/api/game/chat.php?action=get&game_id=${game_id}&since=${lastTimestamp}`)
            .then(r => r.json())
            .then(d => {
                if (d.success && Array.isArray(d.messages)) {
                    d.messages.forEach(m => {
                        appendMessage(m);
                        lastTimestamp = Math.max(lastTimestamp, m.timestamp);
                    });
                }
            })
            .catch(console.error);
    }

    setInterval(fetchChat, 2000);
    fetchChat();

    chatForm.addEventListener('submit', e => {
        e.preventDefault();
        const text = chatInput.value.trim();
        if (!text) return;
        fetch(`/api/game/chat.php?action=send&game_id=${game_id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
        }).then(r => r.json()).then(d => {
            if (d.success) {
                chatInput.value = '';
                fetchChat();
            }
        });
    });

    // ----- QUICK EMOJI BUTTONS -----
    document.querySelectorAll('.quick-msg').forEach(btn => {
        btn.addEventListener('click', () => {
            const txt = btn.textContent.trim();
            chatInput.value = txt;
            chatForm.dispatchEvent(new Event('submit'));
        });
    });

    // ----- SONS AVEC FICHIERS AUDIO (style chess.com) -----
    const moveSound = new Audio('https://cdn.jsdelivr.net/gh/ornicar/lila@master/public/sound/move.mp3');
    moveSound.volume = 0.6;

    const chatPing = new Audio('https://cdn.jsdelivr.net/gh/thatoddmailbox/chess-sounds@master/notify.mp3');
    chatPing.volume = 0.6;

    // fallback WebAudio en cas d'échec de chargement
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      
    function playTone(freq, type='sine', attack=0.01, release=0.2, gainVal=0.4){
        const now = audioCtx.currentTime;
        const osc = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        osc.type = type;
        osc.frequency.setValueAtTime(freq, now);
        gainNode.gain.setValueAtTime(0.0001, now);
        gainNode.gain.linearRampToValueAtTime(gainVal, now + attack);
        gainNode.gain.exponentialRampToValueAtTime(0.0001, now + release);
        osc.connect(gainNode).connect(audioCtx.destination);
        osc.start(now);
        osc.stop(now + release + 0.05);
    }

    function notifyChat(){
        chatPing.currentTime = 0;
        chatPing.play().catch(()=>{playTone(650,'triangle',0.005,0.15,0.3);});
    }

    function notifyMove(){
        moveSound.currentTime = 0;
        moveSound.play().catch(()=>{playTone(180,'square',0.005,0.25,0.45);});
    }

    // jouer le son lorsqu'un déplacement est confirmé côté client
    const originalMakeMove = (typeof makeMove === 'function') ? makeMove : null;
    if(originalMakeMove){
        makeMove = function(move){
            originalMakeMove(move);
            notifyMove();
        };
    }

    // Wrap appendMessage to trigger system notification
    const _appendMsgFn = appendMessage;
    appendMessage = function(m){
        _appendMsgFn(m);
        if(parseInt(m.user_id)!==currentUserId){
            notifyChat();
            showNotif(`${m.username}: ${m.message}`);
        }
    };

    // Notify when it becomes user's turn
    const pollInterval = setInterval(() => {
        fetch(`/api/game/status.php?game_id=${game_id}`)
            .then(r=>r.json())
            .then(d=>{
                if(d.success && d.game.current_player === userPlayer){
                    showNotif('À votre tour de jouer !');
                }
            }).catch(()=>{});
    }, 8000);

    // ----------------------------------------
    // DARK THEME & NOTIFICATIONS ENHANCEMENTS
    // ----------------------------------------
    // Inject toggle button
    const themeBtn = document.createElement('button');
    themeBtn.id = 'themeToggle';
    themeBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m8.66-13.66l-.71.71M4.05 19.95l-.71-.71M21 12h-1M4 12H3m16.66 4.95l-.71-.71M4.05 4.05l-.71.71M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>`;
    themeBtn.className = 'fixed bottom-4 right-4 bg-purple-600 hover:bg-purple-700 text-white p-3 rounded-full shadow-lg focus:outline-none';
    document.body.appendChild(themeBtn);

    // Apply saved theme
    if(localStorage.getItem('themeDark')==='1'){ document.body.classList.add('theme-dark'); changeThemeIcon(); }

    themeBtn.addEventListener('click', () => {
        document.body.classList.toggle('theme-dark');
        const isDark = document.body.classList.contains('theme-dark');
        localStorage.setItem('themeDark', isDark ? '1' : '0');
        changeThemeIcon();
    });

    function changeThemeIcon(){
        const isDark = document.body.classList.contains('theme-dark');
        themeBtn.innerHTML = isDark
            ? `<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3a9 9 0 000 18 9 9 0 010-18z" /></svg>`
            : `<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m8.66-13.66l-.71.71M4.05 19.95l-.71-.71M21 12h-1M4 12H3m16.66 4.95l-.71-.71M4.05 4.05l-.71.71M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>`;
    }

    // Browser notifications permission
    let notifGranted = false;
    if ("Notification" in window) {
        if(Notification.permission === 'granted') notifGranted = true;
        else if(Notification.permission !== 'denied'){
            Notification.requestPermission().then(p => { notifGranted = p==='granted'; });
        }
    }

    function showNotif(text){
        if(notifGranted && document.hidden){
            try{ new Notification('Jeu de Dames', { body: text, icon: '/assets/img/icon-192.png' }); }catch(e){ console.warn(e); }
        }
    }

    let lastMoveId = 0;
    function fetchMoves(){
        fetch(`/api/game/moves.php?game_id=${game_id}&last_id=${lastMoveId}`)
            .then(r=>r.json())
            .then(d=>{
                if(d.success&&Array.isArray(d.moves)&&d.moves.length){
                    const panel=document.getElementById('moves-panel');
                    if(panel){
                        d.moves.forEach(m=>{
                            const line=document.createElement('div');
                            line.textContent=`${m.username}: (${m.from_row},${m.from_col}) ➜ (${m.to_row},${m.to_col})`;
                            panel.appendChild(line);
                            lastMoveId=Math.max(lastMoveId, m.id);
                        });
                        panel.scrollTop=panel.scrollHeight;
                    }
                }
            }).catch(()=>{});
    }
    setInterval(fetchMoves, 3000);
    fetchMoves();

    // ------------- BOARD FLIP -------------
    const flipBtn=document.createElement('button');
    flipBtn.className='absolute top-2 right-2 bg-gray-800 bg-opacity-70 text-white rounded-full p-1 hover:bg-opacity-90';
    flipBtn.innerHTML='<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.818 9H20v-5M4 4l16 16" /></svg>';
    document.getElementById('board').appendChild(flipBtn);

    const boardEl=document.getElementById('board');
    function applyFlip(flip){
        if(flip){boardEl.classList.add('board-flipped');}
        else{boardEl.classList.remove('board-flipped');}
        localStorage.setItem('boardFlip'+game_id, flip?'1':'0');
    }
    // default orientation: player2 gets flipped
    const storedFlip=localStorage.getItem('boardFlip'+game_id);
    const initialFlip = storedFlip!==null ? storedFlip==='1' : (userPlayer===2);
    applyFlip(initialFlip);

    flipBtn.addEventListener('click', ()=>{
        applyFlip(!boardEl.classList.contains('board-flipped'));
    });

    // ----- AUTO-SCROLL & BOUTON "DERNIERS MESSAGES" -----
    const scrollBtn = document.createElement('button');
    scrollBtn.id = 'scrollBottomBtn';
    scrollBtn.className = 'hidden absolute bottom-2 right-2 bg-purple-600 text-white w-8 h-8 rounded-full shadow flex items-center justify-center focus:outline-none';
    scrollBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 16l6-6H6z"/></svg>';
    chatMessages.appendChild(scrollBtn);

    scrollBtn.addEventListener('click', ()=>{
        chatMessages.scrollTo({top: chatMessages.scrollHeight, behavior: 'smooth'});
    });

    function nearBottom(){
        return chatMessages.scrollHeight - (chatMessages.scrollTop + chatMessages.clientHeight) < 40;
    }

    chatMessages.addEventListener('scroll', ()=>{
        scrollBtn.classList.toggle('hidden', nearBottom());
    });
});

// Fonction pour abandonner la partie via le bouton Abandonner
function abandonGame() {
    // Ouvrir le modal d'abandon
    const abandonModal = document.getElementById('abandonModal');
    abandonModal.classList.remove('hidden');
}
</script>

<style>
.hover-effect {
    transform: translateY(-5px) !important;
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.3) !important;
    transition: all 0.2s ease;
}

.selected {
    box-shadow: 0 0 0 3px #ffeb3b, 0 5px 10px rgba(0, 0, 0, 0.3) !important;
}

.selectable-piece {
    cursor: pointer;
    transition: all 0.2s ease;
}

.selectable-piece:hover {
    transform: translateY(-3px);
}

.move-indicator {
    animation: pulse 1.5s infinite;
    transition: transform 0.2s ease;
}

.move-indicator:hover {
    transform: scale(1.1);
    animation: none;
}

@keyframes pulse {
    0% { opacity: 0.6; transform: scale(0.95); }
    50% { opacity: 0.8; transform: scale(1.05); }
    100% { opacity: 0.6; transform: scale(0.95); }
}

/* --------- DARK THEME --------- */
body.theme-dark{ background-color:#1f2937; color:#d1d5db; }
body.theme-dark .bg-white{ background-color:#374151 !important; }
body.theme-dark .bg-gray-100{ background-color:#4b5563 !important; }
body.theme-dark .bg-gray-200{ background-color:#6b7280 !important; }
body.theme-dark .text-gray-700{ color:#d1d5db !important; }
body.theme-dark .text-gray-800{ color:#f3f4f6 !important; }
body.theme-dark .text-indigo-700, body.theme-dark .text-indigo-600{ color:#a5b4fc !important; }
.board-flipped{ transform: rotate(180deg); }
.board-flipped .piece,.board-flipped .move-indicator{ transform: rotate(180deg); }
</style>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>