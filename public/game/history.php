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

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; // Increased number of games per page for grid view
$offset = ($page - 1) * $limit;

// Log l'ID de l'utilisateur et les paramètres de pagination
error_log("history.php: Demande d'historique pour l'utilisateur ID: " . $userId . ", page: " . $page . ", limit: " . $limit . ", offset: " . $offset);

// Create game controller
$gameController = new GameController();

// Get user's games with pagination
try {
    $games = $gameController->getUserGames($userId, $limit, $offset);
    $totalGames = $gameController->countUserGames($userId);
    $totalPages = ceil($totalGames / $limit);
    
    // Log le nombre de parties récupérées
    error_log("history.php: Nombre de parties récupérées: " . count($games) . ", total: " . $totalGames);
    
    // Vérification directe dans la base de données
    if (empty($games) && $totalGames === 0) {
        error_log("Vérification directe dans la base de données");
        $db = Database::getInstance()->getConnection();
        $query = "SELECT id, player1_id, player2_id, status, winner_id, created_at FROM games WHERE player1_id = ? OR player2_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $userId, PDO::PARAM_INT);
        $stmt->execute();
        $dbGames = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Vérification directe: " . count($dbGames) . " parties trouvées.");
        
        if (!empty($dbGames)) {
            foreach ($dbGames as $g) {
                error_log("Partie ID: " . $g['id'] . ", status: " . $g['status'] . ", winner_id: " . ($g['winner_id'] ?? 'null'));
            }
        }
    }
} catch (Exception $e) {
    $error = "Une erreur est survenue lors de la récupération de l'historique des parties : " . $e->getMessage();
    error_log("history.php: Erreur: " . $e->getMessage());
    $games = [];
    $totalGames = 0;
    $totalPages = 0;
}

// Function to get status class and text
function getStatusInfo($status) {
    // Log le statut reçu
    error_log("getStatusInfo appelé avec status: " . $status);
    
    switch ($status) {
        case 'finished':
            return [
                'class' => 'bg-green-500',
                'text' => 'Terminée',
                'icon' => 'fa-flag-checkered'
            ];
        case 'completed': // Alias possible pour finished
            error_log("Status 'completed' converti en 'Terminée'");
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
        case 'waiting':
            return [
                'class' => 'bg-yellow-500',
                'text' => 'En attente',
                'icon' => 'fa-clock'
            ];
        default:
            error_log("Status inconnu: " . $status);
            return [
                'class' => 'bg-gray-500',
                'text' => 'Inconnu (' . $status . ')',
                'icon' => 'fa-question-circle'
            ];
    }
}

/**
 * Get result info for a game
 * @param array $game
 * @return array
 */
function getResultInfo($game, $userId)
{
    $resultClass = 'text-gray-500';
    $resultText = 'En attente';

    if ($game['status'] === 'cancelled') {
        $resultClass = 'text-gray-500';
        $resultText = 'Annulée';
        return [
            'class' => $resultClass,
            'text' => $resultText,
        ];
    }

    if ($game['status'] === 'completed' || $game['status'] === 'finished') {
        // Vérifier si c'est une partie contre l'IA
        $isPlayer1 = $game['player1_id'] == $userId;
        $isAgainstAI = ($isPlayer1 && $game['player2_id'] == 0) || (!$isPlayer1 && $game['player1_id'] == 0);
        
        // Utiliser le champ result pour déterminer le résultat
        if ($game['result'] === 'draw') {
            // Contre l'IA, un match nul est en réalité une défaite pour le joueur humain
            if ($isAgainstAI && (($isPlayer1 && $game['player2_id'] == 0) || (!$isPlayer1 && $game['player1_id'] == 0))) {
                $resultClass = 'text-red-600';
                $resultText = 'Défaite';
            } else {
                // Un vrai match nul entre joueurs humains
                $resultClass = 'text-yellow-500';
                $resultText = 'Match nul';
            }
        } else {
            $isWinner = ($isPlayer1 && $game['result'] === 'player1_won') || 
                        (!$isPlayer1 && $game['result'] === 'player2_won');
            
            if ($isWinner) {
                $resultClass = 'text-green-600';
                $resultText = 'Victoire';
            } else {
                $resultClass = 'text-red-600';
                $resultText = 'Défaite';
            }
        }
    }

    return [
        'class' => $resultClass,
        'text' => $resultText,
    ];
}

// Set the title of the page
$pageTitle = "Historique des parties";

