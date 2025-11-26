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
                $stmt = $conn->prepare("INSERT INTO progress_reports (student_id, report_date, academic_performance, social_skills, physical_development, behavioral_notes, teacher_comments, overall_rating, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['student_id'],
                    $_POST['report_date'],
                    $_POST['academic_performance'],
                    $_POST['social_skills'],
                    $_POST['physical_development'],
                    $_POST['behavioral_notes'],
                    $_POST['teacher_comments'],
                    $_POST['overall_rating'],
                    $_SESSION['user_id']
                ]);
                header('Location: progress.php?success=report_created');
                exit();
                break;
                
            case 'edit_report':
                $stmt = $conn->prepare("UPDATE progress_reports SET student_id = ?, report_date = ?, academic_performance = ?, social_skills = ?, physical_development = ?, behavioral_notes = ?, teacher_comments = ?, overall_rating = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['student_id'],
                    $_POST['report_date'],
                    $_POST['academic_performance'],
                    $_POST['social_skills'],
                    $_POST['physical_development'],
                    $_POST['behavioral_notes'],
                    $_POST['teacher_comments'],
                    $_POST['overall_rating'],
                    $_POST['report_id']
                ]);
                header('Location: progress.php?success=report_updated');
                exit();
                break;
                
            case 'delete_report':
                $stmt = $conn->prepare("DELETE FROM progress_reports WHERE id = ?");
                $stmt->execute([$_POST['report_id']]);
                header('Location: progress.php?success=report_deleted');
                exit();
                break;
        }
    }
}

