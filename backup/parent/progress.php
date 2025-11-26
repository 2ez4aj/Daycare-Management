<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is parent
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'parent') {
    header('Location: ../index.php');
    exit();
}

// Get parent's children and their progress reports
try {
    $conn = getDBConnection();
    
    // Get parent's children
    $stmt = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE parent_id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get progress reports for parent's children
    $reports = [];
    if (!empty($children)) {
        $child_ids = array_column($children, 'id');
        $placeholders = str_repeat('?,', count($child_ids) - 1) . '?';
        
        $stmt = $conn->prepare("
            SELECT pr.*, s.first_name as student_first_name, s.last_name as student_last_name,
                   u.first_name as teacher_first_name, u.last_name as teacher_last_name
            FROM progress_reports pr 
            LEFT JOIN students s ON pr.student_id = s.id 
            LEFT JOIN users u ON pr.created_by = u.id 
            WHERE pr.student_id IN ($placeholders)
            ORDER BY pr.report_date DESC, pr.created_at DESC
        ");
        $stmt->execute($child_ids);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    $unread_messages = 0;
    $unread_notifications = 0;
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
    <link href="../assets/css/parent.css" rel="stylesheet">
</head>
<body>
    <div class="parent-container">
        <?php include 'parent_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-chart-line me-3"></i>Progress Reports</h1>
            </div>

            <div class="content-body">
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

                <!-- Recent Progress Reports -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent Progress Reports</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($children)): ?>
                            <div class="text-center py-5">
                                <div class="display-1 text-muted mb-3">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <h4>No enrolled children</h4>
                                <p class="text-muted">You don't have any children enrolled yet. Please enroll your child to view progress reports.</p>
                                <a href="enroll.php" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i>Enroll Child
                                </a>
                            </div>
                        <?php elseif (empty($reports)): ?>
                            <div class="text-center py-5">
                                <div class="display-1 text-muted mb-3">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <h4>No progress reports yet</h4>
                                <p class="text-muted">Progress reports will appear here once your child's teachers create them.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($reports as $report): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="report-card">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-user me-2"></i>
                                                    <?php echo htmlspecialchars($report['student_first_name'] . ' ' . $report['student_last_name']); ?>
                                                </h6>
                                                <span class="badge rating-badge rating-<?php echo $report['overall_rating']; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $report['overall_rating'])); ?>
                                                </span>
                                            </div>
                                            <div class="card-body">
                                                <div class="row mb-3">
                                                    <div class="col-6">
                                                        <small class="text-muted">Report Date</small>
                                                        <div><strong><?php echo date('M d, Y', strtotime($report['report_date'])); ?></strong></div>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted">Teacher</small>
                                                        <div><strong><?php echo htmlspecialchars($report['teacher_first_name'] . ' ' . $report['teacher_last_name']); ?></strong></div>
                                                    </div>
                                                </div>

                                                <?php if ($report['academic_performance']): ?>
                                                    <div class="mb-3">
                                                        <h6 class="text-primary"><i class="fas fa-graduation-cap me-2"></i>Academic Performance</h6>
                                                        <p class="small"><?php echo nl2br(htmlspecialchars($report['academic_performance'])); ?></p>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($report['social_skills']): ?>
                                                    <div class="mb-3">
                                                        <h6 class="text-success"><i class="fas fa-users me-2"></i>Social Skills</h6>
                                                        <p class="small"><?php echo nl2br(htmlspecialchars($report['social_skills'])); ?></p>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($report['physical_development']): ?>
                                                    <div class="mb-3">
                                                        <h6 class="text-warning"><i class="fas fa-running me-2"></i>Physical Development</h6>
                                                        <p class="small"><?php echo nl2br(htmlspecialchars($report['physical_development'])); ?></p>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($report['behavioral_notes']): ?>
                                                    <div class="mb-3">
                                                        <h6 class="text-info"><i class="fas fa-clipboard-list me-2"></i>Behavioral Notes</h6>
                                                        <p class="small"><?php echo nl2br(htmlspecialchars($report['behavioral_notes'])); ?></p>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($report['teacher_comments']): ?>
                                                    <div class="mb-3">
                                                        <h6 class="text-secondary"><i class="fas fa-comment me-2"></i>Teacher Comments</h6>
                                                        <p class="small"><?php echo nl2br(htmlspecialchars($report['teacher_comments'])); ?></p>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="text-end">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewFullReport(<?php echo $report['id']; ?>)">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </button>
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

    <!-- View Report Modal -->
    <div class="modal fade" id="viewReportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Progress Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="reportDetails">
                    <!-- Report details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printReport()">
                        <i class="fas fa-print me-2"></i>Print Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewFullReport(reportId) {
            // Find the report data
            const reports = <?php echo json_encode($reports); ?>;
            const report = reports.find(r => r.id == reportId);
            
            if (report) {
                const modalBody = document.getElementById('reportDetails');
                modalBody.innerHTML = `
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Student</h6>
                            <p><strong>${report.student_first_name} ${report.student_last_name}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Report Date</h6>
                            <p><strong>${new Date(report.report_date).toLocaleDateString()}</strong></p>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Teacher</h6>
                            <p><strong>${report.teacher_first_name} ${report.teacher_last_name}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Overall Rating</h6>
                            <p><span class="badge rating-badge rating-${report.overall_rating}">${report.overall_rating.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span></p>
                        </div>
                    </div>
                    ${report.academic_performance ? `
                        <div class="mb-3">
                            <h6 class="text-primary"><i class="fas fa-graduation-cap me-2"></i>Academic Performance</h6>
                            <p>${report.academic_performance.replace(/\n/g, '<br>')}</p>
                        </div>
                    ` : ''}
                    ${report.social_skills ? `
                        <div class="mb-3">
                            <h6 class="text-success"><i class="fas fa-users me-2"></i>Social Skills</h6>
                            <p>${report.social_skills.replace(/\n/g, '<br>')}</p>
                        </div>
                    ` : ''}
                    ${report.physical_development ? `
                        <div class="mb-3">
                            <h6 class="text-warning"><i class="fas fa-running me-2"></i>Physical Development</h6>
                            <p>${report.physical_development.replace(/\n/g, '<br>')}</p>
                        </div>
                    ` : ''}
                    ${report.behavioral_notes ? `
                        <div class="mb-3">
                            <h6 class="text-info"><i class="fas fa-clipboard-list me-2"></i>Behavioral Notes</h6>
                            <p>${report.behavioral_notes.replace(/\n/g, '<br>')}</p>
                        </div>
                    ` : ''}
                    ${report.teacher_comments ? `
                        <div class="mb-3">
                            <h6 class="text-secondary"><i class="fas fa-comment me-2"></i>Teacher Comments</h6>
                            <p>${report.teacher_comments.replace(/\n/g, '<br>')}</p>
                        </div>
                    ` : ''}
                `;
                
                const modal = new bootstrap.Modal(document.getElementById('viewReportModal'));
                modal.show();
            }
        }

        function printReport() {
            const reportContent = document.getElementById('reportDetails').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Progress Report</title>
                        <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
                        <style>
                            body { padding: 20px; }
                            .rating-excellent { background-color: #28a745; }
                            .rating-good { background-color: #17a2b8; }
                            .rating-satisfactory { background-color: #ffc107; color: #000; }
                            .rating-needs_improvement { background-color: #dc3545; }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <h2 class="mb-4">Gumamela Daycare Center - Progress Report</h2>
                            ${reportContent}
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html>
