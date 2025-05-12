<?php
// Système de classement ELO qui fonctionne sans base de données
// Les données sont stockées dans un fichier JSON

// Inclure les fichiers nécessaires pour le header
require_once __DIR__ . '/../backend/includes/config.php';
require_once __DIR__ . '/../backend/includes/session.php';

// -----------------------------------------------------------------------------
// REMPLACEMENT : utiliser JsonDatabase pour récupérer le vrai classement
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../backend/db/JsonDatabase.php';

$jsonDb = JsonDatabase::getInstance();
$playersFromJson = $jsonDb->getLeaderboard($jsonDb->countActivePlayers(), 0);

$data = [ 'players' => [], 'games' => [] ];
foreach ($playersFromJson as $p) {
    $data['players'][] = [
        'id'           => $p['user_id'],
        'username'     => $p['username'],
        'avatar'       => null,
        'rating'       => $p['rating'],
        'games_played' => $p['games_played'],
        'wins'         => $p['games_won'],
        'losses'       => $p['games_lost'],
        'draws'        => $p['draws'] ?? 0,
        'rank'         => $p['rank']
    ];
}

// Écraser les joueurs mock par ceux récupérés depuis JsonDatabase
if (!empty($playersFromJson)) {
    $data['players'] = $playersFromJson;
}

// Les blocs de simulation / ajout manuel ne sont plus nécessaires à partir d'ici
// (initializeExamplePlayers, simulate_match, etc.)

// Chemin vers le fichier de données
$dataFile = __DIR__ . '/elo_data.json';

// Fonction pour charger les données
function loadData() {
    global $dataFile;
    if (file_exists($dataFile)) {
        $data = json_decode(file_get_contents($dataFile), true);
        return $data ?: ['players' => [], 'games' => []];
    }
    return ['players' => [], 'games' => []];
}

// Fonction pour sauvegarder les données
function saveData($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
}

// Fonction pour calculer le nouveau classement ELO
function calculateEloChange($playerRating, $opponentRating, $result) {
    // K est le facteur de développement (plus élevé pour les nouveaux joueurs)
    $k = ($playerRating < 2100) ? 32 : (($playerRating < 2400) ? 24 : 16);
    
    // Calcul de la probabilité de gagner
    $expectedScore = 1 / (1 + pow(10, ($opponentRating - $playerRating) / 400));
    
    // Calcul du nouveau classement
    return $k * ($result - $expectedScore);
}

// Fonction pour initialiser des joueurs d'exemple
function initializeExamplePlayers() {
    $data = loadData();
    
    // Seulement initialiser si aucun joueur n'existe
    if (empty($data['players'])) {
        $players = [
            ['id' => 1, 'username' => 'GrandMaster', 'avatar' => null, 'rating' => 2400, 'games_played' => 120, 'wins' => 98, 'losses' => 20, 'draws' => 2],
            ['id' => 2, 'username' => 'CheckerKing', 'avatar' => null, 'rating' => 2250, 'games_played' => 85, 'wins' => 62, 'losses' => 15, 'draws' => 8],
            ['id' => 3, 'username' => 'TacticalPlayer', 'avatar' => null, 'rating' => 2100, 'games_played' => 70, 'wins' => 45, 'losses' => 20, 'draws' => 5],
            ['id' => 4, 'username' => 'CasualGamer', 'avatar' => null, 'rating' => 1850, 'games_played' => 40, 'wins' => 20, 'losses' => 18, 'draws' => 2],
            ['id' => 5, 'username' => 'Beginner123', 'avatar' => null, 'rating' => 1600, 'games_played' => 25, 'wins' => 10, 'losses' => 15, 'draws' => 0],
            ['id' => 6, 'username' => 'CheckersFan', 'avatar' => null, 'rating' => 1750, 'games_played' => 30, 'wins' => 15, 'losses' => 10, 'draws' => 5],
            ['id' => 7, 'username' => 'ProMaster', 'avatar' => null, 'rating' => 2300, 'games_played' => 100, 'wins' => 80, 'losses' => 15, 'draws' => 5],
            ['id' => 8, 'username' => 'StrategyLover', 'avatar' => null, 'rating' => 1950, 'games_played' => 50, 'wins' => 30, 'losses' => 15, 'draws' => 5],
            ['id' => 9, 'username' => 'BoardGamePro', 'avatar' => null, 'rating' => 2050, 'games_played' => 65, 'wins' => 40, 'losses' => 20, 'draws' => 5],
            ['id' => 10, 'username' => 'NewPlayer', 'avatar' => null, 'rating' => 1500, 'games_played' => 10, 'wins' => 3, 'losses' => 7, 'draws' => 0],
        ];
        
        $data['players'] = $players;
        saveData($data);
    }
}

