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

// Récupérer les parties actives de l'utilisateur
$gameController = new GameController();
$activeGames = $gameController->getActiveGames($user_id);

// Récupérer les parties terminées (historique)
$gameHistory = $gameController->readGameHistory($user_id);

// Récupérer un message éventuel de redirection
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';

$pageTitle = "Jouer - " . APP_NAME;
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<link rel="stylesheet" href="/assets/css/style.css">

<div class="container mx-auto px-4 py-8 mt-2 pt-4">
    <h1 class="text-3xl font-bold text-center text-purple-600 mb-8">Choisissez votre mode de jeu</h1>
    
    <?php if ($message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
        <!-- Option Jouer contre l'IA -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Jouer contre l'IA</h2>
                </div>
                
                <p class="text-gray-600 mb-4">Entraînez-vous contre notre intelligence artificielle</p>
                
                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <h3 class="text-md font-semibold text-purple-600 mb-2 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Avantages
                    </h3>
                    <ul class="space-y-2">
                        <li class="flex items-center text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Disponible 24h/24, 7j/7
                        </li>
                        <li class="flex items-center text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Commencez immédiatement
                        </li>
                        <li class="flex items-center text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Parfait pour s'entraîner
                        </li>
                    </ul>
                </div>
                
                <button id="play-bot" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg shadow transition duration-200 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                    </svg>
                    Commencer une partie contre l'IA
                </button>
            </div>
        </div>
        
        <!-- Section matchmaking -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Jouer contre un joueur</h2>
                </div>
                
                <p class="text-gray-600 mb-4">Affrontez d'autres joueurs en ligne en temps réel</p>
                
                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <h3 class="text-md font-semibold text-purple-600 mb-2 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Avantages
                    </h3>
                    <ul class="space-y-2">
                        <li class="flex items-center text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Matchmaking intelligent
                        </li>
                        <li class="flex items-center text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Compétitif et stimulant
                        </li>
                        <li class="flex items-center text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Rejoignez notre communauté
                        </li>
                    </ul>
                </div>
                
                <a href="/game/matchmaking.php" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg shadow transition duration-200 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                    </svg>
                    Trouver un adversaire
                </a>
            </div>
        </div>
    </div>
    
    <!-- Vos parties en cours -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden mb-8">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-800">Vos parties en cours</h2>
            </div>
            
            <div class="mb-4 overflow-auto max-h-80">
                <?php if ($activeGames && $activeGames->rowCount() > 0): ?>
                    <ul class="divide-y divide-gray-200">
                        <?php while ($game = $activeGames->fetch(PDO::FETCH_ASSOC)): ?>
                            <?php
                            // Déterminer si l'utilisateur est le joueur 1 ou 2
                            $isPlayer1 = $game['player1_id'] == $user_id;
                            
                            // Déterminer l'adversaire
                            $opponentName = $isPlayer1 ? $game['player2_name'] : $game['player1_name'];
                            if ($game['player2_id'] === '0' || $game['player2_id'] === 0) {
                                $opponentName = 'IA';
                            }
                            
                            // Déterminer si c'est au tour de l'utilisateur
                            $isUserTurn = ($isPlayer1 && $game['current_player'] == 1) || (!$isPlayer1 && $game['current_player'] == 2);
                            ?>
                            <li class="py-3">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <span class="font-medium">Partie #<?php echo $game['id']; ?></span>
                                        <p class="text-sm text-gray-600">
                                            Contre <?php echo htmlspecialchars($opponentName); ?>
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <?php if ($isUserTurn): ?>
                                            <span class="px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full">Votre tour</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full">Tour de l'adversaire</span>
                                        <?php endif; ?>
                                        <a href="/game/board.php?id=<?php echo $game['id']; ?>" class="text-purple-600 hover:text-purple-900">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <p class="text-gray-500 text-center">Vous n'avez aucune partie en cours.</p>
                        <p class="text-gray-500 text-center text-sm mt-1">Commencez une nouvelle partie en choisissant un mode de jeu.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Statistiques et historique des parties -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden mb-8">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-800">Vos statistiques</h2>
            </div>
            
            <?php
            // Calculer les statistiques à partir de l'historique des parties
            $total_games = 0;
            $victories = 0;
            $defeats = 0;
            $draws = 0;
            
            // 1. Essayer d'obtenir les statistiques directement de la table stats (méthode préférée)
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT * FROM stats WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user_stats) {
                    $total_games = $user_stats['games_played'];
                    $victories = $user_stats['games_won'];
                    $defeats = $user_stats['games_lost'];
                    $draws = $total_games - $victories - $defeats;
                    
                    error_log("play.php: Statistiques récupérées depuis la table stats - parties: {$total_games}, victoires: {$victories}, défaites: {$defeats}, nuls: {$draws}");
                } else {
                    // Si aucune statistique n'est trouvée, on les calcule directement avec une requête
                    error_log("play.php: Aucune statistique trouvée dans la table stats, calcul direct via SQL");
                    
                    $stmt = $db->prepare("SELECT 
                        COUNT(*) as total_games,
                        SUM(CASE WHEN winner_id = ? THEN 1 ELSE 0 END) as victories,
                        SUM(CASE WHEN winner_id IS NULL THEN 1 ELSE 0 END) as draws,
                        SUM(CASE WHEN winner_id IS NOT NULL AND winner_id != ? THEN 1 ELSE 0 END) as defeats
                        FROM games 
                        WHERE (player1_id = ? OR player2_id = ?) 
                        AND status = 'finished'");
                    $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
                    $calculated_stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $total_games = $calculated_stats['total_games'];
                    $victories = $calculated_stats['victories'];
                    $draws = $calculated_stats['draws'];
                    $defeats = $calculated_stats['defeats'];
                    
                    error_log("play.php: Stats calculées via SQL - parties: {$total_games}, victoires: {$victories}, défaites: {$defeats}, nuls: {$draws}");
                    
                    // Créer ou mettre à jour les statistiques dans la table stats
                    $stmt = $db->prepare("INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game) 
                        VALUES (?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                        games_played = ?,
                        games_won = ?,
                        games_lost = ?,
                        last_game = NOW()");
                    $stmt->execute([$user_id, $total_games, $victories, $defeats, $total_games, $victories, $defeats]);
                    error_log("play.php: Statistiques créées/mises à jour dans la table stats");
                }
            } catch (Exception $e) {
                error_log("play.php: Erreur lors de la récupération des statistiques: " . $e->getMessage());
                
                // En cas d'erreur, fallback sur la méthode originale
                // Récupérer les parties terminées pour l'affichage des statistiques
                $historyForStats = $gameController->readGameHistory($user_id);
                
                // Ajout d'un log de débogage
                error_log("play.php: Récupération de l'historique pour l'utilisateur {$user_id}, nombre de parties: " . ($historyForStats ? $historyForStats->rowCount() : 'null'));
                
                // Si le comptage direct a échoué, utiliser la méthode par défaut
                if ($historyForStats && $historyForStats->rowCount() > 0) {
                    // Compter les parties
                    $total_games = $historyForStats->rowCount();
                    
                    // Calculer les statistiques
                    while ($game = $historyForStats->fetch(PDO::FETCH_ASSOC)) {
                        error_log("play.php: Examen de la partie ID: " . $game['id'] . ", winner_id: " . $game['winner_id'] . ", user_id: {$user_id}");
                        
                        if ($game['winner_id'] == $user_id) {
                            $victories++;
                            error_log("play.php: Comptabilisé comme victoire");
                        } elseif ($game['winner_id'] == null) {
                            $draws++;
                            error_log("play.php: Comptabilisé comme match nul");
                        } else {
                            $defeats++;
                            error_log("play.php: Comptabilisé comme défaite");
                        }
                    }
                    
                    error_log("play.php: Totaux - parties: {$total_games}, victoires: {$victories}, défaites: {$defeats}, nuls: {$draws}");
                }
            }
            
            // Récupérer à nouveau l'historique pour l'affichage du tableau
            $gameHistory = $gameController->readGameHistory($user_id);
            
            // Calculer les pourcentages
            $win_rate = $total_games > 0 ? round(($victories / $total_games) * 100) : 0;
            $loss_rate = $total_games > 0 ? round(($defeats / $total_games) * 100) : 0;
            $draw_rate = $total_games > 0 ? round(($draws / $total_games) * 100) : 0;
            ?>
            
            <!-- Affichage des statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-50 p-4 rounded-lg text-center">
                    <h3 class="text-lg font-semibold text-gray-800"><?php echo $total_games; ?></h3>
                    <p class="text-sm text-gray-600">Parties jouées</p>
                </div>
                
                <div class="bg-green-50 p-4 rounded-lg text-center">
                    <h3 class="text-lg font-semibold text-green-600"><?php echo $victories; ?> (<?php echo $win_rate; ?>%)</h3>
                    <p class="text-sm text-gray-600">Victoires</p>
                </div>
                
                <div class="bg-red-50 p-4 rounded-lg text-center">
                    <h3 class="text-lg font-semibold text-red-600"><?php echo $defeats; ?> (<?php echo $loss_rate; ?>%)</h3>
                    <p class="text-sm text-gray-600">Défaites</p>
                </div>
                
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <h3 class="text-lg font-semibold text-blue-600"><?php echo $draws; ?> (<?php echo $draw_rate; ?>%)</h3>
                    <p class="text-sm text-gray-600">Matchs nuls</p>
                </div>
            </div>
            
            <h3 class="text-lg font-semibold text-purple-600 mb-4">Historique des parties</h3>
            
            <?php if ($gameHistory && $gameHistory->rowCount() > 0): ?>
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Partie</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adversaire</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Résultat</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($game = $gameHistory->fetch(PDO::FETCH_ASSOC)): ?>
                                <?php
                                // Déterminer si l'utilisateur est le joueur 1 ou 2
                                $isPlayer1 = $game['player1_id'] == $user_id;
                                
                                // Déterminer l'adversaire
                                if ($game['player2_id'] === '0' || $game['player2_id'] === 0) {
                                    $opponentName = 'Intelligence Artificielle';
                                } else {
                                    $opponentName = $isPlayer1 ? $game['player2_name'] : $game['player1_name'];
                                }
                                
                                // Déterminer le résultat
                                $result = '';
                                $resultClass = '';
                                $resultBadgeClass = '';
                                $resultIcon = '';
                                
                                if ($game['winner_id'] == $user_id) {
                                    $result = 'Victoire';
                                    $resultClass = 'text-green-600';
                                    $resultBadgeClass = 'bg-green-100 border-green-500';
                                    $resultIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>';
                                } elseif ($game['winner_id'] == null) {
                                    $result = 'Match nul';
                                    $resultClass = 'text-yellow-600';
                                    $resultBadgeClass = 'bg-yellow-100 border-yellow-500';
                                    $resultIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                                    </svg>';
                                } else {
                                    // Défaite si quelqu'un d'autre a gagné, ou si on a joué contre l'IA et qu'on n'est pas le vainqueur
                                    $result = 'Défaite';
                                    $resultClass = 'text-red-600';
                                    $resultBadgeClass = 'bg-red-100 border-red-500';
                                    $resultIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>';
                                }
                                
                                // Formater la date
                                $date = new DateTime($game['created_at']);
                                $formattedDate = $date->format('d/m/Y H:i');
                                ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            #<?php echo $game['id']; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-medium">
                                            <?php echo htmlspecialchars($opponentName); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo $game['player2_id'] === '0' || $game['player2_id'] === 0 ? 'Intelligence Artificielle' : 'Joueur humain'; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo $formattedDate; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="px-3 py-2 inline-flex items-center text-sm leading-5 font-semibold rounded-lg border <?php echo $resultBadgeClass; ?> <?php echo $resultClass; ?>">
                                                <?php echo $resultIcon; ?>
                                                <?php echo $result; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="/game/board.php?id=<?php echo $game['id']; ?>&view=true" class="text-purple-600 hover:text-purple-900 flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                            Voir le replay
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="flex flex-col items-center justify-center py-8 bg-gray-50 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <p class="text-gray-500 text-center">Aucune partie terminée pour le moment.</p>
                    <p class="text-gray-500 text-center text-sm mt-1">Votre historique de jeu apparaîtra ici après avoir terminé des parties.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de chargement pour IA -->
<div id="loading-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold text-indigo-600 mb-4">Création de la partie...</h3>
        <div class="flex items-center justify-center mb-6">
            <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-indigo-500"></div>
        </div>
        <p class="text-gray-700 text-center">Veuillez patienter pendant que nous préparons votre partie contre l'IA.</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variables pour la gestion de la partie contre l'IA
    const playBotBtn = document.getElementById('play-bot');
    const loadingModal = document.getElementById('loading-modal');
    
    // Afficher automatiquement l'historique si l'URL contient #history-section
    if (window.location.hash === '#history-section') {
        const historySection = document.getElementById('history-section');
        if (historySection) {
            historySection.classList.remove('hidden');
            // Faire défiler jusqu'à la section
            historySection.scrollIntoView({ behavior: 'smooth' });
        }
    }
    
    // Gestion du bouton d'affichage de l'historique
    const showHistoryBtn = document.getElementById('show-history');
    if (showHistoryBtn) {
        showHistoryBtn.addEventListener('click', function() {
            const historySection = document.getElementById('history-section');
            if (historySection) {
                historySection.classList.toggle('hidden');
                if (!historySection.classList.contains('hidden')) {
                    historySection.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    }
    
    // Fonction pour créer une partie contre l'IA
    function playAgainstBot() {
        console.log('Clic sur le bouton IA détecté');
        loadingModal.classList.remove('hidden');
        
        fetch('/api/game/create_bot_game.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({})
        })
        .then(response => {
            console.log('Réponse reçue:', response);
            return response.json();
        })
        .then(data => {
            console.log('Données reçues:', data);
            
            if (data.success) {
                // Rediriger vers la partie
                window.location.href = '/game/board.php?id=' + data.game_id;
            } else {
                loadingModal.classList.add('hidden');
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            loadingModal.classList.add('hidden');
            alert('Une erreur est survenue lors de la création de la partie.');
        });
    }
    
    // Ajouter l'écouteur d'événement
    if (playBotBtn) {
        playBotBtn.addEventListener('click', playAgainstBot);
        console.log('Écouteur configuré pour le bouton IA');
    } else {
        console.error('Bouton IA non trouvé');
    }
});
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>