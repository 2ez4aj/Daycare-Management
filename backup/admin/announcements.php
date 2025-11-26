<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $stmt = $conn->prepare("INSERT INTO announcements (title, content, author_id, priority, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['title'], $_POST['content'], $_SESSION['user_id'], $_POST['priority'], $_POST['status']]);
                $announcement_id = $conn->lastInsertId();
                
                // Create notifications for all parent users when announcement is published
                if ($_POST['status'] === 'published') {
                    $parent_stmt = $conn->prepare("SELECT id FROM users WHERE user_type = 'parent' AND status = 'active'");
                    $parent_stmt->execute();
                    $parents = $parent_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')");
                    foreach ($parents as $parent) {
                        $notification_title = "New Announcement: " . $_POST['title'];
                        $notification_message = "A new announcement has been posted. Click to view details.";
                        $notification_stmt->execute([$parent['id'], $notification_title, $notification_message]);
                    }
                }
                
                header('Location: announcements.php?success=announcement_created');
                exit();
                break;
                
            case 'edit_announcement':
                $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, priority = ?, status = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['content'],
                    $_POST['priority'],
                    $_POST['status'],
                    $_POST['announcement_id']
                ]);
                header('Location: announcements.php?success=announcement_updated');
                exit();
                break;
                
            case 'delete_announcement':
                $stmt = $conn->prepare("UPDATE announcements SET status = 'archived' WHERE id = ?");
                $stmt->execute([$_POST['announcement_id']]);
                header('Location: announcements.php?success=announcement_deleted');
                exit();
                break;
                
            case 'toggle_status':
                $new_status = $_POST['current_status'] === 'published' ? 'draft' : 'published';
                $stmt = $conn->prepare("UPDATE announcements SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $_POST['announcement_id']]);
                
                // Create notifications for all parent users when announcement is published
                if ($new_status === 'published') {
                    // Get announcement title
                    $ann_stmt = $conn->prepare("SELECT title FROM announcements WHERE id = ?");
                    $ann_stmt->execute([$_POST['announcement_id']]);
                    $announcement = $ann_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($announcement) {
                        $parent_stmt = $conn->prepare("SELECT id FROM users WHERE user_type = 'parent' AND status = 'active'");
                        $parent_stmt->execute();
                        $parents = $parent_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')");
                        foreach ($parents as $parent) {
                            $notification_title = "New Announcement: " . $announcement['title'];
                            $notification_message = "A new announcement has been posted. Click to view details.";
                            $notification_stmt->execute([$parent['id'], $notification_title, $notification_message]);
                        }
                    }
                }
                
                header('Location: announcements.php?success=status_updated');
                exit();
                break;
        }
    }
}

