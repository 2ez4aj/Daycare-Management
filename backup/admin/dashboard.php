<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Handle enrollment toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_enrollment'])) {
    $conn = getDBConnection();
    $current_status = $_POST['current_status'] === '1' ? '0' : '1';
    
    $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'enrollment_enabled'");
    $stmt->execute([$current_status]);
    
    header('Location: dashboard.php?success=enrollment_toggled');
    exit();
}

// Get dashboard statistics
try {
    $conn = getDBConnection();
    
    // Get total students
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    $stmt->execute();
    $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total parents
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE user_type = 'parent' AND status = 'active'");
    $stmt->execute();
    $total_parents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get today's attendance
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT 
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent
        FROM attendance WHERE date = ?");
    $stmt->execute([$today]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent announcements
    $stmt = $conn->prepare("SELECT title, content, created_at FROM announcements WHERE status = 'published' ORDER BY created_at DESC LIMIT 3");
    $stmt->execute();
    $recent_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get enrollment status
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'enrollment_enabled'");
    $stmt->execute();
    $enrollment_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $enrollment_enabled = $enrollment_result ? (bool)$enrollment_result['setting_value'] : true;
    
    // Get pending parent approvals count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'parent' AND status = 'pending'");
    $stmt->execute();
    $pending_parents = $stmt->fetchColumn();
    
    // Get pending enrollments count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE status = 'pending'");
    $stmt->execute();
    $pending_enrollments = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $total_students = 0;
    $total_parents = 0;
    $attendance = ['present' => 0, 'absent' => 0];
    $recent_announcements = [];
    $enrollment_enabled = true;
    $pending_parents = 0;
    $pending_enrollments = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css?v=<?php echo time(); ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <button class="sidebar-toggle-btn d-lg-none" type="button">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>Admin Dashboard</h1>
            </div>
            
            <!-- Enrollment Control Alert -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert <?php echo $enrollment_enabled ? 'alert-success' : 'alert-warning'; ?> d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <i class="fas <?php echo $enrollment_enabled ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-3 fs-4"></i>
                            <div>
                                <h5 class="mb-1">
                                    Parent Enrollment: <?php echo $enrollment_enabled ? 'ENABLED' : 'DISABLED'; ?>
                                </h5>
                                <p class="mb-0">
                                    <?php if ($enrollment_enabled): ?>
                                        Parents can currently enroll their children through the parent portal.
                                    <?php else: ?>
                                        Parent enrollment is currently disabled. New enrollment requests are blocked.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="toggle_enrollment" value="1">
                            <input type="hidden" name="current_status" value="<?php echo $enrollment_enabled ? '1' : '0'; ?>">
                            <button type="submit" class="btn <?php echo $enrollment_enabled ? 'btn-warning' : 'btn-success'; ?> btn-lg">
                                <i class="fas <?php echo $enrollment_enabled ? 'fa-toggle-off' : 'fa-toggle-on'; ?> me-2"></i>
                                <?php echo $enrollment_enabled ? 'DISABLE' : 'ENABLE'; ?> Enrollment
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card stat-card-blue">
                        <div class="stat-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_students; ?></h3>
                            <p>Total Children</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card stat-card-green">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_parents; ?></h3>
                            <p>Total Parent</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card stat-card-orange">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $attendance['present']; ?></h3>
                            <p>Present Today</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card stat-card-red">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $attendance['absent']; ?></h3>
                            <p>Absent Today</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Quick Action</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="attendance.php" class="quick-action-btn btn-pink">
                                <i class="fas fa-calendar-check"></i>
                                <span>Attendance</span>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="progress.php" class="quick-action-btn btn-blue">
                                <i class="fas fa-chart-line"></i>
                                <span>Add Progress</span>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="students.php?action=add" class="quick-action-btn btn-green">
                                <i class="fas fa-user-plus"></i>
                                <span>Register Child</span>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="announcements.php?action=add" class="quick-action-btn btn-orange">
                                <i class="fas fa-bullhorn"></i>
                                <span>Announcement</span>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Pending Parent Approvals Alert -->
                    <?php if ($pending_parents > 0): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-users me-2"></i>
                        <strong>Pending Parent Approvals!</strong>
                        You have <?php echo $pending_parents; ?> parent account<?php echo $pending_parents > 1 ? 's' : ''; ?> waiting for approval.
                        <a href="parents.php" class="alert-link ms-2">Review Now</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Pending Enrollments Alert -->
                    <?php if ($pending_enrollments > 0): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-user-plus me-2"></i>
                        <strong>New Enrollment Applications!</strong>
                        You have <?php echo $pending_enrollments; ?> child enrollment<?php echo $pending_enrollments > 1 ? 's' : ''; ?> waiting for review.
                        <a href="students.php" class="alert-link ms-2">Review Enrollments</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- System Status Cards -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1">Enrollment Status</h6>
                                    <h4 class="mb-0 <?php echo $enrollment_enabled ? 'text-success' : 'text-warning'; ?>">
                                        <?php echo $enrollment_enabled ? 'OPEN' : 'CLOSED'; ?>
                                    </h4>
                                </div>
                                <div class="enrollment-status-icon <?php echo $enrollment_enabled ? 'text-success' : 'text-warning'; ?>">
                                    <i class="fas <?php echo $enrollment_enabled ? 'fa-door-open' : 'fa-door-closed'; ?> fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1">Pending Approvals</h6>
                                    <h4 class="mb-0 <?php echo $pending_parents > 0 ? 'text-warning' : 'text-success'; ?>">
                                        <?php echo $pending_parents; ?>
                                    </h4>
                                </div>
                                <div class="text-<?php echo $pending_parents > 0 ? 'warning' : 'success'; ?>">
                                    <i class="fas fa-user-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Recent Announcements -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Recent Announcement</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_announcements)): ?>
                                <div class="text-center text-muted py-4">
                                    <p>No Announcement</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_announcements as $announcement): ?>
                                    <div class="announcement-item">
                                        <h6><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                        <p><?php echo substr(htmlspecialchars($announcement['content']), 0, 100) . '...'; ?></p>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Today's Attendance Overview -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Today's Attendance Overview</h5>
                        </div>
                        <div class="card-body">
                            <div class="attendance-chart-container">
                                <canvas id="attendanceChart"></canvas>
                            </div>
                            <div class="attendance-legend mt-3">
                                <div class="legend-item">
                                    <span class="legend-color bg-success"></span>
                                    <span>Present</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color bg-danger"></span>
                                    <span>Absent</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Attendance Chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent'],
                datasets: [{
                    data: [<?php echo $attendance['present']; ?>, <?php echo $attendance['absent']; ?>],
                    backgroundColor: ['#28a745', '#dc3545'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Handle success messages
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success) {
            let message = '';
            switch (success) {
                case 'enrollment_toggled':
                    message = 'Enrollment status updated successfully!';
                    break;
            }
            if (message) {
                showNotification(message, 'success');
            }
        }
        
        // Confirm enrollment toggle
        document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
            const currentStatus = document.querySelector('input[name="current_status"]').value;
            const action = currentStatus === '1' ? 'disable' : 'enable';
            const confirmMessage = `Are you sure you want to ${action} parent enrollment? This will ${action === 'disable' ? 'prevent parents from enrolling new children' : 'allow parents to enroll new children'}.`;
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
