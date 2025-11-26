<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div class="brand-text">
            <h4>Gumamela Daycare</h4>
            <span>Parent Portal</span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="announcements.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>">
            <i class="fas fa-bullhorn"></i>
            <span>Announcements</span>
        </a>
        <a href="messages.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i>
            <span>Message</span>
        </a>
        <a href="enroll.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'enroll.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-plus"></i>
            <span>Enroll Child</span>
        </a>
        <a href="attendance.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span>Attendance</span>
        </a>
        <a href="learning_activities.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'learning_activities.php' ? 'active' : ''; ?>">
            <i class="fas fa-graduation-cap"></i>
            <span>Learning Activities</span>
        </a>
        <a href="progress.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'progress.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Progress Reports</span>
        </a>
        <a href="events.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Events</span>
        </a>
        <a href="faqs.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'faqs.php' ? 'active' : ''; ?>">
            <i class="fas fa-question-circle"></i>
            <span>FAQ</span>
        </a>
        <a href="notifications.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i>
            <span>Notification</span>
            <?php if (isset($unread_notifications) && $unread_notifications > 0): ?>
                <span class="notification-badge"><?php echo $unread_notifications; ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-cog"></i>
            <span>My Profile</span>
        </a>
        <a href="settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
                <span class="user-role">Parent</span>
            </div>
        </div>
        <a href="../auth/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Log out</span>
        </a>
    </div>
</div>
<div class="sidebar-overlay"></div>
