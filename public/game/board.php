<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/controllers/GameController.php';
require_once __DIR__ . '/../../backend/controllers/AuthController.php';
require_once __DIR__ . '/../../backend/includes/session.php';

// Vérifier si l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    header('Location: /auth/login.php');
    exit();
}

// Vérifier si l'ID de la partie est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: /game/play.php');
    exit();
}

$gameId = intval($_GET['id']);
$gameController = new GameController();
$userId = Session::getUserId();

// Récupérer les informations sur la partie
$gameData = $gameController->getGameStatus(['game_id' => $gameId]);
if (!$gameData['success']) {
    header('Location: /game/play.php');
    exit();
}

$game = $gameData['game'];

// Vérifier si l'utilisateur est l'un des joueurs
if ($game['player1_id'] !== $userId && $game['player2_id'] !== $userId) {
    header('Location: /game/play.php');
    exit();
}

// Déterminer si c'est le tour du joueur actuel
$isMyTurn = ($game['current_player'] == $userId);
$myId = $userId;
$opponentId = ($game['player1_id'] == $userId) ? $game['player2_id'] : $game['player1_id'];
$myColor = ($game['player1_id'] == $userId) ? 'black' : 'white';
$opponentColor = ($myColor == 'black') ? 'white' : 'black';

// Récupérer les noms des joueurs
$authController = new AuthController();
$player1 = $authController->getUserById($game['player1_id']);
$player2 = $authController->getUserById($game['player2_id']);
$player1_name = $player1 ? $player1->username : 'Joueur 1';
$player2_name = $player2 ? $player2->username : 'Joueur 2';

// Décoder l'état du plateau
$boardState = json_decode($game['board_state'], true);

// Vérifier si la partie est terminée
$isGameOver = ($game['status'] == 'finished');
$isWinner = ($isGameOver && $game['winner_id'] == $userId);
$isLoser = ($isGameOver && $game['winner_id'] == $opponentId);
$isDraw = ($isGameOver && is_null($game['winner_id']));

