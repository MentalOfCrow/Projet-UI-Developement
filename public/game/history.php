<?php
// Start output buffering to prevent any previous output
ob_start();

// Set display_errors for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include configuration files
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/includes/session.php';
require_once __DIR__ . '/../../backend/controllers/GameController.php';
require_once __DIR__ . '/../../backend/controllers/ProfileController.php';
require_once __DIR__ . '/../../backend/db/Database.php';
require_once __DIR__ . '/../../backend/db/JsonDatabase.php';

// Chemin vers le fichier de données pour l'historique des parties
$gameHistoryFile = __DIR__ . '/../game_history.json';

// Fonction pour charger les données d'historique
function loadGameHistory() {
    global $gameHistoryFile;
    if (file_exists($gameHistoryFile)) {
        $data = json_decode(file_get_contents($gameHistoryFile), true);
        return $data ?: ['games' => [], 'stats' => []];
    }
    return ['games' => [], 'stats' => []];
}

// Fonction pour sauvegarder les données d'historique
function saveGameHistory($data) {
    global $gameHistoryFile;
    file_put_contents($gameHistoryFile, json_encode($data, JSON_PRETTY_PRINT));
}

// Initialiser l'historique avec des parties d'exemple si nécessaire
function initializeExampleGames() {
    $data = loadGameHistory();
    
    // Seulement initialiser si aucune partie n'existe
    if (empty($data['games'])) {
        // Créer des exemples de parties pour tous les utilisateurs
        $userIds = [1, 2, 3, 4]; // IDs d'utilisateurs supposés exister
        $usernames = [
            1 => 'Joueur1',
            2 => 'Joueur2',
            3 => 'Joueur3',
            4 => 'Joueur4',
            0 => 'Intelligence Artificielle' // ID 0 réservé pour l'IA
        ];
        
        // Types de parties: victoire, défaite, match nul et partie en cours
        $gameTypes = ['player1_won', 'player2_won', 'draw', 'in_progress'];
        
        $gameId = 1;
        $games = [];
        $stats = [];
        
        // Initialiser les statistiques pour chaque utilisateur
        foreach ($userIds as $userId) {
            $stats[$userId] = [
                'games_played' => 0,
                'games_won' => 0,
                'games_lost' => 0,
                'draws' => 0
            ];
        }
        
        // Créer des parties aléatoires pour chaque utilisateur
        foreach ($userIds as $userId) {
            // Chaque utilisateur a 5 parties
            for ($i = 0; $i < 5; $i++) {
                // Déterminer l'adversaire (soit un autre joueur soit l'IA)
                $opponentOptions = array_diff($userIds, [$userId]);
                $opponentOptions[] = 0; // Ajouter l'IA comme adversaire possible
                $opponentId = $opponentOptions[array_rand($opponentOptions)];
                
                // Déterminer le type de partie
                $gameType = $gameTypes[array_rand($gameTypes)];
                
                // Déterminer le statut de la partie
                $status = ($gameType === 'in_progress') ? 'in_progress' : 'finished';
                
                // Calculer la date de la partie (dans les 30 derniers jours)
                $daysAgo = rand(0, 30);
                $date = new DateTime();
                $date->modify("-$daysAgo days");
                $createdAt = $date->format('Y-m-d H:i:s');
                
                // Créer la partie
                $game = [
                    'id' => $gameId++,
                    'player1_id' => $userId,
                    'player1_username' => $usernames[$userId],
                    'player2_id' => $opponentId,
                    'player2_username' => $usernames[$opponentId],
                    'status' => $status,
                    'result' => $gameType,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    'moves' => [] // Pour stocker les mouvements si nécessaire
                ];
                
                $games[] = $game;
                
                // Ne pas mettre à jour les statistiques pour les parties en cours
                if ($status === 'finished') {
                    $stats[$userId]['games_played']++;
                    
                    if ($gameType === 'player1_won') {
                        $stats[$userId]['games_won']++;
                        if ($opponentId > 0) { // Si ce n'est pas l'IA
                            $stats[$opponentId]['games_played']++;
                            $stats[$opponentId]['games_lost']++;
                        }
                    } elseif ($gameType === 'player2_won') {
                        $stats[$userId]['games_lost']++;
                        if ($opponentId > 0) { // Si ce n'est pas l'IA
                            $stats[$opponentId]['games_played']++;
                            $stats[$opponentId]['games_won']++;
                        }
                    } elseif ($gameType === 'draw') {
                        $stats[$userId]['draws']++;
                        if ($opponentId > 0) { // Si ce n'est pas l'IA
                            $stats[$opponentId]['games_played']++;
                            $stats[$opponentId]['draws']++;
                        }
                    }
                }
            }
        }
        
        $data['games'] = $games;
        $data['stats'] = $stats;
        saveGameHistory($data);
    }
}

// Initialiser l'historique avec des exemples si nécessaire
initializeExampleGames();

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

