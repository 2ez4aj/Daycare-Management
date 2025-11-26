<?php
session_start();
require_once '../config/database.php';
require_once '../config/upload.php';
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = getDBConnection();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_activity':
                    $title = trim($_POST['title']);
                    $description = trim($_POST['description']);
                    $age_group = trim($_POST['age_group']);
                    $materials_needed = trim($_POST['materials_needed']);
                    $instructions = trim($_POST['instructions']);
                    $learning_objectives = trim($_POST['learning_objectives']);
                    $schedule_id = !empty($_POST['schedule_id']) ? $_POST['schedule_id'] : null;
                    
                    // Handle file upload
                    $attachment_path = null;
                    $attachment_name = null;
                    $attachment_size = null;
                    $attachment_type = null;
                    
                    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                        $upload_result = FileUpload::handleUpload($_FILES['attachment'], 'learning_activities');
                        if ($upload_result['success']) {
                            $attachment_path = $upload_result['file_path'];
                            $attachment_name = $upload_result['original_name'];
                            $attachment_size = $upload_result['file_size'];
                            
                            // Determine attachment type
                            $mime_type = $upload_result['mime_type'];
                            if (strpos($mime_type, 'image/') === 0) {
                                $attachment_type = 'image';
                            } elseif (in_array($mime_type, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) {
                                $attachment_type = 'document';
                            } elseif (strpos($mime_type, 'video/') === 0) {
                                $attachment_type = 'video';
                            } else {
                                $attachment_type = 'other';
                            }
                        } else {
                            throw new Exception($upload_result['error']);
                        }
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO learning_activities (title, description, age_group, materials_needed, instructions, learning_objectives, attachment_path, attachment_name, attachment_size, attachment_type, schedule_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $age_group, $materials_needed, $instructions, $learning_objectives, $attachment_path, $attachment_name, $attachment_size, $attachment_type, $schedule_id, $_SESSION['user_id']]);
                    
                    // Send notifications to parents with children in the selected schedule
                    if ($schedule_id) {
                        // Send to parents of children in this specific schedule
                        $stmt = $conn->prepare("
                            SELECT DISTINCT u.id 
                            FROM users u 
                            JOIN students s ON u.id = s.parent_id 
                            JOIN student_schedules ss ON s.id = ss.student_id 
                            WHERE u.user_type = 'parent' AND u.status = 'active' AND s.status = 'active' 
                            AND ss.schedule_id = ? AND ss.is_active = 1
                        ");
                        $stmt->execute([$schedule_id]);
                    } else {
                        // Send to all parents if no schedule specified
                        $stmt = $conn->prepare("
                            SELECT DISTINCT u.id 
                            FROM users u 
                            JOIN students s ON u.id = s.parent_id 
                            WHERE u.user_type = 'parent' AND u.status = 'active' AND s.status = 'active'
                        ");
                        $stmt->execute();
                    }
                    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($parents as $parent) {
                        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                        $stmt->execute([
                            $parent['id'],
                            'New Learning Activity Available',
                            "A new learning activity '{$title}' has been posted for your child.",
                            'info'
                        ]);
                    }
                    
                    $success_message = "Learning activity added successfully!";
                    break;
                    
                case 'edit_activity':
                    $activity_id = $_POST['activity_id'];
                    $title = trim($_POST['title']);
                    $description = trim($_POST['description']);
                    $age_group = trim($_POST['age_group']);
                    $materials_needed = trim($_POST['materials_needed']);
                    $instructions = trim($_POST['instructions']);
                    $learning_objectives = trim($_POST['learning_objectives']);
                    $schedule_id = !empty($_POST['schedule_id']) ? $_POST['schedule_id'] : null;
                    $status = $_POST['status'];
                    
                    // Get current activity data
                    $stmt = $conn->prepare("SELECT * FROM learning_activities WHERE id = ?");
                    $stmt->execute([$activity_id]);
                    $current_activity = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $attachment_path = $current_activity['attachment_path'];
                    $attachment_name = $current_activity['attachment_name'];
                    $attachment_size = $current_activity['attachment_size'];
                    $attachment_type = $current_activity['attachment_type'];
                    
                    // Handle new file upload
                    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                        // Delete old file if exists
                        if ($attachment_path && file_exists($attachment_path)) {
                            unlink($attachment_path);
                        }
                        
                        $upload_result = FileUpload::handleUpload($_FILES['attachment'], 'learning_activities');
                        if ($upload_result['success']) {
                            $attachment_path = $upload_result['file_path'];
                            $attachment_name = $upload_result['original_name'];
                            $attachment_size = $upload_result['file_size'];
                            
                            // Determine attachment type
                            $mime_type = $upload_result['mime_type'];
                            if (strpos($mime_type, 'image/') === 0) {
                                $attachment_type = 'image';
                            } elseif (in_array($mime_type, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) {
                                $attachment_type = 'document';
                            } elseif (strpos($mime_type, 'video/') === 0) {
                                $attachment_type = 'video';
                            } else {
                                $attachment_type = 'other';
                            }
                        } else {
                            throw new Exception($upload_result['error']);
                        }
                    }
                    
                    $stmt = $conn->prepare("UPDATE learning_activities SET title = ?, description = ?, age_group = ?, materials_needed = ?, instructions = ?, learning_objectives = ?, attachment_path = ?, attachment_name = ?, attachment_size = ?, attachment_type = ?, schedule_id = ?, status = ? WHERE id = ?");
                    $stmt->execute([$title, $description, $age_group, $materials_needed, $instructions, $learning_objectives, $attachment_path, $attachment_name, $attachment_size, $attachment_type, $schedule_id, $status, $activity_id]);
                    
                    $success_message = "Learning activity updated successfully!";
                    break;
                    
                case 'delete_activity':
                    $activity_id = $_POST['activity_id'];
                    
                    // Get activity data to delete file
                    $stmt = $conn->prepare("SELECT attachment_path FROM learning_activities WHERE id = ?");
                    $stmt->execute([$activity_id]);
                    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Delete file if exists
                    if ($activity['attachment_path'] && file_exists($activity['attachment_path'])) {
                        unlink($activity['attachment_path']);
                    }
                    
                    $stmt = $conn->prepare("DELETE FROM learning_activities WHERE id = ?");
                    $stmt->execute([$activity_id]);
                    
                    $success_message = "Learning activity deleted successfully!";
                    break;
            }
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get all learning activities
try {
    $conn = getDBConnection();
    
    // Get filter parameters
    $filter_creator = $_GET['creator'] ?? 'all';
    $filter_status = $_GET['status'] ?? 'all';
    
    // Build the base query
    $query = "
        SELECT la.*, u.first_name, u.last_name, u.user_type 
        FROM learning_activities la 
        JOIN users u ON la.created_by = u.id 
        WHERE 1=1
    ";
    
    $params = [];
    
    // Add creator filter
    if ($filter_creator === 'admin') {
        $query .= " AND u.user_type = 'admin'";
    } elseif ($filter_creator === 'parent') {
        $query .= " AND u.user_type = 'parent'";
    }
    
    // Add status filter
    if ($filter_status === 'active') {
        $query .= " AND la.status = 'active'";
    } elseif ($filter_status === 'inactive') {
        $query .= " AND la.status = 'inactive'";
    }
    
    // Add sorting
    $query .= " ORDER BY la.created_at DESC";
    
    // Execute the query
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats = [
        'total_activities' => count($activities),
        'admin_activities' => count(array_filter($activities, function($a) { return $a['user_type'] === 'admin'; })),
        'parent_activities' => count(array_filter($activities, function($a) { return $a['user_type'] === 'parent'; }))
    ];
    
    // Get schedules for dropdown
    $stmt = $conn->prepare("SELECT id, schedule_name, start_time, end_time FROM schedules WHERE is_active = 1 ORDER BY start_time");
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $activities = [];
    $stats = ['total_activities' => 0];
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
    <link href="../assets/css/admin.css" rel="stylesheet">
    <link href="../assets/css/mobile_nav.css" rel="stylesheet">
    <style>
        .activity-card {
            border: 1px solid #dee2e6;
            border-radius: 12px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            background: #fff;
        }
        .activity-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .activity-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px 12px 0 0;
        }
        .attachment-preview {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }
        .file-icon {
            font-size: 3rem;
            color: #6c757d;
        }
        .age-badge {
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            color: white;
            border: none;
        }
        .status-active { border-left: 5px solid #28a745; }
        .status-inactive { border-left: 5px solid #dc3545; }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-graduation-cap me-3"></i>Learning Activities Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                    <i class="fas fa-plus me-2"></i>Add New Activity
                </button>
            </div>

            <div class="content-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['total_activities']; ?></h4>
                                        <p class="mb-0">Active Activities</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-graduation-cap fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activities List -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>All Learning Activities
                        </h5>
                        <div class="d-flex
                            <form method="GET" class="d-flex gap-2">
                                <div class="input-group input-group-sm" style="width: 200px;">
                                    <select name="creator" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="all" <?php echo ($_GET['creator'] ?? '') === 'all' ? 'selected' : ''; ?>>All Creators</option>
                                        <option value="admin" <?php echo ($_GET['creator'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="parent" <?php echo ($_GET['creator'] ?? '') === 'parent' ? 'selected' : ''; ?>>Parents</option>
                                    </select>
                                </div>
                                <div class="input-group input-group-sm" style="width: 150px;">
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="all" <?php echo ($_GET['status'] ?? '') === 'all' ? 'selected' : ''; ?>>All Status</option>
                                        <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($_GET['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activities)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                                <h5>No learning activities yet</h5>
                                <p class="text-muted">Create your first learning activity to get started.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                                    <i class="fas fa-plus me-2"></i>Add First Activity
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($activities as $activity): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="activity-card status-<?php echo $activity['status']; ?>">
                                            <div class="activity-header">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h5 class="mb-1"><?php echo htmlspecialchars($activity['title']); ?></h5>
                                                        <small>
                                                            <i class="fas fa-user me-1"></i>
                                                            <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="#" onclick="editActivity(<?php echo $activity['id']; ?>)">
                                                                <i class="fas fa-edit me-2"></i>Edit
                                                            </a></li>
                                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteActivity(<?php echo $activity['id']; ?>)">
                                                                <i class="fas fa-trash me-2"></i>Delete
                                                            </a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <?php if ($activity['age_group']): ?>
                                                        <span class="badge age-badge me-2">
                                                            <i class="fas fa-child me-1"></i><?php echo htmlspecialchars($activity['age_group']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="badge bg-<?php echo $activity['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($activity['status']); ?>
                                                    </span>
                                                </div>
                                                
                                                <p class="text-muted mb-3"><?php echo htmlspecialchars(substr($activity['description'], 0, 150)) . (strlen($activity['description']) > 150 ? '...' : ''); ?></p>
                                                
                                                <?php if ($activity['attachment_path']): ?>
                                                    <div class="mb-3">
                                                        <strong class="d-block mb-2">
                                                            <i class="fas fa-paperclip me-1"></i>Attachment:
                                                        </strong>
                                                        <div class="d-flex align-items-center">
                                                            <?php if ($activity['attachment_type'] === 'image'): ?>
                                                                <img src="../<?php echo $activity['attachment_path']; ?>" alt="Activity Image" class="attachment-preview me-3">
                                                            <?php else: ?>
                                                                <div class="text-center me-3">
                                                                    <i class="fas fa-file-<?php echo $activity['attachment_type'] === 'document' ? 'pdf' : 'alt'; ?> file-icon"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($activity['attachment_name']); ?></div>
                                                                <small class="text-muted">
                                                                    <?php echo number_format($activity['attachment_size'] / 1024, 1); ?> KB
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="row text-center">
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">Created</small>
                                                        <strong><?php echo date('M d, Y', strtotime($activity['created_at'])); ?></strong>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">Updated</small>
                                                        <strong><?php echo date('M d, Y', strtotime($activity['updated_at'])); ?></strong>
                                                    </div>
                                                </div>
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
    </div>

    <!-- Add Activity Modal -->
    <div class="modal fade" id="addActivityModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add New Learning Activity
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_activity">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Activity Title *</label>
                                    <input type="text" class="form-control" name="title" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Age Group</label>
                                    <input type="text" class="form-control" name="age_group" placeholder="e.g., 3-5 years">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Schedule/Section</label>
                                    <select class="form-control" name="schedule_id">
                                        <option value="">All Schedules</option>
                                        <?php foreach ($schedules as $schedule): ?>
                                            <option value="<?php echo $schedule['id']; ?>">
                                                <?php echo htmlspecialchars($schedule['schedule_name']); ?> (<?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Materials Needed</label>
                            <textarea class="form-control" name="materials_needed" rows="2" placeholder="List all materials required for this activity"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Instructions</label>
                            <textarea class="form-control" name="instructions" rows="4" placeholder="Step-by-step instructions for the activity"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Learning Objectives</label>
                            <textarea class="form-control" name="learning_objectives" rows="2" placeholder="What will children learn from this activity?"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Attachment (Images, Documents, Videos)</label>
                            <input type="file" class="form-control" name="attachment" accept="image/*,.pdf,.doc,.docx,.mp4,.avi,.mov">
                            <div class="form-text">Supported formats: Images (JPG, PNG, GIF), Documents (PDF, DOC, DOCX), Videos (MP4, AVI, MOV). Max size: 10MB</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Activity
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Activity Modal -->
    <div class="modal fade" id="editActivityModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Learning Activity
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_activity">
                        <input type="hidden" name="activity_id" id="edit_activity_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Activity Title *</label>
                                    <input type="text" class="form-control" name="title" id="edit_title" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Age Group</label>
                                    <input type="text" class="form-control" name="age_group" id="edit_age_group">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Schedule/Section</label>
                                    <select class="form-control" name="schedule_id" id="edit_schedule_id">
                                        <option value="">All Schedules</option>
                                        <?php foreach ($schedules as $schedule): ?>
                                            <option value="<?php echo $schedule['id']; ?>">
                                                <?php echo htmlspecialchars($schedule['schedule_name']); ?> (<?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-control" name="status" id="edit_status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Materials Needed</label>
                            <textarea class="form-control" name="materials_needed" id="edit_materials_needed" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Instructions</label>
                            <textarea class="form-control" name="instructions" id="edit_instructions" rows="4"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Learning Objectives</label>
                            <textarea class="form-control" name="learning_objectives" id="edit_learning_objectives" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Replace Attachment (Optional)</label>
                            <input type="file" class="form-control" name="attachment" accept="image/*,.pdf,.doc,.docx,.mp4,.avi,.mov">
                            <div class="form-text">Leave empty to keep current attachment. Supported formats: Images, Documents, Videos. Max size: 10MB</div>
                            <div id="current_attachment" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Activity
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        const activities = <?php echo json_encode($activities); ?>;
        
        function editActivity(activityId) {
            const activity = activities.find(a => a.id == activityId);
            if (!activity) return;
            
            document.getElementById('edit_activity_id').value = activity.id;
            document.getElementById('edit_title').value = activity.title;
            document.getElementById('edit_description').value = activity.description;
            document.getElementById('edit_age_group').value = activity.age_group || '';
            document.getElementById('edit_materials_needed').value = activity.materials_needed || '';
            document.getElementById('edit_instructions').value = activity.instructions || '';
            document.getElementById('edit_learning_objectives').value = activity.learning_objectives || '';
            document.getElementById('edit_schedule_id').value = activity.schedule_id || '';
            document.getElementById('edit_status').value = activity.status;
            
            // Show current attachment
            const currentAttachment = document.getElementById('current_attachment');
            if (activity.attachment_name) {
                currentAttachment.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-paperclip me-2"></i>
                        Current attachment: <strong>${activity.attachment_name}</strong>
                        (${(activity.attachment_size / 1024).toFixed(1)} KB)
                    </div>
                `;
            } else {
                currentAttachment.innerHTML = '<div class="text-muted">No current attachment</div>';
            }
            
            new bootstrap.Modal(document.getElementById('editActivityModal')).show();
        }
        
        function deleteActivity(activityId) {
            if (confirm('Are you sure you want to delete this learning activity? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_activity">
                    <input type="hidden" name="activity_id" value="${activityId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    <script src="../assets/js/mobile_nav.js"></script>
    <?php include 'mobile_nav.php'; ?>
</body>
</html>
