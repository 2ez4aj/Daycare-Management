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
                
            case 'delete_student':
                $stmt = $conn->prepare("UPDATE students SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$_POST['student_id']]);
                header('Location: students.php?success=student_deleted');
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
try {
    $conn = getDBConnection();
    
    // Get all active students with parent information and schedule details
    $stmt = $conn->prepare("
        SELECT s.*, u.first_name as parent_first_name, u.last_name as parent_last_name, u.email as parent_email,
               sch.schedule_name, sch.start_time, sch.end_time
        FROM students s 
        JOIN users u ON s.parent_id = u.id 
        LEFT JOIN schedules sch ON s.schedule_id = sch.id
        WHERE s.status = 'active'
        ORDER BY s.first_name, s.middle_name, s.last_name
    ");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending enrollments for approval
    $stmt = $conn->prepare("
        SELECT s.*, u.first_name as parent_first_name, u.last_name as parent_last_name, u.email as parent_email, u.phone as parent_phone,
               sch.schedule_name, sch.start_time, sch.end_time
        FROM students s 
        JOIN users u ON s.parent_id = u.id 
        LEFT JOIN schedules sch ON s.schedule_id = sch.id
        WHERE s.status = 'pending'
        ORDER BY s.created_at DESC
    ");
    $stmt->execute();
    $pending_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all active parents for dropdown
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE user_type = 'parent' AND status = 'active' ORDER BY first_name, last_name");
    $stmt->execute();
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $students = [];
    $parents = [];
    $pending_enrollments = [];
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
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header d-flex justify-content-between align-items-center">
                <h1>Student Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="fas fa-plus"></i> Add New Student
                </button>
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Active Students (<span id="studentCount"><?php echo count($students); ?></span>)</h5>
                    <div class="search-container" style="width: 300px;">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" id="studentSearch" placeholder="Search students by name, parent, or email...">
                            <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="studentsTable">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Date of Birth</th>
                                    <th>Gender</th>
                                    <th>Parent</th>
                                    <th>Schedule</th>
                                    <th>Enrollment Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="studentsTableBody">
                                <?php if (empty($students)): ?>
                                    <tr id="noStudentsRow">
                                        <td colspan="7" class="text-center text-muted py-4">
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
                                                <strong><?php echo htmlspecialchars($student['first_name'] . (!empty($student['middle_name']) ? ' ' . $student['middle_name'] : '') . ' ' . $student['last_name']); ?></strong>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($student['date_of_birth'])); ?></td>
                                            <td><?php echo ucfirst($student['gender']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($student['parent_email']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($student['schedule_name']): ?>
                                                    <span class="badge bg-success"><?php echo htmlspecialchars($student['schedule_name']); ?></span><br>
                                                    <small class="text-muted">
                                                        <?php echo date('g:i A', strtotime($student['start_time'])); ?> - 
                                                        <?php echo date('g:i A', strtotime($student['end_time'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Not Assigned</span><br>
                                                    <a href="schedules.php" class="btn btn-sm btn-outline-primary mt-1">
                                                        <i class="fas fa-clock"></i> Assign
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="editStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . (!empty($student['middle_name']) ? ' ' . $student['middle_name'] : '') . ' ' . $student['last_name']); ?>')">
                                                    <i class="fas fa-trash"></i>
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
        
        function approveEnrollment(studentId) {
            if (confirm('Are you sure you want to approve this enrollment? The child will be added as an active student.')) {
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
                case 'student_deleted':
                    message = 'Student has been deactivated.';
                    break;
            }
            if (message) {
                showNotification(message, 'success');
            }
        }
    </script>
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
        
        function deleteStudent(studentId, studentName) {
            if (confirm(`Are you sure you want to delete ${studentName}? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_student">
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
                case 'student_added':
                    message = 'Student added successfully!';
                    break;
                case 'student_updated':
                    message = 'Student updated successfully!';
                    break;
                case 'student_deleted':
                    message = 'Student deleted successfully!';
                    break;
            }
            if (message) {
                showNotification(message, 'success');
            }
        }
    </script>
</body>
</html>
