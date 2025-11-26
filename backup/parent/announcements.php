<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is parent
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'parent') {
    header('Location: ../index.php');
    exit();
}

// Get published announcements
try {
    $conn = getDBConnection();
    
    // Get all published announcements with author information
    $stmt = $conn->prepare("
        SELECT a.*, u.first_name, u.last_name 
        FROM announcements a 
        JOIN users u ON a.author_id = u.id 
        WHERE a.status = 'published'
        ORDER BY a.priority DESC, a.created_at DESC
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread messages count for sidebar
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_messages = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
    
    // Get unread notifications count for sidebar badge
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
    
} catch (Exception $e) {
    $announcements = [];
    $unread_messages = 0;
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Gumamela Daycare Center</title>
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
                <h1>Announcements</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Announcements</li>
                    </ol>
                </nav>
            </div>
            
            <!-- Announcements List -->
            <?php if (empty($announcements)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No announcements available</h5>
                        <p class="text-muted">Check back later for important updates from the daycare center.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="col-12 mb-4">
                            <div class="card announcement-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-<?php 
                                            echo $announcement['priority'] === 'high' ? 'danger' : 
                                                ($announcement['priority'] === 'medium' ? 'warning' : 'info'); 
                                        ?>">
                                            <i class="fas fa-<?php 
                                                echo $announcement['priority'] === 'high' ? 'exclamation-triangle' : 
                                                    ($announcement['priority'] === 'medium' ? 'info-circle' : 'info'); 
                                            ?>"></i>
                                            <?php echo ucfirst($announcement['priority']); ?> Priority
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="card-body">
                                    <h4 class="card-title"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                    <div class="card-text">
                                        <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-light">
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i>
                                        Posted by <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?>
                                        <?php if ($announcement['updated_at'] != $announcement['created_at']): ?>
                                            <span class="ms-3">
                                                <i class="fas fa-edit me-1"></i>
                                                Updated <?php echo date('M j, Y g:i A', strtotime($announcement['updated_at'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Announcement Summary -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-bullhorn fa-2x text-primary mb-2"></i>
                                <h5><?php echo count($announcements); ?></h5>
                                <small class="text-muted">Total Announcements</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                                <h5><?php echo count(array_filter($announcements, function($a) { return $a['priority'] === 'high'; })); ?></h5>
                                <small class="text-muted">High Priority</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-calendar fa-2x text-success mb-2"></i>
                                <h5><?php echo count(array_filter($announcements, function($a) { return date('Y-m-d', strtotime($a['created_at'])) === date('Y-m-d'); })); ?></h5>
                                <small class="text-muted">Posted Today</small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/parent.js"></script>
    
</body>
</html>
