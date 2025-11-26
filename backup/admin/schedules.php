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
            case 'add_schedule':
                $stmt = $conn->prepare("INSERT INTO schedules (schedule_name, start_time, end_time, max_capacity) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['schedule_name'],
                    $_POST['start_time'],
                    $_POST['end_time'],
                    $_POST['max_capacity']
                ]);
                header('Location: schedules.php?success=schedule_added');
                exit();
                break;
                
            case 'edit_schedule':
                $stmt = $conn->prepare("UPDATE schedules SET schedule_name = ?, start_time = ?, end_time = ?, max_capacity = ?, is_active = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['schedule_name'],
                    $_POST['start_time'],
                    $_POST['end_time'],
                    $_POST['max_capacity'],
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['schedule_id']
                ]);
                header('Location: schedules.php?success=schedule_updated');
                exit();
                break;
                
            case 'assign_schedule':
                // First, deactivate any existing schedule assignment
                $stmt = $conn->prepare("UPDATE student_schedules SET is_active = 0 WHERE student_id = ? AND is_active = 1");
                $stmt->execute([$_POST['student_id']]);
                
                // Update student table
                $stmt = $conn->prepare("UPDATE students SET schedule_id = ? WHERE id = ?");
                $stmt->execute([$_POST['schedule_id'], $_POST['student_id']]);
                
                // Add new schedule assignment record
                $stmt = $conn->prepare("INSERT INTO student_schedules (student_id, schedule_id, assigned_by, notes) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['student_id'],
                    $_POST['schedule_id'],
                    $_SESSION['user_id'],
                    $_POST['notes'] ?? ''
                ]);
                
                header('Location: schedules.php?success=schedule_assigned');
                exit();
                break;
                
            case 'delete_schedule':
                $stmt = $conn->prepare("UPDATE schedules SET is_active = 0 WHERE id = ?");
                $stmt->execute([$_POST['schedule_id']]);
                header('Location: schedules.php?success=schedule_deleted');
                exit();
                break;
        }
    }
}

