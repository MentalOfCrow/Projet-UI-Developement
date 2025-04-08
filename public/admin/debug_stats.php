<?php
// Démarrer la session et la mise en tampon de sortie
session_start();
ob_start();

// Activer l'affichage des erreurs en développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enregistrer les erreurs dans un fichier de log
ini_set('log_errors', 1);
ini_set('error_log', '../../backend/logs/php_errors.log');

// Inclure les fichiers nécessaires
require_once '../../backend/includes/config.php';
require_once '../../backend/db/Database.php';

// Variables pour les messages
$message = '';
$messageType = '';
$userStats = null;
$gameHistory = [];

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Utilisateur';

// Générer un jeton CSRF pour la protection du formulaire
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Traiter l'action de réparation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Vérifier le jeton CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Erreur de sécurité: jeton CSRF invalide.";
        $messageType = 'danger';
    } else {
        try {
            $action = $_POST['action'];
            
            if ($action === 'repair_stats') {
                // Inclure le script de réparation
                require_once '../../backend/tools/repair_stats.php';
                
                // Exécuter la réparation pour l'utilisateur connecté
                $success = repairUserStats($userId);
                
                if ($success) {
                    $message = "Vos statistiques ont été réparées avec succès.";
                    $messageType = 'success';
                } else {
                    $message = "Erreur lors de la réparation des statistiques.";
                    $messageType = 'danger';
                }
            }
        } catch (Exception $e) {
            $message = "Erreur lors de la réparation: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Récupérer les statistiques actuelles de l'utilisateur
try {
    $db = Database::getInstance()->getConnection();
    
    // Vérifier si la table stats existe
    $tableExists = false;
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'stats'");
        $tableExists = ($stmt->rowCount() > 0);
    } catch (Exception $e) {
        error_log("Erreur lors de la vérification de la table stats: " . $e->getMessage());
    }
    
    if ($tableExists) {
        // Récupérer les statistiques de l'utilisateur
        $stmt = $db->prepare("SELECT * FROM stats WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Récupérer l'historique des parties de l'utilisateur (max 10 dernières parties)
    $stmt = $db->prepare("
        SELECT g.*, 
               p1.username as player1_name, 
               p2.username as player2_name,
               CASE 
                  WHEN g.player2_id = 0 THEN 'BOT'
                  ELSE p2.username 
               END as opponent_name
        FROM games g
        LEFT JOIN users p1 ON g.player1_id = p1.id
        LEFT JOIN users p2 ON g.player2_id = p2.id
        WHERE (g.player1_id = ? OR g.player2_id = ?)
        AND g.status = 'finished'
        ORDER BY g.updated_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId, $userId]);
    $gameHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des données: " . $e->getMessage());
    $message = "Erreur lors de la récupération des données.";
    $messageType = 'danger';
}

// Inclure l'en-tête
include_once '../../backend/includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="display-4">Mes statistiques</h1>
            <p class="lead">Visualisez et réparez vos statistiques de jeu.</p>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h2 class="h5 mb-0">Mes statistiques actuelles</h2>
                        </div>
                        <div class="card-body">
                            <?php if ($userStats): ?>
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <h4 class="h6">Parties jouées</h4>
                                        <div class="display-5"><?php echo $userStats['games_played']; ?></div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h4 class="h6">Victoires</h4>
                                        <div class="display-5 text-success"><?php echo $userStats['games_won']; ?></div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h4 class="h6">Défaites</h4>
                                        <div class="display-5 text-danger"><?php echo $userStats['games_lost']; ?></div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h4 class="h6">Nuls</h4>
                                        <div class="display-5 text-info"><?php echo $userStats['games_played'] - $userStats['games_won'] - $userStats['games_lost']; ?></div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <p class="text-muted">Dernière partie: <?php echo !empty($userStats['last_game']) ? date('d/m/Y H:i', strtotime($userStats['last_game'])) : 'N/A'; ?></p>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    Aucune statistique trouvée. Cliquez sur le bouton "Réparer" pour générer vos statistiques.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <form method="post" action="" id="repair-form">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="repair_stats">
                                <button type="submit" class="btn btn-warning" id="repair-button" onclick="return confirm('Êtes-vous sûr de vouloir réparer vos statistiques? Cela recalculera toutes vos statistiques en fonction de votre historique de jeu.');">
                                    <i class="bi bi-tools"></i> Réparer mes statistiques
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h2 class="h5 mb-0">Que fait la réparation?</h2>
                        </div>
                        <div class="card-body">
                            <p>La réparation de vos statistiques permet de:</p>
                            <ul>
                                <li>Recalculer le nombre total de parties jouées</li>
                                <li>Recalculer le nombre de victoires et de défaites</li>
                                <li>Mettre à jour la date de votre dernière partie</li>
                                <li>Corriger toute incohérence dans vos statistiques</li>
                            </ul>
                            <div class="alert alert-warning mt-3">
                                <i class="bi bi-exclamation-triangle"></i> Cette opération est utile si vous pensez que vos statistiques affichées ne correspondent pas à votre historique de jeu réel.
                            </div>
                            <p class="mt-3">Les statistiques sont calculées uniquement à partir des parties terminées (statut "finished").</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($gameHistory)): ?>
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h2 class="h5 mb-0">Mes dernières parties</h2>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Adversaire</th>
                                        <th>Date</th>
                                        <th>Résultat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gameHistory as $game): ?>
                                        <tr>
                                            <td><?php echo $game['id']; ?></td>
                                            <td>
                                                <?php 
                                                    // Déterminer l'adversaire
                                                    if ($game['player1_id'] == $userId) {
                                                        echo $game['player2_id'] == 0 ? 'BOT' : htmlspecialchars($game['player2_name']);
                                                    } else {
                                                        echo htmlspecialchars($game['player1_name']);
                                                    }
                                                ?>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($game['updated_at'])); ?></td>
                                            <td>
                                                <?php
                                                    // Déterminer le résultat
                                                    if ($game['winner_id'] == $userId) {
                                                        echo '<span class="badge bg-success">Victoire</span>';
                                                    } elseif ($game['winner_id'] !== null && $game['winner_id'] != $userId) {
                                                        echo '<span class="badge bg-danger">Défaite</span>';
                                                    } else {
                                                        echo '<span class="badge bg-info">Nul</span>';
                                                    }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <a href="/game/history.php" class="btn btn-primary">Voir tout mon historique</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    Aucune partie terminée trouvée dans votre historique.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Inclure le pied de page
include_once '../../backend/includes/footer.php';

// Termine la mise en tampon de sortie et envoie le contenu
ob_end_flush();
?> 