<?php
/**
 * Classe de gestion de la base de données JSON
 * Cette classe fournit des méthodes pour gérer les données stockées en JSON
 */
class JsonDatabase {
    private static $instance = null;
    private $usersDir;
    private $gamesDir;
    private $statsDir;
    
    /**
     * Constructeur privé pour le singleton
     */
    private function __construct() {
        $this->usersDir = dirname(dirname(__DIR__)) . '/data/users/';
        $this->gamesDir = dirname(dirname(__DIR__)) . '/data/games/';
        $this->statsDir = dirname(dirname(__DIR__)) . '/data/stats/';
        
        // Vérifier et créer les répertoires si nécessaire
        if (!is_dir($this->usersDir)) {
            mkdir($this->usersDir, 0755, true);
        }
        if (!is_dir($this->gamesDir)) {
            mkdir($this->gamesDir, 0755, true);
        }
        if (!is_dir($this->statsDir)) {
            mkdir($this->statsDir, 0755, true);
        }
    }
    
    /**
     * Obtenir l'instance unique de la base de données
     * @return JsonDatabase Instance unique
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtenir un utilisateur par son ID
     * @param int $userId ID de l'utilisateur
     * @return array|null Données de l'utilisateur ou null si non trouvé
     */
    public function getUserById($userId) {
        $filePath = $this->usersDir . 'user_' . $userId . '.json';
        if (file_exists($filePath)) {
            return json_decode(file_get_contents($filePath), true);
        }
        return null;
    }
    
    /**
     * Obtenir un utilisateur par son nom d'utilisateur
     * @param string $username Nom d'utilisateur
     * @return array|null Données de l'utilisateur ou null si non trouvé
     */
    public function getUserByUsername($username) {
        $indexFile = $this->usersDir . 'index.json';
        if (file_exists($indexFile)) {
            $index = json_decode(file_get_contents($indexFile), true);
            if (isset($index['usernames'][$username])) {
                return $this->getUserById($index['usernames'][$username]);
            }
        }
        return null;
    }
    
    /**
     * Sauvegarder un utilisateur
     * @param array $userData Données de l'utilisateur
     * @return bool Succès de l'opération
     */
    public function saveUser($userData) {
        // Vérifier si l'ID est défini
        if (!isset($userData['id'])) {
            $userData['id'] = $this->getNextUserId();
        }
        
        // Mettre à jour l'index des noms d'utilisateurs
        $this->updateUsernameIndex($userData['username'], $userData['id']);
        
        // Sauvegarder les données de l'utilisateur
        $filePath = $this->usersDir . 'user_' . $userData['id'] . '.json';
        return file_put_contents($filePath, json_encode($userData, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Mettre à jour l'index des noms d'utilisateurs
     * @param string $username Nom d'utilisateur
     * @param int $userId ID de l'utilisateur
     */
    private function updateUsernameIndex($username, $userId) {
        $indexFile = $this->usersDir . 'index.json';
        $index = [];
        
        if (file_exists($indexFile)) {
            $index = json_decode(file_get_contents($indexFile), true);
        }
        
        if (!isset($index['usernames'])) {
            $index['usernames'] = [];
        }
        
        $index['usernames'][$username] = $userId;
        file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT));
    }
    
    /**
     * Obtenir le prochain ID d'utilisateur disponible
     * @return int Prochain ID
     */
    private function getNextUserId() {
        $indexFile = $this->usersDir . 'index.json';
        $index = [];
        
        if (file_exists($indexFile)) {
            $index = json_decode(file_get_contents($indexFile), true);
        }
        
        if (!isset($index['next_user_id'])) {
            $index['next_user_id'] = 1;
        }
        
        $nextId = $index['next_user_id']++;
        file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT));
        