// Get progress reports data
try {
    $conn = getDBConnection();
    
    // Get all progress reports with student and author information
    $stmt = $conn->prepare("
        SELECT pr.*, s.first_name as student_first_name, s.last_name as student_last_name,
               u.first_name as teacher_first_name, u.last_name as teacher_last_name
        FROM progress_reports pr 
        LEFT JOIN students s ON pr.student_id = s.id 
        LEFT JOIN users u ON pr.created_by = u.id 
        ORDER BY pr.report_date DESC, pr.created_at DESC
    ");
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get students for dropdown
    $stmt = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE status = 'active' ORDER BY first_name, last_name");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get report statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN overall_rating = 'excellent' THEN 1 ELSE 0 END) as excellent,
            SUM(CASE WHEN overall_rating = 'good' THEN 1 ELSE 0 END) as good,
            SUM(CASE WHEN overall_rating = 'satisfactory' THEN 1 ELSE 0 END) as satisfactory,
            SUM(CASE WHEN overall_rating = 'needs_improvement' THEN 1 ELSE 0 END) as needs_improvement
        FROM progress_reports
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $stats = ['total' => 0, 'excellent' => 0, 'good' => 0, 'satisfactory' => 0, 'needs_improvement' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Reports - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .development-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            background: #fff;
        }
        .development-card:hover {
            border-color: #007bff;
            box-shadow: 0 4px 12px rgba(0,123,255,0.15);
        }
        .development-card .card-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        .social { color: #28a745; }
        .cognitive { color: #007bff; }
        .physical { color: #fd7e14; }
        .emotional { color: #dc3545; }
        .language { color: #6f42c1; }
        .rating-badge {
            font-size: 0.9rem;
            padding: 6px 12px;
        }
        .rating-excellent { background-color: #28a745; }
        .rating-good { background-color: #17a2b8; }
        .rating-satisfactory { background-color: #ffc107; color: #000; }
        .rating-needs_improvement { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h1><i class="fas fa-chart-line me-3"></i>Progress Reports</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createReportModal">
                        <i class="fas fa-plus me-2"></i>Add Progress Report
                    </button>
                </div>
            </div>

            <div class="content-body">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php
                        switch ($_GET['success']) {
                            case 'report_created':
                                echo 'Progress report created successfully!';
                                break;
                            case 'report_updated':
                                echo 'Progress report updated successfully!';
                                break;
                            case 'report_deleted':
                                echo 'Progress report deleted successfully!';
                                break;
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Development Categories -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-seedling me-2"></i>Development Categories</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="development-card text-center">
                                    <div class="card-icon social">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <h6>Social Development</h6>
                                    <p class="text-muted small">Evaluates the child's social skills and interactions with peers and adults.</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="development-card text-center">
                                    <div class="card-icon cognitive">
                                        <i class="fas fa-brain"></i>
                                    </div>
                                    <h6>Cognitive Development</h6>
                                    <p class="text-muted small">Assesses cognitive abilities including critical thinking and problem-solving skills.</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="development-card text-center">
                                    <div class="card-icon physical">
                                        <i class="fas fa-running"></i>
                                    </div>
                                    <h6>Physical Development</h6>
                                    <p class="text-muted small">Tracks fine and gross motor skills and overall physical development.</p>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="development-card text-center">
                                    <div class="card-icon emotional">
                                        <i class="fas fa-heart"></i>
                                    </div>
                                    <h6>Emotional Development</h6>
                                    <p class="text-muted small">Monitors self-regulation, expression of feelings, and emotional awareness.</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="development-card text-center">
                                    <div class="card-icon language">
                                        <i class="fas fa-comments"></i>
                                    </div>
                                    <h6>Language Development</h6>
                                    <p class="text-muted small">Evaluates communication skills, vocabulary growth, and language comprehension.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="display-6 text-primary">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <h3 class="mt-3"><?php echo $stats['total']; ?></h3>
                                <p class="text-muted">Total Reports</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="display-6 text-success">
                                    <i class="fas fa-star"></i>
                                </div>
                                <h3 class="mt-3"><?php echo $stats['excellent']; ?></h3>
                                <p class="text-muted">Excellent</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="display-6 text-info">
                                    <i class="fas fa-thumbs-up"></i>
                                </div>
                                <h3 class="mt-3"><?php echo $stats['good']; ?></h3>
                                <p class="text-muted">Good</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="display-6 text-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <h3 class="mt-3"><?php echo $stats['needs_improvement']; ?></h3>
                                <p class="text-muted">Needs Attention</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Progress Reports -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent Progress Reports</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($reports)): ?>
                            <div class="text-center py-5">
                                <div class="display-1 text-muted mb-3">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <h4>No progress reports yet</h4>
                                <p class="text-muted">Create your first progress report to track student development.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createReportModal">
                                    <i class="fas fa-plus me-2"></i>Create First Report
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Student</th>
                                            <th>Overall Rating</th>
                                            <th>Teacher</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports as $report): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($report['report_date'])); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($report['student_first_name'] . ' ' . $report['student_last_name']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge rating-badge rating-<?php echo $report['overall_rating']; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $report['overall_rating'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($report['teacher_first_name'] . ' ' . $report['teacher_last_name']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="viewReport(<?php echo $report['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary me-1" onclick="editReport(<?php echo $report['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteReport(<?php echo $report['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Report Modal -->
    <div class="modal fade" id="createReportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Progress Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="student_id" class="form-label">Student</label>
                                    <select class="form-select" id="student_id" name="student_id" required>
                                        <option value="">Select Student</option>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?php echo $student['id']; ?>">
                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="report_date" class="form-label">Report Date</label>
                                    <input type="date" class="form-control" id="report_date" name="report_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="academic_performance" class="form-label">Academic Performance</label>
                            <textarea class="form-control" id="academic_performance" name="academic_performance" rows="3" placeholder="Describe the student's academic progress and achievements..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="social_skills" class="form-label">Social Skills</label>
                            <textarea class="form-control" id="social_skills" name="social_skills" rows="3" placeholder="Evaluate social interactions and peer relationships..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="physical_development" class="form-label">Physical Development</label>
                            <textarea class="form-control" id="physical_development" name="physical_development" rows="3" placeholder="Assess motor skills and physical growth..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="behavioral_notes" class="form-label">Behavioral Notes</label>
                            <textarea class="form-control" id="behavioral_notes" name="behavioral_notes" rows="3" placeholder="Note any behavioral observations or concerns..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="teacher_comments" class="form-label">Teacher Comments</label>
                            <textarea class="form-control" id="teacher_comments" name="teacher_comments" rows="3" placeholder="Additional comments and recommendations..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="overall_rating" class="form-label">Overall Rating</label>
                            <select class="form-select" id="overall_rating" name="overall_rating" required>
                                <option value="">Select Rating</option>
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="satisfactory">Satisfactory</option>
                                <option value="needs_improvement">Needs Improvement</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewReport(reportId) {
            // Implementation for viewing report details
            alert('View report functionality - ID: ' + reportId);
        }

        function editReport(reportId) {
            // Implementation for editing report
            alert('Edit report functionality - ID: ' + reportId);
        }

        function deleteReport(reportId) {
            if (confirm('Are you sure you want to delete this progress report?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_report">
                    <input type="hidden" name="report_id" value="${reportId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