// Initialiser des joueurs d'exemple si nécessaire
initializeExamplePlayers();

// Traitement des actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $data = loadData();
        
        // Simule un match entre deux joueurs
        if ($_POST['action'] === 'simulate_match') {
            $player1Id = (int)$_POST['player1'];
            $player2Id = (int)$_POST['player2'];
            $result = $_POST['result']; // 'win', 'loss', 'draw'
            
            // Trouver les joueurs
            $player1Key = array_search($player1Id, array_column($data['players'], 'id'));
            $player2Key = array_search($player2Id, array_column($data['players'], 'id'));
            
            if ($player1Key !== false && $player2Key !== false) {
                $player1 = &$data['players'][$player1Key];
                $player2 = &$data['players'][$player2Key];
                
                // Définir les scores pour le calcul ELO
                $player1Result = ($result === 'win') ? 1 : (($result === 'draw') ? 0.5 : 0);
                $player2Result = ($result === 'loss') ? 1 : (($result === 'draw') ? 0.5 : 0);
                
                // Calculer les changements d'ELO
                $player1Change = calculateEloChange($player1['rating'], $player2['rating'], $player1Result);
                $player2Change = calculateEloChange($player2['rating'], $player1['rating'], $player2Result);
                
                // Mettre à jour les classements
                $player1['rating'] = max(round($player1['rating'] + $player1Change), 100);
                $player2['rating'] = max(round($player2['rating'] + $player2Change), 100);
                
                // Mettre à jour les statistiques
                $player1['games_played']++;
                $player2['games_played']++;
                
                if ($result === 'win') {
                    $player1['wins']++;
                    $player2['losses']++;
                } elseif ($result === 'loss') {
                    $player1['losses']++;
                    $player2['wins']++;
                } else {
                    $player1['draws']++;
                    $player2['draws']++;
                }
                
                // Sauvegarder le match dans l'historique
                $data['games'][] = [
                    'id' => count($data['games']) + 1,
                    'player1_id' => $player1Id,
                    'player2_id' => $player2Id,
                    'result' => $result,
                    'player1_rating_change' => round($player1Change),
                    'player2_rating_change' => round($player2Change),
                    'date' => date('Y-m-d H:i:s')
                ];
                
                saveData($data);
                
                $message = "Match simulé avec succès! {$player1['username']} (" . round($player1Change) . " points) vs {$player2['username']} (" . round($player2Change) . " points)";
                $messageType = 'success';
            }
        }
        
        // Ajouter un nouveau joueur
        elseif ($_POST['action'] === 'add_player') {
            $username = trim($_POST['username']);
            
            if (!empty($username)) {
                $newId = 1;
                if (!empty($data['players'])) {
                    $newId = max(array_column($data['players'], 'id')) + 1;
                }
                
                $data['players'][] = [
                    'id' => $newId,
                    'username' => $username,
                    'avatar' => null,
                    'rating' => 1500, // Rating de départ
                    'games_played' => 0,
                    'wins' => 0,
                    'losses' => 0,
                    'draws' => 0
                ];
                
                saveData($data);
                
                $message = "Joueur {$username} ajouté avec succès!";
                $messageType = 'success';
            }
        }
        
        // Réinitialiser tous les joueurs
        elseif ($_POST['action'] === 'reset') {
            // Supprimer le fichier de données
            if (file_exists($dataFile)) {
                unlink($dataFile);
            }
            // Réinitialiser les joueurs d'exemple
            initializeExamplePlayers();
            
            $message = "Classement réinitialisé avec les joueurs par défaut!";
            $messageType = 'success';
        }
    }
}

// Charger les données actuelles
$data = loadData();

