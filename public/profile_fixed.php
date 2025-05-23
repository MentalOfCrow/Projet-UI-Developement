﻿<?php
// Start output buffering to prevent any output before headers are sent
ob_start();

// Enable error display in development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DÃ©marrer la session si elle n'est pas dÃ©jÃ  dÃ©marrÃ©e
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
        $error = "Ce profil est privÃ©.";
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
    
    // RÃ©cupÃ©ration des statistiques de jeu depuis les fichiers JSON (plus de dÃ©pendance MySQL)
    $jsonDb = JsonDatabase::getInstance();
    // Correction ultime : forcer la reconstruction de l'index ET la resynchronisation complÃ¨te
    $gamesDir = dirname(__DIR__) . '/data/games/';
    $games = glob($gamesDir . 'game_*.json');
    foreach ($games as $gameFile) {
        $gameData = json_decode(file_get_contents($gameFile), true);
        if (!$gameData) continue;
        // Marquer comme terminÃ©e si un rÃ©sultat est prÃ©sent
        if (isset($gameData['result']) && (!isset($gameData['status']) || $gameData['status'] !== 'finished')) {
            $gameData['status'] = 'finished';
            $jsonDb->saveGame($gameData);
        }
        // On ne prend que les parties terminÃ©es
        if ((isset($gameData['status']) && $gameData['status'] === 'finished')) {
            if ((isset($gameData['player1_id']) && $gameData['player1_id'] == $profileUserId) ||
                (isset($gameData['player2_id']) && $gameData['player2_id'] == $profileUserId)) {
                $jsonDb->updateGamesIndex($profileUserId, $gameData['id']);
            }
        }
    }
    // Synchronisation forcÃ©e
    $jsonDb->synchronizeUserStats($profileUserId);
    $stats = $jsonDb->getUserStats($profileUserId);

    // AprÃ¨s rÃ©cupÃ©ration des stats JSON, recalculer via les parties rÃ©elles
    $allGames = $jsonDb->getUserGames($profileUserId);
    $totalGames = count($allGames);
    $victories = 0;
    $defeats   = 0;
    $draws     = 0;
    foreach ($allGames as $g) {
        $winnerId = $g['winner_id'] ?? null;
        if (array_key_exists('winner_id', $g)) {
            // Partie terminÃ©e: dÃ©terminer le rÃ©sultat
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
    // Gestion des amis (facultatif â€“ dÃ©sactivÃ© si MySQL n'est pas dispo)
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

    // Pending requests uniquement si l'utilisateur possÃ¨de FriendController
    $pendingRequests = [];
    if ($isOwnProfile && $friendController) {
        $pendingRequestsData = $friendController->getPendingFriendRequests();
        $pendingRequests     = $pendingRequestsData['success'] ? ($pendingRequestsData['received'] ?? []) : [];
    }
    
    // RÃ©cupÃ©rer le rang du joueur dans le leaderboard (mode JSON uniquement)
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

// DÃ©finir le titre de la page
$pageTitle = isset($username) ? "Profil de " . $username : "Profil - " . APP_NAME;

// Inclure l'en-tÃªte
include __DIR__ . '/../backend/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php else: ?>
    
    <!-- Hero Banner -->
    <div class="relative mb-8 bg-gradient-to-r from-purple-900 to-indigo-800 rounded-xl shadow-lg overflow-hidden">
        <div class="absolute inset-0 bg-pattern opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=\'20\' height=\'20\' viewBox=\'0 0 20 20\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\' fill-rule=\'evenodd\'%3E%3Ccircle cx=\'3\' cy=\'3\' r=\'3\'/%3E%3Ccircle cx=\'13\' cy=\'13\' r=\'3\'/%3E%3C/g%3E%3C/svg%3E');"></div>
        <div class="container mx-auto px-6 py-12 relative z-10">
            <div class="flex flex-col md:flex-row items-center">
                <div class="profile-avatar bg-white text-purple-700 rounded-full w-28 h-28 flex items-center justify-center text-4xl font-bold shadow-lg border-4 border-white">
                    <span><?php echo isset($username) ? strtoupper(substr($username, 0, 1)) : '?'; ?></span>
                </div>
                <div class="profile-info md:ml-6 mt-4 md:mt-0 text-center md:text-left">
                    <h1 class="text-3xl font-bold text-white"><?php echo isset($username) ? htmlspecialchars($username) : 'Utilisateur inconnu'; ?></h1>
                    <?php if ($isOwnProfile && isset($email)): ?>
                        <p class="text-purple-200"><?php echo htmlspecialchars($email); ?></p>
                    <?php endif; ?>
                    <p class="text-purple-200 mt-1 flex items-center justify-center md:justify-start">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        Membre depuis: <?php echo isset($memberSince) ? $memberSince : 'Date inconnue'; ?>
                    </p>
                    
                    <?php if ($rank > 0): ?>
                    <div class="mt-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-400 text-gray-900">
                            <i class="fas fa-crown mr-1"></i> Rang #<?php echo $rank; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="md:ml-auto mt-4 md:mt-0">
                    <?php if ($isOwnProfile): ?>
                        <a href="#settings" class="inline-flex items-center px-4 py-2 bg-white text-purple-700 rounded-lg font-medium shadow-md hover:bg-purple-100 transition">
                            <i class="fas fa-cog mr-2"></i> ParamÃ¨tres
                        </a>
                    <?php elseif ($currentUserId): ?>
                        <?php 
                        // SystÃ¨me d'amis uniquement si disponible
                        $areFriends       = false;
                        $hasSentRequest   = false;
                        $hasReceivedRequest = false;

                        if ($friendController) {
                            // VÃ©rifier si ils sont amis
                            $areFriends = $friendController->areFriends($currentUserId, $profileUserId);

                            // VÃ©rifier les demandes d'amis en attente
                            $pendingRequestsInfo = $friendController->getPendingFriendRequests();
                            if ($pendingRequestsInfo['success'] && !empty($pendingRequestsInfo['requests'])) {
                                foreach ($pendingRequestsInfo['requests'] as $request) {
                                    if ($request['sender_id'] == $profileUserId) {
                                        $hasReceivedRequest = true;
                                        break;
                                    }
                                }
                            }

                            // VÃ©rifier si l'utilisateur courant a envoyÃ© une demande
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
                            <button id="removeFriend" class="inline-flex items-center px-4 py-2 bg-red-500 text-white rounded-lg font-medium shadow-md hover:bg-red-600 transition" data-id="<?php echo $profileUserId; ?>">
                                <i class="fas fa-user-times mr-2"></i> Retirer des amis
                            </button>
                        <?php elseif ($hasSentRequest): ?>
                            <button class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 rounded-lg font-medium shadow-md" disabled>
                                <i class="fas fa-clock mr-2"></i> Demande envoyÃ©e
                            </button>
                        <?php elseif ($hasReceivedRequest): ?>
                            <div class="flex space-x-2">
                                <button id="acceptRequest" class="inline-flex items-center px-4 py-2 bg-green-500 text-white rounded-lg font-medium shadow-md hover:bg-green-600 transition" data-id="<?php echo $profileUserId; ?>">
                                    <i class="fas fa-check mr-2"></i> Accepter
                                </button>
                                <button id="rejectRequest" class="inline-flex items-center px-4 py-2 bg-red-500 text-white rounded-lg font-medium shadow-md hover:bg-red-600 transition" data-id="<?php echo $profileUserId; ?>">
                                    <i class="fas fa-times mr-2"></i> Refuser
                                </button>
                            </div>
                        <?php else: ?>
                            <button id="sendRequest" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium shadow-md hover:bg-purple-700 transition" data-id="<?php echo $profileUserId; ?>">
                                <i class="fas fa-user-plus mr-2"></i> Ajouter en ami
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Dashboard -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stats-card bg-white rounded-xl shadow-lg p-6 transform transition hover:scale-105">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 font-medium text-sm mb-1">PARTIES JOUÃ‰ES</p>
                    <h3 class="text-3xl font-bold text-gray-800"><?php echo $totalGames; ?></h3>
                </div>
                <div class="rounded-full bg-blue-100 p-3">
                    <i class="fas fa-gamepad text-blue-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center">
                <span class="text-gray-500 text-sm">PROGRESSION</span>
                <?php $ratio = min(100, $totalGames / 10); ?>
                <div class="ml-2 flex-grow bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $ratio; ?>%"></div>
                </div>
            </div>
        </div>

        <div class="stats-card bg-white rounded-xl shadow-lg p-6 transform transition hover:scale-105">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 font-medium text-sm mb-1">VICTOIRES</p>
                    <h3 class="text-3xl font-bold text-green-600"><?php echo $victories; ?></h3>
                </div>
                <div class="rounded-full bg-green-100 p-3">
                    <i class="fas fa-trophy text-green-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center">
                    <span class="text-green-600 font-medium">
                        <i class="fas fa-arrow-up mr-1"></i><?php echo $winRate; ?>%
                    </span>
                    <span class="ml-2 text-gray-500 text-sm">Taux de victoire</span>
                </div>
            </div>
        </div>

        <div class="stats-card bg-white rounded-xl shadow-lg p-6 transform transition hover:scale-105">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 font-medium text-sm mb-1">DÃ‰FAITES</p>
                    <h3 class="text-3xl font-bold text-red-600"><?php echo $defeats; ?></h3>
                </div>
                <div class="rounded-full bg-red-100 p-3">
                    <i class="fas fa-times-circle text-red-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center">
                    <span class="text-red-600 font-medium">
                        <?php echo $totalGames > 0 ? round(($defeats / $totalGames) * 100, 1) : 0; ?>%
                    </span>
                    <span class="ml-2 text-gray-500 text-sm">Ratio de dÃ©faites</span>
                </div>
            </div>
        </div>

        <div class="stats-card bg-white rounded-xl shadow-lg p-6 transform transition hover:scale-105">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 font-medium text-sm mb-1">CLASSEMENT</p>
                    <h3 class="text-3xl font-bold text-purple-600">
                        <?php echo $rank > 0 ? '#' . $rank : 'N/A'; ?>
                    </h3>
                </div>
                <div class="rounded-full bg-purple-100 p-3">
                    <i class="fas fa-medal text-purple-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <a href="/leaderboard.php" class="text-sm text-purple-600 hover:text-purple-800 flex items-center">
                    Voir le classement complet
                    <i class="fas fa-chevron-right ml-1"></i>
                </a>
            </div>
        </div>
    </div>
    
    <?php if ($isOwnProfile && ($totalGames != ($stats['games_played'] ?? 0) || $totalGames == 0)): ?>
    <div class="mt-4 text-center">
        <a href="/sync_stats.php" class="text-blue-600 hover:text-blue-800 underline">
            <i class="fas fa-sync-alt mr-1"></i> Synchroniser mes statistiques
        </a>
        <p class="text-xs text-gray-500 mt-1">Si vos statistiques ne semblent pas Ã  jour, cliquez ici pour les synchroniser</p>
    </div>
    <?php endif; ?>
    
    <!-- Affichage du rang -->
    <?php if ($rank > 0): ?>
    <div class="mt-4 text-center">
        <span class="inline-block bg-purple-100 text-purple-800 px-4 py-2 rounded-full text-lg font-semibold">
            Classement: <?php echo $rank; ?><?php echo $rank == 1 ? 'er' : 'Ã¨me'; ?>
        </span>
        <a href="/leaderboard.php" class="text-purple-600 hover:text-purple-800 ml-3">
            Voir le classement complet â†’
        </a>
    </div>
    <?php endif; ?>
    
    <?php if ($isOwnProfile): ?>
    <!-- Profile Settings - Only visible to profile owner -->
    <div class="profile-settings mb-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">ParamÃ¨tres du profil</h2>
        
        <div class="tabs">
            <div class="tab-buttons flex border-b mb-4">
                <button class="tab-button py-2 px-4 font-medium text-gray-600 border-b-2 border-transparent hover:text-primary hover:border-primary active" data-tab="general">GÃ©nÃ©ral</button>
                <button class="tab-button py-2 px-4 font-medium text-gray-600 border-b-2 border-transparent hover:text-primary hover:border-primary" data-tab="security">SÃ©curitÃ©</button>
                <button class="tab-button py-2 px-4 font-medium text-gray-600 border-b-2 border-transparent hover:text-primary hover:border-primary" data-tab="privacy">ConfidentialitÃ©</button>
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
                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark">Mettre Ã  jour</button>
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
                        <label class="block text-gray-700 mb-2">Niveau de confidentialitÃ© du profil</label>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <input type="radio" id="privacy_public" name="privacy_level" value="public" class="mr-2" <?php echo (isset($privacyLevel) && $privacyLevel === 'public') ? 'checked' : ''; ?>>
                                <label for="privacy_public">Public - Tout le monde peut voir mon profil</label>
                            </div>
                            <div class="flex items-center">
                                <input type="radio" id="privacy_friends" name="privacy_level" value="friends" class="mr-2" <?php echo (isset($privacyLevel) && $privacyLevel === 'friends') ? 'checked' : ''; ?>>
                                <label for="privacy_friends">Amis - Seulement mes amis peuvent voir mon profil</label>
                            </div>
                            <div class="flex items-center">
                                <input type="radio" id="privacy_private" name="privacy_level" value="private" class="mr-2" <?php echo (isset($privacyLevel) && $privacyLevel === 'private') ? 'checked' : ''; ?>>
                                <label for="privacy_private">PrivÃ© - Personne ne peut voir mon profil sauf moi</label>
                            </div>
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark">Mettre Ã  jour</button>
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
            <p class="text-gray-500 italic">Aucun ami Ã  afficher.</p>
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
                                    'DerniÃ¨re activitÃ©: ' . date('d/m/Y H:i', strtotime($friend['last_activity'])); 
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
                        <p class="text-xs text-gray-500">Demande envoyÃ©e le <?php echo date('d/m/Y', strtotime($request['request_date'])); ?></p>
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
                <h3 class="text-lg font-semibold text-purple-800 mb-2">Parties jouÃ©es</h3>
                <p class="text-3xl font-bold"><?php echo $totalGames; ?></p>
            </div>
            <div class="bg-white p-4 shadow rounded">
                <h3 class="text-lg font-semibold text-green-600 mb-2">Victoires</h3>
                <p class="text-3xl font-bold"><?php echo $victories; ?></p>
            </div>
            <div class="bg-white p-4 shadow rounded">
                <h3 class="text-lg font-semibold text-red-600 mb-2">DÃ©faites</h3>
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
                Voir l'historique complet des parties â†’
            </a>
        </div>
        <?php endif; ?>
    </div>
    
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
                    showNotification('Profil mis Ã  jour avec succÃ¨s', 'success');
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
                    showNotification('Mot de passe mis Ã  jour avec succÃ¨s', 'success');
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
                    showNotification('ParamÃ¨tres de confidentialitÃ© mis Ã  jour', 'success');
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
    const sendRequestBtn = document.getElementById('sendRequest');
    if (sendRequestBtn) {
        sendRequestBtn.addEventListener('click', function() {
            const userId = this.dataset.id;
            
            fetch('/api/friend/send_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `user_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.disabled = true;
                    this.textContent = 'Demande envoyÃ©e';
                    this.classList.remove('bg-purple-600');
                    this.classList.add('bg-gray-300', 'text-gray-700');
                    showNotification('Demande d\'ami envoyÃ©e', 'success');
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
    
    // Accept friend request
    const acceptRequestBtns = document.querySelectorAll('#acceptRequest, .accept-request');
    acceptRequestBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.id;
            
            fetch('/api/friend/respond_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `user_id=${userId}&action=accept`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Demande d\'ami acceptÃ©e', 'success');
                    // Reload page to update UI
                    location.reload();
                } else {
                    showNotification(data.message || 'Une erreur est survenue', 'error');
                }
            })
            .catch(error => {
                showNotification('Une erreur est survenue', 'error');
                console.error('Error:', error);
            });
        });
    });
    
    // Reject friend request
    const rejectRequestBtns = document.querySelectorAll('#rejectRequest, .reject-request');
    rejectRequestBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.id;
            
            fetch('/api/friend/respond_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `user_id=${userId}&action=reject`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Demande d\'ami refusÃ©e', 'success');
                    // Reload page to update UI
                    location.reload();
                } else {
                    showNotification(data.message || 'Une erreur est survenue', 'error');
                }
            })
            .catch(error => {
                showNotification('Une erreur est survenue', 'error');
                console.error('Error:', error);
            });
        });
    });
    
    // Remove friend
    const removeFriendBtns = document.querySelectorAll('#removeFriend, .remove-friend');
    removeFriendBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.id;
            
            if (!confirm('ÃŠtes-vous sÃ»r de vouloir retirer cet ami ?')) {
                return;
            }
            
            fetch('/api/friend/remove_friend.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `user_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Ami retirÃ© avec succÃ¨s', 'success');
                    // Reload page to update UI
                    location.reload();
                } else {
                    showNotification(data.message || 'Une erreur est survenue', 'error');
                }
            })
            .catch(error => {
                showNotification('Une erreur est survenue', 'error');
                console.error('Error:', error);
            });
        });
    });
    
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
});
</script>

<?php
// Inclure le pied de page
include __DIR__ . '/../backend/includes/footer.php';
?>?>
