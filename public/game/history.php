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
require_once __DIR__ . '/../../backend/includes/session.php';

// Rediriger si l'utilisateur n'est pas connecté
if (!Session::isLoggedIn()) {
    // Nettoyer le buffer avant de rediriger
    ob_end_clean();
    header('Location: /auth/login.php');
    exit;
}

// Récupérer l'ID de l'utilisateur
$user_id = Session::getUserId();
$username = Session::getUsername() ? Session::getUsername() : 'Joueur';

// Récupérer les parties terminées (historique)
$gameController = new GameController();

// Essayer d'utiliser le contrôleur pour obtenir l'historique
$gameHistoryFromController = $gameController->readGameHistory($user_id);

// Ajouter des logs détaillés pour comprendre pourquoi l'historique ne s'affiche pas
error_log("history.php: Récupération de l'historique de jeu pour l'utilisateur {$user_id}");
error_log("history.php: Historique via contrôleur disponible? " . (isset($gameHistoryFromController) ? "Oui" : "Non"));
if (isset($gameHistoryFromController) && method_exists($gameHistoryFromController, 'rowCount')) {
    error_log("history.php: Nombre de parties via contrôleur: " . $gameHistoryFromController->rowCount());
} else {
    error_log("history.php: L'objet retourné par le contrôleur n'est pas un PDOStatement valide ou est null");
}

// REMPLACEZ TOUJOURS PAR UNE REQUÊTE DIRECTE - Solution de secours fiable
try {
    $db = Database::getInstance()->getConnection();
    
    // Vérifier d'abord si la table existe
    $checkTableQuery = "SHOW TABLES LIKE 'games'";
    $tableCheck = $db->query($checkTableQuery);
    
    if ($tableCheck->rowCount() === 0) {
        error_log("history.php: ERREUR CRITIQUE - La table 'games' n'existe pas dans la base de données!");
    } else {
        error_log("history.php: La table 'games' existe dans la base de données");
        
        // Vérifier que le statut 'finished' est bien utilisé
        $checkStatusQuery = "SELECT DISTINCT status FROM games";
        $statusCheck = $db->query($checkStatusQuery);
        $statuses = $statusCheck->fetchAll(PDO::FETCH_COLUMN);
        error_log("history.php: Statuts présents dans la base: " . implode(', ', $statuses));
        
        // Récupérer directement les parties terminées
        $query = "SELECT g.*, 
                 u1.username as player1_name, 
                 u2.username as player2_name 
                 FROM games g
                 LEFT JOIN users u1 ON g.player1_id = u1.id
                 LEFT JOIN users u2 ON g.player2_id = u2.id
                 WHERE (g.player1_id = ? OR g.player2_id = ?) 
                 AND g.status = 'finished'
                 ORDER BY g.updated_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id, $user_id]);
        
        error_log("history.php: Requête SQL directe - nombre de parties terminées: " . $stmt->rowCount());
        
        // Afficher quelques détails des parties trouvées pour le débogage
        $debugGames = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($debugGames as $game) {
            error_log("history.php: Partie ID " . $game['id'] . 
                      ", status: " . $game['status'] . 
                      ", player1_id: " . $game['player1_id'] . 
                      ", player2_id: " . $game['player2_id'] . 
                      ", winner_id: " . $game['winner_id']);
        }
        
        // Réexécuter la requête pour avoir les résultats
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id, $user_id]);
        $gameHistory = $stmt;
    }
} catch (Exception $e) {
    error_log("history.php: ERREUR lors de la requête directe: " . $e->getMessage());
    
    // Créer un PDOStatement vide en cas d'erreur
    $emptyQuery = "SELECT 1 WHERE 1=0";
    $emptyStmt = $db->prepare($emptyQuery);
    $emptyStmt->execute();
    $gameHistory = $emptyStmt;
}

// Calculer les statistiques à partir de l'historique des parties
$total_games = 0;
$victories = 0;
$defeats = 0;
$draws = 0;

