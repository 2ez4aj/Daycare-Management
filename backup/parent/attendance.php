<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is parent
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'parent') {
    header('Location: ../index.php');
    exit();
}

// Get parent's children and their attendance
try {
    $conn = getDBConnection();
    
    // Get parent's children
    $stmt = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE parent_id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance records for parent's children
    $attendance_records = [];
    if (!empty($children)) {
        $child_ids = array_column($children, 'id');
        $placeholders = str_repeat('?,', count($child_ids) - 1) . '?';
        
        // Get recent attendance (last 30 days)
        $stmt = $conn->prepare("
            SELECT a.*, s.first_name, s.last_name,
                   sch.schedule_name as session_name, sch.start_time, sch.end_time
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            LEFT JOIN student_schedules ss ON s.id = ss.student_id AND ss.is_active = 1
            LEFT JOIN schedules sch ON ss.schedule_id = sch.id
            WHERE a.student_id IN ($placeholders) 
            AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY a.date DESC, s.first_name
        ");
        $stmt->execute($child_ids);
        $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get today's attendance
        $stmt = $conn->prepare("
            SELECT a.*, s.first_name, s.last_name, s.id as student_id,
                   sch.schedule_name as session_name, sch.start_time, sch.end_time
            FROM students s
            LEFT JOIN student_schedules ss ON s.id = ss.student_id AND ss.is_active = 1
            LEFT JOIN attendance a ON s.id = a.student_id AND a.date = CURDATE()
            LEFT JOIN schedules sch ON ss.schedule_id = sch.id
            WHERE s.parent_id = ? AND s.status = 'active'
            ORDER BY s.first_name
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $today_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Attendance - Gumamela Daycare Center</title>
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
                <h1><i class="fas fa-calendar-check me-3"></i>Attendance Tracking</h1>
            </div>

            <div class="content-body">
                <?php if (empty($children)): ?>
                    <div class="text-center py-5">
                        <div class="display-1 text-muted mb-3">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h4>No enrolled children</h4>
                        <p class="text-muted">You don't have any children enrolled yet. Please enroll your child to track attendance.</p>
                        <a href="enroll.php" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>Enroll Child
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Live Status Today -->
                    <div class="live-status">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-2">
                                    <i class="fas fa-broadcast-tower me-2"></i>
                                    Live Status - <?php echo date('F d, Y'); ?>
                                </h4>
                                <p class="mb-0">Real-time attendance tracking for your children</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="h5 mb-0">
                                    <i class="fas fa-clock me-2"></i>
                                    <span id="current-time"><?php echo date('g:i:s A'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Today's Status Cards -->
                    <div class="row mb-4">
                        <?php foreach ($today_attendance as $record): ?>
                            <?php
                            $status_class = 'status-not-marked';
                            $status_text = 'Not Marked';
                            $status_icon = 'fas fa-question-circle text-muted';
                            $indicator_class = 'indicator-not-marked';
                            
                            if ($record['status']) {
                                switch ($record['status']) {
                                    case 'present':
                                        $status_class = 'status-present';
                                        $status_text = 'Present';
                                        $status_icon = 'fas fa-check-circle text-success';
                                        $indicator_class = 'indicator-present';
                                        break;
                                    case 'absent':
                                        $status_class = 'status-absent';
                                        $status_text = 'Absent';
                                        $status_icon = 'fas fa-times-circle text-danger';
                                        $indicator_class = 'indicator-absent';
                                        break;
                                    case 'late':
                                        $status_class = 'status-late';
                                        $status_text = 'Late';
                                        $status_icon = 'fas fa-clock text-warning';
                                        $indicator_class = 'indicator-late';
                                        break;
                                }
                            }
                            ?>
                            <div class="col-md-6 mb-4">
                                <div class="attendance-card <?php echo $status_class; ?>">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    <span class="status-indicator <?php echo $indicator_class; ?>"></span>
                                                    <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                                </h5>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar-alt me-1"></i>
                                                    <?php echo $record['session_name'] ? htmlspecialchars($record['session_name']) : 'No Schedule Assigned'; ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <i class="<?php echo $status_icon; ?> fa-2x"></i>
                                                <div class="mt-1">
                                                    <span class="badge bg-light text-dark"><?php echo $status_text; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($record['session_name']): ?>
                                            <div class="attendance-summary">
                                                <small class="text-muted d-block mb-1">Scheduled Time:</small>
                                                <div class="d-flex justify-content-between">
                                                    <span class="small">
                                                        <i class="fas fa-play text-success me-1"></i>
                                                        <?php echo date('g:i A', strtotime($record['start_time'])); ?>
                                                    </span>
                                                    <span class="small">
                                                        <i class="fas fa-stop text-danger me-1"></i>
                                                        <?php echo date('g:i A', strtotime($record['end_time'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="row mt-3">
                                            <?php if ($record['check_in_time']): ?>
                                                <div class="col-6">
                                                    <div class="text-center p-3 bg-light rounded">
                                                        <div class="time-display time-in">
                                                            <?php echo date('g:i A', strtotime($record['check_in_time'])); ?>
                                                        </div>
                                                        <small class="text-muted">
                                                            <i class="fas fa-sign-in-alt me-1"></i>Arrived
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="col-6">
                                                    <div class="text-center p-3 bg-light rounded">
                                                        <div class="time-display text-muted">--:--</div>
                                                        <small class="text-muted">
                                                            <i class="fas fa-sign-in-alt me-1"></i>Not Arrived
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($record['check_out_time']): ?>
                                                <div class="col-6">
                                                    <div class="text-center p-3 bg-light rounded">
                                                        <div class="time-display time-out">
                                                            <?php echo date('g:i A', strtotime($record['check_out_time'])); ?>
                                                        </div>
                                                        <small class="text-muted">
                                                            <i class="fas fa-sign-out-alt me-1"></i>Departed
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="col-6">
                                                    <div class="text-center p-3 bg-light rounded">
                                                        <div class="time-display text-muted">--:--</div>
                                                        <small class="text-muted">
                                                            <i class="fas fa-sign-out-alt me-1"></i>
                                                            <?php echo $record['check_in_time'] ? 'Still Here' : 'Not Departed'; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($record['notes']): ?>
                                            <div class="mt-3 p-2 bg-info bg-opacity-10 rounded">
                                                <small class="text-info">
                                                    <i class="fas fa-sticky-note me-1"></i>
                                                    <strong>Note:</strong> <?php echo htmlspecialchars($record['notes']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Attendance History -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Attendance History (Last 30 Days)
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($attendance_records)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h5>No attendance records yet</h5>
                                    <p class="text-muted">Attendance records will appear here once teachers start marking attendance.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Child</th>
                                                <th>Schedule</th>
                                                <th>Status</th>
                                                <th>Check In</th>
                                                <th>Check Out</th>
                                                <th>Duration</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendance_records as $record): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo date('M d', strtotime($record['date'])); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo date('D', strtotime($record['date'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="status-indicator indicator-<?php echo $record['status']; ?>"></span>
                                                        <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($record['session_name']): ?>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($record['session_name']); ?>
                                                                <br>
                                                                <?php echo date('g:i A', strtotime($record['start_time'])); ?> - <?php echo date('g:i A', strtotime($record['end_time'])); ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <small class="text-muted">No Schedule</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $badge_class = 'bg-secondary';
                                                        switch ($record['status']) {
                                                            case 'present': $badge_class = 'bg-success'; break;
                                                            case 'absent': $badge_class = 'bg-danger'; break;
                                                            case 'late': $badge_class = 'bg-warning text-dark'; break;
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?>">
                                                            <?php echo ucfirst($record['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="time-display time-in">
                                                        <?php echo $record['check_in_time'] ? date('g:i A', strtotime($record['check_in_time'])) : '-'; ?>
                                                    </td>
                                                    <td class="time-display time-out">
                                                        <?php echo $record['check_out_time'] ? date('g:i A', strtotime($record['check_out_time'])) : '-'; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if ($record['check_in_time'] && $record['check_out_time']) {
                                                            $checkin = new DateTime($record['check_in_time']);
                                                            $checkout = new DateTime($record['check_out_time']);
                                                            $duration = $checkin->diff($checkout);
                                                            echo $duration->format('%h:%I');
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($record['notes']): ?>
                                                            <i class="fas fa-sticky-note text-info" title="<?php echo htmlspecialchars($record['notes']); ?>"></i>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update current time every second
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
            document.getElementById('current-time').textContent = timeString;
        }
        
        // Update time immediately and then every second
        updateTime();
        setInterval(updateTime, 1000);
        
        // Auto-refresh page every 5 minutes to get latest attendance updates
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes
    </script>
</body>
</html>
