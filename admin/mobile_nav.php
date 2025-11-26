<?php
// admin/mobile_nav.php
require_once '../config/database.php';

// Get unread notifications count
$unread_count = 0;
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = $result ? $result['count'] : 0;
} catch (Exception $e) {
    // Log error or handle it
    error_log("Error fetching notification count: " . $e->getMessage());
}
?>
<nav class="mobile-nav admin-dashboard d-lg-none">
    <div class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
        <a href="dashboard.php" class="nav-link">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </div>
    <div class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'parents.php' ? 'active' : ''; ?>">
        <a href="parents.php" class="nav-link">
            <i class="fas fa-users"></i>
            <span>Parents</span>
        </a>
    </div>
    <div class="mobile-nav-item enrollment <?php echo basename($_SERVER['PHP_SELF']) === 'parent_verification.php' ? 'active' : ''; ?>">
        <a href="parent_verification.php" class="nav-link">
            <i class="fas fa-user-check"></i>
            <span>Verify</span>
        </a>
    </div>
    <div class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
        <a href="reports.php" class="nav-link">
            <i class="fas fa-file-alt"></i>
            <span>Reports</span>
        </a>
    </div>
    <!-- Notifications removed as per request -->
    
    <div class="mobile-nav-item dropdown">
        <a href="#" class="nav-link" id="adminMoreDropdown" aria-expanded="false">
            <i class="fas fa-ellipsis-h"></i>
            <span>More</span>
        </a>
        <div class="dropdown-menu" aria-labelledby="adminMoreDropdown">
            <h6 class="dropdown-header">Students</h6>
            <a class="dropdown-item" href="students.php"><i class="fas fa-child me-2"></i>Active Students</a>
            <a class="dropdown-item" href="students_archive.php"><i class="fas fa-box-archive me-2"></i>Archived Students</a>
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Daily Operations</h6>
            <a class="dropdown-item" href="attendance.php"><i class="fas fa-clipboard-check me-2"></i>Attendance</a>
            <a class="dropdown-item" href="announcements.php"><i class="fas fa-bullhorn me-2"></i>Announcements</a>
            <a class="dropdown-item" href="communication.php"><i class="fas fa-comments me-2"></i>Communication</a>
            <a class="dropdown-item" href="schedules.php"><i class="fas fa-calendar-alt me-2"></i>Schedules</a>
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Growth</h6>
            <a class="dropdown-item" href="progress.php"><i class="fas fa-chart-line me-2"></i>Progress Reports</a>
            <a class="dropdown-item" href="learning_activities.php"><i class="fas fa-puzzle-piece me-2"></i>Learning Activities</a>
            <a class="dropdown-item ps-4" href="activity_submissions.php"><i class="fas fa-tasks me-2"></i>Activity Submissions</a>
            <a class="dropdown-item" href="events.php"><i class="fas fa-calendar-star me-2"></i>Events</a>
            <a class="dropdown-item" href="faqs.php"><i class="fas fa-question-circle me-2"></i>FAQs</a>
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Account</h6>
            <a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a>
            <a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </div>
    </div>
</nav>
