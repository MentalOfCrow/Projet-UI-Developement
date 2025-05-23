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
        try {
            // Établir la connexion à la base de données
            error_log("GameController::__construct - tentative de récupération de la connexion");
            $this->db = Database::getInstance()->getConnection();
            
            // Vérifier que la connexion est établie
            if (!($this->db instanceof PDO)) {
                error_log("GameController: Échec de la connexion à la base de données - La connexion n'est pas un objet PDO");
                throw new Exception("Impossible de se connecter à la base de données");
            }
            
            // Vérifions le type de $this->db
            error_log("GameController::__construct - type de this->db: " . get_class($this->db));
            
            // Vérifier si $this->db est une instance de PDO
            error_log("GameController::__construct - La connexion est bien une instance de PDO");

            // Initialiser le modèle Game
            error_log("GameController::__construct - initialisation du modèle Game");
            $this->game = new Game($this->db);
            error_log("GameController::__construct - modèle Game initialisé avec succès");
        } catch (Exception $e) {
            error_log("GameController: " . $e->getMessage());
            throw $e; // Propager l'exception pour qu'elle puisse être capturée dans play.php
        }
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
                
                // Mettre à jour l'index JSON des parties
                $jsonDb = JsonDatabase::getInstance();
                $jsonDb->updateGamesIndex($data['player1_id'], $gameId);
                $jsonDb->updateGamesIndex($data['player2_id'], $gameId);
                
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
     * Récupère les informations d'une partie
     * @param int $id ID de la partie
     * @param bool $history Inclure l'historique des mouvements
     * @return array Données de la partie
     */
    public function getGame($id, $history = false) {
        try {
            // Logs détaillés pour le débogage
            error_log("GameController::getGame - ID reçu: " . var_export($id, true));
            error_log("GameController::getGame - Type de l'ID: " . gettype($id));
            error_log("GameController::getGame - Demande d'historique: " . ($history ? 'Oui' : 'Non'));
            
            // Vérifier si l'ID est valide
            if (!$id || !is_numeric($id)) {
                error_log("GameController::getGame - ID invalide: " . var_export($id, true));
                return [
                    'success' => false,
                    'message' => 'ID de partie invalide.'
                ];
            }
            
            // Vérifier la connexion à la base de données
            if (!$this->db) {
                error_log("GameController::getGame - Connexion à la base de données non disponible");
                return [
                    'success' => false,
                    'message' => 'Connexion à la base de données non disponible.'
                ];
            }
            
            // Enregistrer cette action pour le débogage
            error_log("getGame appelé pour l'ID de partie: " . $id);
            
            // Utiliser directement this->db qui est déjà une connexion PDO
            $conn = $this->db;
            
            // Requête pour récupérer les informations de la partie
            $query = "SELECT g.*, u1.username as player1_name, u2.username as player2_name 
                      FROM games g 
                      LEFT JOIN users u1 ON g.player1_id = u1.id 
                      LEFT JOIN users u2 ON g.player2_id = u2.id 
                      WHERE g.id = :id";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            error_log("GameController::getGame - Exécution de la requête pour l'ID: " . $id);
            $stmt->execute();
            
            $game = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$game) {
                error_log("Partie non trouvée pour l'ID: " . $id);
                
                // Requête directe pour vérifier si la partie existe sans jointures
                $directQuery = "SELECT id, player1_id, player2_id FROM games WHERE id = :id";
                $directStmt = $conn->prepare($directQuery);
                $directStmt->bindParam(':id', $id, PDO::PARAM_INT);
                $directStmt->execute();
                
                $directResult = $directStmt->fetch(PDO::FETCH_ASSOC);
                if ($directResult) {
                    error_log("La partie existe mais n'a pas été récupérée avec les jointures. Données directes: " . json_encode($directResult));
                } else {
                    error_log("La partie n'existe pas du tout dans la base de données.");
                }
                
                return [
                    'success' => false,
                    'message' => 'Partie non trouvée.'
                ];
            }
            
            error_log("GameController::getGame - Partie trouvée avec succès pour l'ID: " . $id);
            
            // Si l'adversaire est un bot (identifié par player2_id = 0), définir un nom pour le bot
            if ($game['player2_id'] === '0' || $game['player2_id'] === 0) {
                $game['player2_name'] = 'Joueur';
            }
            
            // Si l'historique est demandé, récupérer les mouvements
            if ($history) {
                $query = "SELECT * FROM moves WHERE game_id = :game_id ORDER BY move_time ASC";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':game_id', $id, PDO::PARAM_INT);
                $stmt->execute();
                
                $moves = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $game['moves'] = $moves;
            }
            
            error_log("Partie récupérée avec succès: " . json_encode($game));
            
            return [
                'success' => true,
                'game' => $game
            ];
        } catch (PDOException $e) {
            error_log("Erreur dans getGame: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération de la partie: ' . $e->getMessage()
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
                      FROM " . Game::TABLE . " g
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
            error_log("Récupération de l'historique des parties pour le joueur ID: " . $playerId);
            
            $query = "SELECT g.*, 
                      u1.username as player1_name, 
                      u2.username as player2_name 
                      FROM {$this->game->table} g
                      LEFT JOIN users u1 ON g.player1_id = u1.id
                      LEFT JOIN users u2 ON g.player2_id = u2.id
                      WHERE (g.player1_id = :player_id OR g.player2_id = :player_id) 
                      AND g.status = 'finished'
                      ORDER BY g.updated_at DESC";
                      
            error_log("Requête SQL pour l'historique: " . $query);
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->execute();
            
            error_log("Nombre de parties trouvées: " . $stmt->rowCount());
            
            // Journaliser le contenu des résultats pour le débogage
            $debugResults = [];
            $resultsCopy = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($resultsCopy as $game) {
                $debugResults[] = [
                    'id' => $game['id'],
                    'player1_id' => $game['player1_id'],
                    'player2_id' => $game['player2_id'],
                    'winner_id' => $game['winner_id'],
                    'status' => $game['status']
                ];
            }
            error_log("Résultats de l'historique: " . json_encode($debugResults));
            
            // Réexécuter la requête car fetchAll() a consommé tous les résultats
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
     * Effectue un mouvement dans une partie
     * @param int $game_id ID de la partie
     * @param int $from_row Ligne de départ
     * @param int $from_col Colonne de départ
     * @param int $to_row Ligne d'arrivée
     * @param int $to_col Colonne d'arrivée
     * @param int $player Joueur qui effectue le mouvement (1 ou 2)
     * @return array Résultat du mouvement
     */
    public function makeMove($game_id, $from_row, $from_col, $to_row, $to_col, $player) {
        try {
            // Récupérer l'état actuel de la partie
            $query = "SELECT * FROM games WHERE id = :game_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':game_id', $game_id);
            $stmt->execute();
            
            $game = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$game) {
                return [
                    'success' => false,
                    'message' => 'Partie introuvable.'
                ];
            }
            
            // Vérifier que la partie est en cours
            if ($game['status'] !== 'in_progress') {
                return [
                    'success' => false,
                    'message' => 'Cette partie est terminée.'
                ];
            }
            
            // Vérifier que c'est au tour du joueur
            if ($game['current_player'] != $player) {
                return [
                    'success' => false,
                    'message' => 'Ce n\'est pas votre tour.'
                ];
            }
            
            // Récupérer l'état du plateau
            $board = json_decode($game['board_state'], true);
            
            // Vérifier que la case de départ contient une pièce du joueur
            if (!isset($board[$from_row][$from_col]) || 
                !is_array($board[$from_row][$from_col]) || 
                $board[$from_row][$from_col]['player'] != $player) {
                    
                return [
                    'success' => false,
                    'message' => 'Aucune pièce à vous sur la case de départ.'
                ];
            }
            
            // Vérifier que la case d'arrivée est vide
            if (isset($board[$to_row][$to_col]) && $board[$to_row][$to_col] !== null) {
                return [
                    'success' => false,
                    'message' => 'La case d\'arrivée est déjà occupée.'
                ];
            }
            
            // Vérifier si le mouvement est valide
            $validMove = $this->isValidMove($board, $from_row, $from_col, $to_row, $to_col, $player);
            
            if (!$validMove['valid']) {
                return [
                    'success' => false,
                    'message' => 'Mouvement invalide: ' . $validMove['message']
                ];
            }
            
            // Capture d'une pièce adverse
            $captured = isset($validMove['capture']) && $validMove['capture'];
            
            // Effectuer le mouvement sur le plateau
            $newBoard = $this->moveChecker($board, $from_row, $from_col, $to_row, $to_col, $player, $validMove);
            
            // Vérifier si la pièce devient une dame
            $becomes_king = false;
            if (($player == 1 && $to_row == 7) || ($player == 2 && $to_row == 0)) {
                $becomes_king = true;
                $newBoard[$to_row][$to_col]['type'] = 'king';
            }
            
            // Vérifier si le jeu est terminé (l'autre joueur n'a plus de pièces ou de mouvements)
            $nextPlayer = $player == 1 ? 2 : 1;
            $gameOver = $this->checkGameOver($newBoard, $nextPlayer);
            
            // Déterminer le prochain joueur (si le joueur actuel peut encore capturer, c'est encore son tour)
            $canCaptureAgain = false;
            if ($captured) {
                // Vérifier si la pièce déplacée peut encore capturer
                $canCaptureAgain = $this->canCaptureFrom($newBoard, $to_row, $to_col, $player);
                
                if ($canCaptureAgain) {
                    $nextPlayer = $player; // Le joueur continue son tour
                }
            }
            
            // Mettre à jour l'état de la partie dans la base de données
            $boardJson = json_encode($newBoard);
            
            // Si le jeu est terminé, mettre à jour le statut et déclarer le gagnant
            $status = $gameOver ? 'finished' : 'in_progress';
            $winner_id = null;
            
            if ($gameOver) {
                // Déterminer le gagnant
                $winner_id = $player == 1 ? $game['player1_id'] : $game['player2_id'];
            }
            
            // Mettre à jour la partie
            $query = "UPDATE games SET 
                     board_state = :board_state, 
                     current_player = :next_player, 
                     status = :status, 
                     winner_id = :winner_id,
                     updated_at = NOW() 
                     WHERE id = :game_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':board_state', $boardJson);
            $stmt->bindParam(':next_player', $nextPlayer);
            $stmt->bindParam(':status', $status);
            // Si l'IA est gagnante (winner_id = 0) on met NULL pour éviter la contrainte FK,
            // on continuera à nous baser sur le champ `result` pour l'affichage et les stats.
            $finalWinnerId = ($winner_id === 0) ? null : $winner_id; // Peut être null si match nul ou bot gagnant
            if (is_null($finalWinnerId)) {
                $stmt->bindValue(':winner_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':winner_id', $finalWinnerId, PDO::PARAM_INT);
            }
            $stmt->bindParam(':game_id', $game_id);
            
            if (!$stmt->execute()) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la mise à jour de la partie.'
                ];
            }
            
            // Enregistrer le mouvement dans l'historique
            $this->recordMove($game_id, $player == 1 ? $game['player1_id'] : $game['player2_id'], $from_row, $from_col, $to_row, $to_col, $captured);
            
            // Si la partie est contre un bot et que c'est maintenant son tour, faire jouer le bot
            if ($game['player2_id'] == 0 && $nextPlayer == 2 && !$gameOver) {
                $this->makeBotMove($game_id);
            }
            
            return [
                'success' => true,
                'message' => $captured ? 'Pièce capturée !' : 'Mouvement effectué avec succès.',
                'became_king' => $becomes_king,
                'game_over' => $gameOver,
                'next_player' => $nextPlayer,
                'can_capture_again' => $canCaptureAgain
            ];
            
        } catch (Exception $e) {
            error_log("Erreur dans makeMove: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors du mouvement: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Vérifie si un mouvement est valide
     * @param array $board État du plateau
     * @param int $from_row Ligne de départ
     * @param int $from_col Colonne de départ
     * @param int $to_row Ligne d'arrivée
     * @param int $to_col Colonne d'arrivée
     * @param int $player Joueur qui effectue le mouvement (1 ou 2)
     * @return array Résultat de la validation avec des informations supplémentaires
     */
    public function isValidMove($board, $from_row, $from_col, $to_row, $to_col, $player) {
        // Vérifier si les coordonnées sont dans les limites du plateau
        if ($from_row < 0 || $from_row > 7 || $from_col < 0 || $from_col > 7 ||
            $to_row < 0 || $to_row > 7 || $to_col < 0 || $to_col > 7) {
            return [
                'valid' => false, 
                'message' => 'Position hors des limites du plateau.'
            ];
        }
        
        // Vérifier que la pièce existe et appartient au joueur
        if (!isset($board[$from_row][$from_col]) || 
            !is_array($board[$from_row][$from_col]) || 
            !isset($board[$from_row][$from_col]['player']) || 
            $board[$from_row][$from_col]['player'] != $player) {
            return [
                'valid' => false, 
                'message' => 'Aucune pièce du joueur à la position de départ.'
            ];
        }
        
        // Vérifier que la destination est vide
        if (isset($board[$to_row][$to_col]) && $board[$to_row][$to_col] !== null) {
            return [
                'valid' => false, 
                'message' => 'La position de destination n\'est pas vide.'
            ];
        }
        
        // Vérifier que la position de départ et de destination sont différentes
        if ($from_row == $to_row && $from_col == $to_col) {
            return [
                'valid' => false, 
                'message' => 'La position de départ et de destination sont identiques.'
            ];
        }
        
        // Déterminer si la pièce est une dame
        $isKing = isset($board[$from_row][$from_col]['type']) && $board[$from_row][$from_col]['type'] === 'king';
        
        // Direction de déplacement pour les pions (selon le joueur)
        $forward_direction = ($player == 1) ? 1 : -1;
        
        // Calculer la distance du mouvement
        $row_distance = $to_row - $from_row;
        $col_distance = abs($to_col - $from_col);
        
        // Vérifier si le mouvement est en diagonale
        if (abs($row_distance) != $col_distance) {
                return [
                    'valid' => false, 
                'message' => 'Les pièces doivent se déplacer en diagonale.'
            ];
        }
        
        // Vérifier si le joueur a une capture obligatoire disponible
        $hasCapture = $this->hasForcedCapture($board, $player);
        
        // Pour les pions (non-rois)
        if (!$isKing) {
            // Si c'est un déplacement simple (distance de 1)
            if (abs($row_distance) == 1) {
                // Vérifier si le joueur est obligé de capturer ailleurs
                if ($hasCapture) {
                return [
                    'valid' => false, 
                        'message' => 'Vous avez une capture obligatoire à effectuer.'
                ];
            }
            
                // Pour les déplacements simples uniquement, vérifier la direction
                if (($player == 1 && $row_distance < 0) || ($player == 2 && $row_distance > 0)) {
            return [
                        'valid' => false,
                        'message' => 'Les pions ne peuvent se déplacer que vers l\'avant pour les mouvements simples.'
            ];
        }
        
                return [
                    'valid' => true,
                    'message' => 'Mouvement valide.',
                    'capture' => false
                ];
            }
            // Si c'est un mouvement de capture (distance de 2)
            else if (abs($row_distance) == 2) {
                // Calculer la position de la pièce à capturer
            $capture_row = $from_row + ($row_distance / 2);
            $capture_col = $from_col + (($to_col - $from_col) / 2);
            
                // Vérifier si la case intermédiaire contient une pièce adverse
                if (isset($board[$capture_row][$capture_col]) && 
                    is_array($board[$capture_row][$capture_col]) &&
                    isset($board[$capture_row][$capture_col]['player']) && 
                    $board[$capture_row][$capture_col]['player'] != $player) {
                    
                    // Si on a une capture disponible mais pas celle qu'on essaie de faire,
                    // vérifier si cette pièce spécifique peut capturer
                    if ($hasCapture && !$this->canCaptureFrom($board, $from_row, $from_col, $player)) {
                return [
                    'valid' => false, 
                            'message' => 'Vous devez capturer avec une pièce qui peut le faire.'
                ];
            }
            
                    // Capture valide pour un pion (peut être en avant ou en arrière)
            return [
                'valid' => true,
                        'message' => 'Capture valide.',
                'capture' => true,
                'captured' => [
                    'row' => $capture_row,
                    'col' => $capture_col
                ]
            ];
        }
        
                        return [
                            'valid' => false, 
                    'message' => 'Aucune pièce adverse à capturer.'
                ];
            }
        }
        // Logique pour les dames
        else {
            // Vérifier si le joueur est obligé de capturer et si cette dame peut capturer
            if ($hasCapture && !$this->canCaptureFrom($board, $from_row, $from_col, $player)) {
                return [
                    'valid' => false,
                    'message' => 'Vous avez une capture obligatoire à effectuer avec une autre pièce.'
                ];
            }
            
            // Déterminer la direction du mouvement
            $row_dir = ($row_distance > 0) ? 1 : -1;
            $col_dir = ($to_col > $from_col) ? 1 : -1;
            
            // Vérifier si le chemin est libre
            $current_row = $from_row + $row_dir;
            $current_col = $from_col + $col_dir;
            $capture = false;
            $captured = null;
            
            // Corriger la condition de la boucle pour vérifier correctement le chemin
            while ($current_row != $to_row || $current_col != $to_col) {
                if (isset($board[$current_row][$current_col]) && $board[$current_row][$current_col] !== null) {
                    // Si on a déjà trouvé une pièce sur le chemin, le mouvement est invalide
                    if ($capture) {
                return [
                    'valid' => false, 
                            'message' => 'Une dame ne peut pas sauter par-dessus plusieurs pièces.'
                ];
            }
            
                    // Si c'est une pièce du même joueur, le mouvement est invalide
                    if ($board[$current_row][$current_col]['player'] == $player) {
                return [
                            'valid' => false,
                            'message' => 'Une dame ne peut pas sauter par-dessus ses propres pièces.'
                        ];
                    }
                    
                    // C'est une pièce adverse, marquer comme capture potentielle
                    $capture = true;
                    $captured = [
                        'row' => $current_row,
                        'col' => $current_col
                    ];
                }
                
                $current_row += $row_dir;
                $current_col += $col_dir;
                
                // Vérifier si on est toujours sur le plateau
                if ($current_row < 0 || $current_row > 7 || $current_col < 0 || $current_col > 7) {
                    return [
                        'valid' => false, 
                        'message' => 'Mouvement hors des limites du plateau.'
                    ];
                }
                }
                
            // Si on a trouvé une capture, retourner les informations
            if ($capture) {
                return [
                    'valid' => true,
                    'message' => 'Capture valide.',
                    'capture' => true,
                    'captured' => $captured
                ];
            }
            
            // Mouvement simple valide pour une dame
            return [
                'valid' => true,
                'message' => 'Mouvement valide.',
                    'capture' => false
                ];
        }
        
        return [
            'valid' => false, 
            'message' => 'Mouvement invalide.'
        ];
    }
    
    /**
     * Vérifie si le joueur a une capture obligatoire disponible
     * @param array $board État du plateau
     * @param int $player Joueur à vérifier (1 ou 2)
     * @return bool True si une capture est disponible
     */
    private function hasForcedCapture($board, $player) {
        // Parcourir toutes les pièces du joueur
        for ($row = 0; $row < 8; $row++) {
            for ($col = 0; $col < 8; $col++) {
                // Vérifier si la case contient une pièce du joueur
                if (isset($board[$row][$col]) && 
                    is_array($board[$row][$col]) && 
                    isset($board[$row][$col]['player']) && 
                    $board[$row][$col]['player'] == $player) {
                    
                    // Vérifier si cette pièce peut capturer
                    if ($this->canCaptureFrom($board, $row, $col, $player)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Vérifie si une pièce peut effectuer une capture depuis sa position
     * @param array $board État du plateau
     * @param int $row Ligne de la pièce
     * @param int $col Colonne de la pièce
     * @param int $player Joueur propriétaire de la pièce
     * @return bool True si la pièce peut capturer
     */
    public function canCaptureFrom($board, $row, $col, $player) {
        if (!isset($board[$row][$col]) || !is_array($board[$row][$col]) || $board[$row][$col]['player'] != $player) {
            return false;
        }
        
        $isKing = isset($board[$row][$col]['type']) && $board[$row][$col]['type'] === 'king';
        $directions = [
            ['row' => -1, 'col' => -1], // Haut-Gauche
            ['row' => -1, 'col' => 1],  // Haut-Droite
            ['row' => 1, 'col' => -1],  // Bas-Gauche
            ['row' => 1, 'col' => 1]    // Bas-Droite
        ];

        // Pour les pions normaux, on vérifie maintenant toutes les directions
        // Les pions peuvent capturer dans toutes les directions, même arrière
        // C'est seulement pour les déplacements simples qu'ils sont limités à l'avant

        foreach ($directions as $dir) {
            // Pour les pions, on vérifie seulement une case plus loin
            if (!$isKing) {
                $captureRow = $row + $dir['row'];
                $captureCol = $col + $dir['col'];
                $landingRow = $row + 2 * $dir['row'];
                $landingCol = $col + 2 * $dir['col'];

                // Vérifier si la position de capture est dans les limites du plateau
                if ($captureRow < 0 || $captureRow > 7 || $captureCol < 0 || $captureCol > 7) {
                    continue;
                }

                // Vérifier si la position d'atterrissage est dans les limites du plateau
                if ($landingRow < 0 || $landingRow > 7 || $landingCol < 0 || $landingCol > 7) {
                    continue;
                }

                // Vérifier s'il y a une pièce adverse à capturer
                if (isset($board[$captureRow][$captureCol]) && 
                    is_array($board[$captureRow][$captureCol]) && 
                    isset($board[$captureRow][$captureCol]['player']) && 
                    $board[$captureRow][$captureCol]['player'] != $player) {
                    
                    // Vérifier si la case d'atterrissage est vide
                    if (!isset($board[$landingRow][$landingCol]) || $board[$landingRow][$landingCol] === null) {
                        return true;
                    }
                }
            } 
            // Pour les dames, on vérifie plus loin (jusqu'au bord du plateau)
            else {
                $step_row = $dir['row'];
                $step_col = $dir['col'];
                $capture_row = -1;
                $capture_col = -1;
                $found_opponent = false;
                $current_row = $row + $step_row;
                $current_col = $col + $step_col;
                
                while ($current_row >= 0 && $current_row <= 7 && $current_col >= 0 && $current_col <= 7) {
                    // Si on trouve une pièce
                    if (isset($board[$current_row][$current_col]) && $board[$current_row][$current_col] !== null) {
                        // Si on a déjà trouvé un adversaire, on ne peut pas capturer (chemin bloqué)
                        if ($found_opponent) {
                            break;
                        }
                        
                        // Si c'est notre propre pièce, on ne peut pas capturer
                        if ($board[$current_row][$current_col]['player'] == $player) {
                            break;
                        }
                        
                        // C'est une pièce adverse, on peut potentiellement la capturer
                        $found_opponent = true;
                        $capture_row = $current_row;
                        $capture_col = $current_col;
                        
                    } 
                    // Si on a trouvé un adversaire et qu'on arrive sur une case vide, on peut capturer
                    else if ($found_opponent) {
                        return true;
                    }
                    
                    // Avancer à la case suivante
                    $current_row += $step_row;
                    $current_col += $step_col;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Effectue le mouvement sur le plateau et retourne le nouvel état
     * @param array $board État du plateau
     * @param int $from_row Ligne de départ
     * @param int $from_col Colonne de départ
     * @param int $to_row Ligne d'arrivée
     * @param int $to_col Colonne d'arrivée
     * @param int $player Joueur qui effectue le mouvement
     * @param array $validMove Résultat de la validation du mouvement
     * @return array Nouvel état du plateau
     */
    private function moveChecker($board, $from_row, $from_col, $to_row, $to_col, $player, $validMove) {
        // Copier la pièce
        $piece = $board[$from_row][$from_col];
        
        // Déplacer la pièce
        $board[$to_row][$to_col] = $piece;
        $board[$from_row][$from_col] = null;
        
        // Si c'est une capture, supprimer la pièce capturée
        $captured = false;
        if (isset($validMove['capture']) && $validMove['capture'] && isset($validMove['captured'])) {
            $capture_row = $validMove['captured']['row'];
            $capture_col = $validMove['captured']['col'];
            
            error_log("Capture: Joueur $player supprime la pièce à la position [$capture_row,$capture_col]");
            
            // Vérifier que la pièce capturée existe et est adverse
            if (isset($board[$capture_row][$capture_col]) && 
                is_array($board[$capture_row][$capture_col]) && 
                $board[$capture_row][$capture_col]['player'] != $player) {
                
                // Supprimer la pièce
            $board[$capture_row][$capture_col] = null;
                $captured = true;
                
                error_log("Capture réussie: Pièce supprimée à [$capture_row,$capture_col]");
            } else {
                error_log("Erreur de capture: Aucune pièce adverse trouvée à [$capture_row,$capture_col]");
            }
        }
        
        return $board;
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
     * Crée une partie contre un bot
     * @param int $user_id ID de l'utilisateur
     * @return array Résultat de la création
     */
    public function createBotGame($user_id) {
        try {
            // Utiliser directement this->db qui est déjà une connexion PDO
            $conn = $this->db;
            
            // Log pour débogage
            error_log("Création d'une partie contre un bot pour l'utilisateur ID: " . $user_id);
            
            // Commencer une transaction
            $conn->beginTransaction();
            
            // Initialiser le plateau
            $board = $this->initializeBoard();
            
            // Créer la partie
            $query = "INSERT INTO games (player1_id, player2_id, current_player, status, board_state, created_at, updated_at) 
                      VALUES (:player1_id, 0, 1, 'in_progress', :board_state, NOW(), NOW())";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':player1_id', $user_id, PDO::PARAM_INT);
            
            // Convertir le tableau du plateau en JSON et le stocker dans une variable avant de le lier
            $boardJson = json_encode($board);
            $stmt->bindParam(':board_state', $boardJson);
            
            $stmt->execute();
            
            // Récupérer l'ID de la partie créée
            $game_id = $conn->lastInsertId();
            
            // Vérifier que la partie a bien été créée
            $verifyQuery = "SELECT id FROM games WHERE id = :game_id";
            $verifyStmt = $conn->prepare($verifyQuery);
            $verifyStmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
            $verifyStmt->execute();
            
            if (!$verifyStmt->fetch()) {
                error_log("Erreur: La partie n'a pas été créée correctement. ID: " . $game_id);
                $conn->rollBack();
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la création de la partie'
                ];
            }
            
            error_log("Partie créée avec l'ID: " . $game_id);
            
            // Valider la transaction
            $conn->commit();
            
            return [
                'success' => true,
                'message' => 'Partie contre bot créée avec succès',
                'game_id' => $game_id
            ];
        } catch (PDOException $e) {
            // En cas d'erreur, annuler la transaction
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            
            error_log("Erreur dans createBotGame: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la création de la partie: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Termine une partie et déclare un gagnant
     * @param int $game_id ID de la partie
     * @param int $winner_id ID du joueur gagnant
     * @param int|null $loser_id ID du joueur perdant en cas d'abandon contre bot
     * @return bool Succès de l'opération
     */
    public function endGame($game_id, $winner_id, $loser_id = null) {
        try {
            // Démarrer une transaction
            $this->db->beginTransaction();
            
            // Récupérer les informations de la partie
            $query = "SELECT * FROM games WHERE id = :game_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':game_id', $game_id);
            $stmt->execute();
            
            $gameInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$gameInfo) {
                error_log("endGame: Partie ID {$game_id} introuvable");
                $this->db->rollBack();
                return false;
            }
            
            // Si la partie est déjà terminée, ne rien faire
            if ($gameInfo['status'] === 'finished') {
                error_log("endGame: La partie ID {$game_id} est déjà terminée (statut: {$gameInfo['status']})");
                $this->db->rollBack();
                return true;
            }
            
            error_log("endGame: Traitement de fin de partie ID: {$game_id}, winner_id: " . ($winner_id ?? 'null') . ", loser_id: " . ($loser_id ?? 'null'));
            error_log("endGame: Infos partie - player1_id: {$gameInfo['player1_id']}, player2_id: {$gameInfo['player2_id']}");
            
            // Vérifier si c'est un match nul (les deux joueurs sont bloqués)
            $isDrawGame = false;
            
            // Correction: Partie contre l'IA ne peut jamais être un match nul
            // Si winner_id est null et que c'est une partie contre l'IA, l'IA gagne
            if ($gameInfo['player2_id'] == 0 && $winner_id === null) {
                $winner_id = 0; // L'IA gagne par défaut
                error_log("endGame: Partie contre l'IA avec winner_id null, l'IA gagne automatiquement");
            }
            // Pour les parties normales, vérifier si c'est un vrai match nul
            else if ($winner_id === null && $loser_id === null) {
                // Analyser l'état du plateau pour confirmer que c'est bien un match nul
                $board = json_decode($gameInfo['board_state'], true);
                $player1Blocked = $this->checkGameOver($board, 1);
                $player2Blocked = $this->checkGameOver($board, 2);
                
                // C'est un match nul seulement si les deux joueurs sont bloqués
                $isDrawGame = $player1Blocked && $player2Blocked;
                
                if (!$isDrawGame) {
                    // Si ce n'est pas un match nul, déterminer le gagnant
                    if ($player1Blocked) {
                        // Le joueur 1 est bloqué, le joueur 2 gagne
                        $winner_id = $gameInfo['player2_id'];
                    } else if ($player2Blocked) {
                        // Le joueur 2 est bloqué, le joueur 1 gagne
                        $winner_id = $gameInfo['player1_id'];
                    }
                    
                    error_log("endGame: Match nul indiqué mais situation non évaluée comme match nul. Gagnant déterminé: " . $winner_id);
                }
            }
            
            // Cas spécial: le joueur abandonne contre l'IA
            if ($gameInfo['player2_id'] == 0) {
                // Si c'est une partie contre l'IA
                if ($winner_id == 0) {
                    // L'IA est marquée comme gagnante, donc le joueur humain a abandonné
                    $loser_id = $gameInfo['player1_id'];
                    error_log("endGame: Cas spécial - Abandon contre l'IA par le joueur {$loser_id}");
                } else if ($winner_id === null && $loser_id !== null) {
                    // Le joueur est indiqué explicitement comme perdant
                    $winner_id = 0; // L'IA gagne
                    error_log("endGame: Cas spécial - Joueur {$loser_id} a perdu contre l'IA");
                }
            }
            
            // Mettre à jour le statut de la partie - valeur par défaut pour winner_id
            $finalWinnerId = ($winner_id === 0) ? null : $winner_id; // Peut être null si match nul ou bot gagnant
            $status = 'finished';
            
            // -----------------------------------------------------------------
            // Déterminer le résultat de la partie (champ "result")
            // -----------------------------------------------------------------
            // On se base sur $winner_id AVANT conversion FK (0 → NULL) pour que
            // les parties contre l'IA et les abandons soient correctement
            // comptabilisées. 3 cas :
            //   • $isDrawGame  → "draw"
            //   • $winner_id == player1_id → "player1_won"
            //   • Sinon (player2_id ou bot) → "player2_won"
            $result = null;
            if ($isDrawGame) {
                $result = 'draw';
            } elseif ($winner_id == $gameInfo['player1_id']) {
                $result = 'player1_won';
            } else {
                // Gagnant = joueur 2 ou bot IA
                $result = 'player2_won';
            }
            
            // Vérifier si la colonne `result` existe (certaines bases plus anciennes ne l'ont pas)
            $hasResultColumn = false;
            try {
                $colStmt = $this->db->prepare("SHOW COLUMNS FROM games LIKE 'result'");
                $colStmt->execute();
                $hasResultColumn = $colStmt->rowCount() > 0;
            } catch (PDOException $ex) {
                // On ignore – on considérera que la colonne n'existe pas
            }

            if ($hasResultColumn) {
                $updateQuery = "UPDATE games SET 
                         status = :status, 
                         winner_id = :winner_id,
                         result = :result,
                         updated_at = NOW()
                         WHERE id = :game_id";
            } else {
                $updateQuery = "UPDATE games SET 
                         status = :status, 
                         winner_id = :winner_id,
                         updated_at = NOW()
                         WHERE id = :game_id";
            }

            $updateStmt = $this->db->prepare($updateQuery);

            $updateStmt->bindParam(':status', $status);
            if (is_null($finalWinnerId)) {
                $updateStmt->bindValue(':winner_id', null, PDO::PARAM_NULL);
            } else {
                $updateStmt->bindValue(':winner_id', $finalWinnerId, PDO::PARAM_INT);
            }
            if ($hasResultColumn) {
                $updateStmt->bindParam(':result', $result);
            }
            $updateStmt->bindParam(':game_id', $game_id);
            
            if (!$updateStmt->execute()) {
                $this->db->rollBack();
                error_log("endGame: Échec de la mise à jour du statut de la partie ID: " . $game_id);
                return false;
            }
            
            error_log("endGame: Statut de la partie mis à jour avec succès, statut: '{$status}', winner_id: " . ($finalWinnerId ?? 'null') . ", result: " . ($result ?? 'null') . ", isDrawGame: " . ($isDrawGame ? 'true' : 'false'));
            
            // Mettre à jour les statistiques manuellement (en plus du trigger)
            $player1_id = $gameInfo['player1_id'];
            $player2_id = $gameInfo['player2_id'];
            
            // Créer des notifications pour les joueurs
            $this->createGameCompletionNotifications($game_id, $player1_id, $player2_id, $winner_id, $isDrawGame);
            
            // Cas spécial d'abandon contre un bot
            if ($player2_id == 0) {
                if ($winner_id == 0 || $loser_id !== null) {
                    error_log("endGame: Mise à jour des stats pour le joueur qui a perdu contre l'IA: " . ($loser_id ?? $player1_id));
                    // Le joueur a perdu contre le bot
                    $this->updatePlayerStats($loser_id ?? $player1_id, false);
                } else if ($winner_id == $player1_id) {
                    // Le joueur humain a gagné contre l'IA
                    error_log("endGame: Mise à jour des stats pour le joueur qui a gagné contre l'IA: {$player1_id}");
                    $this->updatePlayerStats($player1_id, true);
                }
                
                // Valider la transaction
                $this->db->commit();
                
                error_log("Partie contre l'IA terminée avec succès. ID: " . $game_id);
                return true;
            }
            
            // Match nul - pas de mise à jour des stats victoire/défaite, seulement partie jouée
            if ($isDrawGame) {
                error_log("endGame: Match nul confirmé. Mise à jour statistiques sans victoire/défaite.");
                
                // Mise à jour des statistiques du joueur 1 pour un match nul
                $this->updatePlayerStatsForDraw($player1_id);
                
                // Mise à jour des statistiques du joueur 2 pour un match nul (seulement s'il n'est pas un bot)
                if ($player2_id != 0) {
                    $this->updatePlayerStatsForDraw($player2_id);
                }
            } else {
                // Mise à jour des statistiques du joueur 1
                $player1Won = ($winner_id == $player1_id);
                $this->updatePlayerStats($player1_id, $player1Won);
                error_log("endGame: Statistiques du joueur 1 (ID: {$player1_id}) mises à jour, victoire: " . ($player1Won ? 'oui' : 'non'));
                
                // Mise à jour des statistiques du joueur 2 (seulement s'il n'est pas un bot, player_id != 0)
                if ($player2_id != 0) {
                    $player2Won = ($winner_id == $player2_id);
                    $this->updatePlayerStats($player2_id, $player2Won);
                    error_log("endGame: Statistiques du joueur 2 (ID: {$player2_id}) mises à jour, victoire: " . ($player2Won ? 'oui' : 'non'));
                }
            }
            
            // Valider la transaction
            $this->db->commit();
            error_log("endGame: Transaction confirmée avec succès");
            
            // Synchroniser les statistiques JSON
            $this->syncJsonStatsAfterGame($game_id);
            
            return true;
        } catch (PDOException $e) {
            // En cas d'erreur, annuler la transaction
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            error_log("Erreur dans endGame: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Synchronise les statistiques JSON après la fin d'une partie
     * @param int $game_id ID de la partie
     */
    private function syncJsonStatsAfterGame($game_id) {
        try {
            // Appeler directement la classe JsonDatabase
            require_once __DIR__ . '/../db/JsonDatabase.php';
            $jsonDb = JsonDatabase::getInstance();
            
            // Exporter (ou mettre à jour) la partie complète dans la base JSON
            $gameRes = $this->getGame($game_id, true);
            if ($gameRes['success']) {
                $jsonDb->saveGame($gameRes['game']);
            }
            
            // Mettre à jour les statistiques et le classement ELO à partir de la partie JSON
            $jsonDb->updateStatsAfterGame($game_id);
            $jsonDb->updateEloRating($game_id);
            
            error_log("syncJsonStatsAfterGame : stats & ELO mis à jour pour la partie ID " . $game_id);
        } catch (Exception $e) {
            error_log("Erreur lors de la synchronisation des statistiques JSON: " . $e->getMessage());
        }
    }
    
    /**
     * Crée des notifications pour les joueurs lorsqu'une partie est terminée
     * 
     * @param int $game_id ID de la partie
     * @param int $player1_id ID du joueur 1
     * @param int $player2_id ID du joueur 2
     * @param int|null $winner_id ID du joueur gagnant (null si match nul)
     * @param bool $isDrawGame True si le match est nul
     * @return bool Succès de l'opération
     */
    private function createGameCompletionNotifications($game_id, $player1_id, $player2_id, $winner_id, $isDrawGame) {
        try {
            // Charger le contrôleur de notifications si nécessaire
            require_once __DIR__ . '/NotificationController.php';
            $notificationController = new NotificationController();
            
            // Récupérer les noms des joueurs
            $stmt = $this->db->prepare("SELECT id, username FROM users WHERE id IN (?, ?)");
            $stmt->execute([$player1_id, $player2_id == 0 ? $player1_id : $player2_id]);
            $players = [];
            
            while ($player = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $players[$player['id']] = $player['username'];
            }
            
            // Message spécifique pour le joueur 1
            if ($isDrawGame) {
                // Match nul
                $message1 = "Votre partie contre " . ($player2_id == 0 ? "l'IA" : $players[$player2_id]) . " s'est terminée par un match nul.";
            } else if ($winner_id == $player1_id) {
                // Joueur 1 a gagné
                $message1 = "Vous avez gagné votre partie contre " . ($player2_id == 0 ? "l'IA" : $players[$player2_id]) . " !";
            } else {
                // Joueur 1 a perdu
                $message1 = "Vous avez perdu votre partie contre " . ($player2_id == 0 ? "l'IA" : $players[$player2_id]) . ".";
            }
            
            // Créer la notification pour le joueur 1
            $data1 = [
                'game_id' => $game_id,
                'opponent_id' => $player2_id,
                'result' => $isDrawGame ? 'draw' : ($winner_id == $player1_id ? 'win' : 'loss')
            ];
            
            $notificationController->createNotification(
                $player1_id,
                'game_completed',
                $message1,
                $data1
            );
            
            // Si le joueur 2 n'est pas un bot, créer une notification pour lui aussi
            if ($player2_id != 0) {
                if ($isDrawGame) {
                    // Match nul
                    $message2 = "Votre partie contre " . $players[$player1_id] . " s'est terminée par un match nul.";
                } else if ($winner_id == $player2_id) {
                    // Joueur 2 a gagné
                    $message2 = "Vous avez gagné votre partie contre " . $players[$player1_id] . " !";
                } else {
                    // Joueur 2 a perdu
                    $message2 = "Vous avez perdu votre partie contre " . $players[$player1_id] . ".";
                }
                
                // Créer la notification pour le joueur 2
                $data2 = [
                    'game_id' => $game_id,
                    'opponent_id' => $player1_id,
                    'result' => $isDrawGame ? 'draw' : ($winner_id == $player2_id ? 'win' : 'loss')
                ];
                
                $notificationController->createNotification(
                    $player2_id,
                    'game_completed',
                    $message2,
                    $data2
                );
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de la création des notifications de fin de partie: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Met à jour les statistiques d'un joueur pour un match nul
     * @param int $user_id ID du joueur
     */
    private function updatePlayerStatsForDraw($user_id) {
        try {
            error_log("updatePlayerStatsForDraw: Mise à jour des statistiques pour le joueur ID: {$user_id}");
            
            // Vérifier si l'utilisateur existe
            $checkUserQuery = "SELECT id FROM users WHERE id = ?";
            $checkUserStmt = $this->db->prepare($checkUserQuery);
            $checkUserStmt->execute([$user_id]);
            
            if ($checkUserStmt->rowCount() == 0) {
                error_log("updatePlayerStatsForDraw: Utilisateur ID {$user_id} non trouvé dans la base de données");
                return false;
            }
            
            // Mettre à jour les statistiques
            $query = "INSERT INTO stats (user_id, games_played, draws, last_game) 
                      VALUES (?, 1, 1, NOW()) 
                      ON DUPLICATE KEY UPDATE 
                      games_played = games_played + 1,
                      draws = draws + 1,
                      last_game = NOW()";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$user_id]);
            
            // Vérifier que les statistiques ont bien été mises à jour
            $checkStatsQuery = "SELECT * FROM stats WHERE user_id = ?";
            $checkStatsStmt = $this->db->prepare($checkStatsQuery);
            $checkStatsStmt->execute([$user_id]);
            
            if ($checkStatsStmt->rowCount() > 0) {
                $stats = $checkStatsStmt->fetch(PDO::FETCH_ASSOC);
                error_log("updatePlayerStatsForDraw: Statistiques mises à jour - Parties: {$stats['games_played']}, Matchs nuls: {$stats['draws']}");
                return true;
            } else {
                error_log("updatePlayerStatsForDraw: Les statistiques n'ont pas été mises à jour correctement");
                return false;
            }
        } catch (PDOException $e) {
            error_log("Erreur dans updatePlayerStatsForDraw: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Met à jour les statistiques d'un joueur
     * @param int $user_id ID du joueur
     * @param bool $is_winner True si le joueur a gagné, False sinon
     */
    private function updatePlayerStats($user_id, $is_winner) {
        try {
            error_log("updatePlayerStats: Mise à jour des statistiques pour le joueur ID: {$user_id}, Victoire: " . ($is_winner ? 'Oui' : 'Non'));
            
            // Vérifier si l'utilisateur existe (pour éviter les erreurs de clé étrangère)
            $checkUserQuery = "SELECT id FROM users WHERE id = ?";
            $checkUserStmt = $this->db->prepare($checkUserQuery);
            $checkUserStmt->execute([$user_id]);
            
            if ($checkUserStmt->rowCount() == 0) {
                error_log("updatePlayerStats: Utilisateur ID {$user_id} non trouvé dans la base de données");
                return false;
            }
            
            // Mettre à jour les statistiques dans la table stats
            // Utiliser INSERT ... ON DUPLICATE KEY UPDATE pour créer ou mettre à jour
            $wins = $is_winner ? 1 : 0;
            $losses = $is_winner ? 0 : 1;
            
            $query = "INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game) 
                      VALUES (?, 1, ?, ?, NOW()) 
                      ON DUPLICATE KEY UPDATE 
                      games_played = games_played + 1, 
                      games_won = games_won + ?, 
                      games_lost = games_lost + ?,
                      last_game = NOW()";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$user_id, $wins, $losses, $wins, $losses]);
            
            // Vérifier que les statistiques ont bien été mises à jour
            $checkStatsQuery = "SELECT * FROM stats WHERE user_id = ?";
            $checkStatsStmt = $this->db->prepare($checkStatsQuery);
            $checkStatsStmt->execute([$user_id]);
            
            if ($checkStatsStmt->rowCount() > 0) {
                $stats = $checkStatsStmt->fetch(PDO::FETCH_ASSOC);
                error_log("updatePlayerStats: Statistiques mises à jour - Parties: {$stats['games_played']}, Victoires: {$stats['games_won']}, Défaites: {$stats['games_lost']}");
                return true;
            } else {
                error_log("updatePlayerStats: Les statistiques n'ont pas été mises à jour correctement");
                return false;
            }
        } catch (PDOException $e) {
            error_log("Erreur dans updatePlayerStats: " . $e->getMessage());
            
            // En cas d'erreur, vérifier si la table stats existe
            try {
                $checkTableQuery = "SHOW TABLES LIKE 'stats'";
                $checkTableStmt = $this->db->query($checkTableQuery);
                
                if ($checkTableStmt->rowCount() == 0) {
                    // La table stats n'existe pas, on la crée
                    error_log("La table stats n'existe pas. Création de la table...");
                    
                    $createTableQuery = "CREATE TABLE IF NOT EXISTS stats (
                        user_id INT PRIMARY KEY,
                        games_played INT DEFAULT 0,
                        games_won INT DEFAULT 0,
                        games_lost INT DEFAULT 0,
                        last_game TIMESTAMP NULL,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )";
                    
                    $this->db->exec($createTableQuery);
                    
                    // Réessayer la mise à jour
                    return $this->updatePlayerStats($user_id, $is_winner);
                }
            } catch (PDOException $tableError) {
                error_log("Erreur lors de la vérification/création de la table stats: " . $tableError->getMessage());
            }
            
            return false;
        }
    }
    
    /**
     * Enregistre un mouvement dans la base de données
     * @param int $game_id ID de la partie
     * @param int $user_id ID du joueur qui a fait le mouvement
     * @param int $fromRow Ligne de départ
     * @param int $fromCol Colonne de départ
     * @param int $toRow Ligne d'arrivée
     * @param int $toCol Colonne d'arrivée
     * @param bool $captured Indique si une pièce a été capturée
     * @return bool Succès de l'opération
     */
    public function recordMove($game_id, $user_id, $fromRow, $fromCol, $toRow, $toCol, $captured) {
        try {
            $query = "INSERT INTO moves (game_id, user_id, from_row, from_col, to_row, to_col, captured) 
                      VALUES (:game_id, :user_id, :from_row, :from_col, :to_row, :to_col, :captured)";
            
            $stmt = $this->db->prepare($query);
            
            $captured_val = $captured ? 1 : 0;
            
            $stmt->bindParam(':game_id', $game_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':from_row', $fromRow);
            $stmt->bindParam(':from_col', $fromCol);
            $stmt->bindParam(':to_row', $toRow);
            $stmt->bindParam(':to_col', $toCol);
            $stmt->bindParam(':captured', $captured_val);
            
            $success = $stmt->execute();
            
            // -----------------------------------------------------------------
            //  Mise à jour de la base JSON pour permettre le replay côté frontend
            // -----------------------------------------------------------------
            if ($success) {
                require_once __DIR__ . '/../db/JsonDatabase.php';
                $jsonDb = JsonDatabase::getInstance();

                // Récupérer (ou créer) la partie dans le stockage JSON
                $jsonGame = $jsonDb->getGameById($game_id);

                if ($jsonGame === null) {
                    // Export minimal de la partie depuis MySQL afin d'avoir une entrée JSON
                    $gameRes = $this->getGame($game_id);
                    if ($gameRes['success']) {
                        $jsonGame = $gameRes['game'];
                    } else {
                        // Créer une structure basique – ne devrait presque jamais arriver
                        $jsonGame = [
                            'id'          => $game_id,
                            'player1_id'  => $user_id, // approximation
                            'player2_id'  => 0,
                            'status'      => 'in_progress',
                            'board_state' => json_encode([]),
                            'moves'       => []
                        ];
                    }
                }

                // S'assurer que le tableau des mouvements existe
                if (!isset($jsonGame['moves']) || !is_array($jsonGame['moves'])) {
                    $jsonGame['moves'] = [];
                }

                // Ajouter le mouvement courant
                $jsonGame['moves'][] = [
                    'user_id'       => $user_id,
                    'from_position' => $fromRow . ',' . $fromCol,
                    'to_position'   => $toRow   . ',' . $toCol,
                    'captured'      => $captured ? 1 : 0,
                    'played_at'     => date('Y-m-d H:i:s')
                ];

                // Sauvegarder la partie mise à jour
                $jsonDb->saveGame($jsonGame);
            }

            return $success;
            
        } catch (Exception $e) {
            error_log("Erreur lors de l'enregistrement du mouvement: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Vérifie si une pièce peut effectuer des captures multiples
     * 
     * @param array $board État actuel du plateau
     * @param int $playerNumber Numéro du joueur (1 ou 2)
     * @param int $row Ligne de la pièce
     * @param int $col Colonne de la pièce
     * @return array Liste des captures possibles
     */
    private function checkMultipleCaptures($board, $playerNumber, $row, $col) {
        $possibleCaptures = [];
        $piece = $board[$row][$col];
        
        if ($piece === null || $piece['player'] !== $playerNumber) {
            return $possibleCaptures;
        }
        
        $directions = [];
        // Pour les pions, les directions dépendent du joueur
        if ($piece['type'] === 'pawn') {
            // Les pions peuvent capturer dans toutes les directions (y compris en arrière)
            $directions = [
                [-2, -2], [-2, 2], [2, -2], [2, 2]
            ];
        } 
        // Pour les dames, toutes les directions diagonales
        else if ($piece['type'] === 'king') {
            $directions = [
                [-2, -2], [-2, 2], [2, -2], [2, 2]
            ];
            // Les dames peuvent capturer à plus grande distance
            for ($i = 3; $i <= 7; $i++) {
                $directions[] = [-$i, -$i];
                $directions[] = [-$i, $i];
                $directions[] = [$i, -$i];
                $directions[] = [$i, $i];
            }
        }
        
        foreach ($directions as $dir) {
            $newRow = $row + $dir[0];
            $newCol = $col + $dir[1];
            
            // Vérifier si les coordonnées sont valides
            if ($newRow >= 0 && $newRow < 8 && $newCol >= 0 && $newCol < 8) {
                // Vérifier si la case est vide
                if ($board[$newRow][$newCol] === null) {
                    // Pour un pion, vérifier s'il y a une pièce à capturer sur le chemin
                    if ($piece['type'] === 'pawn') {
                        $midRow = $row + ($dir[0] / 2);
                        $midCol = $col + ($dir[1] / 2);
                        
                        if ($board[$midRow][$midCol] !== null && 
                            $board[$midRow][$midCol]['player'] !== $playerNumber) {
                            $possibleCaptures[] = [
                                'fromRow' => $row,
                                'fromCol' => $col,
                                'toRow' => $newRow,
                                'toCol' => $newCol,
                                'captureRow' => $midRow,
                                'captureCol' => $midCol
                            ];
                        }
                    }
                    // Pour une dame, vérifier s'il y a une seule pièce à capturer sur le chemin
                    else if ($piece['type'] === 'king') {
                        $rowStep = $dir[0] / abs($dir[0]);
                        $colStep = $dir[1] / abs($dir[1]);
                        $distance = abs($dir[0]);
                        $foundOpponent = false;
                        $captureRow = -1;
                        $captureCol = -1;
                        
                        for ($i = 1; $i < $distance; $i++) {
                            $checkRow = $row + ($i * $rowStep);
                            $checkCol = $col + ($i * $colStep);
                            
                            if ($board[$checkRow][$checkCol] !== null) {
                                if ($foundOpponent) {
                                    // Deux pièces sur le chemin, capture impossible
                                    $foundOpponent = false;
                                    break;
                                }
                                
                                if ($board[$checkRow][$checkCol]['player'] === $playerNumber) {
                                    // Une de nos pièces est sur le chemin, capture impossible
                                    break;
                                }
                                
                                // Une pièce adverse est sur le chemin, potentielle capture
                                $foundOpponent = true;
                                $captureRow = $checkRow;
                                $captureCol = $checkCol;
                            }
                        }
                        
                        if ($foundOpponent) {
                            $possibleCaptures[] = [
                                'fromRow' => $row,
                                'fromCol' => $col,
                                'toRow' => $newRow,
                                'toCol' => $newCol,
                                'captureRow' => $captureRow,
                                'captureCol' => $captureCol
                            ];
                        }
                    }
                }
            }
        }
        
        return $possibleCaptures;
    }

    /**
     * Fait jouer le bot
     * 
     * @param int $gameId Identifiant de la partie
     * @return array Réponse de l'API
     */
    public function makeBotMove($gameId) {
        try {
            // Récupérer les informations de la partie
            $result = $this->getGame($gameId);
            if (!$result['success']) {
                return [
                    'success' => false,
                    'message' => 'Partie introuvable.'
                ];
            }
            
            $game = $result['game'];
            $botNumber = $game['player2_id'] == 0 ? 2 : 1;
            $boardState = json_decode($game['board_state'], true);
            
            // Liste de tous les mouvements possibles pour le bot
        $possibleMoves = [];
            $possibleCaptures = [];
        
            // Parcourir tout le plateau
        for ($row = 0; $row < 8; $row++) {
            for ($col = 0; $col < 8; $col++) {
                    $piece = $boardState[$row][$col];
                    
                    // Vérifier si la pièce appartient au bot
                    if ($piece !== null && $piece['player'] === $botNumber) {
                        // Vérifier les captures multiples pour cette pièce
                        $captures = $this->checkMultipleCaptures($boardState, $botNumber, $row, $col);
                        
                        if (!empty($captures)) {
                            // Ajouter les captures aux mouvements possibles avec une priorité élevée
                            foreach ($captures as $capture) {
                                $possibleCaptures[] = $capture;
                            }
                        }
                        
                        // Chercher aussi les mouvements simples
                        for ($newRow = 0; $newRow < 8; $newRow++) {
                            for ($newCol = 0; $newCol < 8; $newCol++) {
                                $validMove = $this->isValidMove($boardState, $row, $col, $newRow, $newCol, $botNumber);
                                
                                if ($validMove['valid']) {
                                    if (isset($validMove['capture']) && $validMove['capture']) {
                                        // Capture prioritaire
                                        $possibleCaptures[] = [
                                    'fromRow' => $row,
                                    'fromCol' => $col,
                                    'toRow' => $newRow,
                                            'toCol' => $newCol,
                                            'captureRow' => isset($validMove['captured']) ? $validMove['captured']['row'] : null,
                                            'captureCol' => isset($validMove['captured']) ? $validMove['captured']['col'] : null
                                        ];
                                    } else {
                                        // Mouvement simple
                                        $possibleMoves[] = [
                                            'fromRow' => $row,
                                            'fromCol' => $col,
                                            'toRow' => $newRow,
                                            'toCol' => $newCol
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            // Priorité aux captures
            if (!empty($possibleCaptures)) {
                $selectedMove = $possibleCaptures[array_rand($possibleCaptures)];
            } 
            // Si pas de capture, mouvement normal
            else if (!empty($possibleMoves)) {
                $selectedMove = $possibleMoves[array_rand($possibleMoves)];
            } 
            // Aucun mouvement possible, le bot a perdu
            else {
                // Mettre à jour le statut du jeu
                $this->endGame($gameId, $botNumber === 1 ? 2 : 1, null);
                
                return [
                    'success' => false,
                    'message' => 'Le bot n\'a plus de mouvements possibles.'
                ];
            }
            
            // Effectuer le mouvement sélectionné
            $result = $this->makeMove(
                $gameId,
                $selectedMove['fromRow'], 
                $selectedMove['fromCol'], 
                $selectedMove['toRow'], 
                $selectedMove['toCol'], 
                $botNumber
            );
            
            // Vérifier si le mouvement a créé une dame (promotion)
            $newResult = $this->getGame($gameId);
            $newBoardState = json_decode($newResult['game']['board_state'], true);
            $toRow = $selectedMove['toRow'];
            $toCol = $selectedMove['toCol'];
            
            // Promotion en dame si un pion atteint la dernière rangée
            if ($botNumber === 1 && $toRow === 7 && $newBoardState[$toRow][$toCol]['type'] === 'pawn') {
                $newBoardState[$toRow][$toCol]['type'] = 'king';
                $boardJson = json_encode($newBoardState);
                
                $query = "UPDATE games SET board_state = :board_state WHERE id = :game_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':board_state', $boardJson);
                $stmt->bindParam(':game_id', $gameId);
                $stmt->execute();
            } else if ($botNumber === 2 && $toRow === 0 && $newBoardState[$toRow][$toCol]['type'] === 'pawn') {
                $newBoardState[$toRow][$toCol]['type'] = 'king';
                $boardJson = json_encode($newBoardState);
                
                $query = "UPDATE games SET board_state = :board_state WHERE id = :game_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':board_state', $boardJson);
                $stmt->bindParam(':game_id', $gameId);
                $stmt->execute();
            }
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Le bot a joué',
                    'move' => $selectedMove
                ];
            } else {
                return $result;
            }
        } catch (Exception $e) {
            error_log("Erreur dans makeBotMove: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors du mouvement du bot: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère toutes les parties d'un joueur avec pagination
     * @param int $userId ID du joueur
     * @param int $limit Nombre de parties à récupérer
     * @param int $offset Offset pour la pagination
     * @return array Liste des parties
     */
    public function getUserGames($userId, $limit, $offset) {
        try {
            // Préparer la requête SQL
            $query = "SELECT g.*, 
                      u1.username as player1_username, 
                      u2.username as player2_username,
                      CASE 
                          WHEN g.status = 'finished' THEN 'Terminée'
                          WHEN g.status = 'in_progress' THEN 'En cours'
                          ELSE g.status
                      END as status_text
                      FROM games g
                      LEFT JOIN users u1 ON g.player1_id = u1.id
                      LEFT JOIN users u2 ON g.player2_id = u2.id
                      WHERE (g.player1_id = :user_id OR g.player2_id = :user_id)
                      AND g.status = 'finished'
                      ORDER BY g.updated_at DESC
                      LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            // Récupérer les résultats
            $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ajouter des informations supplémentaires à chaque partie
            foreach ($games as &$game) {
                // Définir le résultat pour l'utilisateur
                $isPlayer1 = ($game['player1_id'] == $userId);
                
                // Déterminer le résultat en fonction du champ result et du joueur
                if ($game['status'] === 'finished' || $game['status'] === 'completed') {
                    if ($game['result'] === 'draw') {
                        // Vérifier si c'est vraiment un match nul ou si c'est une défaite contre l'IA
                        if ($game['player2_id'] == 0 && $isPlayer1) {
                            // Pour une partie contre l'IA, si le résultat est nul, c'est une défaite pour le joueur humain
                            $game['result_for_user'] = 'loss';
                            error_log("Partie ID: " . $game['id'] . " contre IA avec résultat draw reclassée comme défaite");
                        } else {
                            // Vrai match nul entre deux joueurs humains
                            $game['result_for_user'] = 'draw';
                            error_log("Partie ID: " . $game['id'] . " classée comme match nul");
                        }
                    } 
                    else if (($isPlayer1 && $game['result'] === 'player1_won') || 
                           (!$isPlayer1 && $game['result'] === 'player2_won')) {
                        $game['result_for_user'] = 'win';
                        error_log("Partie ID: " . $game['id'] . " classée comme victoire pour l'utilisateur");
                    } 
                    else {
                        $game['result_for_user'] = 'loss';
                        error_log("Partie ID: " . $game['id'] . " classée comme défaite pour l'utilisateur");
                    }
                } else {
                    $game['result_for_user'] = 'in_progress';
                    error_log("Partie ID: " . $game['id'] . " n'est pas terminée, status: " . $game['status']);
                }
            }
            
            error_log("Retour des parties avec leurs résultats assignés");
            return $games;
        } catch (PDOException $e) {
            error_log('Erreur lors de la récupération des parties: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Compte le nombre total de parties d'un joueur
     * @param int $userId ID du joueur
     * @return int Nombre total de parties
     */
    public function countUserGames($userId) {
        try {
            // Log pour débogage
            error_log("countUserGames appelé pour l'utilisateur ID: " . $userId);
            
            // Convertir en entier pour s'assurer que le type est correct
            $userId = (int)$userId;
            
            // Modifier la requête pour ne compter que les parties terminées
            $query = "SELECT COUNT(*) FROM games WHERE (player1_id = ? OR player2_id = ?) AND status = 'finished'";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $count = $stmt->fetchColumn();
            
            // Log le résultat
            error_log("countUserGames: Nombre de parties terminées trouvées: " . $count);
            
            return $count;
        } catch (PDOException $e) {
            error_log('Erreur lors du comptage des parties: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Récupère les meilleurs joueurs pour le classement
     * @param int $limit Nombre de joueurs à récupérer
     * @param int $offset À partir de quel joueur commencer (pour la pagination)
     * @return array Liste des joueurs et leurs statistiques
     */
    public function getTopPlayers($limit, $offset) {
        try {
            // D'abord, vérifier si la table leaderboard existe
            $checkTable = "SELECT COUNT(*) as count FROM information_schema.tables 
                         WHERE table_schema = DATABASE() 
                         AND table_name = 'leaderboard'";
            $checkStmt = $this->db->prepare($checkTable);
            $checkStmt->execute();
            $tableExists = ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0);
            
            if ($tableExists) {
                // Si la table leaderboard existe, utiliser celle-ci
                $query = "SELECT l.user_id as id, u.username, l.games_played, l.games_won, l.games_lost, 
                          l.win_percentage, l.rank_position as rank
                          FROM leaderboard l
                          JOIN users u ON l.user_id = u.id
                          WHERE l.games_played > 0
                          ORDER BY l.win_percentage DESC, l.games_played DESC
                          LIMIT :limit OFFSET :offset";
            } else {
                // Sinon, utiliser la table stats
                $query = "SELECT s.user_id as id, u.username, s.games_played, s.games_won, s.games_lost, 
                          CASE 
                              WHEN s.games_played > 0 THEN ROUND((s.games_won / s.games_played) * 100, 1) 
                              ELSE 0 
                          END as win_percentage
                          FROM stats s
                          JOIN users u ON s.user_id = u.id
                          WHERE s.games_played > 0
                          ORDER BY win_percentage DESC, s.games_played DESC
                          LIMIT :limit OFFSET :offset";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Erreur lors de la récupération des meilleurs joueurs: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Compte le nombre de joueurs actifs (ceux qui ont joué au moins une partie)
     * @return int Nombre de joueurs actifs
     */
    public function countActivePlayers() {
        try {
            // D'abord, vérifier si la table leaderboard existe
            $checkTable = "SELECT COUNT(*) as count FROM information_schema.tables 
                         WHERE table_schema = DATABASE() 
                         AND table_name = 'leaderboard'";
            $checkStmt = $this->db->prepare($checkTable);
            $checkStmt->execute();
            $tableExists = ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0);
            
            if ($tableExists) {
                $query = "SELECT COUNT(*) as count 
                        FROM leaderboard 
                        WHERE games_played > 0";
                        } else {
                $query = "SELECT COUNT(*) as count 
                        FROM stats
                        WHERE games_played > 0";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'];
        } catch (PDOException $e) {
            error_log('Erreur lors du comptage des joueurs actifs: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Récupère le rang d'un joueur spécifique
     * @param int $userId ID du joueur
     * @return array Informations sur le rang du joueur
     */
    public function getPlayerRank($userId) {
        try {
            // D'abord, vérifier si la table leaderboard existe
            $checkTable = "SELECT COUNT(*) as count FROM information_schema.tables 
                         WHERE table_schema = DATABASE() 
                         AND table_name = 'leaderboard'";
            $checkStmt = $this->db->prepare($checkTable);
            $checkStmt->execute();
            $tableExists = ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0);
            
            if ($tableExists) {
                $query = "SELECT l.rank_position as rank, l.games_played, l.games_won, l.games_lost, l.win_percentage
                        FROM leaderboard l
                        WHERE l.user_id = :user_id";
                    } else {
                // Si leaderboard n'existe pas, calculer le rang manuellement
                $query = "SELECT 
                            s.games_played, 
                            s.games_won, 
                            s.games_lost,
                            CASE 
                                WHEN s.games_played > 0 THEN ROUND((s.games_won / s.games_played) * 100, 1) 
                                ELSE 0 
                            END as win_percentage,
                            (SELECT COUNT(*) + 1 FROM stats s2 
                             WHERE (s2.games_won / s2.games_played) > (s.games_won / s.games_played)
                             OR ((s2.games_won / s2.games_played) = (s.games_won / s.games_played) AND s2.games_played > s.games_played)
                             OR ((s2.games_won / s2.games_played) = (s.games_won / s.games_played) AND s2.games_played = s.games_played AND s2.games_won > s.games_won)) as rank
                        FROM stats s
                        WHERE s.user_id = :user_id";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Si le joueur n'a pas encore de stats, retourner des valeurs par défaut
            if (!$result) {
                return [
                    'rank' => 0,
                    'games_played' => 0,
                    'games_won' => 0,
                    'games_lost' => 0,
                    'win_percentage' => 0
                ];
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log('Erreur lors de la récupération du rang du joueur: ' . $e->getMessage());
            return [
                'rank' => 0,
                'games_played' => 0,
                'games_won' => 0,
                'games_lost' => 0,
                'win_percentage' => 0
            ];
        }
    }
}
