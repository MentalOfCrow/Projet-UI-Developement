<?php
// Start output buffering to prevent any previous output
ob_start();

// Set display_errors for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include configuration files
require_once __DIR__ . '/../backend/includes/config.php';
require_once __DIR__ . '/../backend/includes/session.php';
require_once __DIR__ . '/../backend/controllers/ProfileController.php';
require_once __DIR__ . '/../backend/controllers/NotificationController.php';

// Redirect to login if not logged in
if (!Session::isLoggedIn()) {
    header('Location: /auth/login.php');
    exit;
}

// Get current user and update activity
$userId = Session::getUserId();
$profileController = new ProfileController();
$profileController->updateActivity();

// Get notifications controller
$notificationController = new NotificationController();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Notifications per page
$offset = ($page - 1) * $limit;

// Get user notifications with pagination
try {
    $notifications = $notificationController->getNotifications($userId, $limit, $offset);
    $totalNotifications = count($notificationController->getNotifications($userId, 1000, 0));
    $totalPages = ceil($totalNotifications / $limit);
    $unreadCount = $notificationController->countUnread($userId);
} catch (Exception $e) {
    error_log('Error fetching notifications: ' . $e->getMessage());
    $notifications = [];
    $totalNotifications = 0;
    $totalPages = 0;
    $unreadCount = 0;
}

// Set the title of the page
$pageTitle = "Notifications";

// Include the header
include_once __DIR__ . '/../backend/includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-purple-800">Notifications
            <?php if ($unreadCount > 0): ?><span class="ml-2 inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-600 text-white text-xs"><?php echo $unreadCount; ?></span><?php endif; ?></h1>
        <a href="/game/history.php" class="inline-flex items-center text-sm font-medium bg-purple-600 hover:bg-purple-700 text-white px-3 py-1.5 rounded">
            <i class="fas fa-history mr-2"></i> Historique des parties
        </a>
    </div>

    <div class="bg-white shadow rounded-lg">
        <div class="flex items-center justify-between px-4 py-3 border-b">
            <h2 class="text-lg font-semibold">Toutes vos notifications</h2>
            <?php if (!empty($notifications)): ?>
            <div class="space-x-2">
                <button onclick="markAllAsRead()" class="px-3 py-1.5 text-sm bg-green-100 hover:bg-green-200 text-green-700 rounded">Tout marquer comme lu</button>
                <button onclick="deleteAllNotifications()" class="px-3 py-1.5 text-sm bg-red-100 hover:bg-red-200 text-red-700 rounded">Supprimer tout</button>
            </div>
            <?php endif; ?>
        </div>
        <div class="p-0 divide-y" id="notif-list">
            <?php if (empty($notifications)): ?>
            <div class="text-center my-5">
                <i class="fas fa-bell fa-3x text-muted mb-3"></i>
                <p class="lead">Vous n'avez pas de notifications</p>
            </div>
            <?php else: ?>
                <div class="list-group">
                <?php foreach ($notifications as $notification): ?>
                    <div id="notification-<?php echo htmlspecialchars($notification['id']); ?>" class="px-4 py-3 flex justify-between items-start hover:bg-gray-50 <?php echo isset($notification['read_status']) && !$notification['read_status'] ? 'bg-purple-50' : ''; ?>">
                        <div class="flex-1">
                            <div class="font-medium">
                                <?php if ($notification['type'] === 'friend_request'): ?>
                                    <i class="fas fa-user-plus text-purple-600 mr-2"></i>
                                <?php elseif ($notification['type'] === 'friend_accepted'): ?>
                                    <i class="fas fa-handshake text-green-600 mr-2"></i>
                                <?php elseif ($notification['type'] === 'game_invite'): ?>
                                    <i class="fas fa-gamepad text-yellow-500 mr-2"></i>
                                <?php elseif ($notification['type'] === 'game_completed'): ?>
                                    <?php if (isset($notification['data']) && isset($notification['data']['result'])): ?>
                                        <?php if ($notification['data']['result'] === 'win'): ?>
                                            <i class="fas fa-trophy text-green-500 mr-2"></i>
                                        <?php elseif ($notification['data']['result'] === 'loss'): ?>
                                            <i class="fas fa-times-circle text-red-500 mr-2"></i>
                                        <?php else: ?>
                                            <i class="fas fa-handshake text-yellow-500 mr-2"></i>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <i class="fas fa-chess-board text-indigo-500 mr-2"></i>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <i class="fas fa-bell text-indigo-500 mr-2"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?>
                                
                                <?php if ($notification['type'] === 'game_completed' && isset($notification['data']) && isset($notification['data']['game_id'])): ?>
                                    <a href="/game/replay.php?game_id=<?php echo htmlspecialchars($notification['data']['game_id']); ?>" class="ml-2 text-purple-600 hover:underline">
                                        <i class="fas fa-eye"></i> Revoir cette partie
                                    </a>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="flex space-x-1 ml-3">
                            <?php if (isset($notification['read_status']) && !$notification['read_status']): ?>
                            <button onclick="markAsRead('<?php echo htmlspecialchars($notification['id']); ?>')" class="w-8 h-8 flex items-center justify-center bg-gray-100 hover:bg-gray-200 rounded" title="Marquer comme lu">
                                <i class="fas fa-check text-gray-600 text-sm"></i>
                            </button>
                            <?php endif; ?>
                            
                            <?php 
                            // Check if notification has data and it's a friend request
                            if ($notification['type'] === 'friend_request' && $notification['data'] && isset($notification['data']['request_id'])): ?>
                                <button onclick="respondToFriendRequest('<?php echo htmlspecialchars($notification['data']['request_id']); ?>', 'accept')" class="w-8 h-8 flex items-center justify-center bg-green-100 hover:bg-green-200 rounded" title="Accepter">
                                    <i class="fas fa-check text-green-600 text-sm"></i>
                                </button>
                                <button onclick="respondToFriendRequest('<?php echo htmlspecialchars($notification['data']['request_id']); ?>', 'reject')" class="w-8 h-8 flex items-center justify-center bg-red-100 hover:bg-red-200 rounded" title="Refuser">
                                    <i class="fas fa-times text-red-600 text-sm"></i>
                                </button>
                            <?php endif; ?>
                            
                            <button onclick="deleteNotification('<?php echo htmlspecialchars($notification['id']); ?>')" class="w-8 h-8 flex items-center justify-center bg-red-100 hover:bg-red-200 rounded" title="Supprimer">
                                <i class="fas fa-trash text-red-600 text-sm"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Pagination des notifications">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>" tabindex="-1" aria-disabled="<?php echo ($page <= 1) ? 'true' : 'false'; ?>">Précédent</a>
                    </li>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-disabled="<?php echo ($page >= $totalPages) ? 'true' : 'false'; ?>">Suivant</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function markAsRead(notificationId) {
    fetch('/api/notifications/mark_as_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ notification_id: notificationId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the background color
            document.getElementById(`notification-${notificationId}`).classList.remove('bg-purple-50');
            
            // Hide the mark as read button
            const markAsReadBtn = document.getElementById(`notification-${notificationId}`).querySelector('button[title="Marquer comme lu"]');
            if (markAsReadBtn) {
                markAsReadBtn.remove();
            }
            
            // Update unread badge if present
            updateUnreadCount();
        } else {
            alert(data.message || 'Erreur lors du marquage de la notification');
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
        alert('Une erreur est survenue');
    });
}

