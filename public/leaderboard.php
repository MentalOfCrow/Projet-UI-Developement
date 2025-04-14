<?php
// Start output buffering to prevent any previous output
ob_start();

// Set display_errors for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include configuration files
require_once __DIR__ . '/../backend/includes/config.php';
require_once __DIR__ . '/../backend/includes/session.php';
require_once __DIR__ . '/../backend/models/Game.php';
require_once __DIR__ . '/../backend/controllers/GameController.php';
require_once __DIR__ . '/../backend/controllers/ProfileController.php';

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; // Number of players per page
$offset = ($page - 1) * $limit;

// Create profile controller
$profileController = new ProfileController();

// Update user activity if logged in
if (Session::isLoggedIn()) {
    $profileController->updateActivity();
}

// Create game controller
$gameController = new GameController();

// Get top players with pagination
try {
    $players = $gameController->getTopPlayers($limit, $offset);
    $totalPlayers = $gameController->countActivePlayers();
    $totalPages = ceil($totalPlayers / $limit);
} catch (Exception $e) {
    $error = "Une erreur est survenue lors de la récupération du classement : " . $e->getMessage();
    $players = [];
    $totalPlayers = 0;
    $totalPages = 0;
}

// Set the title of the page
$pageTitle = "Classement des joueurs";

// Include the header
include_once __DIR__ . '/../backend/includes/header.php';
?>

<div class="container mt-4">
    <h1>Classement des joueurs</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($players)): ?>
        <div class="alert alert-info">
            Aucun joueur classé pour le moment.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Joueur</th>
                        <th>Parties jouées</th>
                        <th>Victoires</th>
                        <th>Défaites</th>
                        <th>% Victoires</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $index => $player): ?>
                        <?php 
                        $rank = $offset + $index + 1;
                        
                        // Calculate percentages and format data
                        $totalGames = $player['games_played'];
                        $wins = $player['games_won'];
                        $losses = $player['games_lost'];
                        $winPercentage = $totalGames > 0 ? round(($wins / $totalGames) * 100, 1) : 0;
                        
                        // Determine row style for current user
                        $isCurrentUser = Session::isLoggedIn() && Session::getUserId() == $player['id'];
                        $rowClass = $isCurrentUser ? 'table-primary' : '';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td><?php echo $rank; ?></td>
                            <td>
                                <a href="/profile.php?user_id=<?php echo htmlspecialchars($player['id']); ?>">
                                    <?php echo htmlspecialchars($player['username']); ?>
                                </a>
                            </td>
                            <td><?php echo $totalGames; ?></td>
                            <td><?php echo $wins; ?></td>
                            <td><?php echo $losses; ?></td>
                            <td><?php echo $winPercentage; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Pagination du classement">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Précédent">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link" aria-hidden="true">&laquo;</span>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Suivant">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link" aria-hidden="true">&raquo;</span>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Include the footer
include_once __DIR__ . '/../backend/includes/footer.php';
?> 