// Get announcements data
try {
    $conn = getDBConnection();
    
    // Get all announcements with author information
    $stmt = $conn->prepare("
        SELECT a.*, u.first_name, u.last_name 
        FROM announcements a 
        LEFT JOIN users u ON a.author_id = u.id 
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check what we got
    error_log("DEBUG: Found " . count($announcements) . " announcements");
    if (!empty($announcements)) {
        error_log("DEBUG: First announcement: " . print_r($announcements[0], true));
    }
    
    // Get announcement statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts,
            SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority
        FROM announcements
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug: Check stats
    error_log("DEBUG: Stats: " . print_r($stats, true));
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("DEBUG: Database error: " . $e->getMessage());
    $stats = ['total' => 0, 'published' => 0, 'drafts' => 0, 'high_priority' => 0];
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
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header d-flex justify-content-between align-items-center">
                <h1>Announcements</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                    <i class="fas fa-plus"></i> Create Announcement
                </button>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-bullhorn fa-2x text-primary mb-2"></i>
                            <h4><?php echo $stats['total']; ?></h4>
                            <small class="text-muted">Total Announcements</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-eye fa-2x text-success mb-2"></i>
                            <h4><?php echo $stats['published']; ?></h4>
                            <small class="text-muted">Published</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-edit fa-2x text-warning mb-2"></i>
                            <h4><?php echo $stats['drafts']; ?></h4>
                            <small class="text-muted">Drafts</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                            <h4><?php echo $stats['high_priority']; ?></h4>
                            <small class="text-muted">High Priority</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Announcements List -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>All Announcements</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($announcements)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No announcements yet</h5>
                            <p class="text-muted">Create your first announcement to communicate with parents.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                                <i class="fas fa-plus"></i> Create First Announcement
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card announcement-card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-<?php 
                                                    echo $announcement['priority'] === 'high' ? 'danger' : 
                                                        ($announcement['priority'] === 'medium' ? 'warning' : 'info'); 
                                                ?>">
                                                    <?php echo ucfirst($announcement['priority']); ?> Priority
                                                </span>
                                                <span class="badge bg-<?php echo $announcement['status'] === 'published' ? 'success' : 'secondary'; ?> ms-2">
                                                    <?php echo ucfirst($announcement['status']); ?>
                                                </span>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="editAnnouncement(<?php echo htmlspecialchars(json_encode($announcement)); ?>)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="toggleStatus(<?php echo $announcement['id']; ?>, '<?php echo $announcement['status']; ?>')">
                                                        <i class="fas fa-<?php echo $announcement['status'] === 'published' ? 'eye-slash' : 'eye'; ?>"></i> 
                                                        <?php echo $announcement['status'] === 'published' ? 'Unpublish' : 'Publish'; ?>
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars($announcement['title']); ?>')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars(substr($announcement['content'], 0, 150))); ?><?php echo strlen($announcement['content']) > 150 ? '...' : ''; ?></p>
                                        </div>
                                        <div class="card-footer text-muted">
                                            <small>
                                                <i class="fas fa-user me-1"></i>By <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?>
                                                <br>
                                                <i class="fas fa-clock me-1"></i><?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Announcement Modal -->
    <div class="modal fade" id="addAnnouncementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" required placeholder="Enter announcement title">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Content <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="content" rows="6" required placeholder="Write your announcement content here..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-control" name="priority" required>
                                    <option value="low">Low Priority</option>
                                    <option value="medium" selected>Medium Priority</option>
                                    <option value="high">High Priority</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status" required>
                                    <option value="draft">Save as Draft</option>
                                    <option value="published" selected>Publish Immediately</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Announcement Modal -->
    <div class="modal fade" id="editAnnouncementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editAnnouncementForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_announcement">
                        <input type="hidden" name="announcement_id" id="edit_announcement_id">
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" id="edit_title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Content <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="content" id="edit_content" rows="6" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-control" name="priority" id="edit_priority" required>
                                    <option value="low">Low Priority</option>
                                    <option value="medium">Medium Priority</option>
                                    <option value="high">High Priority</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status" id="edit_status" required>
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    
    <script>
        function editAnnouncement(announcement) {
            document.getElementById('edit_announcement_id').value = announcement.id;
            document.getElementById('edit_title').value = announcement.title;
            document.getElementById('edit_content').value = announcement.content;
            document.getElementById('edit_priority').value = announcement.priority;
            document.getElementById('edit_status').value = announcement.status;
            
            new bootstrap.Modal(document.getElementById('editAnnouncementModal')).show();
        }
        
        function toggleStatus(announcementId, currentStatus) {
            const action = currentStatus === 'published' ? 'unpublish' : 'publish';
            if (confirm(`Are you sure you want to ${action} this announcement?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="announcement_id" value="${announcementId}">
                    <input type="hidden" name="current_status" value="${currentStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteAnnouncement(announcementId, title) {
            if (confirm(`Are you sure you want to delete "${title}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_announcement">
                    <input type="hidden" name="announcement_id" value="${announcementId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Handle success messages
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success) {
            let message = '';
            switch (success) {
                case 'announcement_added':
                    message = 'Announcement created successfully!';
                    break;
                case 'announcement_updated':
                    message = 'Announcement updated successfully!';
                    break;
                case 'announcement_deleted':
                    message = 'Announcement deleted successfully!';
                    break;
                case 'status_updated':
                    message = 'Announcement status updated successfully!';
                    break;
            }
            if (message) {
                showNotification(message, 'success');
            }
        }
    </script>
    
    <style>
        .announcement-card {
            border-left: 4px solid #FF6B9D;
            transition: transform 0.2s;
        }
        
        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .card-text {
            line-height: 1.5;
        }
    </style>
</body>
</html>