function markAllAsRead() {
    fetch('/api/notifications/mark_as_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ mark_all: true })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove all background colors
            document.querySelectorAll('.bg-purple-50').forEach(el => {
                el.classList.remove('bg-purple-50');
            });
            
            // Hide all mark as read buttons
            document.querySelectorAll('button[title="Marquer comme lu"]').forEach(el => {
                el.remove();
            });
            
            // Update unread badge
            updateUnreadCount(0);
            
            // Display success message
            alert(data.message);
        } else {
            alert(data.message || 'Erreur lors du marquage des notifications');
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
        alert('Une erreur est survenue');
    });
}

function deleteNotification(notificationId) {
    if (!confirm('Voulez-vous vraiment supprimer cette notification ?')) {
        return;
    }
    
    fetch('/api/notifications/delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ notification_id: notificationId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the notification from the DOM
            document.getElementById(`notification-${notificationId}`).remove();
            
            // Check if there are no more notifications
            if (document.querySelectorAll('.list-group-item').length === 0) {
                location.reload(); // Reload to show empty state
            }
        } else {
            alert(data.message || 'Erreur lors de la suppression de la notification');
        }
    })
    .catch(error => {
        console.error('Error deleting notification:', error);
        alert('Une erreur est survenue');
    });
}

function deleteAllNotifications() {
    if (!confirm('Voulez-vous vraiment supprimer toutes vos notifications ?')) {
        return;
    }
    
    fetch('/api/notifications/delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ delete_all: true })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload the page to show empty state
            location.reload();
        } else {
            alert(data.message || 'Erreur lors de la suppression des notifications');
        }
    })
    .catch(error => {
        console.error('Error deleting all notifications:', error);
        alert('Une erreur est survenue');
    });
}

function respondToFriendRequest(requestId, response) {
    fetch('/api/friend/respond_request.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ request_id: requestId, response: response })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload the page to reflect changes
            location.reload();
        } else {
            alert(data.message || 'Une erreur est survenue lors du traitement de la demande d\'ami');
        }
    })
    .catch(error => {
        console.error('Error responding to friend request:', error);
        alert('Une erreur est survenue lors du traitement de la demande d\'ami');
    });
}

function updateUnreadCount(count = null) {
    const badge = document.querySelector('h1 .badge');
    
    if (count === null) {
        // Get updated count from API
        fetch('/api/notifications/get_notifications.php?limit=1')
        .then(response => response.json())
        .then(data => {
            if (data.success && badge) {
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count;
                    badge.style.display = '';
                } else {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(error => console.error('Error updating unread count:', error));
    } else if (badge) {
        // Use provided count
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }
}
</script>

<?php
// Include the footer
include_once __DIR__ . '/../backend/includes/footer.php';
?> 