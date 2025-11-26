<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

try {
    $conn = getDBConnection();
    
    // Get filter parameters
    $activity_id = $_GET['activity_id'] ?? null;
    $parent_id = $_GET['parent_id'] ?? null;
    $status = $_GET['status'] ?? 'all';
    
    // Build the base query
    $query = "
        SELECT 
            las.*, 
            s.first_name AS student_first_name, 
            s.last_name AS student_last_name,
            p.first_name AS parent_first_name,
            p.last_name AS parent_last_name,
            p.email AS parent_email,
            p.phone AS parent_phone,
            la.title AS activity_title
        FROM learning_activity_submissions las
        JOIN students s ON las.student_id = s.id
        JOIN users p ON las.parent_id = p.id
        JOIN learning_activities la ON las.activity_id = la.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Add activity filter
    if ($activity_id) {
        $query .= " AND las.activity_id = ?";
        $params[] = $activity_id;
    }
    
    // Add parent filter
    if ($parent_id) {
        $query .= " AND las.parent_id = ?";
        $params[] = $parent_id;
    }
    
    // Add status filter (if implemented)
    if ($status !== 'all') {
        $query .= " AND las.status = ?";
        $params[] = $status;
    }
    
    // Add sorting
    $query .= " ORDER BY las.created_at DESC";
    
    // Execute the query
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get activities for filter dropdown
    $stmt = $conn->query("SELECT id, title FROM learning_activities ORDER BY title");
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get parents for filter dropdown
    $stmt = $conn->query("SELECT DISTINCT u.id, u.first_name, u.last_name 
                          FROM users u 
                          JOIN learning_activity_submissions las ON u.id = las.parent_id
                          ORDER BY u.first_name, u.last_name");
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $submissions = [];
    $activities = [];
    $parents = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Submissions - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .submission-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #fff;
        }
        .submission-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .file-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-file-upload me-3"></i>Learning Activity Submissions</h1>
            </div>

            <div class="content-body">
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Filter by Activity</label>
                                <select name="activity_id" class="form-select">
                                    <option value="">All Activities</option>
                                    <?php foreach ($activities as $activity): ?>
                                        <option value="<?php echo $activity['id']; ?>" 
                                            <?php echo (isset($_GET['activity_id']) && $_GET['activity_id'] == $activity['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($activity['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Filter by Parent</label>
                                <select name="parent_id" class="form-select">
                                    <option value="">All Parents</option>
                                    <?php foreach ($parents as $parent): ?>
                                        <option value="<?php echo $parent['id']; ?>" 
                                            <?php echo (isset($_GET['parent_id']) && $_GET['parent_id'] == $parent['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter me-1"></i> Filter
                                </button>
                                <a href="activity_submissions.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-sync"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Submissions List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Submissions
                            <span class="badge bg-primary rounded-pill ms-2"><?php echo count($submissions); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($submissions)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5>No submissions found</h5>
                                <p class="text-muted">No submissions match your current filters.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($submissions as $submission): ?>
                                <div class="submission-card">
                                    <div class="submission-header">
                                        <div>
                                            <h5 class="mb-1"><?php echo htmlspecialchars($submission['activity_title']); ?></h5>
                                            <p class="mb-0 text-muted">
                                                Submitted by <?php echo htmlspecialchars($submission['parent_first_name'] . ' ' . $submission['parent_last_name']); ?>
                                                for <?php echo htmlspecialchars($submission['student_first_name'] . ' ' . $submission['student_last_name']); ?>
                                                on <?php echo date('M j, Y g:i A', strtotime($submission['created_at'])); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <a href="../<?php echo htmlspecialchars($submission['file_path']); ?>" 
                                               class="btn btn-sm btn-outline-primary" 
                                               download="<?php echo htmlspecialchars($submission['file_name']); ?>">
                                                <i class="fas fa-download me-1"></i> Download
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($submission['notes'])): ?>
                                        <div class="mb-3">
                                            <h6>Notes:</h6>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($submission['notes'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $file_ext = strtolower(pathinfo($submission['file_name'], PATHINFO_EXTENSION));
                                    $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']);
                                    $is_pdf = $file_ext === 'pdf';
                                    $is_video = in_array($file_ext, ['mp4', 'webm', 'ogg']);
                                    ?>
                                    
                                    <?php if ($is_image): ?>
                                        <div class="file-preview-container">
                                            <img src="../<?php echo htmlspecialchars($submission['file_path']); ?>" 
                                                 alt="Submission preview" 
                                                 class="img-thumbnail file-preview">
                                        </div>
                                    <?php elseif ($is_pdf): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-file-pdf me-2"></i>
                                            PDF file: <?php echo htmlspecialchars($submission['file_name']); ?>
                                        </div>
                                    <?php elseif ($is_video): ?>
                                        <div class="ratio ratio-16x9">
                                            <video controls>
                                                <source src="../<?php echo htmlspecialchars($submission['file_path']); ?>" 
                                                        type="video/<?php echo $file_ext; ?>">
                                                Your browser does not support the video tag.
                                            </video>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-secondary">
                                            <i class="fas fa-file me-2"></i>
                                            File: <?php echo htmlspecialchars($submission['file_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add any necessary JavaScript here
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize any plugins or add event listeners
        });
    </script>
</body>
</html>
