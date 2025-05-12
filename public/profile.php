<?php
// Start output buffering to prevent any output before headers are sent
ob_start();

// Enable error display in development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../backend/includes/config.php';
require_once __DIR__ . '/../backend/includes/session.php';
require_once __DIR__ . '/../backend/db/Database.php';
require_once __DIR__ . '/../backend/controllers/ProfileController.php';
require_once __DIR__ . '/../backend/controllers/FriendController.php';
require_once __DIR__ . '/../backend/controllers/NotificationController.php';
require_once __DIR__ . '/../backend/controllers/GameController.php';
require_once __DIR__ . '/../backend/db/JsonDatabase.php';

// Get user ID from URL or use logged-in user's ID
$profileUserId = isset($_GET['id']) ? intval($_GET['id']) : (Session::isLoggedIn() ? Session::getUserId() : null);

// If no valid user ID is found, redirect to homepage
if (!$profileUserId) {
    header('Location: /index.php');
    exit;
}

// Get logged-in user's ID (if logged in)
$currentUserId = Session::isLoggedIn() ? Session::getUserId() : null;
$isOwnProfile = $currentUserId && $currentUserId === $profileUserId;

// Create instance of ProfileController
$profileController = new ProfileController();

// Try to get the profile data
try {
    $profileData = $profileController->getProfile($profileUserId);
    
    // If profile not found
    if (!$profileData) {
        $error = "Ce profil n'est pas disponible.";
    } 
    // If profile is private and not the owner
    else if (isset($profileData['privacy_level']) && $profileData['privacy_level'] === 'private' && !$isOwnProfile) {
        $error = "Ce profil est privé.";
    }
    else {
        $username = $profileData['username'] ?? 'Utilisateur inconnu';
        $email = $isOwnProfile ? ($profileData['email'] ?? '') : '';
        $memberSince = isset($profileData['created_at']) ? date('d/m/Y', strtotime($profileData['created_at'])) : 'Date inconnue';
        $privacyLevel = $profileData['privacy_level'] ?? 'friends';
        
        // Update user activity if they're logged in
        if ($currentUserId) {
            $profileController->updateActivity();
        }
    }
    
    // Récupération des statistiques de jeu depuis les fichiers JSON (plus de dépendance MySQL)
    $jsonDb = JsonDatabase::getInstance();
    // Correction ultime : forcer la reconstruction de l'index ET la resynchronisation complète
    $gamesDir = dirname(__DIR__) . '/data/games/';
    $games = glob($gamesDir . 'game_*.json');
    foreach ($games as $gameFile) {
        $gameData = json_decode(file_get_contents($gameFile), true);
        if (!$gameData) continue;
        // Marquer comme terminée si un résultat est présent
        if (isset($gameData['result']) && (!isset($gameData['status']) || $gameData['status'] !== 'finished')) {
            $gameData['status'] = 'finished';
            $jsonDb->saveGame($gameData);
        }
        // On ne prend que les parties terminées
        if ((isset($gameData['status']) && $gameData['status'] === 'finished')) {
            if ((isset($gameData['player1_id']) && $gameData['player1_id'] == $profileUserId) ||
                (isset($gameData['player2_id']) && $gameData['player2_id'] == $profileUserId)) {
                $jsonDb->updateGamesIndex($profileUserId, $gameData['id']);
            }
        }
    }
    // Synchronisation forcée
    $jsonDb->synchronizeUserStats($profileUserId);
    $stats = $jsonDb->getUserStats($profileUserId);

    // Après récupération des stats JSON, recalculer via les parties réelles
    $allGames = $jsonDb->getUserGames($profileUserId);
    $totalGames = count($allGames);
    $victories = 0;
    $defeats   = 0;
    $draws     = 0;
    foreach ($allGames as $g) {
        $winnerId = $g['winner_id'] ?? null;
        if (array_key_exists('winner_id', $g)) {
            // Partie terminée: déterminer le résultat
            if ($winnerId === null) {
                $draws++;
            } elseif ($winnerId == $profileUserId) {
                $victories++;
            } else {
                $defeats++;
            }
        } elseif (isset($g['result'])) {
            // Fallback sur result si winner_id manquant
            $res = $g['result'];
            if ($res === 'draw') {
                $draws++;
            } else {
                $isPlayer1 = ($g['player1_id'] == $profileUserId);
                if (($isPlayer1 && $res === 'player1_won') || (!$isPlayer1 && $res === 'player2_won')) {
                    $victories++;
                } else {
                    $defeats++;
                }
            }
        }
    }
    $winRate  = $totalGames > 0 ? round(($victories / $totalGames) * 100, 1) : 0;

    // ------------------------------------------------------------------
    // Gestion des amis (facultatif – désactivé si MySQL n'est pas dispo)
    // ------------------------------------------------------------------
    $friendController = null;
    
    $friends = [];
    if ($friendController) {
        if ($isOwnProfile || 
            (!isset($error) && isset($profileData['privacy_level']) && 
             ($profileData['privacy_level'] === 'public' || 
              ($profileData['privacy_level'] === 'friends' && $currentUserId && $friendController->areFriends($currentUserId, $profileUserId))))) {
            $friendsResult = $friendController->getFriendsList($profileUserId);
            $friends = $friendsResult['success'] ? ($friendsResult['friends'] ?? []) : [];
        }
    }

    // Pending requests uniquement si l'utilisateur possède FriendController
    $pendingRequests = [];
    if ($isOwnProfile && $friendController) {
        $pendingRequestsData = $friendController->getPendingFriendRequests();
        $pendingRequests     = $pendingRequestsData['success'] ? ($pendingRequestsData['received'] ?? []) : [];
    }
    
    // Récupérer le rang du joueur dans le leaderboard (mode JSON uniquement)
    $activePlayers = $jsonDb->countActivePlayers();
    $leaderboard   = $jsonDb->getLeaderboard($activePlayers, 0);
    $rank = 0;
    foreach ($leaderboard as $entry) {
        if (($entry['user_id'] ?? null) == $profileUserId) {
            $rank = $entry['rank'];
            break;
        }
    }

} catch (Exception $e) {
    $error = "Une erreur s'est produite lors du chargement du profil.";
    error_log("Error loading profile: " . $e->getMessage());
}