// D'abord essayer d'obtenir les statistiques depuis la table stats
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM stats WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        error_log("history.php: Statistiques trouvées dans la table stats: " . json_encode($stats));
        $total_games = $stats['games_played'];
        $victories = $stats['games_won']; 
        $defeats = $stats['games_lost'];
        // Calculer les matchs nuls à partir de la table stats si disponible, sinon faire la différence
        // Un match nul est défini uniquement lorsque les deux joueurs sont bloqués
        $draws = $total_games - $victories - $defeats;
        error_log("history.php: Statistiques calculées à partir de la table stats - total: {$total_games}, victoires: {$victories}, défaites: {$defeats}, nuls: {$draws}");
    } else {
        error_log("history.php: Aucune statistique trouvée dans la table stats, calcul à partir de l'historique");
        // Si pas de statistiques dans la table stats, calculer depuis l'historique
        // Récupérer directement les parties de l'utilisateur pour les statistiques
        $query = "SELECT g.*, u1.username as player1_name, u2.username as player2_name 
                 FROM games g
                 JOIN users u1 ON g.player1_id = u1.id
                 LEFT JOIN users u2 ON g.player2_id = u2.id
                 WHERE (g.player1_id = ? OR g.player2_id = ?) 
                 AND g.status = 'finished'
                 ORDER BY g.updated_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id, $user_id]);
        
        $historyForStats = $stmt;
        
        error_log("history.php: Récupération directe de l'historique, nombre de parties: " . $historyForStats->rowCount());
        
        if ($historyForStats && $historyForStats->rowCount() > 0) {
            // Calculer les statistiques à partir de l'historique
            $total_games = $historyForStats->rowCount();
            
            while ($game = $historyForStats->fetch(PDO::FETCH_ASSOC)) {
                error_log("history.php: Traitement de la partie ID: " . $game['id'] . 
                          ", player1_id: " . $game['player1_id'] . 
                          ", player2_id: " . $game['player2_id'] . 
                          ", winner_id: " . $game['winner_id']);
                
                if ($game['winner_id'] === null && $game['player2_id'] == 0) {
                    // Partie contre l'IA sans gagnant = défaite
                    $defeats++;
                    error_log("history.php: Partie ID: " . $game['id'] . " = défaite (contre IA sans gagnant)");
                } else if ($game['winner_id'] == $user_id) {
                    // L'utilisateur est le gagnant
                    $victories++;
                    error_log("history.php: Partie ID: " . $game['id'] . " = victoire");
                } else if ($game['winner_id'] !== null && $game['winner_id'] != $user_id) {
                    // L'adversaire est le gagnant
                    $defeats++;
                    error_log("history.php: Partie ID: " . $game['id'] . " = défaite (l'adversaire a gagné)");
                } else if ($game['winner_id'] === null && $game['player2_id'] != 0) {
                    // Match nul (winner_id = null et pas contre l'IA)
                    $draws++;
                    error_log("history.php: Partie ID: " . $game['id'] . " = match nul");
                }
            }
            
            // Réexécuter la requête pour obtenir à nouveau l'historique
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id, $user_id]);
            $gameHistory = $stmt;
            
            error_log("history.php: Statistiques calculées - total: {$total_games}, victoires: {$victories}, défaites: {$defeats}, nuls: {$draws}");
        }
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
}

