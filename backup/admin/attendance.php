<?php
session_start();
require_once '../config/database.php';

// Handle AJAX request for loading students
if (isset($_GET['get_students'])) {
    try {
        $conn = getDBConnection();
        $students = [];
        
        if (isset($_GET['schedule_id']) && $_GET['schedule_id']) {
            // Get students for specific schedule
            $stmt = $conn->prepare("
                SELECT s.id, s.first_name, s.last_name 
                FROM students s 
                JOIN student_schedules ss ON s.id = ss.student_id 
                WHERE s.status = 'active' AND ss.schedule_id = ? AND ss.is_active = 1
                ORDER BY s.first_name, s.last_name
            ");
            $stmt->execute([$_GET['schedule_id']]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Get all active students
            $stmt = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE status = 'active' ORDER BY first_name, last_name");
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        header('Content-Type: application/json');
        echo json_encode($students);
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit();
    }
}

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
            case 'mark_attendance':
                $student_id = $_POST['student_id'];
                $date = $_POST['date'];
                $status = $_POST['status'];
                $check_in_time = $_POST['check_in_time'] ?? null;
                $check_out_time = $_POST['check_out_time'] ?? null;
                $notes = $_POST['notes'] ?? '';
                
                // Check if attendance already exists for this student and date
                $stmt = $conn->prepare("SELECT id FROM attendance WHERE student_id = ? AND date = ?");
                $stmt->execute([$student_id, $date]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Update existing record
                    $stmt = $conn->prepare("UPDATE attendance SET status = ?, check_in_time = ?, check_out_time = ?, notes = ?, recorded_by = ? WHERE student_id = ? AND date = ?");
                    $stmt->execute([$status, $check_in_time, $check_out_time, $notes, $_SESSION['user_id'], $student_id, $date]);
                } else {
                    // Insert new record
                    $stmt = $conn->prepare("INSERT INTO attendance (student_id, date, status, check_in_time, check_out_time, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$student_id, $date, $status, $check_in_time, $check_out_time, $notes, $_SESSION['user_id']]);
                }
                
                // Send notification to parent
                $stmt = $conn->prepare("SELECT parent_id, first_name, last_name FROM students WHERE id = ?");
                $stmt->execute([$student_id]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($student) {
                    $notification_title = "Attendance Update: " . $student['first_name'] . " " . $student['last_name'];
                    $notification_message = "";
                    
                    if ($status === 'present' && $check_in_time) {
                        $notification_message = "Your child has arrived at " . date('g:i A', strtotime($check_in_time));
                    } elseif ($check_out_time) {
                        $notification_message = "Your child has left the daycare at " . date('g:i A', strtotime($check_out_time));
                    } else {
                        $notification_message = "Attendance marked as " . ucfirst($status) . " for " . date('M d, Y', strtotime($date));
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')");
                    $stmt->execute([$student['parent_id'], $notification_title, $notification_message]);
                }
                
                header('Location: attendance.php?success=attendance_marked');
                exit();
                break;
                
            case 'quick_checkin':
                $student_id = $_POST['student_id'];
                $current_time = date('H:i:s');
                $current_date = date('Y-m-d');
                
                // Check if already checked in today
                $stmt = $conn->prepare("SELECT id, check_out_time FROM attendance WHERE student_id = ? AND date = ?");
                $stmt->execute([$student_id, $current_date]);
                $existing = $stmt->fetch();
                
                if ($existing && !$existing['check_out_time']) {
                    // Check out
                    $stmt = $conn->prepare("UPDATE attendance SET check_out_time = ? WHERE id = ?");
                    $stmt->execute([$current_time, $existing['id']]);
                    $action_type = 'checkout';
                } else {
                    // Check in (new or after checkout)
                    if ($existing) {
                        $stmt = $conn->prepare("UPDATE attendance SET check_in_time = ?, check_out_time = NULL, status = 'present' WHERE id = ?");
                        $stmt->execute([$current_time, $existing['id']]);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO attendance (student_id, date, status, check_in_time, recorded_by) VALUES (?, ?, 'present', ?, ?)");
                        $stmt->execute([$student_id, $current_date, $current_time, $_SESSION['user_id']]);
                    }
                    $action_type = 'checkin';
                }
                
                // Send notification to parent
                $stmt = $conn->prepare("SELECT parent_id, first_name, last_name FROM students WHERE id = ?");
                $stmt->execute([$student_id]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($student) {
                    $notification_title = ($action_type === 'checkin') ? "Child Arrived" : "Child Departed";
                    $notification_message = $student['first_name'] . " " . $student['last_name'] . " " . 
                                          (($action_type === 'checkin') ? "arrived at" : "left at") . " " . 
                                          date('g:i A', strtotime($current_time));
                    
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')");
                    $stmt->execute([$student['parent_id'], $notification_title, $notification_message]);
                }
                
                header('Location: attendance.php?success=' . $action_type);
                exit();
                break;
        }
    }
}

// Get attendance data
try {
    $conn = getDBConnection();
    
    // Get today's attendance
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT a.*, s.first_name, s.last_name, s.id as student_id,
               sch.session_name, sch.start_time, sch.end_time
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
        LEFT JOIN schedules sch ON s.schedule_id = sch.id
        WHERE s.status = 'active'
        ORDER BY s.first_name, s.last_name
    ");
    $stmt->execute([$today]);
    $today_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent attendance (last 7 days)
    $stmt = $conn->prepare("
        SELECT a.*, s.first_name, s.last_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY a.date DESC, a.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_today,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_today,
            COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_today,
            COUNT(s.id) as total_students
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
        WHERE s.status = 'active'
    ");
    $stmt->execute([$today]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get schedules for dropdown
    $stmt = $conn->prepare("SELECT id, schedule_name, start_time, end_time FROM schedules WHERE is_active = 1 ORDER BY start_time");
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get selected schedule from POST or GET
    $selected_schedule_id = isset($_POST['schedule_id']) ? $_POST['schedule_id'] : (isset($_GET['schedule_id']) ? $_GET['schedule_id'] : null);
    
    // Get students for dropdown based on selected schedule
    $students = [];
    if ($selected_schedule_id) {
        $stmt = $conn->prepare("
            SELECT s.id, s.first_name, s.last_name 
            FROM students s 
            JOIN student_schedules ss ON s.id = ss.student_id 
            WHERE s.status = 'active' AND ss.schedule_id = ? AND ss.is_active = 1
            ORDER BY s.first_name, s.last_name
        ");
        $stmt->execute([$selected_schedule_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // If no schedule selected, get all active students
        $stmt = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE status = 'active' ORDER BY first_name, last_name");
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $stats = ['present_today' => 0, 'absent_today' => 0, 'late_today' => 0, 'total_students' => 0];
    $today_attendance = [];
    $recent_attendance = [];
    $students = [];
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
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .attendance-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .attendance-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .status-present { border-left: 4px solid #28a745; }
        .status-absent { border-left: 4px solid #dc3545; }
        .status-late { border-left: 4px solid #ffc107; }
        .status-not-marked { border-left: 4px solid #6c757d; }
        
        .quick-action-btn {
            padding: 5px 10px;
            font-size: 0.8rem;
            border-radius: 15px;
            margin: 2px;
        }
        .checkin-btn { background: #28a745; border: none; color: white; }
        .checkout-btn { background: #dc3545; border: none; color: white; }
        .time-display {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h1><i class="fas fa-calendar-check me-3"></i>Attendance Management</h1>
                    <div>
                        <span class="badge bg-primary me-2">Today: <?php echo date('M d, Y'); ?></span>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#markAttendanceModal">
                            <i class="fas fa-plus me-2"></i>Mark Attendance
                        </button>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php
                        switch ($_GET['success']) {
                            case 'attendance_marked':
                                echo 'Attendance marked successfully! Parent has been notified.';
                                break;
                            case 'checkin':
                                echo 'Student checked in successfully! Parent has been notified.';
                                break;
                            case 'checkout':
                                echo 'Student checked out successfully! Parent has been notified.';
                                break;
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="display-6 text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3 class="mt-3"><?php echo $stats['present_today']; ?></h3>
                                <p class="text-muted">Present Today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="display-6 text-danger">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <h3 class="mt-3"><?php echo $stats['absent_today']; ?></h3>
                                <p class="text-muted">Absent Today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="display-6 text-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3 class="mt-3"><?php echo $stats['late_today']; ?></h3>
                                <p class="text-muted">Late Today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="display-6 text-primary">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3 class="mt-3"><?php echo $stats['total_students']; ?></h3>
                                <p class="text-muted">Total Students</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's Attendance -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Today's Attendance - <?php echo date('F d, Y'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($today_attendance as $record): ?>
                                <?php
                                $status_class = 'status-not-marked';
                                $status_text = 'Not Marked';
                                $status_icon = 'fas fa-question-circle text-muted';
                                
                                if ($record['status']) {
                                    switch ($record['status']) {
                                        case 'present':
                                            $status_class = 'status-present';
                                            $status_text = 'Present';
                                            $status_icon = 'fas fa-check-circle text-success';
                                            break;
                                        case 'absent':
                                            $status_class = 'status-absent';
                                            $status_text = 'Absent';
                                            $status_icon = 'fas fa-times-circle text-danger';
                                            break;
                                        case 'late':
                                            $status_class = 'status-late';
                                            $status_text = 'Late';
                                            $status_icon = 'fas fa-clock text-warning';
                                            break;
                                    }
                                }
                                ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="attendance-card <?php echo $status_class; ?>">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="card-title mb-1">
                                                        <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo $record['session_name'] ? htmlspecialchars($record['session_name']) : 'No Schedule'; ?>
                                                    </small>
                                                </div>
                                                <i class="<?php echo $status_icon; ?>"></i>
                                            </div>
                                            
                                            <div class="mt-2">
                                                <small class="badge bg-light text-dark"><?php echo $status_text; ?></small>
                                            </div>
                                            
                                            <?php if ($record['check_in_time']): ?>
                                                <div class="mt-2">
                                                    <small class="text-success">
                                                        <i class="fas fa-sign-in-alt me-1"></i>
                                                        In: <span class="time-display"><?php echo date('g:i A', strtotime($record['check_in_time'])); ?></span>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($record['check_out_time']): ?>
                                                <div class="mt-1">
                                                    <small class="text-danger">
                                                        <i class="fas fa-sign-out-alt me-1"></i>
                                                        Out: <span class="time-display"><?php echo date('g:i A', strtotime($record['check_out_time'])); ?></span>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-3">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="quick_checkin">
                                                    <input type="hidden" name="student_id" value="<?php echo $record['student_id']; ?>">
                                                    <?php if (!$record['check_in_time'] || $record['check_out_time']): ?>
                                                        <button type="submit" class="btn quick-action-btn checkin-btn">
                                                            <i class="fas fa-sign-in-alt"></i> Check In
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($record['check_in_time'] && !$record['check_out_time']): ?>
                                                        <button type="submit" class="btn quick-action-btn checkout-btn">
                                                            <i class="fas fa-sign-out-alt"></i> Check Out
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                                <button class="btn btn-sm btn-outline-primary" onclick="editAttendance(<?php echo $record['student_id']; ?>, '<?php echo $record['first_name'] . ' ' . $record['last_name']; ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Attendance -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Attendance (Last 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Student</th>
                                        <th>Status</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_attendance as $record): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                            <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
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
                                            <td class="time-display">
                                                <?php echo $record['check_in_time'] ? date('g:i A', strtotime($record['check_in_time'])) : '-'; ?>
                                            </td>
                                            <td class="time-display">
                                                <?php echo $record['check_out_time'] ? date('g:i A', strtotime($record['check_out_time'])) : '-'; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mark Attendance Modal -->
    <div class="modal fade" id="markAttendanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mark Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="mark_attendance">
                        
                        <div class="mb-3">
                            <label for="schedule_id" class="form-label">Schedule/Section</label>
                            <select class="form-select" id="schedule_id" name="schedule_id" onchange="loadStudents()">
                                <option value="">All Students</option>
                                <?php foreach ($schedules as $schedule): ?>
                                    <option value="<?php echo $schedule['id']; ?>" <?php echo $selected_schedule_id == $schedule['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($schedule['schedule_name']); ?> (<?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

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

                        <div class="mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">Select Status</option>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="late">Late</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="check_in_time" class="form-label">Check In Time</label>
                                    <input type="time" class="form-control" id="check_in_time" name="check_in_time">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="check_out_time" class="form-label">Check Out Time</label>
                                    <input type="time" class="form-control" id="check_out_time" name="check_out_time">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Optional notes about attendance..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Mark Attendance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadStudents() {
            const scheduleId = document.getElementById('schedule_id').value;
            const studentSelect = document.getElementById('student_id');
            
            // Clear current options
            studentSelect.innerHTML = '<option value="">Select Student</option>';
            
            if (scheduleId) {
                // Load students for selected schedule via AJAX
                fetch(`attendance.php?get_students=1&schedule_id=${scheduleId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(student => {
                            const option = document.createElement('option');
                            option.value = student.id;
                            option.textContent = student.first_name + ' ' + student.last_name;
                            studentSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error loading students:', error));
            } else {
                // Load all students
                fetch('attendance.php?get_students=1')
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(student => {
                            const option = document.createElement('option');
                            option.value = student.id;
                            option.textContent = student.first_name + ' ' + student.last_name;
                            studentSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error loading students:', error));
            }
        }

        function editAttendance(studentId, studentName) {
            // Pre-fill the modal with student information
            document.getElementById('student_id').value = studentId;
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('markAttendanceModal'));
            modal.show();
        }

        // Auto-set current time when status is selected
        document.getElementById('status').addEventListener('change', function() {
            const currentTime = new Date().toTimeString().slice(0, 5);
            
            if (this.value === 'present' || this.value === 'late') {
                document.getElementById('check_in_time').value = currentTime;
            }
        });
    </script>
</body>
</html>