// Définir le titre de la page
$pageTitle = isset($username) ? "Profil de " . $username : "Profil - " . APP_NAME;

// Inclure l'en-tête
include __DIR__ . '/../backend/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php else: ?>
    
    <div class="profile-container bg-white rounded-lg shadow-md p-6">
        <div class="profile-header flex items-center mb-6">
            <div class="profile-avatar bg-primary text-white rounded-full w-20 h-20 flex items-center justify-center text-3xl mr-4">
                <span><?php echo isset($username) ? strtoupper(substr($username, 0, 1)) : '?'; ?></span>
            </div>
            <div class="profile-info">
                <h1 class="text-2xl font-bold text-gray-800"><?php echo isset($username) ? htmlspecialchars($username) : 'Utilisateur inconnu'; ?></h1>
                <?php if ($isOwnProfile && isset($email)): ?>
                    <p class="text-gray-600"><?php echo htmlspecialchars($email); ?></p>
                <?php endif; ?>
                <p class="text-gray-500 text-sm mt-1">Membre depuis: <?php echo isset($memberSince) ? $memberSince : 'Date inconnue'; ?></p>
                
                <?php if (!$isOwnProfile && $currentUserId): ?>
                    <?php 
                    // Système d'amis uniquement si disponible
                    $areFriends       = false;
                    $hasSentRequest   = false;
                    $hasReceivedRequest = false;

                    if ($friendController) {
                        // Vérifier si ils sont amis
                        $areFriends = $friendController->areFriends($currentUserId, $profileUserId);

                        // Vérifier les demandes d'amis en attente
                        $pendingRequestsInfo = $friendController->getPendingFriendRequests();
                        if ($pendingRequestsInfo['success'] && !empty($pendingRequestsInfo['requests'])) {
                            foreach ($pendingRequestsInfo['requests'] as $request) {
                                if ($request['sender_id'] == $profileUserId) {
                                    $hasReceivedRequest = true;
                                    break;
                                }
                            }
                        }

                        // Vérifier si l'utilisateur courant a envoyé une demande
                        $sentRequestsInfo = $friendController->getPendingFriendRequests();
                        if ($sentRequestsInfo['success'] && !empty($sentRequestsInfo['requests'])) {
                            foreach ($sentRequestsInfo['requests'] as $request) {
                                if ($request['receiver_id'] == $profileUserId) {
                                    $hasSentRequest = true;
                                    break;
                                }
                            }
                        }
                    }
                    ?>
                    
                    <?php if ($areFriends): ?>
                        <button id="removeFriend" class="mt-2 px-3 py-1 bg-red-500 text-white rounded text-sm" data-id="<?php echo $profileUserId; ?>">Retirer des amis</button>
                    <?php elseif ($hasSentRequest): ?>
                        <button class="mt-2 px-3 py-1 bg-gray-300 text-gray-700 rounded text-sm" disabled>Demande envoyée</button>
                    <?php elseif ($hasReceivedRequest): ?>
                        <div class="flex mt-2">
                            <button id="acceptRequest" class="px-3 py-1 bg-green-500 text-white rounded text-sm mr-2" data-id="<?php echo $profileUserId; ?>">Accepter</button>
                            <button id="rejectRequest" class="px-3 py-1 bg-red-500 text-white rounded text-sm" data-id="<?php echo $profileUserId; ?>">Refuser</button>
                        </div>
                    <?php else: ?>
                        <button id="sendRequest" class="mt-2 px-3 py-1 bg-primary text-white rounded text-sm" data-id="<?php echo $profileUserId; ?>">Ajouter en ami</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="profile-stats grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="stat-card bg-gray-50 p-4 rounded shadow-sm text-center">
                <div class="stat-value text-3xl font-bold text-gray-800"><?php echo $totalGames; ?></div>
                <div class="stat-label text-gray-600">Parties jouées</div>
            </div>
            <div class="stat-card bg-gray-50 p-4 rounded shadow-sm text-center">
                <div class="stat-value text-3xl font-bold text-green-600"><?php echo $victories; ?></div>
                <div class="stat-label text-gray-600">Victoires</div>
            </div>
            <div class="stat-card bg-gray-50 p-4 rounded shadow-sm text-center">
                <div class="stat-value text-3xl font-bold text-yellow-600"><?php echo $draws; ?></div>
                <div class="stat-label text-gray-600">Matchs nuls</div>
            </div>
            <div class="stat-card bg-gray-50 p-4 rounded shadow-sm text-center">
                <div class="stat-value text-3xl font-bold text-blue-600"><?php echo $winRate; ?>%</div>
                <div class="stat-label text-gray-600">Taux de victoire</div>
            </div>
        </div>
        
        <?php if ($isOwnProfile && ($totalGames != ($stats['games_played'] ?? 0) || $totalGames == 0)): ?>
        <div class="mt-4 text-center">
            <a href="/sync_stats.php" class="text-blue-600 hover:text-blue-800 underline">
                <i class="fas fa-sync-alt mr-1"></i> Synchroniser mes statistiques
            </a>
            <p class="text-xs text-gray-500 mt-1">Si vos statistiques ne semblent pas à jour, cliquez ici pour les synchroniser</p>
        </div>
        <?php endif; ?>
        
        <!-- Affichage du rang -->
        <?php if ($rank > 0): ?>
        <div class="mt-4 text-center">
            <span class="inline-block bg-purple-100 text-purple-800 px-4 py-2 rounded-full text-lg font-semibold">
                Classement: <?php echo $rank; ?><?php echo $rank == 1 ? 'er' : 'ème'; ?>
            </span>
            <a href="/leaderboard.php" class="text-purple-600 hover:text-purple-800 ml-3">
                Voir le classement complet →
            </a>
        </div>
        <?php endif; ?>
        
        <?php if ($isOwnProfile): ?>
        <!-- Profile Settings - Only visible to profile owner -->
        <div class="profile-settings mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Paramètres du profil</h2>
            
            <div class="tabs">
                <div class="tab-buttons flex border-b mb-4">
                    <button class="tab-button py-2 px-4 font-medium text-gray-600 border-b-2 border-transparent hover:text-primary hover:border-primary active" data-tab="general">Général</button>
                    <button class="tab-button py-2 px-4 font-medium text-gray-600 border-b-2 border-transparent hover:text-primary hover:border-primary" data-tab="security">Sécurité</button>
                    <button class="tab-button py-2 px-4 font-medium text-gray-600 border-b-2 border-transparent hover:text-primary hover:border-primary" data-tab="privacy">Confidentialité</button>
                </div>
                
                <div class="tab-content" id="general-tab">
                    <form id="updateProfileForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="username" class="block text-gray-700 mb-1">Nom d'utilisateur</label>
                            <input type="text" id="username" name="username" class="w-full px-3 py-2 border rounded" value="<?php echo htmlspecialchars($username); ?>">
                        </div>
                        <div class="form-group">
                            <label for="email" class="block text-gray-700 mb-1">Email</label>
                            <input type="email" id="email" name="email" class="w-full px-3 py-2 border rounded" value="<?php echo htmlspecialchars($email); ?>">
                        </div>
                        <div class="col-span-2">
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark">Mettre à jour</button>
                        </div>
                    </form>
                </div>
                
                <div class="tab-content hidden" id="security-tab">
                    <form id="updatePasswordForm" class="grid grid-cols-1 gap-4">
                        <div class="form-group">
                            <label for="current_password" class="block text-gray-700 mb-1">Mot de passe actuel</label>
                            <input type="password" id="current_password" name="current_password" class="w-full px-3 py-2 border rounded">
                        </div>
                        <div class="form-group">
                            <label for="new_password" class="block text-gray-700 mb-1">Nouveau mot de passe</label>
                            <input type="password" id="new_password" name="new_password" class="w-full px-3 py-2 border rounded">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password" class="block text-gray-700 mb-1">Confirmer le mot de passe</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border rounded">
                        </div>
                        <div>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark">Changer le mot de passe</button>
                        </div>
                    </form>
                </div>
                
                <div class="tab-content hidden" id="privacy-tab">
                    <form id="updatePrivacyForm" class="grid grid-cols-1 gap-4">
                        <div class="form-group">
                            <label class="block text-gray-700 mb-2">Niveau de confidentialité du profil</label>
                            <div class="space-y-4">
                                <div class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-green-50 transition-colors">
                                    <input type="radio" id="privacy_public" name="privacy_level" value="public" class="mr-2 h-4 w-4 text-green-600 focus:ring-green-500" <?php echo (isset($privacyLevel) && $privacyLevel === 'public') ? 'checked' : ''; ?>>
                                    <label for="privacy_public" class="flex flex-col cursor-pointer">
                                        <span class="font-medium text-gray-800">Public</span>
                                        <span class="text-sm text-green-600">Tout le monde peut voir mon profil</span>
                                    </label>
                                </div>
                                <div class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-orange-50 transition-colors">
                                    <input type="radio" id="privacy_friends" name="privacy_level" value="friends" class="mr-2 h-4 w-4 text-orange-500 focus:ring-orange-500" <?php echo (isset($privacyLevel) && $privacyLevel === 'friends') ? 'checked' : ''; ?>>
                                    <label for="privacy_friends" class="flex flex-col cursor-pointer">
                                        <span class="font-medium text-gray-800">Amis</span>
                                        <span class="text-sm text-orange-600">Seulement mes amis peuvent voir mon profil</span>
                                    </label>
                                </div>
                                <div class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-red-50 transition-colors">
                                    <input type="radio" id="privacy_private" name="privacy_level" value="private" class="mr-2 h-4 w-4 text-red-600 focus:ring-red-500" <?php echo (isset($privacyLevel) && $privacyLevel === 'private') ? 'checked' : ''; ?>>
                                    <label for="privacy_private" class="flex flex-col cursor-pointer">
                                        <span class="font-medium text-gray-800">Privé</span>
                                        <span class="text-sm text-red-600">Personne ne peut voir mon profil sauf moi</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark">Mettre à jour</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Friends list -->
        <?php if (!empty($friends) || $isOwnProfile): ?>
        <div class="friends-section mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Amis (<?php echo count($friends); ?>)</h2>
            
            <?php if (empty($friends)): ?>
                <div class="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="text-gray-500 text-lg">
                        <?php if ($isOwnProfile): ?>
                            Vous n'avez pas encore d'amis.
                        <?php else: ?>
                            Cet utilisateur n'a pas encore d'amis.
                        <?php endif; ?>
                    </p>
                    <?php if ($isOwnProfile): ?>
                        <p class="text-gray-400 mt-1">Trouvez des joueurs et envoyez des demandes d'amitié pour agrandir votre cercle.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($friends as $friend): ?>
                        <div class="friend-card bg-gray-50 p-4 rounded shadow-sm flex items-center">
                            <div class="friend-avatar bg-primary text-white rounded-full w-10 h-10 flex items-center justify-center text-lg mr-3">
                                <span><?php echo strtoupper(substr($friend['username'], 0, 1)); ?></span>
                            </div>
                            <div class="friend-info flex-grow">
                                <a href="/profile.php?id=<?php echo $friend['id']; ?>" class="font-medium text-gray-800 hover:text-primary"><?php echo htmlspecialchars($friend['username']); ?></a>
                                <p class="text-xs text-gray-500">
                                    <?php 
                                    echo $friend['is_online'] ? 
                                        '<span class="text-green-500">En ligne</span>' : 
                                        'Dernière activité: ' . date('d/m/Y H:i', strtotime($friend['last_activity'])); 
                                    ?>
                                </p>
                            </div>
                            <?php if ($isOwnProfile): ?>
                                <button class="remove-friend text-red-500 hover:text-red-700" data-id="<?php echo $friend['id']; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Pending friend requests (only for own profile) -->
        <?php if ($isOwnProfile && !empty($pendingRequests)): ?>
        <div class="friend-requests mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Demandes d'amis en attente (<?php echo count($pendingRequests); ?>)</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($pendingRequests as $request): ?>
                    <div class="request-card bg-gray-50 p-4 rounded shadow-sm flex items-center">
                        <div class="request-avatar bg-primary text-white rounded-full w-10 h-10 flex items-center justify-center text-lg mr-3">
                            <span><?php echo strtoupper(substr($request['username'], 0, 1)); ?></span>
                        </div>
                        <div class="request-info flex-grow">
                            <a href="/profile.php?id=<?php echo $request['id']; ?>" class="font-medium text-gray-800 hover:text-primary"><?php echo htmlspecialchars($request['username']); ?></a>
                            <p class="text-xs text-gray-500">Demande envoyée le <?php echo date('d/m/Y', strtotime($request['request_date'])); ?></p>
                        </div>
                        <div class="request-actions flex">
                            <button class="accept-request text-green-500 hover:text-green-700 mr-2" data-id="<?php echo $request['id']; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <button class="reject-request text-red-500 hover:text-red-700" data-id="<?php echo $request['id']; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Statistiques du joueur -->
        <div class="mt-8">
            <h2 class="text-2xl font-bold mb-4">Statistiques de jeu</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white p-4 shadow rounded">
                    <h3 class="text-lg font-semibold text-purple-800 mb-2">Parties jouées</h3>
                    <p class="text-3xl font-bold"><?php echo $totalGames; ?></p>
                </div>
                <div class="bg-white p-4 shadow rounded">
                    <h3 class="text-lg font-semibold text-green-600 mb-2">Victoires</h3>
                    <p class="text-3xl font-bold"><?php echo $victories; ?></p>
                </div>
                <div class="bg-white p-4 shadow rounded">
                    <h3 class="text-lg font-semibold text-red-600 mb-2">Défaites</h3>
                    <p class="text-3xl font-bold"><?php echo $defeats; ?></p>
                </div>
                <div class="bg-white p-4 shadow rounded">
                    <h3 class="text-lg font-semibold text-yellow-600 mb-2">Matchs nuls</h3>
                    <p class="text-3xl font-bold"><?php echo $draws; ?></p>
                </div>
                <div class="bg-white p-4 shadow rounded">
                    <h3 class="text-lg font-semibold text-blue-600 mb-2">% de victoires</h3>
                    <p class="text-3xl font-bold">
                        <?php 
                        echo $totalGames > 0 ? round(($victories / $totalGames) * 100, 1) . '%' : '0%'; 
                        ?>
                    </p>
                </div>
            </div>
            
            <?php if ($isOwnProfile && $totalGames > 0): ?>
            <div class="mt-4">
                <a href="/game/history.php" class="text-purple-600 hover:text-purple-800">
                    Voir l'historique complet des parties →
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Section Amis - Toujours visible -->
        <?php if ($isOwnProfile || !empty($friends)): ?>
        <div class="mt-10 mb-8 border-t pt-6">
            <h2 class="text-2xl font-bold mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                Amis
                <span class="ml-2 text-base font-normal px-2 py-1 bg-indigo-100 text-indigo-800 rounded-full">
                    <?php echo isset($friends) ? count($friends) : 0; ?>
                </span>
            </h2>
            
            <?php if (!$isOwnProfile && $currentUserId): ?>
                <!-- Boutons d'action pour le profil d'un autre utilisateur -->
                <div class="mb-6">
                    <div id="friendActionButtons" class="inline-flex gap-2">
                        <button id="sendFriendRequest" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition-colors flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
                            </svg>
                            Ajouter en ami
                        </button>
                        
                        <button id="cancelFriendRequest" class="hidden px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition-colors flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                            Annuler la demande
                        </button>
                        
                        <div id="pendingRequestActions" class="hidden">
                            <button id="acceptFriendRequest" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors mr-2">
                                Accepter la demande
                            </button>
                            <button id="rejectFriendRequest" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors">
                                Refuser
                            </button>
                        </div>
                        
                        <button id="removeFriend" class="hidden px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M11 6a3 3 0 11-6 0 3 3 0 016 0zM14 17a6 6 0 00-12 0h12zM13 8a1 1 0 100 2h4a1 1 0 100-2h-4z" />
                            </svg>
                            Retirer des amis
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Liste d'amis -->
            <div class="friends-list-container">
                <?php if (empty($friends)): ?>
                    <div class="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p class="text-gray-500 text-lg">
                            <?php if ($isOwnProfile): ?>
                                Vous n'avez pas encore d'amis.
                            <?php else: ?>
                                Cet utilisateur n'a pas encore d'amis.
                            <?php endif; ?>
                        </p>
                        <?php if ($isOwnProfile): ?>
                            <p class="text-gray-400 mt-1">Trouvez des joueurs et envoyez des demandes d'amitié pour agrandir votre cercle.</p>
                            <div class="mt-4">
                                <a href="/search_players.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-md hover:bg-indigo-700 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    Rechercher des joueurs
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($friends as $friend): ?>
                            <div class="friend-card bg-white p-4 rounded shadow flex items-center border-l-4 border-indigo-500">
                                <div class="friend-avatar bg-indigo-600 text-white rounded-full w-12 h-12 flex items-center justify-center text-lg mr-3">
                                    <span><?php echo strtoupper(substr($friend['username'], 0, 1)); ?></span>
                                </div>
                                <div class="friend-info flex-grow">
                                    <a href="/profile.php?id=<?php echo $friend['id']; ?>" class="font-bold text-gray-800 hover:text-indigo-600 transition-colors"><?php echo htmlspecialchars($friend['username']); ?></a>
                                    <p class="text-sm">
                                        <?php if ($friend['is_online']): ?>
                                            <span class="inline-flex items-center">
                                                <span class="w-2 h-2 bg-green-500 rounded-full mr-1"></span>
                                                <span class="text-green-600">En ligne</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-500">Dernière activité: <?php echo date('H:i', strtotime($friend['last_activity'])); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php if ($isOwnProfile): ?>
                                    <button class="remove-friend text-red-500 hover:text-red-700" data-id="<?php echo $friend['id']; ?>" title="Retirer des amis">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M11 6a3 3 0 11-6 0 3 3 0 016 0zM14 17a6 6 0 00-12 0h12zM13 8a1 1 0 100 2h4a1 1 0 100-2h-4z" />
                                        </svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Demandes d'amis en attente (seulement pour son propre profil) -->
            <?php if ($isOwnProfile): ?>
            <div class="mt-8">
                <h3 class="text-xl font-semibold mb-4 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Demandes d'amis en attente
                    <span class="ml-2 text-sm px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">
                        3
                    </span>
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Demandes fictives pour la démo -->
                    <?php
                    $demoPendingRequests = [
                        ['id' => 4, 'username' => 'JoueurAvance42', 'request_date' => date('Y-m-d', strtotime('-1 day'))],
                        ['id' => 5, 'username' => 'CheckersKing', 'request_date' => date('Y-m-d', strtotime('-2 days'))],
                        ['id' => 6, 'username' => 'GrandMaitre', 'request_date' => date('Y-m-d', strtotime('-3 days'))]
                    ];
                    
                    foreach ($demoPendingRequests as $request):
                    ?>
                    <div class="request-card bg-yellow-50 p-4 rounded shadow flex items-center border-l-4 border-yellow-400">
                        <div class="request-avatar bg-yellow-500 text-white rounded-full w-12 h-12 flex items-center justify-center text-lg mr-3">
                            <span><?php echo strtoupper(substr($request['username'], 0, 1)); ?></span>
                        </div>
                        <div class="request-info flex-grow">
                            <a href="/profile.php?id=<?php echo $request['id']; ?>" class="font-bold text-gray-800 hover:text-yellow-600 transition-colors"><?php echo htmlspecialchars($request['username']); ?></a>
                            <p class="text-xs text-gray-500">Demande envoyée le <?php echo date('d/m/Y', strtotime($request['request_date'])); ?></p>
                        </div>
                        <div class="request-actions flex space-x-1">
                            <button class="accept-request p-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-full" data-id="<?php echo $request['id']; ?>" title="Accepter">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <button class="reject-request p-2 bg-red-100 text-red-700 hover:bg-red-200 rounded-full" data-id="<?php echo $request['id']; ?>" title="Refuser">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Suggestions d'amis (pour son propre profil) -->
            <?php if ($isOwnProfile): ?>
            <div class="mt-8">
                <h3 class="text-xl font-semibold mb-4 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Suggestions d'amis
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Suggestions fictives pour la démo -->
                    <?php
                    $demoSuggestions = [
                        ['id' => 7, 'username' => 'DameAuDames', 'mutual_friends' => 2],
                        ['id' => 8, 'username' => 'JoueurExpert', 'mutual_friends' => 1],
                        ['id' => 9, 'username' => 'PartyPlayer', 'mutual_friends' => 3]
                    ];
                    
                    foreach ($demoSuggestions as $suggestion):
                    ?>
                    <div class="suggestion-card bg-blue-50 p-4 rounded shadow flex items-center border-l-4 border-blue-400">
                        <div class="suggestion-avatar bg-blue-600 text-white rounded-full w-12 h-12 flex items-center justify-center text-lg mr-3">
                            <span><?php echo strtoupper(substr($suggestion['username'], 0, 1)); ?></span>
                        </div>
                        <div class="suggestion-info flex-grow">
                            <a href="/profile.php?id=<?php echo $suggestion['id']; ?>" class="font-bold text-gray-800 hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($suggestion['username']); ?></a>
                            <p class="text-xs text-gray-500"><?php echo $suggestion['mutual_friends']; ?> ami<?php echo $suggestion['mutual_friends'] > 1 ? 's' : ''; ?> en commun</p>
                        </div>
                        <button class="send-request-btn px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors" data-id="<?php echo $suggestion['id']; ?>">
                            Ajouter
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Notification toast -->
<div id="notification" class="fixed bottom-4 right-4 px-4 py-2 rounded shadow-lg hidden"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tab = button.dataset.tab;
            
            // Update active button
            tabButtons.forEach(btn => btn.classList.remove('active', 'text-primary', 'border-primary'));
            button.classList.add('active', 'text-primary', 'border-primary');
            
            // Show selected tab content
            tabContents.forEach(content => {
                content.classList.add('hidden');
                if (content.id === `${tab}-tab`) {
                    content.classList.remove('hidden');
                }
            });
        });
    });
    
    // Update profile information
    const updateProfileForm = document.getElementById('updateProfileForm');
    if (updateProfileForm) {
        updateProfileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(updateProfileForm);
            
            fetch('/api/profile/update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Profil mis à jour avec succès', 'success');
                } else {
                    showNotification(data.message || 'Une erreur est survenue', 'error');
                }
            })
            .catch(error => {
                showNotification('Une erreur est survenue', 'error');
                console.error('Error:', error);
            });
        });
    }
    
    // Update password
    const updatePasswordForm = document.getElementById('updatePasswordForm');
    if (updatePasswordForm) {
        updatePasswordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(updatePasswordForm);
            
            if (formData.get('new_password') !== formData.get('confirm_password')) {
                showNotification('Les mots de passe ne correspondent pas', 'error');
                return;
            }
            
            fetch('/api/profile/update_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Mot de passe mis à jour avec succès', 'success');
                    updatePasswordForm.reset();
                } else {
                    showNotification(data.message || 'Une erreur est survenue', 'error');
                }
            })
            .catch(error => {
                showNotification('Une erreur est survenue', 'error');
                console.error('Error:', error);
            });
        });
    }
    
    // Update privacy settings
    const updatePrivacyForm = document.getElementById('updatePrivacyForm');
    if (updatePrivacyForm) {
        updatePrivacyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(updatePrivacyForm);
            
            fetch('/api/profile/update_privacy.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Paramètres de confidentialité mis à jour', 'success');
                } else {
                    showNotification(data.message || 'Une erreur est survenue', 'error');
                }
            })
            .catch(error => {
                showNotification('Une erreur est survenue', 'error');
                console.error('Error:', error);
            });
        });
    }
    
    // Friend request functions
    const friendSendRequestButtons = document.querySelectorAll('#sendFriendRequest, .send-request-btn');
    friendSendRequestButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.id || this.closest('.suggestion-card')?.querySelector('a').getAttribute('href').split('=')[1];
            if (!userId) return;
            
            // Simuler une requête API (frontend seulement)
            setTimeout(() => {
                // Mise à jour de l'interface
                if (this.id === 'sendFriendRequest') {
                    document.getElementById('sendFriendRequest').classList.add('hidden');
                    document.getElementById('cancelFriendRequest').classList.remove('hidden');
                } else {
                    // Pour les suggestions
                    this.textContent = 'Envoyé';
                    this.disabled = true;
                    this.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                    this.classList.add('bg-gray-400', 'cursor-not-allowed');
                }
                showNotification('Demande d\'ami envoyée avec succès', 'success');
            }, 500);
        });
    });
    
    // Gérer l'annulation d'une demande d'ami
    const cancelFriendRequestBtn = document.getElementById('cancelFriendRequest');
    if (cancelFriendRequestBtn) {
        cancelFriendRequestBtn.addEventListener('click', function() {
            // Simuler une requête API (frontend seulement)
            setTimeout(() => {
                document.getElementById('sendFriendRequest').classList.remove('hidden');
                document.getElementById('cancelFriendRequest').classList.add('hidden');
                showNotification('Demande d\'ami annulée', 'success');
            }, 500);
        });
    }
    
    // Gérer l'acceptation des demandes d'ami
    const friendAcceptRequestBtns = document.querySelectorAll('.accept-request');
    friendAcceptRequestBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const requestCard = this.closest('.request-card');
            if (!requestCard) return;
            
            // Récupérer les informations de l'utilisateur
            const userId = this.dataset.id;
            const username = requestCard.querySelector('.request-info a').textContent.trim();
            const firstLetter = username.charAt(0).toUpperCase();
            
            // Simuler une requête API (frontend seulement)
            setTimeout(() => {
                requestCard.classList.add('bg-green-100', 'border-green-500');
                requestCard.classList.remove('bg-yellow-50', 'border-yellow-400');
                requestCard.querySelector('.request-actions').innerHTML = `
                    <span class="text-green-700 font-medium">Acceptée</span>
                `;
                showNotification('Demande d\'ami acceptée', 'success');
                
                // Mettre à jour le compteur d'amis
                const friendsCounter = document.querySelector('h2 > span');
                if (friendsCounter) {
                    const currentCount = parseInt(friendsCounter.textContent);
                    friendsCounter.textContent = currentCount + 1;
                }
                
                // Ajouter l'utilisateur à la liste d'amis
                addFriendToList(userId, username, true);
                
                // Après quelques secondes, supprimer la carte de la liste des demandes en attente
                setTimeout(() => {
                    requestCard.classList.add('opacity-0', 'scale-95');
                    requestCard.style.transition = 'all 0.3s ease';
                    
                    setTimeout(() => {
                        requestCard.remove();
                        
                        // Mettre à jour le compteur de demandes en attente
                        const pendingCounter = document.querySelector('h3 > span');
                        if (pendingCounter) {
                            const currentCount = parseInt(pendingCounter.textContent);
                            pendingCounter.textContent = Math.max(0, currentCount - 1);
                        }
                    }, 300);
                }, 2000);
            }, 500);
        });
    });
    
    // Gérer l'envoi d'une demande d'ami depuis les suggestions
    const suggestionAddBtns = document.querySelectorAll('.send-request-btn');
    suggestionAddBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const suggestionCard = this.closest('.suggestion-card');
            if (!suggestionCard) return;
            
            // Récupérer les informations de l'utilisateur
            const userId = this.dataset.id;
            const username = suggestionCard.querySelector('.suggestion-info a').textContent.trim();
            
            // Simuler une requête API (frontend seulement)
            setTimeout(() => {
                // Mettre à jour l'apparence du bouton
                this.textContent = 'Ajouté';
                this.disabled = true;
                this.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                this.classList.add('bg-green-600');
                
                // Afficher une notification
                showNotification('Ami ajouté avec succès', 'success');
                
                // Mettre à jour le compteur d'amis
                const friendsCounter = document.querySelector('h2 > span');
                if (friendsCounter) {
                    const currentCount = parseInt(friendsCounter.textContent);
                    friendsCounter.textContent = currentCount + 1;
                }
                
                // Ajouter l'utilisateur à la liste d'amis
                addFriendToList(userId, username, true);
                
                // Après quelques secondes, supprimer la carte des suggestions
                setTimeout(() => {
                    suggestionCard.classList.add('opacity-0', 'scale-95');
                    suggestionCard.style.transition = 'all 0.3s ease';
                    
                    setTimeout(() => {
                        suggestionCard.remove();
                    }, 300);
                }, 2000);
            }, 500);
        });
    });
    
    // Fonction pour ajouter un ami à la liste des amis
    function addFriendToList(userId, username, isOnline = false) {
        // Vérifier si la section vide existe et la supprimer
        const emptyFriendsContainer = document.querySelector('.friends-list-container .text-center.py-8');
        if (emptyFriendsContainer) {
            emptyFriendsContainer.remove();
            
            // Créer un container pour la liste
            const friendsGrid = document.createElement('div');
            friendsGrid.className = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4';
            document.querySelector('.friends-list-container').appendChild(friendsGrid);
        }
        
        // Trouver le conteneur de la liste d'amis
        const friendsContainer = document.querySelector('.friends-list-container .grid');
        if (!friendsContainer) return;
        
        // Créer la carte du nouvel ami
        const friendCard = document.createElement('div');
        friendCard.className = 'friend-card bg-white p-4 rounded shadow flex items-center border-l-4 border-indigo-500';
        friendCard.style.opacity = '0';
        friendCard.style.transform = 'scale(0.95)';
        friendCard.style.transition = 'all 0.3s ease';
        
        const firstLetter = username.charAt(0).toUpperCase();
        
        friendCard.innerHTML = `
            <div class="friend-avatar bg-indigo-600 text-white rounded-full w-12 h-12 flex items-center justify-center text-lg mr-3">
                <span>${firstLetter}</span>
            </div>
            <div class="friend-info flex-grow">
                <a href="/profile.php?id=${userId}" class="font-bold text-gray-800 hover:text-indigo-600 transition-colors">${username}</a>
                <p class="text-sm">
                    ${isOnline ? 
                    `<span class="inline-flex items-center">
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-1"></span>
                        <span class="text-green-600">En ligne</span>
                    </span>` : 
                    `<span class="text-gray-500">Dernière activité: ${new Date().getHours()}:${new Date().getMinutes().toString().padStart(2, '0')}</span>`}
                </p>
            </div>
            <button class="remove-friend text-red-500 hover:text-red-700" data-id="${userId}" title="Retirer des amis">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M11 6a3 3 0 11-6 0 3 3 0 016 0zM14 17a6 6 0 00-12 0h12zM13 8a1 1 0 100 2h4a1 1 0 100-2h-4z" />
                </svg>
            </button>
        `;
        
        // Ajouter la carte au conteneur
        friendsContainer.appendChild(friendCard);
        
        // Animation d'apparition
        setTimeout(() => {
            friendCard.style.opacity = '1';
            friendCard.style.transform = 'scale(1)';
            
            // Ajouter l'event listener pour le bouton de suppression
            const removeBtn = friendCard.querySelector('.remove-friend');
            removeBtn.addEventListener('click', function() {
                if (!confirm('Êtes-vous sûr de vouloir retirer cet ami ?')) return;
                
                // Simuler une requête API (frontend seulement)
                setTimeout(() => {
                    friendCard.classList.add('opacity-0', 'scale-95');
                    
                    setTimeout(() => {
                        friendCard.remove();
                        
                        // Mettre à jour le compteur d'amis
                        const friendsCounter = document.querySelector('h2 > span');
                        if (friendsCounter) {
                            const currentCount = parseInt(friendsCounter.textContent);
                            friendsCounter.textContent = Math.max(0, currentCount - 1);
                            
                            // Si plus d'amis, afficher le message vide
                            if (currentCount - 1 <= 0) {
                                createEmptyFriendsMessage();
                            }
                        }
                    }, 300);
                    
                    showNotification('Ami retiré avec succès', 'success');
                }, 500);
            });
        }, 100);
    }
    
    // Fonction pour créer le message "pas d'amis"
    function createEmptyFriendsMessage() {
        const friendsContainer = document.querySelector('.friends-list-container');
        if (!friendsContainer) return;
        
        // Supprimer la grille existante s'il y en a une
        const existingGrid = friendsContainer.querySelector('.grid');
        if (existingGrid) {
            existingGrid.remove();
        }
        
        // Créer le message vide
        const emptyMessage = document.createElement('div');
        emptyMessage.className = 'text-center py-8 bg-gray-50 rounded-lg border border-gray-200';
        emptyMessage.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <p class="text-gray-500 text-lg">Vous n'avez pas encore d'amis.</p>
            <p class="text-gray-400 mt-1">Trouvez des joueurs et envoyez des demandes d'amitié pour agrandir votre cercle.</p>
            <div class="mt-4">
                <a href="/search_players.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-md hover:bg-indigo-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    Rechercher des joueurs
                </a>
            </div>
        `;
        
        friendsContainer.appendChild(emptyMessage);
    }

    // Notification display function
    function showNotification(message, type) {
        const notification = document.getElementById('notification');
        notification.textContent = message;
        notification.classList.remove('hidden', 'bg-green-500', 'bg-red-500');
        
        if (type === 'success') {
            notification.classList.add('bg-green-500', 'text-white');
        } else {
            notification.classList.add('bg-red-500', 'text-white');
        }
        
        notification.classList.remove('hidden');
        
        setTimeout(() => {
            notification.classList.add('hidden');
        }, 3000);
    }

    // Gérer le rejet des demandes d'ami
    const friendRejectRequestBtns = document.querySelectorAll('.reject-request');
    friendRejectRequestBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const requestCard = this.closest('.request-card');
            if (!requestCard) return;
            
            // Simuler une requête API (frontend seulement)
            setTimeout(() => {
                requestCard.classList.add('opacity-0', 'scale-95');
                requestCard.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    requestCard.remove();
                    
                    // Mettre à jour le compteur de demandes en attente
                    const pendingCounter = document.querySelector('h3 > span');
                    if (pendingCounter) {
                        const currentCount = parseInt(pendingCounter.textContent);
                        pendingCounter.textContent = Math.max(0, currentCount - 1);
                    }
                }, 300);
                
                showNotification('Demande d\'ami refusée', 'success');
            }, 500);
        });
    });
    
    // Afficher le bon bouton d'action en fonction de l'état de la relation (pour la démo)
    document.addEventListener('DOMContentLoaded', function() {
        const profileId = new URLSearchParams(window.location.search).get('id');
        
        // Si on est sur un profil spécifique, simulons différents états
        if (profileId) {
            // Cas où nous sommes déjà amis avec l'ID 1
            if (profileId === '1') {
                document.getElementById('sendFriendRequest')?.classList.add('hidden');
                document.getElementById('removeFriend')?.classList.remove('hidden');
            } 
            // Cas où nous avons envoyé une demande à l'ID 2
            else if (profileId === '2') {
                document.getElementById('sendFriendRequest')?.classList.add('hidden');
                document.getElementById('cancelFriendRequest')?.classList.remove('hidden');
            }
            // Cas où nous avons reçu une demande de l'ID 3
            else if (profileId === '3') {
                document.getElementById('sendFriendRequest')?.classList.add('hidden');
                document.getElementById('pendingRequestActions')?.classList.remove('hidden');
            }
        }
        
        // Gestion des options de confidentialité avec mise en évidence visuelle
        const privacyOptions = document.querySelectorAll('input[name="privacy_level"]');
        privacyOptions.forEach(option => {
            // Initialiser l'état visuel au chargement
            if (option.checked) {
                const container = option.closest('div');
                highlightPrivacyOption(container, option.value);
            }
            
            // Ajouter un écouteur d'événements pour la sélection
            option.addEventListener('change', function() {
                // Réinitialiser tous les conteneurs
                privacyOptions.forEach(opt => {
                    const container = opt.closest('div');
                    container.classList.remove('bg-green-100', 'bg-orange-100', 'bg-red-100');
                    container.classList.remove('border-green-300', 'border-orange-300', 'border-red-300');
                });
                
                // Appliquer le style au conteneur sélectionné
                const container = this.closest('div');
                highlightPrivacyOption(container, this.value);
            });
        });
        
        // Fonction pour mettre en évidence une option de confidentialité
        function highlightPrivacyOption(container, value) {
            if (value === 'public') {
                container.classList.add('bg-green-100', 'border-green-300');
            } else if (value === 'friends') {
                container.classList.add('bg-orange-100', 'border-orange-300');
            } else if (value === 'private') {
                container.classList.add('bg-red-100', 'border-red-300');
            }
        }
    });
});
</script>

<?php
// Inclure le pied de page
include __DIR__ . '/../backend/includes/footer.php';
?> 