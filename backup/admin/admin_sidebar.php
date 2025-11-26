<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div class="brand-text">
            <h4>Gumamela Daycare</h4>
            <span>Admin Portal</span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="students.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>">
            <i class="fas fa-child"></i>
            <span>Students</span>
        </a>
        <a href="parents.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'parents.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Parents</span>
        </a>
        <a href="parent_verification.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'parent_verification.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-check"></i>
            <span>Parent Verification</span>
        </a>
        <a href="schedules.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'schedules.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Schedules</span>
        </a>
        <a href="announcements.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>">
            <i class="fas fa-bullhorn"></i>
            <span>Announcements</span>
        </a>
        <a href="attendance.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-check"></i>
            <span>Attendance</span>
        </a>
        <a href="progress.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'progress.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Progress</span>
        </a>
        <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <span>Reports</span>
        </a>
        <a href="communication.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'communication.php' ? 'active' : ''; ?>">
            <i class="fas fa-comments"></i>
            <span>Communication</span>
        </a>
        <a href="learning_activities.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'learning_activities.php' ? 'active' : ''; ?>">
            <i class="fas fa-puzzle-piece"></i>
            <span>Learning Activities</span>
        </a>
        <a href="events.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-star"></i>
            <span>Events</span>
        </a>
        <a href="faqs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'faqs.php' ? 'active' : ''; ?>">
            <i class="fas fa-question-circle"></i>
            <span>FAQs</span>
        </a>
        <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="../auth/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Log out</span>
        </a>
    </div>
</div>
<div class="sidebar-overlay"></div>
