<?php
require_once __DIR__ . '/../db/Database.php';
require_once __DIR__ . '/../includes/session.php';

/**
 * Modèle Game
 * Gère les opérations liées aux parties de jeu
 */
class Game {
    // Constante de table
    const TABLE = 'games';
    
    // Propriétés de la base de données
    private $db;
    public $table = "games";
    
    // Propriétés de la partie
    public $id;
    public $player1_id;
    public $player2_id;
    public $current_player;
    public $status;
    public $winner_id;
    public $board_state;
    public $created_at;
    public $updated_at;
    
    // Propriétés additionnelles pour l'affichage
    public $player1_name;
    public $player2_name;
    
    /**
     * Constructeur
     * @param PDO|null $db Connexion à la base de données (optionnel)
     */
    public function __construct($db = null) {
        if ($db !== null) {
            $this->db = $db;
        } else {
            $this->db = Database::getInstance()->getConnection();
        }
    }
    
    /**
     * Crée une nouvelle partie
     * @param int $player1_id ID du joueur 1
     * @param int $player2_id ID du joueur 2
     * @return bool Succès de l'opération
     */
    public function create($player1_id, $player2_id) {
        try {
            // Initialiser le plateau de jeu
            $boardState = $this->initializeBoard();
            $encodedBoard = json_encode($boardState);
            
            // Déterminer le joueur qui commence (aléatoire)
            $this->current_player = rand(0, 1) ? $player1_id : $player2_id;
            
            // Construire la requête
            $query = "INSERT INTO {$this->table} 
                      (player1_id, player2_id, current_player, status, board_state) 
                      VALUES (:player1_id, :player2_id, :current_player, 'in_progress', :board_state)";
            
            // Préparer et exécuter la requête
            $stmt = $this->db->prepare($query);
            
            $this->player1_id = $player1_id;
            $this->player2_id = $player2_id;
            $this->status = 'in_progress';
            
            $stmt->bindParam(':player1_id', $this->player1_id);
            $stmt->bindParam(':player2_id', $this->player2_id);
            $stmt->bindParam(':current_player', $this->current_player);
            $stmt->bindParam(':board_state', $encodedBoard);
            
            if ($stmt->execute()) {
                $this->id = $this->db->lastInsertId();
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log('Erreur dans Game::create: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lit les informations d'une partie par son ID
     * @param int $id ID de la partie
     * @return bool Succès de l'opération
     */
    public function readOne($id) {
        try {
            $query = "SELECT g.*, u1.username as player1_name, u2.username as player2_name 
                      FROM {$this->table} g
                      LEFT JOIN users u1 ON g.player1_id = u1.id
                      LEFT JOIN users u2 ON g.player2_id = u2.id
                      WHERE g.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $this->id = $row['id'];
                $this->player1_id = $row['player1_id'];
                $this->player2_id = $row['player2_id'];
                $this->current_player = $row['current_player'];
                $this->status = $row['status'];
                $this->winner_id = $row['winner_id'];
                $this->board_state = $row['board_state'];
                $this->created_at = $row['created_at'];
                $this->updated_at = $row['updated_at'];
                $this->player1_name = $row['player1_name'];
                $this->player2_name = $row['player2_name'];
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log('Erreur dans Game::readOne: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtient toutes les parties actives d'un joueur
     * @param int $playerId ID du joueur
     * @return PDOStatement|array Liste des parties actives
     */
    public function getActiveGames($playerId) {
        try {
            $query = "SELECT g.*, 
                      u1.username as player1_name, 
                      u2.username as player2_name 
                      FROM " . $this->table . " g
                      JOIN users u1 ON g.player1_id = u1.id
                      JOIN users u2 ON g.player2_id = u2.id
                      WHERE (g.player1_id = :player_id OR g.player2_id = :player_id) 
                      AND g.status = 'in_progress'
                      ORDER BY g.created_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des parties actives: " . $e->getMessage());
            
            // Créer un PDOStatement vide à retourner en cas d'erreur
            $emptyQuery = "SELECT 1 WHERE 1=0";
            $emptyStmt = $this->db->prepare($emptyQuery);
            $emptyStmt->execute();
            
            return $emptyStmt;
        }
    }
    
    /**
     * Obtient l'historique des parties d'un joueur
     * @param int $playerId ID du joueur
     * @return PDOStatement|array Liste des parties terminées
     */
    public function readGameHistory($playerId) {
        try {
            $query = "SELECT g.*, 
                      u1.username as player1_name, 
                      u2.username as player2_name 
                      FROM " . $this->table . " g
                      LEFT JOIN users u1 ON g.player1_id = u1.id
                      LEFT JOIN users u2 ON g.player2_id = u2.id
                      WHERE (g.player1_id = :player_id OR g.player2_id = :player_id) 
                      AND g.status = 'finished'
                      ORDER BY g.updated_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Journaliser le nombre de parties trouvées
            error_log("Game model: historique de parties pour joueur {$playerId}, nombre trouvé: " . $stmt->rowCount());
            
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération de l'historique des parties: " . $e->getMessage());
            
            // Créer un PDOStatement vide à retourner en cas d'erreur
            $emptyQuery = "SELECT 1 WHERE 1=0";
            $emptyStmt = $this->db->prepare($emptyQuery);
            $emptyStmt->execute();
            
            return $emptyStmt;
        }
    }
    
    /**
     * Effectue un mouvement sur le plateau
     * @param int $fromRow Ligne de départ
     * @param int $fromCol Colonne de départ
     * @param int $toRow Ligne d'arrivée
     * @param int $toCol Colonne d'arrivée
     * @return bool|array Succès de l'opération ou résultat détaillé
     */
    public function makeMove($fromRow, $fromCol, $toRow, $toCol) {
        try {
            // Vérifier si c'est le tour du joueur
            $userId = Session::getUserId();
            
            if ($this->current_player != $userId) {
                return [
                    'success' => false, 
                    'message' => 'Ce n\'est pas votre tour.'
                ];
            }
            
            // Vérifier que la partie est en cours
            if ($this->status != 'in_progress') {
                return [
                    'success' => false, 
                    'message' => 'Cette partie est terminée.'
                ];
            }
            
            // Déterminer le numéro du joueur (1 ou 2)
            $playerNumber = ($this->player1_id == $userId) ? 1 : 2;
            
            // Vérifier que la pièce appartient au joueur
            if (!isset($this->board_state[$fromRow][$fromCol]) || 
                $this->board_state[$fromRow][$fromCol]['player'] != $playerNumber) {
                return [
                    'success' => false, 
                    'message' => 'Cette pièce ne vous appartient pas.'
                ];
            }
            
            // Vérifier que le mouvement est valide
            $validMove = $this->isValidMove($fromRow, $fromCol, $toRow, $toCol, $playerNumber);
            
            if (!$validMove['valid']) {
                return [
                    'success' => false, 
                    'message' => $validMove['message']
                ];
            }
            
            // Effectuer le mouvement
            $newBoard = $this->applyMove($this->board_state, $fromRow, $fromCol, $toRow, $toCol, $playerNumber, $validMove);
            
            // Vérifier si la pièce devient une dame
            if ($playerNumber == 1 && $toRow == 7 && $newBoard[$toRow][$toCol]['type'] == 'pawn') {
                $newBoard[$toRow][$toCol]['type'] = 'king';
            } else if ($playerNumber == 2 && $toRow == 0 && $newBoard[$toRow][$toCol]['type'] == 'pawn') {
                $newBoard[$toRow][$toCol]['type'] = 'king';
            }
            
            // Passer le tour à l'adversaire
            $nextPlayer = ($this->player1_id == $userId) ? $this->player2_id : $this->player1_id;
            
            // Vérifier si la partie est terminée
            $gameOver = $this->checkGameOver($newBoard, ($playerNumber == 1) ? 2 : 1);
            
            // Mettre à jour l'état de la partie
            $newStatus = $gameOver ? 'finished' : 'in_progress';
            $winnerId = $gameOver ? $userId : null;
            
            $query = "UPDATE " . $this->table . " SET 
                     board_state = :board_state, 
                     current_player = :current_player, 
                     status = :status,
                     winner_id = :winner_id,
                     updated_at = NOW()
                     WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            
            $encodedBoard = json_encode($newBoard);
            $stmt->bindParam(':board_state', $encodedBoard);
            $stmt->bindParam(':current_player', $nextPlayer, PDO::PARAM_INT);
            $stmt->bindParam(':status', $newStatus);
            $stmt->bindParam(':winner_id', $winnerId, PDO::PARAM_INT);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                // Enregistrer le mouvement
                $captured = $validMove['capture'] ?? false;
                
                $query = "INSERT INTO moves (game_id, user_id, from_row, from_col, to_row, to_col, captured) 
                         VALUES (:game_id, :user_id, :from_row, :from_col, :to_row, :to_col, :captured)";
                
                $stmt = $this->db->prepare($query);
                
                $stmt->bindParam(':game_id', $this->id, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindParam(':from_row', $fromRow, PDO::PARAM_INT);
                $stmt->bindParam(':from_col', $fromCol, PDO::PARAM_INT);
                $stmt->bindParam(':to_row', $toRow, PDO::PARAM_INT);
                $stmt->bindParam(':to_col', $toCol, PDO::PARAM_INT);
                $stmt->bindParam(':captured', $captured, PDO::PARAM_BOOL);
                
                $stmt->execute();
                
                // Mise à jour des propriétés de l'objet
                $this->board_state = $newBoard;
                $this->current_player = $nextPlayer;
                $this->status = $newStatus;
                $this->winner_id = $winnerId;
                
                return [
                    'success' => true,
                    'message' => 'Mouvement effectué avec succès.',
                    'board' => $newBoard,
                    'status' => $newStatus,
                    'winner_id' => $winnerId
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la mise à jour de la partie.'
                ];
            }
        } catch (PDOException $e) {
            error_log("Erreur de base de données lors du mouvement: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur de base de données: ' . $e->getMessage()
            ];
        } catch (Exception $e) {
            error_log("Erreur lors du mouvement: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Initialise un nouveau plateau de jeu
     * @return array Plateau de jeu
     */
    private function initializeBoard() {
        $board = [];
        
        // Initialiser un tableau 8x8
        for ($i = 0; $i < 8; $i++) {
            $board[$i] = [];
            for ($j = 0; $j < 8; $j++) {
                $board[$i][$j] = null;
            }
        }
        
        // Placer les pions du joueur 1 (noir) sur les 3 premières rangées
        for ($i = 0; $i < 3; $i++) {
            for ($j = 0; $j < 8; $j++) {
                if (($i + $j) % 2 == 1) {
                    $board[$i][$j] = [
                        'player' => 1,
                        'type' => 'pawn'
                    ];
                }
            }
        }
        
        // Placer les pions du joueur 2 (blanc) sur les 3 dernières rangées
        for ($i = 5; $i < 8; $i++) {
            for ($j = 0; $j < 8; $j++) {
                if (($i + $j) % 2 == 1) {
                    $board[$i][$j] = [
                        'player' => 2,
                        'type' => 'pawn'
                    ];
                }
            }
        }
        
        return $board;
    }
    
    /**
     * Vérifie si un mouvement est valide
     * 
     * @param int $fromRow Ligne de départ
     * @param int $fromCol Colonne de départ
     * @param int $toRow Ligne d'arrivée
     * @param int $toCol Colonne d'arrivée
     * @param int $playerNumber Numéro du joueur (1 ou 2)
     * @return array Résultat avec 'valid' => bool et message d'erreur si nécessaire
     */
    public function isValidMove($fromRow, $fromCol, $toRow, $toCol, $playerNumber) {
        try {
            $board = json_decode($this->board_state, true);
            
            // Vérifier si les coordonnées sont valides
            if ($fromRow < 0 || $fromRow > 7 || $fromCol < 0 || $fromCol > 7 ||
                $toRow < 0 || $toRow > 7 || $toCol < 0 || $toCol > 7) {
                return ['valid' => false, 'message' => 'Coordonnées invalides'];
            }
            
            // Vérifier si la case de départ contient une pièce du joueur
            if ($board[$fromRow][$fromCol] === null || $board[$fromRow][$fromCol]['player'] !== $playerNumber) {
                return ['valid' => false, 'message' => 'Pas de pièce à déplacer ou pièce adverse'];
            }
            
            // Vérifier si la case d'arrivée est vide
            if ($board[$toRow][$toCol] !== null) {
                return ['valid' => false, 'message' => 'Case d\'arrivée occupée'];
            }
            
            $pieceType = $board[$fromRow][$fromCol]['type'];
            $rowDiff = $toRow - $fromRow;
            $colDiff = $toCol - $fromCol;
            
            // Vérifier que le mouvement est en diagonale
            if (abs($rowDiff) !== abs($colDiff)) {
                return ['valid' => false, 'message' => 'Mouvement non diagonal'];
            }
            
            // Vérifier la direction pour les pions (sauf captures)
            if ($pieceType === 'pawn') {
                $isCapture = abs($rowDiff) === 2 && abs($colDiff) === 2;
                
                // Pour les mouvements simples (non-captures), les pions ne peuvent aller qu'en avant
                if (!$isCapture) {
                    if ($playerNumber === 1 && $rowDiff <= 0) {
                        return ['valid' => false, 'message' => 'Les pions doivent avancer'];
                    } else if ($playerNumber === 2 && $rowDiff >= 0) {
                        return ['valid' => false, 'message' => 'Les pions doivent avancer'];
                    }
                    
                    // Vérifier que le déplacement est de 1 case pour un mouvement simple
                    if (abs($rowDiff) !== 1 || abs($colDiff) !== 1) {
                        return ['valid' => false, 'message' => 'Déplacement simple invalide'];
                    }
                }
                // Pour les captures, vérifier qu'il y a bien une pièce adverse à capturer
                else if ($isCapture) {
                    $midRow = $fromRow + ($rowDiff / 2);
                    $midCol = $fromCol + ($colDiff / 2);
                    
                    if ($board[$midRow][$midCol] === null || $board[$midRow][$midCol]['player'] === $playerNumber) {
                        return ['valid' => false, 'message' => 'Aucune pièce à capturer'];
                    }
                    
                    return [
                        'valid' => true, 
                        'capture' => true,
                        'captureRow' => $midRow,
                        'captureCol' => $midCol
                    ];
                } else {
                    return ['valid' => false, 'message' => 'Mouvement invalide pour un pion'];
                }
            }
            // Pour les dames, vérifier le chemin
            else if ($pieceType === 'king') {
                $rowStep = ($rowDiff > 0) ? 1 : -1;
                $colStep = ($colDiff > 0) ? 1 : -1;
                $currentRow = $fromRow + $rowStep;
                $currentCol = $fromCol + $colStep;
                $foundOpponent = false;
                $captureRow = -1;
                $captureCol = -1;
                
                while ($currentRow !== $toRow && $currentCol !== $toCol) {
                    if ($board[$currentRow][$currentCol] !== null) {
                        // Si on a déjà trouvé un adversaire, le chemin est bloqué
                        if ($foundOpponent) {
                            return ['valid' => false, 'message' => 'Chemin bloqué'];
                        }
                        
                        // Si c'est une pièce du joueur, le chemin est bloqué
                        if ($board[$currentRow][$currentCol]['player'] === $playerNumber) {
                            return ['valid' => false, 'message' => 'Chemin bloqué par une de vos pièces'];
                        }
                        
                        // Si c'est une pièce adverse, on peut potentiellement la capturer
                        $foundOpponent = true;
                        $captureRow = $currentRow;
                        $captureCol = $currentCol;
                    }
                    
                    $currentRow += $rowStep;
                    $currentCol += $colStep;
                }
                
                // Si on a trouvé un adversaire, c'est une capture
                if ($foundOpponent) {
                    return [
                        'valid' => true, 
                        'capture' => true,
                        'captureRow' => $captureRow,
                        'captureCol' => $captureCol
                    ];
                }
            }
            
            return ['valid' => true];
        } catch (Exception $e) {
            error_log("Erreur dans isValidMove: " . $e->getMessage());
            return ['valid' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Applique un mouvement sur le plateau
     * @param array $board Plateau de jeu
     * @param int $fromRow Ligne de départ
     * @param int $fromCol Colonne de départ
     * @param int $toRow Ligne d'arrivée
     * @param int $toCol Colonne d'arrivée
     * @param int $player Joueur (1 ou 2)
     * @param array $validMove Résultat de la validation du mouvement
     * @return array Nouveau plateau de jeu
     */
    private function applyMove($board, $fromRow, $fromCol, $toRow, $toCol, $player, $validMove) {
        // Copier le plateau pour ne pas modifier l'original
        $newBoard = $board;
        
        // Copier la pièce à la nouvelle position
        $newBoard[$toRow][$toCol] = $newBoard[$fromRow][$fromCol];
        
        // Supprimer la pièce de l'ancienne position
        $newBoard[$fromRow][$fromCol] = null;
        
        // Si c'est une capture, supprimer la pièce capturée
        if (isset($validMove['capture']) && $validMove['capture']) {
            $capturedRow = $validMove['captureRow'];
            $capturedCol = $validMove['captureCol'];
            $newBoard[$capturedRow][$capturedCol] = null;
        }
        
        return $newBoard;
    }
    
    /**
     * Vérifie si la partie est terminée
     * @param array $board Plateau de jeu
     * @param int $player Joueur à vérifier
     * @return bool True si la partie est terminée, false sinon
     */
    private function checkGameOver($board, $player) {
        // Vérifier si le joueur a encore des pièces
        $hasPieces = false;
        
        for ($i = 0; $i < 8; $i++) {
            for ($j = 0; $j < 8; $j++) {
                if ($board[$i][$j] !== null && $board[$i][$j]['player'] == $player) {
                    $hasPieces = true;
                    break 2;
                }
            }
        }
        
        if (!$hasPieces) {
            return true;
        }
        
        // Vérifier si le joueur peut faire un mouvement
        for ($i = 0; $i < 8; $i++) {
            for ($j = 0; $j < 8; $j++) {
                if ($board[$i][$j] !== null && $board[$i][$j]['player'] == $player) {
                    // Vérifier les mouvements possibles
                    $directions = [];
                    
                    if ($board[$i][$j]['type'] == 'pawn') {
                        // Directions pour les pions
                        if ($player == 1) {
                            $directions = [[1, -1], [1, 1]]; // Vers le bas
                        } else {
                            $directions = [[-1, -1], [-1, 1]]; // Vers le haut
                        }
                    } else {
                        // Directions pour les dames (toutes les directions)
                        $directions = [[1, -1], [1, 1], [-1, -1], [-1, 1]];
                    }
                    
                    // Vérifier chaque direction
                    foreach ($directions as $dir) {
                        $newRow = $i + $dir[0];
                        $newCol = $j + $dir[1];
                        
                        // Vérifier les mouvements simples
                        if ($newRow >= 0 && $newRow < 8 && $newCol >= 0 && $newCol < 8 && $board[$newRow][$newCol] === null) {
                            return false; // Le joueur peut encore se déplacer
                        }
                        
                        // Vérifier les captures
                        $captureRow = $i + (2 * $dir[0]);
                        $captureCol = $j + (2 * $dir[1]);
                        
                        if ($captureRow >= 0 && $captureRow < 8 && $captureCol >= 0 && $captureCol < 8 &&
                            $board[$captureRow][$captureCol] === null &&
                            $board[$newRow][$newCol] !== null && $board[$newRow][$newCol]['player'] != $player) {
                            return false; // Le joueur peut encore capturer
                        }
                    }
                }
            }
        }
        
        // Le joueur n'a plus de mouvement possible
        return true;
    }
    
    /**
     * Obtient les mouvements d'une partie
     * @return array Liste des mouvements
     */
    public function getMoves() {
        try {
            $query = "SELECT m.*, u.username 
                     FROM moves m
                     JOIN users u ON m.user_id = u.id
                     WHERE m.game_id = :game_id
                     ORDER BY m.move_time ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':game_id', $this->id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des mouvements: " . $e->getMessage());
            return [];
        }
    }
}
