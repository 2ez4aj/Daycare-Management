<div class="sidebar">
    <div class="sidebar-header">
        <?php
        $logo_path = $_SERVER['DOCUMENT_ROOT'] . '/NewDaycare/assets/images/Gemini_Generated_Image_33wvz333wvz333wv.png';
        $logo_url = '/NewDaycare/assets/images/Gemini_Generated_Image_33wvz333wvz333wv.png';
        $logo_exists = file_exists($logo_path);
        ?>
        <div class="logo" style="width: 60px; height: 60px; border-radius: 50%; background: #4CAF50; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; overflow: hidden; border: 3px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
            <?php if ($logo_exists): ?>
                <img src="<?php echo $logo_url; ?>" alt="Gumamela Daycare Center Logo" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;">
            <?php else: ?>
                <i class="fas fa-child" style="font-size: 30px; color: white;"></i>
            <?php endif; ?>
        </div>
        <div class="brand-text">
            <h4>Gumamela Daycare</h4>
            <span>Admin Portal</span>
        </div>
        <button class="sidebar-close d-lg-none" id="sidebarClose" aria-label="Close navigation">
            <i class="fas fa-times"></i>
        </button>
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
        <a href="students_archive.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'students_archive.php' ? 'active' : ''; ?>">
            <i class="fas fa-box-archive"></i>
            <span>Archived Students</span>
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
        <a href="activity_submissions.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'activity_submissions.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-upload"></i>
            <span>Activity Submissions</span>
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
<link href="../assets/css/mobile_nav.css" rel="stylesheet">