// Get schedules data
try {
    $conn = getDBConnection();
    
    // Get all schedules with student counts
    $stmt = $conn->prepare("
        SELECT s.*, 
               COUNT(st.id) as enrolled_count,
               CONCAT(TIME_FORMAT(s.start_time, '%h:%i %p'), ' - ', TIME_FORMAT(s.end_time, '%h:%i %p')) as time_display
        FROM schedules s 
        LEFT JOIN students st ON s.id = st.schedule_id AND st.status = 'active'
        WHERE s.is_active = 1
        GROUP BY s.id 
        ORDER BY s.start_time
    ");
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get students without schedules
    $stmt = $conn->prepare("
        SELECT s.id, s.first_name, s.last_name, u.first_name as parent_first_name, u.last_name as parent_last_name
        FROM students s 
        JOIN users u ON s.parent_id = u.id 
        WHERE s.status = 'active' AND (s.schedule_id IS NULL OR s.schedule_id = 0)
        ORDER BY s.first_name, s.last_name
    ");
    $stmt->execute();
    $unscheduled_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all active students for schedule assignment
    $stmt = $conn->prepare("
        SELECT s.id, s.first_name, s.last_name, s.schedule_id,
               u.first_name as parent_first_name, u.last_name as parent_last_name,
               sch.schedule_name, sch.start_time, sch.end_time
        FROM students s 
        JOIN users u ON s.parent_id = u.id 
        LEFT JOIN schedules sch ON s.schedule_id = sch.id
        WHERE s.status = 'active'
        ORDER BY s.first_name, s.last_name
    ");
    $stmt->execute();
    $all_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $schedules = [];
    $unscheduled_students = [];
    $all_students = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header d-flex justify-content-between align-items-center">
                <h1>Schedule Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                    <i class="fas fa-plus"></i> Add New Schedule
                </button>
            </div>
            
            <!-- Unscheduled Students Alert -->
            <?php if (!empty($unscheduled_students)): ?>
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Students Without Schedules (<?php echo count($unscheduled_students); ?>)</h5>
                <p class="mb-2">The following students need to be assigned to a schedule:</p>
                <div class="row">
                    <?php foreach ($unscheduled_students as $student): ?>
                        <div class="col-md-6 mb-2">
                            <span class="badge bg-warning text-dark me-2">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                            </span>
                            <button class="btn btn-sm btn-outline-primary" onclick="assignSchedule(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')">
                                <i class="fas fa-clock"></i> Assign Schedule
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Schedule Overview -->
            <div class="row mb-4">
                <?php foreach ($schedules as $schedule): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card schedule-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo htmlspecialchars($schedule['schedule_name']); ?></h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="editSchedule(<?php echo htmlspecialchars(json_encode($schedule)); ?>)">
                                            <i class="fas fa-edit"></i> Edit Schedule
                                        </a></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteSchedule(<?php echo $schedule['id']; ?>, '<?php echo htmlspecialchars($schedule['schedule_name']); ?>')">
                                            <i class="fas fa-trash"></i> Delete Schedule
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="schedule-time mb-3">
                                    <i class="fas fa-clock text-primary me-2"></i>
                                    <strong><?php echo $schedule['time_display']; ?></strong>
                                </div>
                                <div class="schedule-capacity mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Capacity:</span>
                                        <span class="badge bg-<?php echo $schedule['enrolled_count'] >= $schedule['max_capacity'] ? 'danger' : 'success'; ?>">
                                            <?php echo $schedule['enrolled_count']; ?> / <?php echo $schedule['max_capacity']; ?>
                                        </span>
                                    </div>
                                    <div class="progress mt-2">
                                        <div class="progress-bar <?php echo $schedule['enrolled_count'] >= $schedule['max_capacity'] ? 'bg-danger' : 'bg-success'; ?>" 
                                             style="width: <?php echo ($schedule['enrolled_count'] / $schedule['max_capacity']) * 100; ?>%"></div>
                                    </div>
                                </div>
                                <button class="btn btn-outline-primary btn-sm w-100" onclick="viewScheduleStudents(<?php echo $schedule['id']; ?>, '<?php echo htmlspecialchars($schedule['schedule_name']); ?>')">
                                    <i class="fas fa-users"></i> View Students (<?php echo $schedule['enrolled_count']; ?>)
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- All Students Schedule Assignment -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-users me-2"></i>Student Schedule Assignments</h5>
                    <small class="text-muted">Manage individual student schedule assignments</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Parent</th>
                                    <th>Current Schedule</th>
                                    <th>Time Slot</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_students)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i class="fas fa-user-graduate fa-3x mb-3 opacity-25"></i>
                                            <p>No active students found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($all_students as $student): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name']); ?>
                                            </td>
                                            <td>
                                                <?php if ($student['schedule_name']): ?>
                                                    <span class="badge bg-success"><?php echo htmlspecialchars($student['schedule_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($student['start_time'] && $student['end_time']): ?>
                                                    <small class="text-muted">
                                                        <?php echo date('g:i A', strtotime($student['start_time'])); ?> - 
                                                        <?php echo date('g:i A', strtotime($student['end_time'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-muted">-</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="assignSchedule(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')">
                                                    <i class="fas fa-clock"></i> <?php echo $student['schedule_name'] ? 'Change' : 'Assign'; ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_schedule">
                        <div class="mb-3">
                            <label class="form-label">Schedule Name</label>
                            <input type="text" class="form-control" name="schedule_name" required placeholder="e.g., Evening Session">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control" name="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" name="end_time" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Maximum Capacity</label>
                            <input type="number" class="form-control" name="max_capacity" value="20" min="1" max="50" required>
                            <small class="form-text text-muted">Maximum number of students for this schedule</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editScheduleForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_schedule">
                        <input type="hidden" name="schedule_id" id="edit_schedule_id">
                        <div class="mb-3">
                            <label class="form-label">Schedule Name</label>
                            <input type="text" class="form-control" name="schedule_name" id="edit_schedule_name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control" name="start_time" id="edit_start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" name="end_time" id="edit_end_time" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Maximum Capacity</label>
                            <input type="number" class="form-control" name="max_capacity" id="edit_max_capacity" min="1" max="50" required>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" checked>
                            <label class="form-check-label" for="edit_is_active">
                                Active Schedule
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Assign Schedule Modal -->
    <div class="modal fade" id="assignScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="assignScheduleForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_schedule">
                        <input type="hidden" name="student_id" id="assign_student_id">
                        <div class="mb-3">
                            <label class="form-label">Student</label>
                            <input type="text" class="form-control" id="assign_student_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Schedule</label>
                            <select class="form-control" name="schedule_id" required>
                                <option value="">Choose a schedule...</option>
                                <?php foreach ($schedules as $schedule): ?>
                                    <option value="<?php echo $schedule['id']; ?>" 
                                            <?php echo $schedule['enrolled_count'] >= $schedule['max_capacity'] ? 'disabled' : ''; ?>>
                                        <?php echo htmlspecialchars($schedule['schedule_name']); ?> 
                                        (<?php echo $schedule['time_display']; ?>) 
                                        - <?php echo $schedule['enrolled_count']; ?>/<?php echo $schedule['max_capacity']; ?> students
                                        <?php echo $schedule['enrolled_count'] >= $schedule['max_capacity'] ? ' (FULL)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Any special notes about this schedule assignment..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    
    <script>
        function editSchedule(schedule) {
            document.getElementById('edit_schedule_id').value = schedule.id;
            document.getElementById('edit_schedule_name').value = schedule.schedule_name;
            document.getElementById('edit_start_time').value = schedule.start_time;
            document.getElementById('edit_end_time').value = schedule.end_time;
            document.getElementById('edit_max_capacity').value = schedule.max_capacity;
            document.getElementById('edit_is_active').checked = schedule.is_active == 1;
            
            new bootstrap.Modal(document.getElementById('editScheduleModal')).show();
        }
        
        function assignSchedule(studentId, studentName) {
            document.getElementById('assign_student_id').value = studentId;
            document.getElementById('assign_student_name').value = studentName;
            
            new bootstrap.Modal(document.getElementById('assignScheduleModal')).show();
        }
        
        function deleteSchedule(scheduleId, scheduleName) {
            if (confirm(`Are you sure you want to delete the schedule "${scheduleName}"? Students assigned to this schedule will need to be reassigned.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_schedule">
                    <input type="hidden" name="schedule_id" value="${scheduleId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function viewScheduleStudents(scheduleId, scheduleName) {
            // This could open a modal or redirect to a detailed view
            // For now, we'll show an alert - you can enhance this later
            alert(`Viewing students for ${scheduleName}. This feature can be enhanced to show detailed student list.`);
        }
        
        // Handle success messages
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success) {
            let message = '';
            switch (success) {
                case 'schedule_added':
                    message = 'Schedule added successfully!';
                    break;
                case 'schedule_updated':
                    message = 'Schedule updated successfully!';
                    break;
                case 'schedule_assigned':
                    message = 'Schedule assigned to student successfully!';
                    break;
                case 'schedule_deleted':
                    message = 'Schedule deleted successfully!';
                    break;
            }
            if (message) {
                showNotification(message, 'success');
            }
        }
    </script>
    
    <style>
        .schedule-card {
            border-left: 4px solid #FF6B9D;
            transition: transform 0.2s;
        }
        
        .schedule-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .schedule-time {
            font-size: 1.1em;
        }
        
        .progress {
            height: 8px;
        }
        
        .badge.bg-pink {
            background-color: #FF6B9D !important;
        }
    </style>
</body>
</html>
