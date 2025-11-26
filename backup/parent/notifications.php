<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is parent
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'parent') {
    header('Location: ../index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_read':
                $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$_POST['notification_id'], $_SESSION['user_id']]);
                header('Location: notifications.php?success=notification_read');
                exit();
                break;
                
            case 'mark_all_read':
                $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                header('Location: notifications.php?success=all_notifications_read');
                exit();
                break;
                
            case 'delete_notification':
                $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->execute([$_POST['notification_id'], $_SESSION['user_id']]);
                header('Location: notifications.php?success=notification_deleted');
                exit();
                break;
        }
    }
}

// Get notifications data
try {
    $conn = getDBConnection();
    
    // Get all notifications for this parent
    $stmt = $conn->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread notifications count
    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
    
    // Get unread messages count
    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_messages = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
    
    // Get recent announcements
    $stmt = $conn->prepare("
        SELECT * FROM announcements 
        WHERE status = 'published' 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $stmt->execute();
    $recent_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $notifications = [];
    $unread_notifications = 0;
    $unread_messages = 0;
    $recent_announcements = [];
}

// Group notifications by type
$grouped_notifications = [];
foreach ($notifications as $notification) {
    $grouped_notifications[$notification['type']][] = $notification;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/parent.css" rel="stylesheet">
</head>
<body>
    <div class="parent-container">
        <?php include 'parent_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header d-flex justify-content-between align-items-center">
                <div>
                    <h1>Notifications</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Notifications</li>
                        </ol>
                    </nav>
                </div>
                <?php if ($unread_count > 0): ?>
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Notification Stats -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="notification-stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?php echo count($notifications); ?></h4>
                            <p>Total Notifications</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="notification-stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?php echo $unread_count; ?></h4>
                            <p>Unread</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="notification-stat-card">
                        <div class="stat-icon bg-success">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?php echo count($recent_announcements); ?></h4>
                            <p>Recent Announcements</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="notification-stat-card">
                        <div class="stat-icon bg-info">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?php echo $unread_messages; ?></h4>
                            <p>Unread Messages</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-lightning-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="messages.php" class="btn btn-outline-primary btn-sm w-100">
                                <i class="fas fa-envelope"></i> View Messages
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="dashboard.php" class="btn btn-outline-success btn-sm w-100">
                                <i class="fas fa-bullhorn"></i> View Announcements
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="profile.php" class="btn btn-outline-info btn-sm w-100">
                                <i class="fas fa-user-cog"></i> Update Profile
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <a href="settings.php" class="btn btn-outline-warning btn-sm w-100">
                                <i class="fas fa-cog"></i> Notification Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Announcements -->
            <?php if (!empty($recent_announcements)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-bullhorn me-2"></i>Recent Announcements</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($recent_announcements as $announcement): ?>
                        <div class="announcement-item">
                            <div class="announcement-icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="announcement-content">
                                <h6><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                <p><?php echo substr(htmlspecialchars($announcement['content']), 0, 150) . '...'; ?></p>
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Notifications List -->
            <div class="card">
                <div class="card-header">
                    <h5>All Notifications</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-bell-slash fa-3x mb-3 opacity-25"></i>
                            <h5>No notifications yet</h5>
                            <p>You'll receive notifications about important updates, messages, and announcements here.</p>
                        </div>
                    <?php else: ?>
                        <div class="notifications-list">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                    <div class="notification-icon <?php echo getNotificationIconClass($notification['type']); ?>">
                                        <i class="fas <?php echo getNotificationIcon($notification['type']); ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-header">
                                            <h6><?php echo htmlspecialchars($notification['title']); ?></h6>
                                            <div class="notification-time">
                                                <?php echo timeAgo($notification['created_at']); ?>
                                            </div>
                                        </div>
                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <div class="notification-meta">
                                            <span class="badge <?php echo getNotificationBadgeClass($notification['type']); ?>">
                                                <?php echo ucfirst($notification['type']); ?>
                                            </span>
                                            <?php if (!$notification['is_read']): ?>
                                                <span class="badge bg-primary ms-2">New</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="notification-actions">
                                        <?php if (!$notification['is_read']): ?>
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Mark as read">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="action" value="delete_notification">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this notification?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/parent.js"></script>
    <script>
        // Handle success messages
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success) {
            let message = '';
            switch (success) {
                case 'notification_read':
                    message = 'Notification marked as read!';
                    break;
                case 'all_notifications_read':
                    message = 'All notifications marked as read!';
                    break;
                case 'notification_deleted':
                    message = 'Notification deleted successfully!';
                    break;
            }
            if (message) {
                showNotification(message, 'success');
            }
        }
    </script>
</body>
</html>

<?php
function getNotificationIcon($type) {
    switch ($type) {
        case 'message': return 'fa-envelope';
        case 'announcement': return 'fa-bullhorn';
        case 'enrollment': return 'fa-user-plus';
        case 'attendance': return 'fa-calendar-check';
        case 'payment': return 'fa-credit-card';
        case 'system': return 'fa-cog';
        default: return 'fa-bell';
    }
}

function getNotificationIconClass($type) {
    switch ($type) {
        case 'message': return 'info';
        case 'announcement': return 'success';
        case 'enrollment': return 'warning';
        case 'attendance': return 'info';
        case 'payment': return 'danger';
        case 'system': return 'warning';
        default: return 'info';
    }
}

function getNotificationBadgeClass($type) {
    switch ($type) {
        case 'message': return 'bg-info';
        case 'announcement': return 'bg-success';
        case 'enrollment': return 'bg-warning';
        case 'attendance': return 'bg-primary';
        case 'payment': return 'bg-danger';
        case 'system': return 'bg-secondary';
        default: return 'bg-info';
    }
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M d, Y', strtotime($datetime));
}
?>
