<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $conn = getDBConnection();

    switch ($_POST['action']) {
        case 'restore_student':
            $stmt = $conn->prepare("UPDATE students SET status = 'active' WHERE id = ?");
            $stmt->execute([$_POST['student_id']]);
            header('Location: students_archive.php?success=student_restored');
            exit();
            break;
    }
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT s.*, u.first_name AS parent_first_name, u.last_name AS parent_last_name, u.email AS parent_email,
               sch.schedule_name, sch.start_time, sch.end_time
        FROM students s
        JOIN users u ON s.parent_id = u.id
        LEFT JOIN schedules sch ON s.schedule_id = sch.id
        WHERE s.status = 'inactive'
        ORDER BY s.updated_at DESC
    ");
    $stmt->execute();
    $archivedStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $archivedStudents = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Students - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <link href="../assets/css/mobile_nav.css" rel="stylesheet">

</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>

        <div class="main-content">
            <div class="content-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="mb-0">Archived Students</h1>
                    <p class="text-muted mb-0">Students that have been archived from the active roster</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="students.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Active Students
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <h5 class="mb-0">Archived Students (<span id="archivedCount"><?php echo count($archivedStudents); ?></span>)</h5>
                    <div class="search-container" style="width: 300px;">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" id="archivedSearch" placeholder="Search archived students...">
                            <button class="btn btn-outline-secondary" type="button" id="clearArchivedSearch">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Parent</th>
                                    <th>Schedule</th>
                                    <th>Enrollment Date</th>
                                    <th>Archived On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="archivedTableBody">
                                <?php if (empty($archivedStudents)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-box-archive fa-3x mb-3 opacity-25"></i>
                                            <p>No archived students.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($archivedStudents as $student): ?>
                                        <tr class="archived-row"
                                            data-student-name="<?php echo strtolower(htmlspecialchars($student['first_name'] . ' ' . (!empty($student['middle_name']) ? $student['middle_name'] . ' ' : '') . $student['last_name'])); ?>"
                                            data-parent-name="<?php echo strtolower(htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name'])); ?>"
                                            data-parent-email="<?php echo strtolower(htmlspecialchars($student['parent_email'])); ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($student['first_name'] . (!empty($student['middle_name']) ? ' ' . $student['middle_name'] : '') . ' ' . $student['last_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo ucfirst($student['gender']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($student['parent_email']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($student['schedule_name']): ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($student['schedule_name']); ?></span><br>
                                                    <small class="text-muted">
                                                        <?php echo date('g:i A', strtotime($student['start_time'])); ?> -
                                                        <?php echo date('g:i A', strtotime($student['end_time'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                                            <td><?php echo date('M d, Y g:i A', strtotime($student['updated_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-success" onclick="restoreStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . (!empty($student['middle_name']) ? ' ' . $student['middle_name'] : '') . ' ' . $student['last_name']); ?>')">
                                                    <i class="fas fa-undo"></i> Restore
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

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('archivedSearch');
            const clearButton = document.getElementById('clearArchivedSearch');
            const archivedRows = document.querySelectorAll('.archived-row');
            const archivedCount = document.getElementById('archivedCount');

            function filterArchived() {
                const term = searchInput.value.toLowerCase().trim();
                let visible = 0;

                archivedRows.forEach(row => {
                    const studentName = row.getAttribute('data-student-name');
                    const parentName = row.getAttribute('data-parent-name');
                    const parentEmail = row.getAttribute('data-parent-email');

                    const isVisible = term === '' ||
                        studentName.includes(term) ||
                        parentName.includes(term) ||
                        parentEmail.includes(term);

                    if (isVisible) {
                        row.style.display = '';
                        visible++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                archivedCount.textContent = visible;
            }

            searchInput.addEventListener('input', filterArchived);
            clearButton.addEventListener('click', () => {
                searchInput.value = '';
                filterArchived();
                searchInput.focus();
            });
        });

        function restoreStudent(studentId, studentName) {
            if (confirm(`Restore ${studentName} to the active roster?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="restore_student">
                    <input type="hidden" name="student_id" value="${studentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success) {
            let message = '';
            switch (success) {
                case 'student_archived':
                    message = 'Student has been archived. You can restore them anytime.';
                    break;
                case 'student_restored':
                    message = 'Student restored to the active roster.';
                    break;
            }
            if (message) {
                showNotification(message, 'success');
            }
        }
    </script>
    <script src="../assets/js/mobile_nav.js"></script>
    <?php include 'mobile_nav.php'; ?>
</body>
</html>

