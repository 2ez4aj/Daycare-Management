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
            case 'add_student':
                $stmt = $conn->prepare("INSERT INTO students (parent_id, first_name, middle_name, last_name, date_of_birth, gender, emergency_contact_name, emergency_contact_phone, medical_conditions, allergies, enrollment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['parent_id'],
                    $_POST['first_name'],
                    $_POST['middle_name'] ?? '',
                    $_POST['last_name'],
                    $_POST['date_of_birth'],
                    $_POST['gender'],
                    $_POST['emergency_contact_name'],
                    $_POST['emergency_contact_phone'],
                    $_POST['medical_conditions'],
                    $_POST['allergies'],
                    $_POST['enrollment_date']
                ]);
                header('Location: students.php?success=student_added');
                exit();
                break;
                
            case 'edit_student':
                $stmt = $conn->prepare("UPDATE students SET first_name = ?, middle_name = ?, last_name = ?, date_of_birth = ?, gender = ?, emergency_contact_name = ?, emergency_contact_phone = ?, medical_conditions = ?, allergies = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['first_name'],
                    $_POST['middle_name'] ?? '',
                    $_POST['last_name'],
                    $_POST['date_of_birth'],
                    $_POST['gender'],
                    $_POST['emergency_contact_name'],
                    $_POST['emergency_contact_phone'],
                    $_POST['medical_conditions'],
                    $_POST['allergies'],
                    $_POST['student_id']
                ]);
                header('Location: students.php?success=student_updated');
                exit();
                break;
                
            case 'archive_student':
                $stmt = $conn->prepare("UPDATE students SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$_POST['student_id']]);
                header('Location: students_archive.php?success=student_archived');
                exit();
                break;
                
            case 'approve_enrollment':
                $stmt = $conn->prepare("UPDATE students SET status = 'active' WHERE id = ?");
                $stmt->execute([$_POST['student_id']]);
                header('Location: students.php?success=enrollment_approved');
                exit();
                break;
                
            case 'reject_enrollment':
                $stmt = $conn->prepare("UPDATE students SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$_POST['student_id']]);
                header('Location: students.php?success=enrollment_rejected');
                exit();
                break;
        }
    }
}