// -------------------------------------------------------------------------
// AJOUT : garantir que l'utilisateur connecté figure dans le classement
// -------------------------------------------------------------------------
if (Session::isLoggedIn()) {
    $currentUserId = Session::getUserId();

    // Vérifier si l'utilisateur existe déjà dans le tableau $data['players']
    $found = false;
    foreach ($data['players'] as $p) {
        if (isset($p['id']) && $p['id'] == $currentUserId) {
            $found = true;
            break;
        }
    }

    if (!$found) {
        // Récupérer les stats depuis JsonDatabase
        $userStats = $jsonDb->getUserStats($currentUserId);
        $user = $jsonDb->getUserById($currentUserId);

        $data['players'][] = [
            'id'           => $currentUserId,
            'username'     => $user['username'] ?? ('Utilisateur #' . $currentUserId),
            'avatar'       => null,
            'rating'       => is_array($userStats) && isset($userStats['rating']) ? $userStats['rating'] : 1500,
            'games_played' => is_array($userStats) && isset($userStats['games_played']) ? $userStats['games_played'] : 0,
            'wins'         => is_array($userStats) && isset($userStats['games_won']) ? $userStats['games_won'] : 0,
            'losses'       => is_array($userStats) && isset($userStats['games_lost']) ? $userStats['games_lost'] : 0,
            'draws'        => is_array($userStats) && isset($userStats['draws']) ? $userStats['draws'] : 0,
        ];
    }
}

// Trier les joueurs par classement (du plus élevé au plus bas)
usort($data['players'], function($a, $b) {
    return $b['rating'] - $a['rating'];
});

// Limiter l'historique aux 10 derniers matchs
$recentGames = array_slice(array_reverse($data['games']), 0, 10);

// Attribuer des rangs
foreach ($data['players'] as $key => $player) {
    $data['players'][$key]['rank'] = $key + 1;
}

// Calculer les pourcentages de victoire
foreach ($data['players'] as $key => $player) {
    if ($player['games_played'] > 0) {
        $data['players'][$key]['win_percentage'] = round(($player['wins'] / $player['games_played']) * 100, 1);
    } else {
        $data['players'][$key]['win_percentage'] = 0;
    }
}

// Set the title of the page
$pageTitle = "Classement des joueurs";

