<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is parent
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'parent') {
    header('Location: ../index.php');
    exit();
}

// Get unread messages count for sidebar
$unread_messages = 0;
$unread_notifications = 0;
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_messages = $stmt->fetchColumn();
    
    // Get unread notifications count for sidebar badge
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetchColumn();
} catch (Exception $e) {
    // Ignore error for sidebar count
}

// Check if enrollment is enabled
$enrollment_enabled = false;
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'enrollment_enabled'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $enrollment_enabled = $result && $result['setting_value'] == '1';
} catch (Exception $e) {
    // Ignore error
}

// Get upcoming events
$events = [];
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT * FROM events 
        WHERE event_date >= CURDATE() 
        ORDER BY event_date ASC, start_time ASC
    ");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to load events.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upcoming Events - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/parent.css" rel="stylesheet">
</head>
<body>
    <div class="parent-container">
        <?php include 'parent_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1>Upcoming Events</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Events</li>
                    </ol>
                </nav>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Events List -->
            <div class="row">
                <?php if (empty($events)): ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Upcoming Events</h5>
                                <p class="text-muted">There are no scheduled events at this time. Check back later for updates!</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <div class="col-lg-6 col-md-12 mb-4">
                            <div class="card event-card">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($event['title']); ?></h6>
                                        <span class="badge bg-primary">
                                            <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="event-details mb-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-clock text-primary me-2"></i>
                                            <span>
                                                <?php if ($event['start_time']): ?>
                                                    <?php echo date('g:i A', strtotime($event['start_time'])); ?>
                                                    <?php if ($event['end_time']): ?>
                                                        - <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    All Day
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php if ($event['location']): ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                                <span><?php echo htmlspecialchars($event['location']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($event['description']): ?>
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="event-type">
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst(str_replace('_', ' ', $event['event_type'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/parent.js"></script>
    
</body>
</html>
