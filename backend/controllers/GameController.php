<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../db/Database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Game.php';

/**
 * Contrôleur pour gérer les parties de jeu de dames
 */
class GameController {
    private $db;
    private $game;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->game = new Game();
    }
    
    /**
     * Crée une nouvelle partie
     * @param array $data Données de la partie (player1_id, player2_id)
     * @return array Résultat de l'opération
     */
    public function createGame($data) {
        try {
            // Vérifier les données requises
            if (!isset($data['player1_id']) || !isset($data['player2_id'])) {
                return [
                    'success' => false,
                    'message' => 'Données requises manquantes.'
                ];
            }
            
            // Initialiser le plateau de jeu
            $boardState = $this->initializeBoard();
            
            // Déterminer le joueur qui commence (aléatoire)
            $currentPlayer = rand(0, 1) ? $data['player1_id'] : $data['player2_id'];
            
            // Insérer la partie dans la base de données
            $query = "INSERT INTO {$this->game->table} (player1_id, player2_id, current_player, status, board_state) 
                     VALUES (:player1_id, :player2_id, :current_player, 'in_progress', :board_state)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':player1_id', $data['player1_id'], PDO::PARAM_INT);
            $stmt->bindParam(':player2_id', $data['player2_id'], PDO::PARAM_INT);
            $stmt->bindParam(':current_player', $currentPlayer, PDO::PARAM_INT);
            
            $encodedBoard = json_encode($boardState);
            $stmt->bindParam(':board_state', $encodedBoard);
            
            if ($stmt->execute()) {
                $gameId = $this->db->lastInsertId();
                
                return [
                    'success' => true,
                    'message' => 'Partie créée avec succès.',
                    'game_id' => $gameId
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la création de la partie.'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur de base de données: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère une partie par son ID ou les parties actives d'un joueur
     * @param int $id ID de la partie ou ID du joueur
     * @param bool $history Si true, récupère l'historique des parties terminées
     * @return array|PDOStatement Données de la partie ou liste des parties
     */
    public function getGame($id, $history = false) {
        try {
            // Si c'est un ID de partie
            if (is_numeric($id) && $id > 0 && strpos($_SERVER['REQUEST_URI'], '/game/board.php') !== false) {
                $query = "SELECT g.*, 
                          u1.username as player1_name, 
                          u2.username as player2_name 
                          FROM {$this->game->table} g
                          JOIN users u1 ON g.player1_id = u1.id
                          JOIN users u2 ON g.player2_id = u2.id
                          WHERE g.id = :id";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $game = $stmt->fetch(PDO::FETCH_ASSOC);
                    $game['board_state'] = json_decode($game['board_state'], true);
                    
                    return [
                        'success' => true,
                        'game' => $game
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => 'Partie non trouvée.'
                ];
            } 
            // Si c'est un ID de joueur (parties actives ou historique)
            else {
                $status = $history ? 'finished' : 'in_progress';
                
                $query = "SELECT g.*, 
                          u1.username as player1_name, 
                          u2.username as player2_name 
                          FROM {$this->game->table} g
                          JOIN users u1 ON g.player1_id = u1.id
                          JOIN users u2 ON g.player2_id = u2.id
                          WHERE (g.player1_id = :player_id OR g.player2_id = :player_id) 
                          AND g.status = :status
                          ORDER BY g.created_at DESC";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':player_id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':status', $status);
                $stmt->execute();
                
                return $stmt;
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erreur de base de données: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère toutes les parties actives d'un joueur
     * @param int $playerId ID du joueur
     * @return PDOStatement Liste des parties actives
     */
    public function getActiveGames($playerId) {
        try {
            $query = "SELECT g.*, 
                      u1.username as player1_name, 
                      u2.username as player2_name 
                      FROM {$this->game->table} g
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
            error_log('Erreur lors de la récupération des parties actives: ' . $e->getMessage());
            
            // Créer un PDOStatement vide à retourner en cas d'erreur
            $emptyQuery = "SELECT 1 WHERE 1=0";
            $emptyStmt = $this->db->prepare($emptyQuery);
            $emptyStmt->execute();
            
            return $emptyStmt;
        }
    }
    
    /**
     * Récupère l'historique des parties d'un joueur
     * @param int $playerId ID du joueur
     * @return PDOStatement Liste des parties terminées
     */
    public function readGameHistory($playerId) {
        try {
            $query = "SELECT g.*, 
                      u1.username as player1_name, 
                      u2.username as player2_name 
                      FROM {$this->game->table} g
                      JOIN users u1 ON g.player1_id = u1.id
                      JOIN users u2 ON g.player2_id = u2.id
                      WHERE (g.player1_id = :player_id OR g.player2_id = :player_id) 
                      AND g.status = 'finished'
                      ORDER BY g.created_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt;
        } catch (PDOException $e) {
            error_log('Erreur lors de la récupération de l\'historique des parties: ' . $e->getMessage());
            
            // Créer un PDOStatement vide à retourner en cas d'erreur
            $emptyQuery = "SELECT 1 WHERE 1=0";
            $emptyStmt = $this->db->prepare($emptyQuery);
            $emptyStmt->execute();
            
            return $emptyStmt;
        }
    }
    
    /**
     * Récupère le statut d'une partie
     * @param array $data Données de la partie (game_id)
     * @return array Résultat de l'opération
     */
    public function getGameStatus($data) {
        try {
            // Vérifier les données requises
            if (!isset($data['game_id'])) {
                return [
                    'success' => false,
                    'message' => 'ID de partie manquant.'
                ];
            }
            
            $gameId = intval($data['game_id']);
            
            // Récupérer les informations sur la partie
            $query = "SELECT g.*, 
                       p1.username as player1_name, 
                       p2.username as player2_name 
                     FROM {$this->game->table} g 
                     JOIN users p1 ON g.player1_id = p1.id 
                     JOIN users p2 ON g.player2_id = p2.id 
                     WHERE g.id = :game_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':game_id', $gameId);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'Partie non trouvée.'
                ];
            }
            
            $game = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'game' => $game,
                'status' => $game['status'],
                'current_player' => $game['current_player'],
                'winner_id' => $game['winner_id']
            ];
        } catch (PDOException $e) {
            error_log('Erreur dans getGameStatus: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération du statut de la partie.'
            ];
        }
    }
    
    /**
     * Effectue un mouvement dans la partie
     * @param array $data Données du mouvement
     * @return array Résultat de l'opération
     */
    public function makeMove($data) {
        // Vérifier que les données nécessaires sont présentes
        if (!isset($data['game_id'])) {
            return [
                'success' => false,
                'message' => 'ID de partie manquant.'
            ];
        }
        
        $gameId = intval($data['game_id']);
        
        if (isset($data['move_data'])) {
            $moveData = $data['move_data'];
        } else {
            $moveData = $data;
        }
        
        if (!isset($moveData['from_row']) || !isset($moveData['from_col']) ||
            !isset($moveData['to_row']) || !isset($moveData['to_col'])) {
            return [
                'success' => false,
                'message' => 'Les coordonnées du mouvement sont requises.'
            ];
        }
        
        // Vérifier que la partie existe
        if (!$this->game->readOne($gameId)) {
            return [
                'success' => false,
                'message' => 'Partie non trouvée.'
            ];
        }
        
        // Vérifier que la partie est en cours
        if ($this->game->status !== 'in_progress') {
            return [
                'success' => false,
                'message' => 'Cette partie est déjà terminée.'
            ];
        }
        
        // Vérifier que c'est le tour du joueur
        $userId = Session::getUserId();
        if ($this->game->current_player != $userId) {
            return [
                'success' => false,
                'message' => 'Ce n\'est pas votre tour.'
            ];
        }
        
        // Effectuer le mouvement
        $result = $this->game->makeMove(
            intval($moveData['from_row']),
            intval($moveData['from_col']),
            intval($moveData['to_row']),
            intval($moveData['to_col'])
        );
        
        return $result;
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
     * @param array $board Plateau de jeu
     * @param int $fromRow Ligne de départ
     * @param int $fromCol Colonne de départ
     * @param int $toRow Ligne d'arrivée
     * @param int $toCol Colonne d'arrivée
     * @param int $player Joueur (1 ou 2)
     * @return array Résultat de la validation
     */
    private function isValidMove($board, $fromRow, $fromCol, $toRow, $toCol, $player) {
        // Vérifier que les coordonnées sont dans les limites du plateau
        if ($fromRow < 0 || $fromRow > 7 || $fromCol < 0 || $fromCol > 7 ||
            $toRow < 0 || $toRow > 7 || $toCol < 0 || $toCol > 7) {
            return ['valid' => false, 'message' => 'Coordonnées hors limites.'];
        }
        
        // Vérifier que la case de destination est vide
        if ($board[$toRow][$toCol] !== null) {
            return ['valid' => false, 'message' => 'La case de destination est occupée.'];
        }
        
        // Type de pièce (pion ou dame)
        $piece = $board[$fromRow][$fromCol];
        $pieceType = $piece['type'];
        
        // Direction du mouvement
        $rowDiff = $toRow - $fromRow;
        $colDiff = $toCol - $fromCol;
        $absRowDiff = abs($rowDiff);
        $absColDiff = abs($colDiff);
        
        // Vérifier que le mouvement est en diagonale
        if ($absRowDiff != $absColDiff) {
            return ['valid' => false, 'message' => 'Le mouvement doit être en diagonale.'];
        }
        
        // Cas d'un pion
        if ($pieceType == 'pawn') {
            // Les pions ne peuvent se déplacer qu'en avant
            if (($player == 1 && $rowDiff < 0) || ($player == 2 && $rowDiff > 0)) {
                return ['valid' => false, 'message' => 'Les pions ne peuvent se déplacer qu\'en avant.'];
            }
            
            // Déplacement simple d'une case
            if ($absRowDiff == 1) {
                return ['valid' => true];
            }
            
            // Prise d'une pièce (déplacement de 2 cases)
            if ($absRowDiff == 2) {
                $midRow = ($fromRow + $toRow) / 2;
                $midCol = ($fromCol + $toCol) / 2;
                
                // Vérifier qu'il y a une pièce adverse à capturer
                if ($board[$midRow][$midCol] !== null && $board[$midRow][$midCol]['player'] != $player) {
                    return ['valid' => true, 'captured' => true, 'capturedRow' => $midRow, 'capturedCol' => $midCol];
                } else {
                    return ['valid' => false, 'message' => 'Il n\'y a pas de pièce adverse à capturer.'];
                }
            }
            
            return ['valid' => false, 'message' => 'Mouvement invalide pour un pion.'];
        }
        
        // Cas d'une dame
        if ($pieceType == 'king') {
            // Déplacement simple d'une case
            if ($absRowDiff == 1) {
                return ['valid' => true];
            }
            
            // Vérifier s'il y a une seule pièce adverse sur le chemin
            $rowStep = $rowDiff > 0 ? 1 : -1;
            $colStep = $colDiff > 0 ? 1 : -1;
            
            $capturedRow = null;
            $capturedCol = null;
            $pieceCount = 0;
            
            for ($i = 1; $i < $absRowDiff; $i++) {
                $checkRow = $fromRow + ($i * $rowStep);
                $checkCol = $fromCol + ($i * $colStep);
                
                if ($board[$checkRow][$checkCol] !== null) {
                    $pieceCount++;
                    
                    if ($board[$checkRow][$checkCol]['player'] == $player) {
                        return ['valid' => false, 'message' => 'Vous ne pouvez pas sauter par-dessus vos propres pièces.'];
                    }
                    
                    $capturedRow = $checkRow;
                    $capturedCol = $checkCol;
                }
            }
            
            // Une dame peut se déplacer en diagonale sur plusieurs cases vides
            if ($pieceCount == 0) {
                return ['valid' => true];
            }
            
            // Une dame peut capturer une seule pièce adverse
            if ($pieceCount == 1) {
                return ['valid' => true, 'captured' => true, 'capturedRow' => $capturedRow, 'capturedCol' => $capturedCol];
            }
            
            return ['valid' => false, 'message' => 'Il y a trop de pièces sur le chemin.'];
        }
        
        return ['valid' => false, 'message' => 'Type de pièce inconnu.'];
    }
    
    /**
     * Déplace une pièce sur le plateau
     * @param array $board Plateau de jeu
     * @param int $fromRow Ligne de départ
     * @param int $fromCol Colonne de départ
     * @param int $toRow Ligne d'arrivée
     * @param int $toCol Colonne d'arrivée
     * @param int $player Joueur (1 ou 2)
     * @param array $validMove Résultat de la validation du mouvement
     * @return array Nouveau plateau de jeu
     */
    private function moveChecker($board, $fromRow, $fromCol, $toRow, $toCol, $player, $validMove) {
        // Copier la pièce à la nouvelle position
        $board[$toRow][$toCol] = $board[$fromRow][$fromCol];
        
        // Supprimer la pièce de l'ancienne position
        $board[$fromRow][$fromCol] = null;
        
        // Si c'est une capture, supprimer la pièce capturée
        if (isset($validMove['captured']) && $validMove['captured']) {
            $capturedRow = $validMove['capturedRow'];
            $capturedCol = $validMove['capturedCol'];
            $board[$capturedRow][$capturedCol] = null;
        }
        
        return $board;
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
}