$pageTitle = "Partie #" . $gameId . " - " . APP_NAME;
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-indigo-600">Partie #<?php echo $gameId; ?></h1>
        <div>
            <a href="/game/play.php" class="text-indigo-600 hover:underline">← Retour</a>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Colonne de gauche: Informations sur la partie -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Informations</h2>
                
                <div class="space-y-4">
                    <div>
                        <p class="text-gray-600">
                            <span class="font-semibold">Statut:</span> 
                            <?php if ($game['status'] == 'waiting'): ?>
                                <span class="text-yellow-500">En attente</span>
                            <?php elseif ($game['status'] == 'in_progress'): ?>
                                <span class="text-green-500">En cours</span>
                            <?php else: ?>
                                <span class="text-red-500">Terminée</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-gray-600">
                            <span class="font-semibold">Tour actuel:</span> 
                            <?php if ($isMyTurn): ?>
                                <span class="text-green-500">C'est votre tour</span>
                            <?php else: ?>
                                <span class="text-yellow-500">Tour de l'adversaire</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-gray-600">
                            <span class="font-semibold">Votre couleur:</span> 
                            <span class="<?php echo ($myColor == 'black') ? 'text-black' : 'text-gray-500'; ?>"><?php echo ($myColor == 'black') ? 'Noir' : 'Blanc'; ?></span>
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-gray-600">
                            <span class="font-semibold">Adversaire:</span> 
                            <?php echo $game['player1_id'] == $userId ? $player2_name : $player1_name; ?>
                        </p>
                    </div>
                    
                    <?php if ($isGameOver): ?>
                        <div class="mt-4 p-3 rounded-lg <?php echo $isWinner ? 'bg-green-100 text-green-700' : ($isLoser ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'); ?>">
                            <?php if ($isWinner): ?>
                                <p class="font-bold">Félicitations ! Vous avez gagné !</p>
                            <?php elseif ($isLoser): ?>
                                <p class="font-bold">Vous avez perdu. Meilleure chance la prochaine fois !</p>
                            <?php else: ?>
                                <p class="font-bold">Match nul !</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!$isGameOver): ?>
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Aide</h2>
                    
                    <div class="space-y-2 text-gray-600 text-sm">
                        <p>• Cliquez sur l'une de vos pièces pour la sélectionner.</p>
                        <p>• Les cases en surbrillance indiquent les déplacements possibles.</p>
                        <p>• Cliquez sur une case en surbrillance pour y déplacer votre pièce.</p>
                        <p>• La prise est obligatoire lorsqu'elle est possible.</p>
                        <p>• Un pion devient une dame en atteignant la dernière rangée.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Colonne centrale: Plateau de jeu -->
        <div class="lg:col-span-2" x-data="checkerboard">
            <?php if ($isGameOver): ?>
                <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Résultat de la partie</h2>
                    <p class="text-gray-600 mb-2">
                        <?php if ($isWinner): ?>
                            Vous avez remporté la victoire ! Félicitations !
                        <?php elseif ($isLoser): ?>
                            Votre adversaire a remporté la partie.
                        <?php else: ?>
                            La partie s'est terminée par un match nul.
                        <?php endif; ?>
                    </p>
                    <div class="mt-4">
                        <a href="/game/play.php" class="bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700 transition">
                            Jouer une nouvelle partie
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <div class="aspect-w-1 aspect-h-1">
                    <div class="grid grid-cols-8 grid-rows-8 gap-0 border border-gray-400 checkerboard">
                        <?php for($row = 0; $row < 8; $row++): ?>
                            <?php for($col = 0; $col < 8; $col++): ?>
                                <?php 
                                $isBlackSquare = ($row + $col) % 2 == 1;
                                $piece = isset($boardState[$row][$col]) ? $boardState[$row][$col] : null;
                                $pieceColor = null;
                                $isQueen = false;
                                
                                if ($piece) {
                                    $pieceColor = $piece[0]; // 'b' for black, 'w' for white
                                    $isQueen = isset($piece[1]) && $piece[1] == 'q';
                                }
                                
                                $isCurrentPlayerPiece = ($pieceColor == 'b' && $myColor == 'black') || ($pieceColor == 'w' && $myColor == 'white');
                                $isSelectable = $isCurrentPlayerPiece && $isMyTurn && !$isGameOver;
                                
                                $squareClasses = $isBlackSquare ? 'bg-gray-700' : 'bg-gray-300';
                                ?>
                                
                                <div 
                                    class="square aspect-w-1 aspect-h-1 <?php echo $squareClasses; ?>" 
                                    data-row="<?php echo $row; ?>" 
                                    data-col="<?php echo $col; ?>"
                                    @click="selectSquare(<?php echo $row; ?>, <?php echo $col; ?>)"
                                    :class="{ 'bg-indigo-400': isValidMove(<?php echo $row; ?>, <?php echo $col; ?>) }"
                                >
                                    <?php if ($piece): ?>
                                        <div class="piece flex items-center justify-center w-full h-full">
                                            <div 
                                                class="w-4/5 h-4/5 rounded-full flex items-center justify-center <?php echo $pieceColor == 'b' ? 'bg-black' : 'bg-white'; ?> shadow" 
                                                data-piece="<?php echo $piece; ?>"
                                                :class="{ 'ring-2 ring-yellow-400': isSelected(<?php echo $row; ?>, <?php echo $col; ?>) }"
                                            >
                                                <?php if ($isQueen): ?>
                                                    <div class="w-1/2 h-1/2 text-center text-xl <?php echo $pieceColor == 'b' ? 'text-white' : 'text-black'; ?>">♕</div>
                                                <?php else: ?>
                                                    <div class="w-3/4 h-3/4 rounded-full border-2 <?php echo $pieceColor == 'b' ? 'border-gray-700' : 'border-gray-300'; ?>"></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('checkerboard', () => ({
        gameId: <?php echo $gameId; ?>,
        isMyTurn: <?php echo $isMyTurn ? 'true' : 'false'; ?>,
        myColor: '<?php echo $myColor; ?>',
        selectedPiece: null,
        validMoves: [],
        status: '<?php echo $game['status']; ?>',
        isGameOver: <?php echo $isGameOver ? 'true' : 'false'; ?>,
        winnerId: <?php echo $isGameOver && isset($game['winner_id']) ? $game['winner_id'] : 'null'; ?>,
        myId: <?php echo $myId; ?>,
        currentPlayer: <?php echo $game['current_player']; ?>,
        
        selectSquare(row, col) {
            if (!this.isMyTurn || this.isGameOver) return;
            
            const square = document.querySelector(`.square[data-row="${row}"][data-col="${col}"]`);
            const piece = square.querySelector('.piece');
            
            // Si on clique sur une case vide qui est un mouvement valide
            if (!piece && this.isValidMove(row, col)) {
                this.makeMove(row, col);
                return;
            }
            
            // Si pas de pièce ou pas notre couleur, on ignore
            if (!piece) return;
            
            const pieceElement = piece.querySelector('[data-piece]');
            if (!pieceElement) return;
            
            const pieceData = pieceElement.getAttribute('data-piece');
            const pieceColor = pieceData[0];
            
            // Vérifier si c'est notre pièce
            const isOurPiece = (pieceColor === 'b' && this.myColor === 'black') || 
                              (pieceColor === 'w' && this.myColor === 'white');
            
            if (!isOurPiece) return;
            
            // Si c'est notre pièce, on la sélectionne et on calcule les mouvements possibles
            this.selectedPiece = { row, col, pieceData };
            this.calculateValidMoves();
        },
        
        isSelected(row, col) {
            return this.selectedPiece && 
                   this.selectedPiece.row === row && 
                   this.selectedPiece.col === col;
        },
        
        isValidMove(row, col) {
            return this.validMoves.some(move => move.row === row && move.col === col);
        },
        
        calculateValidMoves() {
            // Cette fonction serait normalement plus complexe pour calculer les mouvements valides
            // Pour simplifier, nous utilisons une liste statique de mouvements valides
            this.validMoves = [];
            
            if (!this.selectedPiece) return;
            
            // Exemple simplifié : mouvements diagonaux
            const { row, col, pieceData } = this.selectedPiece;
            const isQueen = pieceData.length > 1 && pieceData[1] === 'q';
            const direction = this.myColor === 'black' ? 1 : -1; // Direction de mouvement
            
            // Pour une reine, on peut aller dans toutes les directions
            if (isQueen) {
                // Diagonales
                for (let i = -1; i <= 1; i += 2) {
                    for (let j = -1; j <= 1; j += 2) {
                        let r = row + i;
                        let c = col + j;
                        while (r >= 0 && r < 8 && c >= 0 && c < 8) {
                            const targetSquare = document.querySelector(`.square[data-row="${r}"][data-col="${c}"]`);
                            const hasPiece = targetSquare.querySelector('.piece');
                            
                            if (!hasPiece) {
                                this.validMoves.push({ row: r, col: c });
                            } else {
                                // Si c'est une pièce adverse, on peut potentiellement la capturer
                                const pieceElement = targetSquare.querySelector('[data-piece]');
                                const pieceData = pieceElement.getAttribute('data-piece');
                                const pieceColor = pieceData[0];
                                
                                const isOpponentPiece = (pieceColor === 'w' && this.myColor === 'black') || 
                                                       (pieceColor === 'b' && this.myColor === 'white');
                                
                                if (isOpponentPiece) {
                                    const jumpRow = r + i;
                                    const jumpCol = c + j;
                                    
                                    if (jumpRow >= 0 && jumpRow < 8 && jumpCol >= 0 && jumpCol < 8) {
                                        const jumpSquare = document.querySelector(`.square[data-row="${jumpRow}"][data-col="${jumpCol}"]`);
                                        const hasJumpPiece = jumpSquare.querySelector('.piece');
                                        
                                        if (!hasJumpPiece) {
                                            this.validMoves.push({ row: jumpRow, col: jumpCol, capture: { row: r, col: c } });
                                        }
                                    }
                                }
                                break;
                            }
                            
                            r += i;
                            c += j;
                        }
                    }
                }
            } else {
                // Pour un pion normal, on ne peut aller que vers l'avant
                for (let j = -1; j <= 1; j += 2) {
                    const r = row + direction;
                    const c = col + j;
                    
                    if (r >= 0 && r < 8 && c >= 0 && c < 8) {
                        const targetSquare = document.querySelector(`.square[data-row="${r}"][data-col="${c}"]`);
                        const hasPiece = targetSquare.querySelector('.piece');
                        
                        if (!hasPiece) {
                            this.validMoves.push({ row: r, col: c });
                        }
                    }
                }
                
                // Vérifier les captures
                for (let j = -1; j <= 1; j += 2) {
                    const r = row + direction * 2;
                    const c = col + j * 2;
                    
                    if (r >= 0 && r < 8 && c >= 0 && c < 8) {
                        const targetSquare = document.querySelector(`.square[data-row="${r}"][data-col="${c}"]`);
                        const hasPiece = targetSquare.querySelector('.piece');
                        
                        if (!hasPiece) {
                            const captureRow = row + direction;
                            const captureCol = col + j;
                            const captureSquare = document.querySelector(`.square[data-row="${captureRow}"][data-col="${captureCol}"]`);
                            const capturePiece = captureSquare.querySelector('.piece');
                            
                            if (capturePiece) {
                                const pieceElement = capturePiece.querySelector('[data-piece]');
                                const pieceData = pieceElement.getAttribute('data-piece');
                                const pieceColor = pieceData[0];
                                
                                const isOpponentPiece = (pieceColor === 'w' && this.myColor === 'black') || 
                                                       (pieceColor === 'b' && this.myColor === 'white');
                                
                                if (isOpponentPiece) {
                                    this.validMoves.push({ row: r, col: c, capture: { row: captureRow, col: captureCol } });
                                }
                            }
                        }
                    }
                }
            }
        },
        
        makeMove(toRow, toCol) {
            if (!this.selectedPiece) return;
            
            const { row: fromRow, col: fromCol } = this.selectedPiece;
            
            // Envoyer le mouvement au serveur
            fetch(`/api/game/move.php?id=${this.gameId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    from_row: fromRow,
                    from_col: fromCol,
                    to_row: toRow,
                    to_col: toCol
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Recharger la page pour mettre à jour l'état du jeu
                    location.reload();
                } else {
                    alert(result.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert("Une erreur est survenue lors de l'envoi du mouvement.");
            });
        },
        
        checkBoardUpdates() {
            fetch(`/api/game/status.php?id=${this.gameId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Vérifier si le statut a changé
                    if (result.status !== this.status) {
                        this.status = result.status;
                        this.winnerId = result.winner_id;
                        
                        if (this.status === 'finished') {
                            // Recharger la page pour afficher les résultats finaux
                            location.reload();
                            return;
                        }
                    }
                    
                    // Vérifier si c'est maintenant notre tour
                    if (result.current_player === this.myId && !this.isMyTurn) {
                        this.isMyTurn = true;
                        this.currentPlayer = this.myId;
                        
                        // Recharger la page pour obtenir le nouvel état du plateau
                        location.reload();
                    } else {
                        // Continuer à vérifier les mises à jour
                        setTimeout(() => this.checkBoardUpdates(), 5000);
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                setTimeout(() => this.checkBoardUpdates(), 10000); // Réessayer après une erreur
            });
        },
        
        init() {
            if (!this.isMyTurn && !this.isGameOver) {
                this.checkBoardUpdates();
            }
        }
    }));
});
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>