// Get students data
// Get students data
try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }
    
    // Get selected schedule and search term
    $selected_schedule = isset($_GET['schedule']) ? $_GET['schedule'] : '';
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Debug: Check if tables exist
    $tables = $conn->query("SHOW TABLES LIKE 'students'")->fetchAll();
    if (empty($tables)) {
        throw new Exception("The 'students' table does not exist in the database.");
    }
    
    // Build the base query
    $query = "
        SELECT s.*, s.photo_path, u.profile_photo_path, u.id_proof_path, 
               u.first_name as parent_first_name, u.last_name as parent_last_name, 
               u.email as parent_email, u.phone as parent_phone,
               sch.schedule_name, sch.start_time, sch.end_time, sch.id as schedule_id
        FROM students s 
        JOIN users u ON s.parent_id = u.id 
        LEFT JOIN schedules sch ON s.schedule_id = sch.id
        WHERE s.status IN ('active', 'pending')
    ";
    
    $params = [];
    
    // Add schedule filter if selected
    if (!empty($selected_schedule) && $selected_schedule !== 'all') {
        $query .= " AND s.schedule_id = ?";
        $params[] = $selected_schedule;
    }
    
    // Add search filter if search query exists
    if (!empty($search_query)) {
        $query .= " AND (
            s.first_name LIKE ? OR 
            s.last_name LIKE ? OR 
            CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR
            u.first_name LIKE ? OR 
            u.last_name LIKE ? OR
            CONCAT(u.first_name, ' ', u.last_name) LIKE ?
        )";
        $search_param = "%$search_query%";
        $params = array_merge($params, array_fill(0, 6, $search_param));
    }
    
    // Add sorting
    $query .= " ORDER BY s.first_name, s.last_name";
    
    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . implode(" ", $conn->errorInfo()));
    }
    
    $success = $stmt->execute($params);
    if (!$success) {
        throw new Exception("Query failed: " . implode(" ", $stmt->errorInfo()));
    }
    
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log the number of students found
    error_log("Found " . count($students) . " students in the database");
    
    // Get all active parents for dropdown
    $stmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE user_type = 'parent' AND status = 'active' ORDER BY first_name, last_name");
    $stmt->execute();
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all schedules for filter
    $stmt = $conn->prepare("SELECT id, schedule_name, start_time, end_time FROM schedules WHERE is_active = 1 ORDER BY start_time");
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize empty enrollments array
    $pending_enrollments = [];
    
    // Try to get pending enrollments if table exists
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'enrollments'");
        if ($tableCheck->rowCount() > 0) {
            $stmt = $conn->prepare("SELECT * FROM enrollments WHERE status = 'pending'");
            $stmt->execute();
            $pending_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            error_log("Enrollments table does not exist");
        }
    } catch (Exception $e) {
        error_log("Error checking enrollments: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in students.php: " . $e->getMessage());
    
    // Set empty arrays
    $students = [];
    $parents = [];
    $pending_enrollments = [];
    
    // Display error message in development
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
        echo '<div class="alert alert-danger">';
        echo '<h4>Database Error</h4>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <link href="../assets/css/mobile_nav.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1>Student Management</h1>
            </div>
            
            <!-- Pending Enrollments Section -->
            <?php if (!empty($pending_enrollments)): ?>
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5><i class="fas fa-clock me-2"></i>Pending Enrollments (<?php echo count($pending_enrollments); ?>)</h5>
                    <small>New enrollment applications waiting for approval</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Child Name</th>
                                    <th>Date of Birth</th>
                                    <th>Gender</th>
                                    <th>Parent</th>
                                    <th>Contact</th>
                                    <th>Schedule</th>
                                    <th>Submitted</th>
                                    <th>Documents</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_enrollments as $enrollment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></strong>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($enrollment['date_of_birth'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $enrollment['gender'] === 'male' ? 'primary' : 'pink'; ?>">
                                                <?php echo ucfirst($enrollment['gender']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($enrollment['parent_first_name'] . ' ' . $enrollment['parent_last_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($enrollment['parent_email']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($enrollment['parent_phone']): ?>
                                                <i class="fas fa-phone text-muted me-1"></i><?php echo htmlspecialchars($enrollment['parent_phone']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">No phone</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($enrollment['schedule_name']): ?>
                                                <span class="badge bg-success"><?php echo htmlspecialchars($enrollment['schedule_name']); ?></span><br>
                                                <small class="text-muted">
                                                    <?php echo date('g:i A', strtotime($enrollment['start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($enrollment['end_time'])); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($enrollment['enrollment_date'])); ?><br>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($enrollment['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($enrollment['photo_path'] || $enrollment['birth_certificate_path']): ?>
                                                <div class="btn-group-vertical btn-group-sm">
                                                    <?php if ($enrollment['photo_path']): ?>
                                                        <a href="../<?php echo htmlspecialchars($enrollment['photo_path']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-image"></i> Photo
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($enrollment['birth_certificate_path']): ?>
                                                        <a href="../<?php echo htmlspecialchars($enrollment['birth_certificate_path']); ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                                            <i class="fas fa-file-alt"></i> Certificate
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">No documents</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm">
                                                <button class="btn btn-success btn-sm" onclick="approveEnrollment(<?php echo $enrollment['id']; ?>)">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewEnrollmentModal<?php echo $enrollment['id']; ?>">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="rejectEnrollment(<?php echo $enrollment['id']; ?>)">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Students Table -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <h5 class="mb-0">Students Management (<span id="studentCount"><?php echo count($students); ?>)</span></h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <form method="GET" class="d-flex gap-2 flex-wrap" id="searchForm">
                                <div class="search-container" style="width: 250px;">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" name="search" id="studentSearch" class="form-control" 
                                               placeholder="Search students or parents..." 
                                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                            <a href="?" class="btn btn-outline-secondary">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <select name="schedule" id="scheduleFilter" class="form-select" style="width: 200px;">
                                    <option value="all" <?php echo (empty($selected_schedule) || $selected_schedule === 'all') ? 'selected' : ''; ?>>All Schedules</option>
                                    <?php foreach ($schedules as $schedule): ?>
                                        <option value="<?php echo $schedule['id']; ?>" 
                                                <?php echo ($selected_schedule == $schedule['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($schedule['schedule_name'] . ' (' . date('g:i A', strtotime($schedule['start_time'])) . ' - ' . date('g:i A', strtotime($schedule['end_time'])) . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="studentsTable">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Student Name</th>
                                    <th>Date of Birth</th>
                                    <th>Gender</th>
                                    <th>Parent Photo</th>
                                    <th>Parent</th>
                                    <th>Schedule</th>
                                    <th>Enrollment Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="studentsTableBody">
                                <?php if (empty($students)): ?>
                                    <tr id="noStudentsRow">
                                        <td colspan="10" class="text-center text-muted py-4">
                                            <i class="fas fa-user-graduate fa-3x mb-3 opacity-25"></i>
                                            <p>No students found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr class="student-row" 
                                            data-student-name="<?php echo strtolower(htmlspecialchars($student['first_name'] . ' ' . (!empty($student['middle_name']) ? $student['middle_name'] . ' ' : '') . $student['last_name'])); ?>"
                                            data-parent-name="<?php echo strtolower(htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name'])); ?>"
                                            data-parent-email="<?php echo strtolower(htmlspecialchars($student['parent_email'])); ?>"
                                            data-student-id="<?php echo $student['id']; ?>">
                                            <td>
                                                <div class="position-relative d-inline-block" style="cursor: pointer;" onclick="viewPhoto('<?php echo !empty($student['photo_path']) ? '../' . htmlspecialchars($student['photo_path']) : 'https://ui-avatars.com/api/?name=' . urlencode($student['first_name'] . '+' . $student['last_name']) . '&background=random'; ?>', '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')">
                                                    <img src="<?php echo !empty($student['photo_path']) ? '../' . htmlspecialchars($student['photo_path']) : 'https://ui-avatars.com/api/?name=' . urlencode($student['first_name'] . '+' . $student['last_name']) . '&background=random' ?>" 
                                                         class="rounded-circle border" 
                                                         style="width:40px;height:40px;object-fit:cover;" 
                                                         alt="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>">
                                                    <?php if (empty($student['photo_path'])): ?>
                                                        <i class="fas fa-camera position-absolute bottom-0 end-0 bg-light rounded-circle p-1" style="font-size: 10px;"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($student['first_name'] . (!empty($student['middle_name']) ? ' ' . $student['middle_name'] : '') . ' ' . $student['last_name']); ?></strong>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($student['date_of_birth'])); ?></td>
                                            <td><?php echo ucfirst($student['gender']); ?></td>
                                            <td>
                                                <?php 
                                                $parentPhoto = !empty($student['profile_photo_path']) ? $student['profile_photo_path'] : 
                                                            (!empty($student['id_proof_path']) ? $student['id_proof_path'] : '');
                                                $parentName = htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name']);
                                                $parentPhotoSrc = !empty($parentPhoto) ? '../' . ltrim($parentPhoto, '/') : 
                                                            'https://ui-avatars.com/api/?name=' . urlencode($student['parent_first_name'] . '+' . $student['parent_last_name']) . '&size=200&background=random';
                                                $isPlaceholder = empty($parentPhoto);
                                                ?>
                                                <div class="position-relative d-inline-block" style="cursor: pointer;" 
                                                     onclick="viewPhoto('<?php echo $parentPhotoSrc; ?>', '<?php echo $parentName; ?>', <?php echo $isPlaceholder ? 'true' : 'false'; ?>)">
                                                    <img src="<?php echo $parentPhotoSrc; ?>" 
                                                         class="rounded-circle border" 
                                                         style="width:40px;height:40px;object-fit:cover;" 
                                                         alt="<?php echo $parentName; ?>
                                                         <?php echo $isPlaceholder ? ' (Default Avatar)' : ''; ?>">
                                                    <i class="fas fa-user position-absolute bottom-0 end-0 bg-light rounded-circle p-1" style="font-size: 10px;"></i>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($student['parent_email']); ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($student['schedule_name'])): ?>
                                                    <?php echo htmlspecialchars($student['schedule_name']); ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo date('g:i A', strtotime($student['start_time'])); ?> - 
                                                        <?php echo date('g:i A', strtotime($student['end_time'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                                            <td>
                                                <?php 
                                                $status_class = '';
                                                if ($student['status'] === 'active') {
                                                    $status_class = 'success';
                                                } elseif ($student['status'] === 'pending') {
                                                    $status_class = 'warning';
                                                } else {
                                                    $status_class = 'secondary';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($student['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($student['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success me-1" onclick="approveStudent(<?php echo $student['id']; ?>)">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger me-1" onclick="rejectStudent(<?php echo $student['id']; ?>)">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-info me-1" onclick="viewStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($student['status'] === 'active'): ?>
                                                <button class="btn btn-sm btn-outline-warning" 
                                                        onclick="archiveStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars(addslashes($student['first_name'] . ' ' . $student['last_name'])); ?>')"
                                                        title="Archive">
                                                    <i class="fas fa-archive"></i>
                                                </button>
                                                <?php endif; ?>
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
    
    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_student">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="middle_name">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-control" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Parent</label>
                            <select class="form-control" name="parent_id" required>
                                <option value="">Select Parent</option>
                                <?php foreach ($parents as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>">
                                        <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name'] . ' (' . $parent['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="tel" class="form-control" name="emergency_contact_phone">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Medical Conditions</label>
                            <textarea class="form-control" name="medical_conditions" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Allergies</label>
                            <textarea class="form-control" name="allergies" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Enrollment Date</label>
                            <input type="date" class="form-control" name="enrollment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Student Modal -->
    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editStudentForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_student">
                        <input type="hidden" name="student_id" id="edit_student_id">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="middle_name" id="edit_middle_name">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth" id="edit_date_of_birth" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-control" name="gender" id="edit_gender" required>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact_name" id="edit_emergency_contact_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="tel" class="form-control" name="emergency_contact_phone" id="edit_emergency_contact_phone">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Medical Conditions</label>
                            <textarea class="form-control" name="medical_conditions" id="edit_medical_conditions" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Allergies</label>
                            <textarea class="form-control" name="allergies" id="edit_allergies" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    
    <script>
        // Handle schedule filter change
        document.getElementById('scheduleFilter')?.addEventListener('change', function() {
            document.getElementById('searchForm').submit();
        });
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Auto-submit form on search input enter key
            const searchInput = document.getElementById('studentSearch');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('searchForm').submit();
                    }
                });
            }
        });
        
        // Student search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('studentSearch');
            const clearButton = document.getElementById('clearSearch');
            const studentRows = document.querySelectorAll('.student-row');
            const studentCount = document.getElementById('studentCount');
            const noStudentsRow = document.getElementById('noStudentsRow');
            
            function filterStudents() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                let visibleCount = 0;
                
                studentRows.forEach(row => {
                    const studentName = row.getAttribute('data-student-name');
                    const parentName = row.getAttribute('data-parent-name');
                    const parentEmail = row.getAttribute('data-parent-email');
                    const studentId = row.getAttribute('data-student-id');
                    
                    const isVisible = searchTerm === '' || 
                                    studentName.includes(searchTerm) || 
                                    parentName.includes(searchTerm) || 
                                    parentEmail.includes(searchTerm) ||
                                    studentId.includes(searchTerm);
                    
                    if (isVisible) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Update student count
                studentCount.textContent = visibleCount;
                
                // Show/hide no results message
                if (visibleCount === 0 && searchTerm !== '') {
                    if (!document.getElementById('noSearchResults')) {
                        const noResultsRow = document.createElement('tr');
                        noResultsRow.id = 'noSearchResults';
                        noResultsRow.innerHTML = `
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
                                <p>No students found matching "${searchTerm}"</p>
                                <button class="btn btn-sm btn-outline-primary" onclick="clearSearch()">
                                    <i class="fas fa-times"></i> Clear Search
                                </button>
                            </td>
                        `;
                        document.getElementById('studentsTableBody').appendChild(noResultsRow);
                    }
                } else {
                    const noResultsRow = document.getElementById('noSearchResults');
                    if (noResultsRow) {
                        noResultsRow.remove();
                    }
                }
            }
            
            // Real-time search as user types
            searchInput.addEventListener('input', filterStudents);
            
            // Clear search functionality
            clearButton.addEventListener('click', function() {
                searchInput.value = '';
                filterStudents();
                searchInput.focus();
            });
            
            // Clear search on escape key
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    searchInput.value = '';
                    filterStudents();
                }
            });
        });
        
        // Global function for clear search button in no results message
        function clearSearch() {
            document.getElementById('studentSearch').value = '';
            document.getElementById('studentSearch').dispatchEvent(new Event('input'));
            document.getElementById('studentSearch').focus();
        }
        
        function approveEnrollment(enrollmentId) {
            if (confirm('Are you sure you want to approve this enrollment?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_enrollment">
                    <input type="hidden" name="student_id" value="${enrollmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function approveStudent(studentId) {
            if (confirm('Are you sure you want to approve this student?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_enrollment">
                    <input type="hidden" name="student_id" value="${studentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function rejectStudent(studentId) {
            if (confirm('Are you sure you want to reject this student? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reject_enrollment">
                    <input type="hidden" name="student_id" value="${studentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function rejectEnrollment(studentId) {
            if (confirm('Are you sure you want to reject this enrollment? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reject_enrollment">
                    <input type="hidden" name="student_id" value="${studentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Handle success messages
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success) {
            let message = '';
            switch (success) {
                case 'enrollment_approved':
                    message = 'Enrollment approved successfully! The child has been added as an active student.';
                    break;
                case 'enrollment_rejected':
                    message = 'Enrollment has been rejected.';
                    break;
                case 'student_added':
                    message = 'Student added successfully!';
                    break;
                case 'student_updated':
                    message = 'Student information updated successfully!';
                    break;
                case 'student_archived':
                    message = 'Student has been archived successfully.';
                    break;
            }
            if (message) {
                showNotification(message, 'success');
            }
        }
    </script>
    <!-- View Enrollment Details Modal -->
    <?php foreach ($pending_enrollments as $enrollment): ?>
    <div class="modal fade" id="viewEnrollmentModal<?php echo $enrollment['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Enrollment Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-4 text-center">
                            <div class="enrollment-avatar mb-3">
                                <?php if ($enrollment['photo_path']): ?>
                                    <img src="../<?php echo htmlspecialchars($enrollment['photo_path']); ?>" class="img-fluid rounded" alt="Student Photo">
                                <?php else: ?>
                                    <i class="fas fa-child fa-5x text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <div class="d-grid gap-2">
                                <?php if ($enrollment['photo_path']): ?>
                                    <a href="../<?php echo htmlspecialchars($enrollment['photo_path']); ?>" target="_blank" class="btn btn-outline-primary">
                                        <i class="fas fa-expand me-1"></i> View Full Photo
                                    </a>
                                <?php endif; ?>
                                <?php if ($enrollment['birth_certificate_path']): ?>
                                    <a href="../<?php echo htmlspecialchars($enrollment['birth_certificate_path']); ?>" target="_blank" class="btn btn-outline-secondary mt-2">
                                        <i class="fas fa-file-pdf me-1"></i> View Birth Certificate
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h4 class="mb-3"><?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Date of Birth:</strong> <?php echo date('F j, Y', strtotime($enrollment['date_of_birth'])); ?></p>
                                    <p><strong>Age:</strong> <?php echo date_diff(date_create($enrollment['date_of_birth']), date_create('today'))->y; ?> years</p>
                                    <p><strong>Gender:</strong> <?php echo ucfirst($enrollment['gender']); ?></p>
                                    <p><strong>Enrollment Date:</strong> <?php echo date('F j, Y', strtotime($enrollment['enrollment_date'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Status:</strong> 
                                        <span class="badge bg-warning"><?php echo ucfirst($enrollment['status']); ?></span>
                                    </p>
                                    <p><strong>Submitted:</strong> <?php echo date('F j, Y g:i A', strtotime($enrollment['created_at'])); ?></p>
                                    <?php if ($enrollment['schedule_name']): ?>
                                        <p><strong>Schedule:</strong> <?php echo htmlspecialchars($enrollment['schedule_name']); ?>
                                        <br><small class="text-muted">
                                            <?php echo date('g:i A', strtotime($enrollment['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($enrollment['end_time'])); ?>
                                        </small></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h5>Parent/Guardian Information</h5>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <p class="mb-1">
                                            <strong><?php echo htmlspecialchars($enrollment['parent_first_name'] . ' ' . $enrollment['parent_last_name']); ?></strong>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-envelope me-2"></i> 
                                            <a href="mailto:<?php echo htmlspecialchars($enrollment['parent_email']); ?>">
                                                <?php echo htmlspecialchars($enrollment['parent_email']); ?>
                                            </a>
                                        </p>
                                        <?php if (!empty($enrollment['parent_phone'])): ?>
                                        <p class="mb-0">
                                            <i class="fas fa-phone me-2"></i> 
                                            <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $enrollment['parent_phone'])); ?>">
                                                <?php echo htmlspecialchars($enrollment['parent_phone']); ?>
                                            </a>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($enrollment['medical_conditions']) || !empty($enrollment['allergies'])): ?>
                            <div class="mt-4">
                                <h5>Medical Information</h5>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <?php if (!empty($enrollment['medical_conditions'])): ?>
                                            <p class="mb-2"><strong>Medical Conditions:</strong></p>
                                            <p class="mb-3"><?php echo nl2br(htmlspecialchars($enrollment['medical_conditions'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($enrollment['allergies'])): ?>
                                            <p class="mb-2"><strong>Allergies:</strong></p>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($enrollment['allergies'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="approveEnrollment(<?php echo $enrollment['id']; ?>)">
                        <i class="fas fa-check me-1"></i> Approve Enrollment
                    </button>
                    <button type="button" class="btn btn-danger" onclick="rejectEnrollment(<?php echo $enrollment['id']; ?>)">
                        <i class="fas fa-times me-1"></i> Reject Enrollment
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <script>
        function editStudent(student) {
            document.getElementById('edit_student_id').value = student.id;
            document.getElementById('edit_first_name').value = student.first_name;
            document.getElementById('edit_middle_name').value = student.middle_name || '';
            document.getElementById('edit_last_name').value = student.last_name;
            document.getElementById('edit_date_of_birth').value = student.date_of_birth;
            document.getElementById('edit_gender').value = student.gender;
            document.getElementById('edit_emergency_contact_name').value = student.emergency_contact_name || '';
            document.getElementById('edit_emergency_contact_phone').value = student.emergency_contact_phone || '';
            document.getElementById('edit_medical_conditions').value = student.medical_conditions || '';
            document.getElementById('edit_allergies').value = student.allergies || '';
            
            new bootstrap.Modal(document.getElementById('editStudentModal')).show();
        }

        // Function to view photo in modal
        function viewPhoto(photoSrc, title, isPlaceholder = false) {
            const modal = new bootstrap.Modal(document.getElementById('photoModal'));
            const modalTitle = document.getElementById('photoModalTitle');
            const modalPhoto = document.getElementById('modalPhoto');
            const downloadLink = document.getElementById('downloadPhoto');
            
            // Set modal title
            modalTitle.textContent = title + (title.includes('Photo') ? '' : ' Photo');
            
            // Handle the photo source
            if (isPlaceholder || photoSrc.includes('ui-avatars.com')) {
                // For placeholder images, use the direct URL
                modalPhoto.src = photoSrc;
                downloadLink.style.display = 'none';
            } else {
                // For actual photos, ensure the path is correct
                const fullPath = photoSrc.startsWith('http') ? photoSrc : 
                               (photoSrc.startsWith('../') ? photoSrc : '../' + photoSrc);
                modalPhoto.src = fullPath;
                
                // Only show download for actual photos
                downloadLink.style.display = 'inline-block';
                downloadLink.href = fullPath;
                downloadLink.download = title.toLowerCase().replace(/\s+/g, '-') + '.jpg';
            }
            
            // Set alt text
            modalPhoto.alt = title + ' Photo' + (isPlaceholder ? ' (Default Avatar)' : '');
            
            // Handle image load errors
            modalPhoto.onerror = function() {
                this.src = 'https://ui-avatars.com/api/?name=' + 
                          encodeURIComponent(title) + '&size=200&background=random';
                downloadLink.style.display = 'none';
            };
            
            // Show the modal
            modal.show();
        }
        
        async function viewStudent(student) {
            document.getElementById('viewStudentName').textContent = student.first_name + ' ' + (student.middle_name ? student.middle_name + ' ' : '') + student.last_name;
            document.getElementById('viewStudentDob').textContent = new Date(student.date_of_birth).toLocaleDateString();
            document.getElementById('viewStudentGender').textContent = student.gender.charAt(0).toUpperCase() + student.gender.slice(1);
            document.getElementById('viewEnrollmentDate').textContent = new Date(student.enrollment_date).toLocaleDateString();
            
            // Set student photo
            const studentPhoto = document.getElementById('viewChildPhoto');
            studentPhoto.src = student.photo_path ? `../${student.photo_path}` : 'https://via.placeholder.com/120';
            studentPhoto.onclick = function() {
                viewPhoto(studentPhoto.src, student.first_name + ' ' + student.last_name);
            };
            
            // Set parent photo
            const parentPhoto = document.getElementById('viewParentPhoto');
            const parentPhotoSrc = student.profile_photo_path || student.id_proof_path;
            const parentPhotoUrl = parentPhotoSrc ? 
                `../${parentPhotoSrc.replace(/^\/+/, '')}` : 
                `https://ui-avatars.com/api/?name=${encodeURIComponent(student.parent_first_name + ' ' + student.parent_last_name)}&size=200&background=random`;
            
            parentPhoto.src = parentPhotoUrl;
            parentPhoto.onclick = function() {
                viewPhoto(parentPhotoUrl, student.parent_first_name + ' ' + student.parent_last_name, !parentPhotoSrc);
            };
            
            // Set parent info
            document.getElementById('viewParentName').textContent = `${student.parent_first_name} ${student.parent_last_name}`;
            
            const parentEmail = document.getElementById('viewParentEmail');
            parentEmail.textContent = student.parent_email;
            parentEmail.href = `mailto:${student.parent_email}`;
            
            const parentPhone = document.getElementById('viewParentPhone');
            parentPhone.textContent = student.parent_phone || 'No phone number';
            parentPhone.href = student.parent_phone ? `tel:${student.parent_phone}` : '#';
            
            // Load guardians information
            try {
                const response = await fetch(`get_guardians.php?parent_id=${student.parent_id}`);
                const guardians = await response.json();
                
                const guardiansContainer = document.getElementById('guardiansContainer');
                guardiansContainer.innerHTML = ''; // Clear previous content
                
                if (guardians.length > 0) {
                    guardians.forEach(guardian => {
                        const guardianCard = document.createElement('div');
                        guardianCard.className = 'card mb-3';
                        guardianCard.innerHTML = `
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="${guardian.photo_path ? `../${guardian.photo_path}` : 'https://via.placeholder.com/80'}" 
                                         class="rounded-circle border" style="width:80px;height:80px;object-fit:cover;" 
                                         alt="${guardian.first_name} ${guardian.last_name}" onclick="viewPhoto('${guardian.photo_path ? `../${guardian.photo_path}` : 'https://via.placeholder.com/80'}', '${guardian.first_name} ${guardian.last_name}')">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">${guardian.first_name} ${guardian.last_name}</h6>
                                        <p class="mb-1"><small class="text-muted">${guardian.relationship}</small></p>
                                        <p class="mb-1"><i class="fas fa-phone text-muted me-1"></i> ${guardian.phone}</p>
                                        ${guardian.email ? `<p class="mb-1"><i class="fas fa-envelope text-muted me-1"></i> ${guardian.email}</p>` : ''}
                                        <span class="badge ${guardian.is_authorized_pickup ? 'bg-success' : 'bg-warning'}">
                                            ${guardian.is_authorized_pickup ? 'Authorized for Pickup' : 'Not Authorized for Pickup'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        `;
                        guardiansContainer.appendChild(guardianCard);
                    });
                } else {
                    guardiansContainer.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No guardians or alternative contacts have been added for this student.
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading guardians:', error);
                document.getElementById('guardiansContainer').innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> Error loading guardian information. Please try again later.
                    </div>
                `;
            }
            new bootstrap.Modal(document.getElementById('viewStudentModal')).show();
        }
        
        function archiveStudent(studentId, studentName) {
            if (confirm(`Archive ${studentName}? You can restore archived students anytime from the archive page.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="archive_student">
                    <input type="hidden" name="student_id" value="${studentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

<!-- Photo View Modal -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="photoModalTitle">Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalPhoto" src="" class="img-fluid rounded" alt="Enlarged photo">
            </div>
            <div class="modal-footer">
                <a id="downloadPhoto" href="#" class="btn btn-primary" download>
                    <i class="fas fa-download me-1"></i> Download
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- View Student Modal -->
<div class="modal fade" id="viewStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Student Verification</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-4 text-center">
                        <img id="viewChildPhoto" src="" class="img-thumbnail mb-2" style="width:120px;height:120px;object-fit:cover;">
                        <p class="fw-semibold mb-0" id="viewStudentName"></p>
                        <small class="text-muted">DOB: <span id="viewStudentDob"></span></small><br>
                        <small class="text-muted">Gender: <span id="viewStudentGender"></span></small><br>
                        <small class="text-muted">Enrolled: <span id="viewEnrollmentDate"></span></small>
                    </div>
                    <div class="col-md-8">
                        <h6>Primary Parent / Guardian</h6>
                        <div class="d-flex align-items-center mb-3 gap-3">
                            <img id="viewParentPhoto" src="" class="rounded-circle border" style="width:60px;height:60px;object-fit:cover;">
                            <div>
                                <p class="mb-0 fw-semibold" id="viewParentName"></p>
                                <a href="#" id="viewParentEmail"></a><br>
                                <a href="#" id="viewParentPhone"></a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Authorized Guardians & Alternative Contacts Section -->
                <div class="mt-4">
                    <h5><i class="fas fa-users me-2"></i>Authorized Guardians & Alternative Contacts</h5>
                    <p class="text-muted">The following individuals are authorized to pick up this student. Please verify their identity using the provided photos and information.</p>
                    <div id="guardiansContainer" class="mt-3">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading guardian information...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

    <script src="../assets/js/mobile_nav.js"></script>
    <?php include 'mobile_nav.php'; ?>
</body>
</html>