// Include the header
include_once __DIR__ . '/../../backend/includes/header.php';
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
    
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                </div>
                <div>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
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
        
        <!-- Message de débogage minimal -->
        <div class="bg-gray-100 border border-gray-300 p-4 rounded-lg mt-6 text-sm text-gray-700">
            <p class="font-semibold mb-2"><strong>Informations de débogage :</strong></p>
            <p>Utilisateur ID: <?php echo $userId; ?></p>
            <p>Nombre total de parties selon countUserGames: <?php echo $totalGames; ?></p>
            
            <?php 
            // S'assurer que $db est défini
            $db = Database::getInstance()->getConnection();
            
            // Vérification des statistiques
            $statQuery = "SELECT games_played, games_won, games_lost, draws FROM stats WHERE user_id = ?";
            $statStmt = $db->prepare($statQuery);
            $statStmt->execute([$userId]);
            $userStats = $statStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userStats) {
                echo "<p>Statistiques dans la base de données:</p>";
                echo "<ul>";
                echo "<li>Parties jouées: " . $userStats['games_played'] . "</li>";
                echo "<li>Victoires: " . $userStats['games_won'] . "</li>";
                echo "<li>Défaites: " . $userStats['games_lost'] . "</li>";
                echo "<li>Matchs nuls: " . $userStats['draws'] . "</li>";
                echo "</ul>";
                
                // Vérifier la cohérence
                $totalFromStats = $userStats['games_played'];
                if ($totalFromStats != $totalGames) {
                    echo "<p style='color:red'>INCOHÉRENCE DÉTECTÉE: Le nombre de parties dans les statistiques (" . $totalFromStats . ") ne correspond pas au nombre de parties terminées (" . $totalGames . ")</p>";
                }
            } else {
                echo "<p>Aucune statistique trouvée pour cet utilisateur.</p>";
            }
            ?>
            
            <p>Si vous voyez ce message alors que vous avez joué des parties, il y a un problème avec la récupération des données.</p>
        </div>
    <?php else: ?>
        <!-- Filtres et tri -->
        <div class="bg-white rounded-xl shadow-md mb-8 overflow-hidden">
            <div class="p-4 flex flex-col sm:flex-row justify-between items-center">
                <div class="flex space-x-1 mb-4 sm:mb-0">
                    <button type="button" class="px-4 py-2 rounded-lg bg-purple-600 text-white font-medium text-sm focus:outline-none hover:bg-purple-700 transition-colors">
                        Toutes
                    </button>
                    <button type="button" class="px-4 py-2 rounded-lg bg-white border border-purple-300 text-purple-700 font-medium text-sm focus:outline-none hover:bg-purple-50 transition-colors">
                        En cours
                    </button>
                    <button type="button" class="px-4 py-2 rounded-lg bg-white border border-purple-300 text-purple-700 font-medium text-sm focus:outline-none hover:bg-purple-50 transition-colors">
                        Terminées
                    </button>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-gray-600 text-sm">Affichage :</span>
                    <div class="flex">
                        <button type="button" class="p-2 bg-purple-600 text-white rounded-l-lg focus:outline-none hover:bg-purple-700 transition-colors">
                            <i class="fas fa-th"></i>
                        </button>
                        <button type="button" class="p-2 bg-white border border-purple-300 text-purple-700 rounded-r-lg focus:outline-none hover:bg-purple-50 transition-colors">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($games as $game): ?>
                <?php
                // Determine opponent information
                $opponentId = $game['player1_id'] == $userId ? $game['player2_id'] : $game['player1_id'];
                $opponentName = $game['player1_id'] == $userId ? $game['player2_username'] : $game['player1_username'];
                
                // For games against AI (player2_id = 0), set a default name
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
                            <?php if ($game['status'] === 'in_progress'): ?>
                                <a href="/game/board.php?id=<?php echo htmlspecialchars($game['id']); ?>" class="flex items-center justify-center w-full py-2 px-4 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors shadow-sm hover:shadow">
                                    <i class="fas fa-play mr-2"></i> Continuer
                                </a>
                            <?php elseif ($game['status'] === 'finished'): ?>
                                <a href="/game/replay.php?game_id=<?php echo htmlspecialchars($game['id']); ?>" class="flex items-center justify-center w-full py-2 px-4 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg transition-colors shadow-sm hover:shadow">
                                    <i class="fas fa-redo mr-2"></i> Revoir
                                </a>
                            <?php else: ?>
                                <span class="flex items-center justify-center w-full py-2 px-4 bg-gray-200 text-gray-500 font-medium rounded-lg cursor-not-allowed">
                                    <i class="fas fa-ban mr-2"></i> Non disponible
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="flex items-center" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-l-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                            <i class="fas fa-chevron-left mr-1"></i>
                            Précédent
                        </a>
                    <?php else: ?>
                        <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-l-md text-gray-400 bg-gray-100 border border-gray-300 cursor-not-allowed">
                            <i class="fas fa-chevron-left mr-1"></i>
                            Précédent
                        </span>
                    <?php endif; ?>
                    
                    <div class="hidden md:flex">
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold border border-purple-500 bg-purple-50 text-purple-700">
                                    <?php echo $i; ?>
                                </span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    
                    <div class="md:hidden flex items-center px-4 py-2 text-sm font-medium border border-gray-300 bg-white text-gray-700">
                        Page <?php echo $page; ?> sur <?php echo $totalPages; ?>
                    </div>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-r-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                            Suivant
                            <i class="fas fa-chevron-right ml-1"></i>
                        </a>
                    <?php else: ?>
                        <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-r-md text-gray-400 bg-gray-100 border border-gray-300 cursor-not-allowed">
                            Suivant
                            <i class="fas fa-chevron-right ml-1"></i>
                        </span>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Include the footer
include_once __DIR__ . '/../../backend/includes/footer.php';
?> 