$pageTitle = "Historique des parties - " . APP_NAME;
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<link rel="stylesheet" href="/assets/css/style.css">

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-purple-600">Historique de vos parties</h1>
        <a href="/game/play.php" class="flex items-center text-purple-600 hover:text-purple-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
            </svg>
            Retour aux parties
        </a>
    </div>
    
    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-gray-50 p-4 rounded-lg shadow">
            <div class="text-center">
                <div class="text-3xl font-bold text-indigo-700"><?php echo $total_games; ?></div>
                <div class="text-gray-500">Parties jouées</div>
            </div>
        </div>
        
        <div class="bg-green-50 p-4 rounded-lg shadow">
            <div class="text-center">
                <div class="text-3xl font-bold text-green-600"><?php echo $victories; ?> (<?php echo $total_games > 0 ? round(($victories / $total_games) * 100) : 0; ?>%)</div>
                <div class="text-gray-500">Victoires</div>
            </div>
        </div>
        
        <div class="bg-red-50 p-4 rounded-lg shadow">
            <div class="text-center">
                <div class="text-3xl font-bold text-red-600"><?php echo $defeats; ?> (<?php echo $total_games > 0 ? round(($defeats / $total_games) * 100) : 0; ?>%)</div>
                <div class="text-gray-500">Défaites</div>
            </div>
        </div>
        
        <div class="bg-blue-50 p-4 rounded-lg shadow">
            <div class="text-center">
                <div class="text-3xl font-bold text-blue-600"><?php echo $draws; ?> (<?php echo $total_games > 0 ? round(($draws / $total_games) * 100) : 0; ?>%)</div>
                <div class="text-gray-500">Matchs nuls</div>
            </div>
        </div>
    </div>
    
    <!-- Historique des parties -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-800">Historique détaillé</h2>
            </div>
            
            <div class="mb-4 overflow-auto">
                <?php if ($gameHistory && method_exists($gameHistory, 'rowCount') && $gameHistory->rowCount() > 0): ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Partie #</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adversaire</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Résultat</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($game = $gameHistory->fetch(PDO::FETCH_ASSOC)): ?>
                                <?php
                                // Debug: Afficher les données de la partie
                                error_log("history.php: Affichage de la partie " . $game['id'] . ", winner_id: " . $game['winner_id']);
                                
                                // Déterminer si l'utilisateur est le joueur 1 ou 2
                                $isPlayer1 = $game['player1_id'] == $user_id;
                                
                                // Déterminer l'adversaire
                                $opponentName = $isPlayer1 ? ($game['player2_name'] ?? 'Inconnu') : ($game['player1_name'] ?? 'Inconnu');
                                if ($game['player2_id'] === '0' || $game['player2_id'] === 0) {
                                    $opponentName = 'Intelligence Artificielle';
                                }
                                
                                // Déterminer le résultat pour l'utilisateur
                                $resultClass = "bg-blue-100 text-blue-800"; // Match nul par défaut
                                $resultText = "Match nul";
                                $resultIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
                                
                                // Cas spécial: partie contre l'IA avec winner_id null, c'est une défaite pour le joueur humain
                                if (($game['player2_id'] === '0' || $game['player2_id'] === 0) && $game['winner_id'] === null) {
                                    $resultClass = "bg-red-100 text-red-800";
                                    $resultText = "Défaite";
                                    $resultIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
                                }
                                // Cas standard avec winner_id défini
                                else if ($game['winner_id'] !== null) {
                                    if ($game['winner_id'] == $user_id) {
                                        $resultClass = "bg-green-100 text-green-800";
                                        $resultText = "Victoire";
                                        $resultIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
                                    } else {
                                        $resultClass = "bg-red-100 text-red-800";
                                        $resultText = "Défaite";
                                        $resultIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
                                    }
                                }
                                // Match nul ne peut arriver que quand les deux joueurs sont bloqués et winner_id est null
                                // Ce cas est rare mais possible lorsque aucun des joueurs ne peut bouger
                                else if ($game['winner_id'] === null && $game['player2_id'] != 0) {
                                    $resultClass = "bg-blue-100 text-blue-800";
                                    $resultText = "Match nul";
                                    $resultIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
                                }
                                
                                // Formater la date
                                $date = new DateTime($game['updated_at']);
                                $formattedDate = $date->format('d/m/Y H:i');
                                ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">#<?php echo $game['id']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($opponentName); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo $formattedDate; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full <?php echo $resultClass; ?>">
                                            <?php echo $resultIcon; ?>
                                            <?php echo $resultText; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <a href="/game/replay.php?id=<?php echo $game['id']; ?>" class="text-purple-600 hover:text-purple-900 mr-3">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Replay
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-gray-500 text-lg text-center mb-2">Aucune partie terminée pour le moment.</p>
                        <p class="text-gray-400 text-center max-w-md">
                            Votre historique de jeu apparaîtra ici après avoir terminé des parties.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Si besoin d'ajouter des interactions JavaScript
});
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?> 