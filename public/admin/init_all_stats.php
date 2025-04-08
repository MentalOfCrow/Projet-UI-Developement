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
$stats = [];

// Vérifier si l'utilisateur est connecté et est administrateur
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Vérifier si l'utilisateur est administrateur (ID = 1 ou rôle admin)
$userId = $_SESSION['user_id'];
$isAdmin = false;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Vérifier si l'utilisateur est admin (ID = 1 ou rôle admin)
    $isAdmin = ($userId == 1 || (isset($user['role']) && $user['role'] === 'admin'));
    
    if (!$isAdmin) {
        header('Location: ../index.php');
        exit;
    }
} catch (Exception $e) {
    $message = "Erreur lors de la vérification des droits d'accès: " . $e->getMessage();
    $messageType = 'danger';
}

// Générer un jeton CSRF pour la protection du formulaire
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Traiter l'action d'initialisation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Vérifier le jeton CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Erreur de sécurité: jeton CSRF invalide.";
        $messageType = 'danger';
    } else {
        try {
            $action = $_POST['action'];
            
            if ($action === 'init_stats') {
                // Déterminer si on doit réinitialiser les statistiques existantes
                $resetExisting = isset($_POST['reset']) && $_POST['reset'] === '1';
                
                // Inclure le script d'initialisation
                require_once '../../backend/tools/init_all_stats.php';
                
                // Exécuter l'initialisation
                ob_start();
                $success = initAllStats($resetExisting);
                $output = ob_get_clean();
                
                if ($success) {
                    $message = "Les statistiques ont été initialisées avec succès.";
                    $messageType = 'success';
                } else {
                    $message = "Erreur lors de l'initialisation des statistiques.";
                    $messageType = 'danger';
                }
                
                // Enregistrer l'output dans le log pour référence
                error_log("Résultat de l'initialisation des statistiques: " . str_replace("\n", " ", $output));
            }
        } catch (Exception $e) {
            $message = "Erreur lors de l'initialisation: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Récupérer les statistiques actuelles pour affichage
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
        // Récupérer les statistiques avec les noms d'utilisateur
        $query = "SELECT s.*, u.username FROM stats s 
                  JOIN users u ON s.user_id = u.id 
                  ORDER BY s.games_played DESC, s.games_won DESC 
                  LIMIT 50";
        $stmt = $db->query($query);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
}

// Inclure l'en-tête
include_once '../../backend/includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="display-4">Administration des Statistiques</h1>
            <p class="lead">Cette page permet d'initialiser les statistiques de tous les utilisateurs.</p>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h2 class="h5 mb-0">Initialisation des statistiques</h2>
                </div>
                <div class="card-body">
                    <form method="post" action="" id="init-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="init_stats">
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="reset" name="reset" value="1">
                            <label class="form-check-label" for="reset">Réinitialiser toutes les statistiques existantes avant de recalculer</label>
                        </div>
                        
                        <div class="mb-3">
                            <p><strong>Note:</strong> Cette opération peut prendre plusieurs minutes selon le nombre d'utilisateurs et de parties.</p>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="init-button" onclick="return confirm('Êtes-vous sûr de vouloir initialiser les statistiques pour tous les utilisateurs?');">
                            Initialiser les statistiques
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($stats)): ?>
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h2 class="h5 mb-0">Statistiques actuelles</h2>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Parties jouées</th>
                                        <th>Victoires</th>
                                        <th>Défaites</th>
                                        <th>Nuls</th>
                                        <th>Dernière partie</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats as $stat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($stat['username']); ?></td>
                                            <td><?php echo $stat['games_played']; ?></td>
                                            <td><?php echo $stat['games_won']; ?></td>
                                            <td><?php echo $stat['games_lost']; ?></td>
                                            <td><?php echo $stat['games_played'] - $stat['games_won'] - $stat['games_lost']; ?></td>
                                            <td><?php echo !empty($stat['last_game']) ? date('d/m/Y H:i', strtotime($stat['last_game'])) : 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    Aucune statistique trouvée. Cliquez sur le bouton "Initialiser les statistiques" pour générer les statistiques.
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