// Obtenir une instance de la base de données JSON
$db = JsonDatabase::getInstance();

// Récupérer les parties de l'utilisateur
$games = $db->getUserGames($userId);

// -----------------------------------------------------------------------------
// Génération de parties d'exemple si l'utilisateur n'a aucune partie enregistrée
// -----------------------------------------------------------------------------
if (empty($games)) {
    function createExampleGames(JsonDatabase $db, int $userId, int $count = 3) {
        // Quelques paramètres fixes / aléatoires pour les exemples
        $now = new DateTime();

        for ($i = 0; $i < $count; $i++) {
            // Décaler la date dans le passé pour chaque partie
            $date = clone $now;
            $date->modify('-' . ($i * 2) . ' days');

            // Alternance victoire/défaite/match nul
            $results = ['player1_won', 'player2_won', 'draw'];
            $result = $results[$i % 3];

            $gameData = [
                // L'ID sera ajouté par saveGame()
                'player1_id' => $userId,
                'player2_id' => 0, // 0 représente l'IA / adversaire fictif
                'status'      => 'finished',
                'result'      => $result,
                'created_at'  => $date->format('Y-m-d H:i:s'),
                'updated_at'  => $date->format('Y-m-d H:i:s'),
                'moves'       => []
            ];

            // Sauvegarde dans la base JSON et mise à jour des index
            $db->saveGame($gameData);
        }

        // Mettre à jour les statistiques utilisateur après création des exemples
        $db->synchronizeUserStats($userId);
    }

    createExampleGames($db, $userId);
    // Récupérer à nouveau pour affichage
    $games = $db->getUserGames($userId);
}

// Définir le titre de la page
$pageTitle = "Historique des parties";

// Include the header
include_once __DIR__ . '/../../backend/includes/header.php';

// Function to get status class and text
function getStatusInfo($status) {
    switch ($status) {
        case 'finished':
            return [
                'class' => 'bg-green-500',
                'text' => 'Terminée',
                'icon' => 'fa-flag-checkered'
            ];
        case 'in_progress':
            return [
                'class' => 'bg-blue-500',
                'text' => 'En cours',
                'icon' => 'fa-play-circle'
            ];
        case 'cancelled':
            return [
                'class' => 'bg-red-500',
                'text' => 'Annulée',
                'icon' => 'fa-times-circle'
            ];
        default:
            return [
                'class' => 'bg-gray-500',
                'text' => 'Inconnu',
                'icon' => 'fa-question-circle'
            ];
    }
}

// Function to get result info for a game
function getResultInfo($game, $userId) {
    // Sécurité : si la clé result est absente on la déduit rapidement
    if (!isset($game['result'])) {
        if ($game['winner_id'] === null) {
            $game['result'] = 'draw';
        } elseif ($game['winner_id'] == $game['player1_id']) {
            $game['result'] = 'player1_won';
        } else {
            $game['result'] = 'player2_won';
        }
    }

    $resultClass = 'text-gray-500';
    $resultText = 'En attente';

    if ($game['status'] === 'finished') {
        $isPlayer1 = $game['player1_id'] == $userId;
        $isWinner = ($isPlayer1 && $game['result'] === 'player1_won') || 
                    (!$isPlayer1 && $game['result'] === 'player2_won');

        if ($game['result'] === 'draw') {
            $resultClass = 'text-yellow-500';
            $resultText = 'Match nul';
        } elseif ($isWinner) {
            $resultClass = 'text-green-600';
            $resultText = 'Victoire';
        } else {
            $resultClass = 'text-red-600';
            $resultText = 'Défaite';
        }
    }

    return [
        'class' => $resultClass,
        'text' => $resultText,
    ];
}
?>

