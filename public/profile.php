<?php
// Activer l'affichage des erreurs en développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Charger les classes nécessaires
require_once __DIR__ . '/../backend/includes/config.php';
require_once __DIR__ . '/../backend/includes/session.php';
require_once __DIR__ . '/../backend/db/Database.php';

// Vérifier si l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    // Rediriger vers la page de connexion
    header('Location: /auth/login.php');
    exit;
}

// Récupérer les informations de l'utilisateur
$userId = Session::getUserId();
$username = Session::getUsername();

// Récupérer les informations d'email et date d'inscription depuis la base de données
try {
    // Utiliser getInstance() au lieu de new Database()
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Requête pour les informations utilisateur
    $userQuery = "SELECT email, created_at FROM users WHERE id = :user_id";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $userStmt->execute();
    
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    $email = $userData['email'] ?? '';
    $memberSince = $userData['created_at'] ?? date('Y-m-d');
    
    // Formater la date d'inscription
    $memberSinceFormatted = date('d/m/Y', strtotime($memberSince));
    
    // Requête pour les statistiques
    $statsQuery = "SELECT 
        COUNT(*) as total_games,
        SUM(CASE WHEN winner_id = :user_id THEN 1 ELSE 0 END) as victories
        FROM games
        WHERE (player1_id = :user_id OR player2_id = :user_id) 
        AND status = 'completed'";
    
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $statsStmt->execute();
    
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    $totalGames = $stats['total_games'] ?? 0;
    $victories = $stats['victories'] ?? 0;
    $winRate = $totalGames > 0 ? round(($victories / $totalGames) * 100) : 0;
    
    // Requête pour les parties récentes
    $recentGamesQuery = "SELECT g.id, g.created_at, g.status, g.winner_id,
        u1.username as player1_name, u2.username as player2_name
        FROM games g
        JOIN users u1 ON g.player1_id = u1.id
        JOIN users u2 ON g.player2_id = u2.id
        WHERE (g.player1_id = :user_id OR g.player2_id = :user_id)
        ORDER BY g.created_at DESC LIMIT 5";
    
    $recentGamesStmt = $conn->prepare($recentGamesQuery);
    $recentGamesStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $recentGamesStmt->execute();
    
    $recentGames = $recentGamesStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // En cas d'erreur, définir des valeurs par défaut
    $email = '';
    $memberSinceFormatted = date('d/m/Y');
    $totalGames = 0;
    $victories = 0;
    $winRate = 0;
    $recentGames = [];
    
    // Log de l'erreur
    error_log("Erreur lors de la récupération des données utilisateur: " . $e->getMessage());
}

// Définir le titre de la page
$pageTitle = "Profil - " . APP_NAME;

// Inclure l'en-tête
include __DIR__ . '/../backend/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="profile-container bg-white rounded-lg shadow-md p-6">
        <div class="profile-header">
            <div class="profile-avatar">
                <span><?php echo strtoupper(substr($username, 0, 1)); ?></span>
            </div>
            <div class="profile-info">
                <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($username); ?></h1>
                <p class="text-gray-600"><?php echo htmlspecialchars($email); ?></p>
                <p class="text-gray-500 text-sm mt-1">Membre depuis: <?php echo $memberSinceFormatted; ?></p>
            </div>
        </div>
        
        <div class="profile-stats">
            <div class="stat-card shadow-sm">
                <div class="stat-value"><?php echo $totalGames; ?></div>
                <div class="stat-label">Parties jouées</div>
            </div>
            <div class="stat-card shadow-sm">
                <div class="stat-value"><?php echo $victories; ?></div>
                <div class="stat-label">Victoires</div>
            </div>
            <div class="stat-card shadow-sm">
                <div class="stat-value"><?php echo $winRate; ?>%</div>
                <div class="stat-label">Taux de victoire</div>
            </div>
        </div>
        
        <div class="recent-games mt-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Parties récentes</h2>
            
            <?php if (empty($recentGames)): ?>
                <div class="text-center py-8">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <p class="text-gray-500">Aucune partie récente à afficher.</p>
                    <a href="/game/play.php" class="button-primary inline-block mt-4">Commencer une partie</a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adversaire</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Résultat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($recentGames as $game): 
                                // Déterminer l'adversaire
                                $opponent = ($game['player1_name'] == $username) ? $game['player2_name'] : $game['player1_name'];
                                
                                // Déterminer le résultat
                                $result = "";
                                if ($game['status'] == 'completed') {
                                    if ($game['winner_id'] == $userId) {
                                        $result = '<span class="text-green-600 font-medium">Victoire</span>';
                                    } else {
                                        $result = '<span class="text-red-600 font-medium">Défaite</span>';
                                    }
                                } else if ($game['status'] == 'in_progress') {
                                    $result = '<span class="text-yellow-600 font-medium">En cours</span>';
                                } else {
                                    $result = '<span class="text-gray-600">Annulée</span>';
                                }
                                
                                // Formater la date
                                $gameDate = date('d/m/Y H:i', strtotime($game['created_at']));
                            ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $gameDate; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($opponent); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                            $statusText = '';
                                            switch ($game['status']) {
                                                case 'in_progress':
                                                    $statusText = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">En cours</span>';
                                                    break;
                                                case 'completed':
                                                    $statusText = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Terminée</span>';
                                                    break;
                                                default:
                                                    $statusText = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">'.ucfirst($game['status']).'</span>';
                                            }
                                            echo $statusText;
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $result; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-6">
                    <a href="/game/play.php" class="button-primary">Jouer une nouvelle partie</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Inclure le pied de page
include __DIR__ . '/../backend/includes/footer.php';
?> 