// Include the header
include_once __DIR__ . '/../backend/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Classement des joueurs</h1>
    
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="bg-white shadow overflow-hidden rounded-lg mb-8">
        <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
            <h2 class="text-lg leading-6 font-medium text-gray-900">Classement des joueurs</h2>
            <div class="flex space-x-2">
                <form method="post" class="inline">
                    <input type="hidden" name="action" value="reset">
                    <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        Réinitialiser
                    </button>
                </form>
            </div>
        </div>
        <div class="border-t border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rang</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joueur</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ELO</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parties</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">V/D/N</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">% Victoires</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($data['players'] as $player): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo $player['rank']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 rounded-full bg-purple-200 flex items-center justify-center">
                                        <span class="text-purple-800 font-bold"><?php echo substr($player['username'], 0, 2); ?></span>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $player['username']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 font-bold"><?php echo $player['rating']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo $player['games_played']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <span class="text-green-600"><?php echo $player['wins']; ?></span> / 
                                    <span class="text-red-600"><?php echo $player['losses']; ?></span> / 
                                    <span class="text-gray-600"><?php echo $player['draws']; ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo $player['win_percentage']; ?>%</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Simuler un match -->
        <div class="bg-white shadow overflow-hidden rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h2 class="text-lg leading-6 font-medium text-gray-900">Simuler un match</h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Créez un match entre deux joueurs pour voir l'évolution du classement</p>
            </div>
            <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                <form method="post" class="space-y-4">
                    <input type="hidden" name="action" value="simulate_match">
                    
                    <div>
                        <label for="player1" class="block text-sm font-medium text-gray-700">Joueur 1</label>
                        <select id="player1" name="player1" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-purple-500 focus:border-purple-500 sm:text-sm rounded-md">
                            <?php foreach ($data['players'] as $player): ?>
                                <option value="<?php echo $player['id']; ?>"><?php echo $player['username']; ?> (ELO: <?php echo $player['rating']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="player2" class="block text-sm font-medium text-gray-700">Joueur 2</label>
                        <select id="player2" name="player2" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-purple-500 focus:border-purple-500 sm:text-sm rounded-md">
                            <?php foreach ($data['players'] as $player): ?>
                                <option value="<?php echo $player['id']; ?>" <?php echo ($player === $data['players'][1]) ? 'selected' : ''; ?>><?php echo $player['username']; ?> (ELO: <?php echo $player['rating']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Résultat (pour le Joueur 1)</label>
                        <div class="mt-2 space-x-4 flex">
                            <div class="flex items-center">
                                <input id="win" name="result" type="radio" value="win" class="focus:ring-purple-500 h-4 w-4 text-purple-600 border-gray-300" checked>
                                <label for="win" class="ml-2 block text-sm text-gray-700">
                                    Victoire
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input id="draw" name="result" type="radio" value="draw" class="focus:ring-purple-500 h-4 w-4 text-purple-600 border-gray-300">
                                <label for="draw" class="ml-2 block text-sm text-gray-700">
                                    Match nul
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input id="loss" name="result" type="radio" value="loss" class="focus:ring-purple-500 h-4 w-4 text-purple-600 border-gray-300">
                                <label for="loss" class="ml-2 block text-sm text-gray-700">
                                    Défaite
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                            Simuler le match
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Ajouter un joueur -->
        <div class="bg-white shadow overflow-hidden rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h2 class="text-lg leading-6 font-medium text-gray-900">Ajouter un joueur</h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Créez un nouveau joueur avec un classement ELO de départ à 1500</p>
            </div>
            <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                <form method="post" class="space-y-4">
                    <input type="hidden" name="action" value="add_player">
                    
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Nom d'utilisateur</label>
                        <input type="text" name="username" id="username" class="mt-1 focus:ring-purple-500 focus:border-purple-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                    </div>
                    
                    <div>
                        <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                            Ajouter le joueur
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Historique des matchs récents -->
    <?php if (!empty($recentGames)): ?>
        <div class="mt-8 bg-white shadow overflow-hidden rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h2 class="text-lg leading-6 font-medium text-gray-900">Matchs récents</h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Les 10 derniers matchs joués</p>
            </div>
            <div class="border-t border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joueur 1</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joueur 2</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Résultat</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Changement ELO</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recentGames as $game): 
                            // Récupérer les joueurs
                            $player1Key = array_search($game['player1_id'], array_column($data['players'], 'id'));
                            $player2Key = array_search($game['player2_id'], array_column($data['players'], 'id'));
                            
                            $player1 = ($player1Key !== false) ? $data['players'][$player1Key] : ['username' => 'Inconnu'];
                            $player2 = ($player2Key !== false) ? $data['players'][$player2Key] : ['username' => 'Inconnu'];
                            
                            // Déterminer le résultat
                            $resultText = '';
                            $resultClass = '';
                            
                            if ($game['result'] === 'win') {
                                $resultText = 'Victoire de ' . $player1['username'];
                                $resultClass = 'text-green-600';
                            } elseif ($game['result'] === 'loss') {
                                $resultText = 'Victoire de ' . $player2['username'];
                                $resultClass = 'text-red-600';
                            } else {
                                $resultText = 'Match nul';
                                $resultClass = 'text-gray-600';
                            }
                        ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $game['date']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo $player1['username']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo $player2['username']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm <?php echo $resultClass; ?>"><?php echo $resultText; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm">
                                        <span class="<?php echo $game['player1_rating_change'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $game['player1_rating_change'] > 0 ? '+' . $game['player1_rating_change'] : $game['player1_rating_change']; ?>
                                        </span>
                                        /
                                        <span class="<?php echo $game['player2_rating_change'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $game['player2_rating_change'] > 0 ? '+' . $game['player2_rating_change'] : $game['player2_rating_change']; ?>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Empêcher la sélection du même joueur dans les deux listes
document.addEventListener('DOMContentLoaded', function() {
    const player1Select = document.getElementById('player1');
    const player2Select = document.getElementById('player2');
    
    function updateSelections() {
        const player1Value = player1Select.value;
        const player2Value = player2Select.value;
        
        // Si les deux joueurs sont les mêmes, changer le joueur 2
        if (player1Value === player2Value) {
            // Trouver une option différente
            for (let i = 0; i < player2Select.options.length; i++) {
                if (player2Select.options[i].value !== player1Value) {
                    player2Select.selectedIndex = i;
                    break;
                }
            }
        }
    }
    
    player1Select.addEventListener('change', updateSelections);
    player2Select.addEventListener('change', updateSelections);
    
    // Vérifier au chargement de la page
    updateSelections();
});
</script>

<?php
// Include the footer
include_once __DIR__ . '/../backend/includes/footer.php';
?> 