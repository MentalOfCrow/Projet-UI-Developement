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

<div class="container mt-4">
    <div class="row">
        <div class="col-12 mb-4">
            <h1>Notifications <?php if ($unreadCount > 0): ?><span class="badge bg-danger"><?php echo $unreadCount; ?></span><?php endif; ?></h1>
            
            <div class="d-flex justify-content-end mb-3">
                <a href="/game/history.php" class="btn btn-primary">
                    <i class="fas fa-history"></i> Voir l'historique complet des parties
                </a>
            </div>
        </div>
        
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">Toutes vos notifications</h2>
                    <?php if (!empty($notifications)): ?>
                    <div>
                        <button onclick="markAllAsRead()" class="btn btn-outline-primary btn-sm">
                            Tout marquer comme lu
                        </button>
                        <button onclick="deleteAllNotifications()" class="btn btn-outline-danger btn-sm ms-2">
                            Supprimer tout
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                    <div class="text-center my-5">
                        <i class="fas fa-bell fa-3x text-muted mb-3"></i>
                        <p class="lead">Vous n'avez pas de notifications</p>
                    </div>
                    <?php else: ?>
                        <div class="list-group">
                        <?php foreach ($notifications as $notification): ?>
                            <div id="notification-<?php echo htmlspecialchars($notification['id']); ?>" class="list-group-item list-group-item-action <?php echo isset($notification['read_status']) && !$notification['read_status'] ? 'list-group-item-light' : ''; ?>">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1">
                                            <?php if ($notification['type'] === 'friend_request'): ?>
                                                <i class="fas fa-user-plus text-primary me-2"></i>
                                            <?php elseif ($notification['type'] === 'friend_accepted'): ?>
                                                <i class="fas fa-handshake text-success me-2"></i>
                                            <?php elseif ($notification['type'] === 'game_invite'): ?>
                                                <i class="fas fa-gamepad text-warning me-2"></i>
                                            <?php elseif ($notification['type'] === 'game_completed'): ?>
                                                <?php if (isset($notification['data']) && isset($notification['data']['result'])): ?>
                                                    <?php if ($notification['data']['result'] === 'win'): ?>
                                                        <i class="fas fa-trophy text-success me-2"></i>
                                                    <?php elseif ($notification['data']['result'] === 'loss'): ?>
                                                        <i class="fas fa-times-circle text-danger me-2"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-handshake text-warning me-2"></i>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <i class="fas fa-chess-board text-info me-2"></i>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <i class="fas fa-bell text-info me-2"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($notification['message']); ?>
                                        </h5>
                                        <p class="mb-1 text-muted small">
                                            <?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?>
                                            
                                            <?php if ($notification['type'] === 'game_completed' && isset($notification['data']) && isset($notification['data']['game_id'])): ?>
                                                <a href="/game/replay.php?game_id=<?php echo htmlspecialchars($notification['data']['game_id']); ?>" class="ms-2 text-primary">
                                                    <i class="fas fa-eye"></i> Revoir cette partie
                                                </a>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="btn-group">
                                        <?php if (isset($notification['read_status']) && !$notification['read_status']): ?>
                                        <button onclick="markAsRead('<?php echo htmlspecialchars($notification['id']); ?>')" class="btn btn-sm btn-outline-secondary" title="Marquer comme lu">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php 
                                        // Check if notification has data and it's a friend request
                                        if ($notification['type'] === 'friend_request' && $notification['data'] && isset($notification['data']['request_id'])): ?>
                                            <button onclick="respondToFriendRequest('<?php echo htmlspecialchars($notification['data']['request_id']); ?>', 'accept')" class="btn btn-sm btn-outline-success" title="Accepter">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button onclick="respondToFriendRequest('<?php echo htmlspecialchars($notification['data']['request_id']); ?>', 'reject')" class="btn btn-sm btn-outline-danger" title="Refuser">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button onclick="deleteNotification('<?php echo htmlspecialchars($notification['id']); ?>')" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
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
            document.getElementById(`notification-${notificationId}`).classList.remove('list-group-item-light');
            
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
            document.querySelectorAll('.list-group-item-light').forEach(el => {
                el.classList.remove('list-group-item-light');
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