<div class="container px-4 py-8 max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-purple-900 mb-4 sm:mb-0">
            <i class="fas fa-history mr-2"></i>Historique des parties
        </h1>
        <a href="/game/play.php" class="flex items-center justify-center gap-2 bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-6 rounded-lg transition-all shadow-md hover:shadow-lg">
            <i class="fas fa-play"></i> Nouvelle partie
        </a>
    </div>
    
    <?php if (empty($games)): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-6 mb-6 rounded-lg shadow-sm">
            <div class="flex flex-col md:flex-row items-center">
                <div class="flex-shrink-0 mr-4 text-blue-500 text-4xl mb-4 md:mb-0">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="flex-grow">
                    <p class="font-medium text-lg mb-3">Vous n'avez pas encore joué de parties.</p>
                    <a href="/game/play.php" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition-all">
                        <i class="fas fa-play mr-2"></i> Commencer à jouer
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($games as $game): ?>
                <?php
                //-------------------------------
                // Forcer l'état « Terminée » pour le rendu :
                // on ne souhaite plus afficher les parties « En cours / En attente ».
                //-------------------------------
                if ($game['status'] !== 'finished') {
                    $game['status'] = 'finished';
                }

                // Garantir la présence de la clé "result" pour éviter le warning PHP
                if (!isset($game['result'])) {
                    if ($game['winner_id'] === null) {
                        $game['result'] = 'draw';
                    } elseif ($game['winner_id'] == $game['player1_id']) {
                        $game['result'] = 'player1_won';
                    } else {
                        $game['result'] = 'player2_won';
                    }
                }

                // Determine opponent information
                $opponentId   = ($game['player1_id'] == $userId) ? $game['player2_id'] : $game['player1_id'];

                // Certains enregistrements peuvent ne pas posséder les clés *_username
                if ($game['player1_id'] == $userId) {
                    $opponentName = $game['player2_username'] ?? null;
                } else {
                    $opponentName = $game['player1_username'] ?? null;
                }

                // Si toujours null, tenter de récupérer dans la base JSON
                if ($opponentName === null && $opponentId > 0) {
                    $opponentUser = $db->getUserById($opponentId);
                    $opponentName = $opponentUser['username'] ?? 'Joueur';
                }
                
                // Pour les parties contre l'IA
                if ($opponentId == 0) {
                    $opponentName = 'Intelligence Artificielle';
                }
                
                // Get status and result information
                $statusInfo = getStatusInfo($game['status']);
                $resultInfo = getResultInfo($game, $userId);
                
                // Format the date
                $gameDate = new DateTime($game['created_at']);
                $formattedDate = $gameDate->format('d/m/Y H:i');
                
                // Determine the card border color based on result
                $cardBorderClass = 'border-gray-200';
                $cardAccentClass = 'border-t-gray-200';
                
                if ($game['status'] === 'finished') {
                    if ($resultInfo['text'] === 'Victoire') {
                        $cardBorderClass = 'border-green-200';
                        $cardAccentClass = 'border-t-green-500';
                    } elseif ($resultInfo['text'] === 'Défaite') {
                        $cardBorderClass = 'border-red-200';
                        $cardAccentClass = 'border-t-red-500';
                    } elseif ($resultInfo['text'] === 'Match nul') {
                        $cardBorderClass = 'border-yellow-200';
                        $cardAccentClass = 'border-t-yellow-500';
                    }
                } elseif ($game['status'] === 'in_progress') {
                    $cardBorderClass = 'border-blue-200';
                    $cardAccentClass = 'border-t-blue-500';
                }
                ?>
                
                <div class="group">
                    <div class="bg-white rounded-xl overflow-hidden shadow-md hover:shadow-lg transition-all border <?php echo $cardBorderClass; ?> border-t-4 <?php echo $cardAccentClass; ?> h-full flex flex-col">
                        <div class="p-4 flex justify-between items-center bg-gray-50">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusInfo['class']; ?> text-white flex items-center">
                                <i class="fas <?php echo $statusInfo['icon']; ?> mr-1"></i>
                                <?php echo htmlspecialchars($statusInfo['text']); ?>
                            </span>
                            <span class="<?php echo $resultInfo['class']; ?> font-semibold text-sm flex items-center">
                                <i class="fas fa-check mr-1"></i>
                                <?php echo htmlspecialchars($resultInfo['text']); ?>
                            </span>
                        </div>
                        <div class="p-5 flex-grow">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-lg font-semibold text-purple-900">
                                    <i class="fas fa-chess-board mr-2 text-purple-700"></i>Partie #<?php echo $game['id']; ?>
                                </h3>
                            </div>
                            
                            <div class="mb-1">
                                <p class="flex items-center mb-2">
                                    <span class="font-medium text-gray-700 mr-2">Adversaire:</span>
                                    <?php if ($opponentId > 0): ?>
                                        <a href="/profile.php?user_id=<?php echo htmlspecialchars($opponentId); ?>" class="text-purple-600 hover:text-purple-800 transition hover:underline">
                                            <?php echo htmlspecialchars($opponentName); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-800 flex items-center">
                                            <i class="fas fa-robot mr-1 text-purple-500"></i>
                                            <?php echo htmlspecialchars($opponentName); ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-gray-500 text-sm flex items-center">
                                    <i class="far fa-calendar-alt mr-2"></i>
                                    <?php echo htmlspecialchars($formattedDate); ?>
                                </p>
                            </div>
                        </div>
                        <div class="px-5 pb-5 pt-2 border-t border-gray-100">
                            <?php /* Toujours "Revoir" puisque status est forcé à finished */ ?>
                            <a href="/game/replay.php?game_id=<?php echo htmlspecialchars($game['id']); ?>" class="flex items-center justify-center w-full py-2 px-4 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg transition-colors shadow-sm hover:shadow">
                                <i class="fas fa-redo mr-2"></i> Revoir
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// Include the footer
include_once __DIR__ . '/../../backend/includes/footer.php';
?> 