        return $nextId;
    }
    
    /**
     * Obtenir les statistiques d'un utilisateur
     * @param int $userId ID de l'utilisateur
     * @return array Statistiques de l'utilisateur
     */
    public function getUserStats($userId) {
        $filePath = $this->statsDir . 'user_' . $userId . '.json';
        if (file_exists($filePath)) {
            return json_decode(file_get_contents($filePath), true);
        }
        
        // Retourner des statistiques par défaut si non trouvées
        return [
            'user_id' => $userId,
            'games_played' => 0,
            'games_won' => 0, 
            'games_lost' => 0,
            'draws' => 0,
            'rating' => 1500, // ELO de départ
            'rank' => null,
            'last_game_date' => null
        ];
    }
    
    /**
     * Sauvegarder les statistiques d'un utilisateur
     * @param array $statsData Données de statistiques
     * @return bool Succès de l'opération
     */
    public function saveUserStats($statsData) {
        if (!isset($statsData['user_id'])) {
            return false;
        }
        
        $filePath = $this->statsDir . 'user_' . $statsData['user_id'] . '.json';
        return file_put_contents($filePath, json_encode($statsData, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Obtenir une partie par son ID
     * @param int $gameId ID de la partie
     * @return array|null Données de la partie ou null si non trouvée
     */
    public function getGameById($gameId) {
        $filePath = $this->gamesDir . 'game_' . $gameId . '.json';
        if (file_exists($filePath)) {
            return json_decode(file_get_contents($filePath), true);
        }
        return null;
    }
    
    /**
     * Sauvegarder une partie
     * @param array $gameData Données de la partie
     * @return bool Succès de l'opération
     */
    public function saveGame($gameData) {
        // Vérifier si l'ID est défini
        if (!isset($gameData['id'])) {
            $gameData['id'] = $this->getNextGameId();
        }
        
        // Mettre à jour l'index des parties par utilisateur
        $this->updateUserGamesIndex($gameData['player1_id'], $gameData['id']);
        if ($gameData['player2_id'] > 0) { // Ne pas indexer le bot (ID 0)
            $this->updateUserGamesIndex($gameData['player2_id'], $gameData['id']);
        }
        
        // Sauvegarder les données de la partie
        $filePath = $this->gamesDir . 'game_' . $gameData['id'] . '.json';
        return file_put_contents($filePath, json_encode($gameData, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Mettre à jour l'index des parties par utilisateur
     * @param int $userId ID de l'utilisateur
     * @param int $gameId ID de la partie
     */
    private function updateUserGamesIndex($userId, $gameId) {
        $indexFile = $this->gamesDir . 'user_' . $userId . '_index.json';
        $index = [];
        
        if (file_exists($indexFile)) {
            $index = json_decode(file_get_contents($indexFile), true);
        }
        
        if (!isset($index['games'])) {
            $index['games'] = [];
        }
        
        if (!in_array($gameId, $index['games'])) {
            $index['games'][] = $gameId;
        }
        
        file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT));
    }
    
    /**
     * Obtenir le prochain ID de partie disponible
     * @return int Prochain ID
     */
    private function getNextGameId() {
        $indexFile = $this->gamesDir . 'index.json';
        $index = [];
        
        if (file_exists($indexFile)) {
            $index = json_decode(file_get_contents($indexFile), true);
        }
        
        if (!isset($index['next_game_id'])) {
            $index['next_game_id'] = 1;
        }
        
        $nextId = $index['next_game_id']++;
        file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT));
        
        return $nextId;
    }
    
    /**
     * Obtenir toutes les parties d'un utilisateur
     * @param int $userId ID de l'utilisateur
     * @return array Liste des parties
     */
    public function getUserGames($userId) {
        $indexFile = $this->gamesDir . 'user_' . $userId . '_index.json';
        $games = [];
        
        if (file_exists($indexFile)) {
            $index = json_decode(file_get_contents($indexFile), true);
            if (isset($index['games']) && is_array($index['games'])) {
                foreach ($index['games'] as $gameId) {
                    $game = $this->getGameById($gameId);
                    if ($game !== null) {
                        $games[] = $game;
                    }
                }
            }
        }
        
        // Trier les parties par date (plus récente en premier)
        usort($games, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $games;
    }
    
    /**
     * Obtenir les parties d'un utilisateur avec pagination
     * @param int $userId ID de l'utilisateur
     * @param int $limit Nombre de parties par page
     * @param int $offset Décalage pour la pagination
     * @return array Liste des parties
     */
    public function getUserGamesWithPagination($userId, $limit, $offset) {
        $allGames = $this->getUserGames($userId);
        return array_slice($allGames, $offset, $limit);
    }
    
    /**
     * Compter le nombre de parties d'un utilisateur
     * @param int $userId ID de l'utilisateur
     * @return int Nombre de parties
     */
    public function countUserGames($userId) {
        $indexFile = $this->gamesDir . 'user_' . $userId . '_index.json';
        if (file_exists($indexFile)) {
            $index = json_decode(file_get_contents($indexFile), true);
            if (isset($index['games']) && is_array($index['games'])) {
                return count($index['games']);
            }
        }
        return 0;
    }
    
    /**
     * Obtenir le classement des joueurs
     * @param int $limit Nombre de joueurs par page
     * @param int $offset Décalage pour la pagination
     * @return array Liste des joueurs avec leur classement
     */
    public function getLeaderboard($limit, $offset) {
        $allStats = $this->getAllUserStats();
        
        // Trier par classement ELO (du plus élevé au plus bas)
        usort($allStats, function($a, $b) {
            return $b['rating'] - $a['rating'];
        });
        
        // Ajouter le rang aux statistiques
        foreach ($allStats as $key => $stats) {
            $allStats[$key]['rank'] = $key + 1;
            
            // Ajouter le nom d'utilisateur
            $user = $this->getUserById($stats['user_id']);
            if ($user) {
                $allStats[$key]['username'] = $user['username'];
            } else {
                $allStats[$key]['username'] = 'Utilisateur inconnu';
            }
        }
        
        // Appliquer la pagination
        return array_slice($allStats, $offset, $limit);
    }
    
    /**
     * Obtenir toutes les statistiques de tous les utilisateurs
     * @return array Liste des statistiques
     */
    private function getAllUserStats() {
        $stats = [];
        $files = glob($this->statsDir . 'user_*.json');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $userStats = json_decode($content, true);
            if ($userStats && isset($userStats['user_id'])) {
                $stats[] = $userStats;
            }
        }
        
        return $stats;
    }
    
    /**
     * Compter le nombre total de joueurs actifs
     * @return int Nombre de joueurs
     */
    public function countActivePlayers() {
        $files = glob($this->statsDir . 'user_*.json');
        return count($files);
    }
    
    /**
     * Mettre à jour les statistiques après une partie
     * @param int $gameId ID de la partie terminée
     */
    public function updateStatsAfterGame($gameId) {
        $game = $this->getGameById($gameId);
        if (!$game || $game['status'] !== 'finished') {
            return;
        }
        
        $player1Id = $game['player1_id'];
        $player2Id = $game['player2_id'];
        
        // Ne pas mettre à jour les statistiques pour l'IA
        $updatePlayer1 = ($player1Id > 0);
        $updatePlayer2 = ($player2Id > 0);
        
        if ($updatePlayer1) {
            $stats1 = $this->getUserStats($player1Id);
            $stats1['games_played']++;
            $stats1['last_game_date'] = $game['updated_at'];
            
            if ($game['result'] === 'player1_won') {
                $stats1['games_won']++;
            } elseif ($game['result'] === 'player2_won') {
                $stats1['games_lost']++;
            } elseif ($game['result'] === 'draw') {
                $stats1['draws']++;
            }
            
            $this->saveUserStats($stats1);
        }
        
        if ($updatePlayer2) {
            $stats2 = $this->getUserStats($player2Id);
            $stats2['games_played']++;
            $stats2['last_game_date'] = $game['updated_at'];
            
            if ($game['result'] === 'player2_won') {
                $stats2['games_won']++;
            } elseif ($game['result'] === 'player1_won') {
                $stats2['games_lost']++;
            } elseif ($game['result'] === 'draw') {
                $stats2['draws']++;
            }
            
            $this->saveUserStats($stats2);
        }
    }
    
    /**
     * Mettre à jour le classement ELO après une partie
     * @param int $gameId ID de la partie terminée
     */
    public function updateEloRating($gameId) {
        $game = $this->getGameById($gameId);
        if (!$game || $game['status'] !== 'finished') {
            return;
        }
        
        $player1Id = $game['player1_id'];
        $player2Id = $game['player2_id'];
        
        // Ne pas mettre à jour l'ELO pour les parties contre l'IA
        if ($player1Id <= 0 || $player2Id <= 0) {
            return;
        }
        
        $stats1 = $this->getUserStats($player1Id);
        $stats2 = $this->getUserStats($player2Id);
        
        $player1Rating = $stats1['rating'];
        $player2Rating = $stats2['rating'];
        
        // Calculer le résultat (1 pour victoire, 0.5 pour match nul, 0 pour défaite)
        $player1Result = 0.5; // Match nul par défaut
        if ($game['result'] === 'player1_won') {
            $player1Result = 1;
        } elseif ($game['result'] === 'player2_won') {
            $player1Result = 0;
        }
        
        $player2Result = 1 - $player1Result;
        
        // Calculer les changements d'ELO
        $player1Change = $this->calculateEloChange($player1Rating, $player2Rating, $player1Result);
        $player2Change = $this->calculateEloChange($player2Rating, $player1Rating, $player2Result);
        
        // Mettre à jour les classements
        $stats1['rating'] = max(round($player1Rating + $player1Change), 100);
        $stats2['rating'] = max(round($player2Rating + $player2Change), 100);
        
        // Sauvegarder les statistiques mises à jour
        $this->saveUserStats($stats1);
        $this->saveUserStats($stats2);
        
        // Enregistrer les changements d'ELO dans la partie
        $game['player1_rating_change'] = round($player1Change);
        $game['player2_rating_change'] = round($player2Change);
        $this->saveGame($game);
    }
    
    /**
     * Calculer le changement de classement ELO
     * @param int $playerRating Classement du joueur
     * @param int $opponentRating Classement de l'adversaire
     * @param float $result Résultat (1 pour victoire, 0.5 pour match nul, 0 pour défaite)
     * @return float Changement de classement
     */
    private function calculateEloChange($playerRating, $opponentRating, $result) {
        // K est le facteur de développement (plus élevé pour les nouveaux joueurs)
        $k = ($playerRating < 2100) ? 32 : (($playerRating < 2400) ? 24 : 16);
        
        // Calcul de la probabilité de gagner
        $expectedScore = 1 / (1 + pow(10, ($opponentRating - $playerRating) / 400));
        
        // Calcul du nouveau classement
        return $k * ($result - $expectedScore);
    }
    
    /**
     * Synchroniser les statistiques d'un utilisateur avec son historique de parties
     * @param int $userId ID de l'utilisateur
     * @return bool Succès de l'opération
     */
    public function synchronizeUserStats($userId) {
        // Récupérer toutes les parties de l'utilisateur
        $games = $this->getUserGames($userId);
        
        // Initialiser les statistiques
        $stats = [
            'user_id' => $userId,
            'games_played' => 0,
            'games_won' => 0,
            'games_lost' => 0,
            'draws' => 0,
            'rating' => 1500, // Conserver le rating existant si disponible
            'rank' => null,
            'last_game_date' => null
        ];
        
        // Récupérer les statistiques existantes pour conserver le rating ELO
        $existingStats = $this->getUserStats($userId);
        if ($existingStats) {
            $stats['rating'] = $existingStats['rating'];
        }
        
        // Parcourir les parties terminées pour recalculer les statistiques
        foreach ($games as $game) {
            if ($game['status'] !== 'finished') {
                continue;
            }
            
            $stats['games_played']++;
            
            // Mettre à jour la date de dernière partie
            if ($stats['last_game_date'] === null || strtotime($game['updated_at']) > strtotime($stats['last_game_date'])) {
                $stats['last_game_date'] = $game['updated_at'];
            }
            
            // Déterminer si l'utilisateur est le joueur 1 ou 2
            $isPlayer1 = ($game['player1_id'] == $userId);
            
            // Mise à jour des statistiques selon le résultat
            if ($game['result'] === 'draw') {
                $stats['draws']++;
            } else if (($isPlayer1 && $game['result'] === 'player1_won') || 
                      (!$isPlayer1 && $game['result'] === 'player2_won')) {
                $stats['games_won']++;
            } else {
                $stats['games_lost']++;
            }
        }
        
        // Sauvegarder les statistiques mises à jour
        return $this->saveUserStats($stats);
    }
    
    /**
     * Mettre à jour l'index des parties pour un utilisateur
     * @param int $userId ID de l'utilisateur
     * @param int $gameId ID de la partie
     */
    public function updateGamesIndex($userId, $gameId) {
        $this->updateUserGamesIndex($userId, $gameId);
    }
}
?> 