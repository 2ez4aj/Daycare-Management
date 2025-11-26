<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is parent
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'parent') {
    header('Location: ../index.php');
    exit();
}

try {
    $conn = getDBConnection();
    
    // Check if parent has enrolled children
    $stmt = $conn->prepare("SELECT COUNT(*) as child_count FROM students WHERE parent_id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $child_count = $stmt->fetch(PDO::FETCH_ASSOC)['child_count'];
    
    // If no enrolled children, redirect or show access denied
    if ($child_count == 0) {
        $no_access = true;
    } else {
        $no_access = false;
        
        // Get learning activities
        $stmt = $conn->prepare("
            SELECT la.*, u.first_name, u.last_name 
            FROM learning_activities la 
            JOIN users u ON la.created_by = u.id 
            WHERE la.status = 'active'
            ORDER BY la.created_at DESC
        ");
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get unread messages count for sidebar
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_messages = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
    
    // Get unread notifications count for sidebar badge
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $activities = [];
    $no_access = true;
    $unread_messages = 0;
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Activities - Gumamela Daycare Center</title>
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
                <h1><i class="fas fa-graduation-cap me-3"></i>Learning Activities</h1>
            </div>

            <div class="content-body">
                <?php if ($no_access): ?>
                    <!-- No Access Message -->
                    <div class="no-access-container">
                        <div class="no-access-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h3>Access Restricted</h3>
                        <p class="text-muted mb-4">
                            Learning activities are only available to parents with enrolled children.<br>
                            Please enroll your child first to access educational content and activities.
                        </p>
                        <a href="enroll.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-user-plus me-2"></i>Enroll Your Child
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Activities Available -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Learning Activities for Your Child</strong><br>
                                These activities are designed to support your child's learning at home, especially during remote learning periods.
                            </div>
                        </div>
                    </div>

                    <?php if (empty($activities)): ?>
                        <div class="text-center py-5">
                            <div class="display-1 text-muted mb-3">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <h4>No learning activities available</h4>
                            <p class="text-muted">Check back later for new educational activities and resources.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($activities as $activity): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="activity-card">
                                        <div class="activity-header">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h4 class="mb-2"><?php echo htmlspecialchars($activity['title']); ?></h4>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-user-tie me-2"></i>
                                                        <span><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <?php if ($activity['age_group']): ?>
                                                        <span class="badge age-badge">
                                                            <i class="fas fa-child me-1"></i><?php echo htmlspecialchars($activity['age_group']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="activity-content">
                                            <!-- Description -->
                                            <div class="mb-3">
                                                <h6><i class="fas fa-info-circle me-2 text-primary"></i>Description</h6>
                                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($activity['description'])); ?></p>
                                            </div>

                                            <!-- Attachment -->
                                            <?php if ($activity['attachment_path']): ?>
                                                <div class="mb-3">
                                                    <h6><i class="fas fa-paperclip me-2 text-success"></i>Activity Resource</h6>
                                                    <?php if ($activity['attachment_type'] === 'image'): ?>
                                                        <img src="../<?php echo $activity['attachment_path']; ?>" 
                                                             alt="Activity Image" 
                                                             class="attachment-preview"
                                                             onclick="window.open('../<?php echo $activity['attachment_path']; ?>', '_blank')">
                                                    <?php else: ?>
                                                        <div class="file-download file-download-clickable" onclick="window.open('../<?php echo $activity['attachment_path']; ?>', '_blank')">
                                                            <div class="file-icon">
                                                                <i class="fas fa-file-<?php echo $activity['attachment_type'] === 'document' ? 'pdf' : ($activity['attachment_type'] === 'video' ? 'video' : 'alt'); ?>"></i>
                                                            </div>
                                                            <h6><?php echo htmlspecialchars($activity['attachment_name']); ?></h6>
                                                            <small class="text-muted">
                                                                <?php echo number_format($activity['attachment_size'] / 1024, 1); ?> KB • 
                                                                Click to <?php echo $activity['attachment_type'] === 'video' ? 'watch' : 'download'; ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Materials Needed -->
                                            <?php if ($activity['materials_needed']): ?>
                                                <div class="materials-list">
                                                    <h6><i class="fas fa-tools me-2"></i>Materials Needed</h6>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($activity['materials_needed'])); ?></p>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Instructions -->
                                            <?php if ($activity['instructions']): ?>
                                                <div class="instructions-box">
                                                    <h6><i class="fas fa-list-ol me-2"></i>Instructions</h6>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($activity['instructions'])); ?></p>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Learning Objectives -->
                                            <?php if ($activity['learning_objectives']): ?>
                                                <div class="objectives-box">
                                                    <h6><i class="fas fa-bullseye me-2"></i>Learning Objectives</h6>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($activity['learning_objectives'])); ?></p>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Activity Footer -->
                                            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    Posted <?php echo date('M d, Y', strtotime($activity['created_at'])); ?>
                                                </small>
                                                <?php if ($activity['attachment_path']): ?>
                                                    <a href="../<?php echo $activity['attachment_path']; ?>" 
                                                       target="_blank" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-download me-1"></i>
                                                        <?php echo $activity['attachment_type'] === 'video' ? 'Watch' : 'Download'; ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add click handlers for file downloads
        document.querySelectorAll('.file-download').forEach(function(element) {
            element.addEventListener('click', function() {
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
        });

        // Add click handlers for image previews
        document.querySelectorAll('.attachment-preview').forEach(function(img) {
            img.style.cursor = 'pointer';
            img.addEventListener('click', function() {
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
        });
    </script>
</body>
</html>
