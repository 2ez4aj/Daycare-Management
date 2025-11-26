<?php
session_start();
require_once '../config/database.php';
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generate_attendance':
                generateAttendanceReport($conn, $_POST);
                break;
            case 'generate_development':
                generateDevelopmentReport($conn, $_POST);
                break;
            case 'generate_enrollment':
                generateEnrollmentReport($conn, $_POST);
                break;
            case 'generate_custom':
                generateCustomReport($conn, $_POST);
                break;
        }
    }
}

function getScheduleNameById($conn, $schedule_id) {
    if (!$schedule_id || $schedule_id === 'all') {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT schedule_name FROM schedules WHERE id = ?");
    $stmt->execute([$schedule_id]);
    return $stmt->fetchColumn() ?: null;
}

function generateAttendanceReport($conn, $data) {
    $report_type = $data['report_type'];
    $student_id = $data['student_id'] ?? null;
    $current_month = $data['current_month'] ?? date('Y-m');
    $schedule_id = $data['schedule_id'] ?? 'all';
    
    // Build query based on report type
    $where_clause = "WHERE 1=1";
    $params = [];
    
    if ($student_id && $student_id !== 'all') {
        $where_clause .= " AND a.student_id = ?";
        $params[] = $student_id;
    }
    
    if ($report_type === 'monthly') {
        $where_clause .= " AND DATE_FORMAT(a.date, '%Y-%m') = ?";
        $params[] = $current_month;
    }
    
    if ($schedule_id && $schedule_id !== 'all') {
        $where_clause .= " AND COALESCE(ss.schedule_id, s.schedule_id) = ?";
        $params[] = $schedule_id;
        $data['selected_schedule_name'] = getScheduleNameById($conn, $schedule_id);
    }
    
    $stmt = $conn->prepare("
        SELECT a.*, s.first_name, s.last_name, s.date_of_birth,
               TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) as age,
               sch.schedule_name, sch.start_time, sch.end_time
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN student_schedules ss ON s.id = ss.student_id AND ss.is_active = 1
        LEFT JOIN schedules sch ON COALESCE(ss.schedule_id, s.schedule_id) = sch.id
        $where_clause
        ORDER BY a.date DESC, s.first_name
    ");
    $stmt->execute($params);
    $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    generatePDF('attendance', $attendance_data, $data);
}

function generateDevelopmentReport($conn, $data) {
    $student_id = $data['student_id'] ?? null;
    $time_period = $data['time_period'] ?? 'all';
    $development_category = $data['development_category'] ?? 'all';
    $schedule_id = $data['schedule_id'] ?? 'all';
    
    $where_clause = "WHERE 1=1";
    $params = [];
    
    if ($student_id && $student_id !== 'all') {
        $where_clause .= " AND pr.student_id = ?";
        $params[] = $student_id;
    }
    
    if ($time_period !== 'all') {
        switch ($time_period) {
            case 'last_month':
                $where_clause .= " AND pr.report_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                break;
            case 'last_quarter':
                $where_clause .= " AND pr.report_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                break;
            case 'last_year':
                $where_clause .= " AND pr.report_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
        }
    }
    
    if ($schedule_id && $schedule_id !== 'all') {
        $where_clause .= " AND COALESCE(ss.schedule_id, s.schedule_id) = ?";
        $params[] = $schedule_id;
        $data['selected_schedule_name'] = getScheduleNameById($conn, $schedule_id);
    }
    
    $stmt = $conn->prepare("
        SELECT pr.*, s.first_name, s.last_name, s.date_of_birth,
               TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) as age,
               u.first_name as teacher_first_name, u.last_name as teacher_last_name,
               sch.schedule_name, sch.start_time, sch.end_time
        FROM progress_reports pr
        JOIN students s ON pr.student_id = s.id
        LEFT JOIN users u ON pr.created_by = u.id
        LEFT JOIN student_schedules ss ON s.id = ss.student_id AND ss.is_active = 1
        LEFT JOIN schedules sch ON COALESCE(ss.schedule_id, s.schedule_id) = sch.id
        $where_clause
        ORDER BY pr.report_date DESC, s.first_name
    ");
    $stmt->execute($params);
    $progress_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    generatePDF('development', $progress_data, $data);
}

function generateEnrollmentReport($conn, $data) {
    $period = $data['period'] ?? 'current_month';
    $status = $data['status'] ?? 'all';
    $schedule_id = $data['schedule_id'] ?? 'all';
    
    $where_clause = "WHERE 1=1";
    $params = [];
    
    if ($status !== 'all') {
        $where_clause .= " AND s.status = ?";
        $params[] = $status;
    }
    
    if ($schedule_id && $schedule_id !== 'all') {
        $where_clause .= " AND s.schedule_id = ?";
        $params[] = $schedule_id;
        $data['selected_schedule_name'] = getScheduleNameById($conn, $schedule_id);
    }
    
    switch ($period) {
        case 'current_month':
            $where_clause .= " AND DATE_FORMAT(s.enrollment_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
            break;
        case 'last_month':
            $where_clause .= " AND DATE_FORMAT(s.enrollment_date, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m')";
            break;
        case 'current_year':
            $where_clause .= " AND YEAR(s.enrollment_date) = YEAR(CURDATE())";
            break;
    }
    
    $stmt = $conn->prepare("
        SELECT s.*, u.first_name as parent_first_name, u.last_name as parent_last_name,
               sch.schedule_name, sch.start_time, sch.end_time,
               TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) as age
        FROM students s
        LEFT JOIN users u ON s.parent_id = u.id
        LEFT JOIN schedules sch ON s.schedule_id = sch.id
        $where_clause
        ORDER BY s.enrollment_date DESC
    ");
    $stmt->execute($params);
    $enrollment_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    generatePDF('enrollment', $enrollment_data, $data);
}

function generateCustomReport($conn, $data) {
    $sections = $data['sections'] ?? [];
    $output_format = $data['output_format'] ?? 'pdf';
    
    $report_data = [];
    
    if (in_array('student_information', $sections)) {
        $stmt = $conn->prepare("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age FROM students WHERE status = 'active' ORDER BY first_name");
        $stmt->execute();
        $report_data['students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (in_array('attendance_information', $sections)) {
        $stmt = $conn->prepare("
            SELECT a.*, s.first_name, s.last_name,
                   TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) as age,
                   sch.schedule_name, sch.start_time, sch.end_time
            FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            LEFT JOIN student_schedules ss ON s.id = ss.student_id AND ss.is_active = 1
            LEFT JOIN schedules sch ON ss.schedule_id = sch.id
            WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY a.date DESC
        ");
        $stmt->execute();
        $report_data['attendance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (in_array('development_progress', $sections)) {
        $stmt = $conn->prepare("
            SELECT pr.*, s.first_name, s.last_name,
                   TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) as age
            FROM progress_reports pr 
            JOIN students s ON pr.student_id = s.id 
            ORDER BY pr.report_date DESC
        ");
        $stmt->execute();
        $report_data['progress'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (in_array('financial_information', $sections)) {
        // Placeholder for financial data
        $report_data['financial'] = [];
    }
    
    if (in_array('parent_information', $sections)) {
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email, phone, user_type FROM users WHERE user_type = 'parent' ORDER BY first_name");
        $stmt->execute();
        $report_data['parents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    generatePDF('custom', $report_data, $data);
}

function generatePDF($type, $data, $params) {
    // Check if data is empty
    if (empty($data)) {
        // Redirect back with error message
        header('Location: reports.php?error=no_data');
        exit();
    }
    
    // Set headers for HTML download (since we're not using a real PDF library)
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="' . $type . '_report_' . date('Y-m-d') . '.html"');
    
    // Generate HTML report
    $html = generateReportHTML($type, $data, $params);
    
    echo $html;
    exit();
}

function generateReportHTML($type, $data, $params) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <link href="../assets/css/mobile_nav.css" rel="stylesheet">
        <title><?php echo ucfirst($type); ?> Report - Gumamela Daycare</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .report-info { margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            .print-btn { margin: 20px 0; text-align: center; }
            .print-btn button { 
                background: #007bff; 
                color: white; 
                border: none; 
                padding: 10px 20px; 
                border-radius: 5px; 
                cursor: pointer; 
                margin: 0 10px;
            }
            @media print {
                .print-btn { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="print-btn">
            <button onclick="window.print()">Print Report</button>
            <button onclick="window.close()">Close</button>
        </div>
        
        <div class="header">
            <h1>Gumamela Daycare Center</h1>
            <h2><?php echo ucfirst($type); ?> Report</h2>
            <p>Generated on: <?php echo date('F d, Y H:i:s'); ?></p>
        </div>
        
        <?php if ($type === 'attendance'): ?>
            <div style="text-align: center; margin-bottom: 20px;">
                <h2>ATTENDANCE RECORD</h2>
                <div style="display: flex; justify-content: space-between; margin: 20px 0;">
                    <div style="text-align: left;">
                        <strong>NAME:</strong> <?php echo !empty($data) ? htmlspecialchars($data[0]['first_name'] . ' ' . $data[0]['last_name']) : ''; ?>
                    </div>
                    <div style="text-align: right;">
                        <strong>YEAR:</strong> <?php echo date('Y'); ?>
                    </div>
                </div>
            </div>
            
            <table style="width: 100%; border-collapse: collapse; margin: 0 auto;" border="1">
                <thead>
                    <tr>
                        <th style="padding: 8px; text-align: center;">DAY</th>
                        <th style="padding: 8px; text-align: center;">JAN</th>
                        <th style="padding: 8px; text-align: center;">FEB</th>
                        <th style="padding: 8px; text-align: center;">MAR</th>
                        <th style="padding: 8px; text-align: center;">APR</th>
                        <th style="padding: 8px; text-align: center;">MAY</th>
                        <th style="padding: 8px; text-align: center;">JUN</th>
                        <th style="padding: 8px; text-align: center;">JUL</th>
                        <th style="padding: 8px; text-align: center;">AUG</th>
                        <th style="padding: 8px; text-align: center;">SEP</th>
                        <th style="padding: 8px; text-align: center;">OCT</th>
                        <th style="padding: 8px; text-align: center;">NOV</th>
                        <th style="padding: 8px; text-align: center;">DEC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($day = 1; $day <= 31; $day++): ?>
                    <tr>
                        <td style="padding: 8px; text-align: center; font-weight: bold;"><?php echo $day; ?></td>
                        <?php 
                        $months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
                        foreach ($months as $monthNum => $month): 
                            $found = false;
                            foreach ($data as $record) {
                                $recordDay = (int)date('j', strtotime($record['date']));
                                $recordMonth = (int)date('n', strtotime($record['date']));
                                if ($recordDay === $day && $recordMonth === ($monthNum + 1)) {
                                    $found = true;
                                    break;
                                }
                            }
                        ?>
                            <td style="padding: 8px; text-align: center; height: 30px; width: 7.69%;"><?php echo $found ? '✓' : ''; ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; text-align: center; font-size: 12px;">
                <p>✓ - Present | Blank - Absent</p>
            </div>
            
        <?php elseif ($type === 'development'): ?>
            <div class="report-info">
                <h3>Development Report Details</h3>
                <p><strong>Time Period:</strong> <?php echo ucfirst($params['time_period'] ?? 'All'); ?></p>
                <p><strong>Category:</strong> <?php echo ucfirst($params['development_category'] ?? 'All Categories'); ?></p>
                <p><strong>Schedule/Section:</strong> 
                    <?php 
                        if (!empty($params['selected_schedule_name'])) {
                            echo htmlspecialchars($params['selected_schedule_name']);
                        } else {
                            echo 'All Schedules';
                        }
                    ?>
                </p>
                <p><strong>Total Reports:</strong> <?php echo count($data); ?></p>
            </div>
            
            <?php foreach ($data as $record): ?>
            <div class="report-container">
                <h4><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></h4>
                <p><strong>Report Date:</strong> <?php echo date('M d, Y', strtotime($record['report_date'])); ?></p>
                <p><strong>Teacher:</strong> <?php echo htmlspecialchars($record['teacher_first_name'] . ' ' . $record['teacher_last_name']); ?></p>
                <p><strong>Schedule/Section:</strong> <?php echo $record['schedule_name'] ? htmlspecialchars($record['schedule_name']) : 'Not Assigned'; ?></p>
                <p><strong>Overall Rating:</strong> <?php echo ucfirst(str_replace('_', ' ', $record['overall_rating'])); ?></p>
                
                <?php if ($record['academic_performance']): ?>
                <p><strong>Academic Performance:</strong> <?php echo htmlspecialchars($record['academic_performance']); ?></p>
                <?php endif; ?>
                
                <?php if ($record['social_skills']): ?>
                <p><strong>Social Skills:</strong> <?php echo htmlspecialchars($record['social_skills']); ?></p>
                <?php endif; ?>
                
                <?php if ($record['physical_development']): ?>
                <p><strong>Physical Development:</strong> <?php echo htmlspecialchars($record['physical_development']); ?></p>
                <?php endif; ?>
                
                <?php if ($record['teacher_comments']): ?>
                <p><strong>Teacher Comments:</strong> <?php echo htmlspecialchars($record['teacher_comments']); ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
        <?php elseif ($type === 'enrollment'): ?>
            <div class="report-info">
                <h3>Enrollment Statistics</h3>
                <p><strong>Period:</strong> <?php echo ucfirst($params['period'] ?? 'All'); ?></p>
                <p><strong>Status Filter:</strong> <?php echo ucfirst($params['status'] ?? 'All'); ?></p>
                <p><strong>Schedule/Section:</strong> 
                    <?php 
                        if (!empty($params['selected_schedule_name'])) {
                            echo htmlspecialchars($params['selected_schedule_name']);
                        } else {
                            echo 'All Schedules';
                        }
                    ?>
                </p>
                <p><strong>Total Enrollments:</strong> <?php echo count($data); ?></p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Enrollment Date</th>
                        <th>Student Name</th>
                        <th>Age</th>
                        <th>Parent</th>
                        <th>Schedule</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $record): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($record['enrollment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                        <td><?php echo $record['age']; ?></td>
                        <td><?php echo htmlspecialchars($record['parent_first_name'] . ' ' . $record['parent_last_name']); ?></td>
                        <td><?php echo $record['schedule_name'] ? htmlspecialchars($record['schedule_name']) : 'Not Assigned'; ?></td>
                        <td><?php echo ucfirst($record['status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
        <?php elseif ($type === 'custom'): ?>
            <div class="report-info">
                <h3>Custom Report</h3>
                <p><strong>Generated:</strong> <?php echo date('F d, Y H:i:s'); ?></p>
            </div>
            
            <?php if (isset($data['students'])): ?>
            <h3>Student Information</h3>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Enrollment Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['students'] as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                        <td><?php echo $student['age']; ?></td>
                        <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                        <td><?php echo ucfirst($student['status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <?php if (isset($data['attendance'])): ?>
            <h3>Recent Attendance</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Schedule/Section</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['attendance'] as $attendance): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($attendance['date'])); ?></td>
                        <td><?php echo htmlspecialchars($attendance['first_name'] . ' ' . $attendance['last_name']); ?></td>
                        <td><?php echo $attendance['schedule_name'] ? htmlspecialchars($attendance['schedule_name']) : 'Not Assigned'; ?></td>
                        <td><?php echo ucfirst($attendance['status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
        <?php endif; ?>
        
        <div class="footer">
            <p>This report was generated by Gumamela Daycare Center Management System</p>
            <p>© <?php echo date('Y'); ?> Gumamela Daycare Center. All rights reserved.</p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Get data for dropdowns
try {
    $conn = getDBConnection();
    
    // Get students for dropdowns (with schedule information)
    $stmt = $conn->prepare("
        SELECT s.id, s.first_name, s.last_name, COALESCE(ss.schedule_id, s.schedule_id) as active_schedule_id
        FROM students s
        LEFT JOIN student_schedules ss ON s.id = ss.student_id AND ss.is_active = 1
        WHERE s.status = 'active'
        ORDER BY s.first_name
    ");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active schedules/sections
    $stmt = $conn->prepare("SELECT id, schedule_name, start_time, end_time FROM schedules WHERE is_active = 1 ORDER BY start_time");
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $students = [];
    $schedules = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .report-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
            background: #fff;
        }
        .report-card:hover {
            border-color: #007bff;
            box-shadow: 0 4px 12px rgba(0,123,255,0.15);
        }
        .report-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        .report-description {
            color: #666;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .generate-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .generate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-file-alt me-3"></i>Reports</h1>
            </div>

            <div class="content-body">
                <?php if (isset($_GET['error']) && $_GET['error'] === 'no_data'): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No data found for the selected criteria. Please adjust your filters and try again.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Attendance Report -->
                    <div class="col-md-6">
                        <div class="report-card">
                            <div class="report-title">
                                <i class="fas fa-calendar-check me-2 text-primary"></i>
                                Attendance Report
                            </div>
                            <div class="report-description">
                                Generate attendance reports for individual or all entire daycare center.
                            </div>
                            
                            <form method="POST" target="_blank">
                                <input type="hidden" name="action" value="generate_attendance">
                                
                                <div class="mb-3">
                                    <label class="form-label small">Report Type</label>
                                    <select class="form-select form-select-sm" name="report_type">
                                        <option value="daily">Daily Report</option>
                                        <option value="monthly" selected>Monthly Report</option>
                                        <option value="all">All Records</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small">Schedule/Section</label>
                                    <select class="form-select form-select-sm" name="schedule_id" id="attendance_schedule">
                                        <option value="all">All Sections</option>
                                        <?php foreach ($schedules as $schedule): ?>
                                            <option value="<?php echo $schedule['id']; ?>">
                                                <?php echo htmlspecialchars($schedule['schedule_name']); ?> (<?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small">Individual Student</label>
                                    <select class="form-select form-select-sm" name="student_id" id="attendance_student" data-student-select data-schedule-select="attendance_schedule">
                                        <option value="all" data-schedule="all">Select a Student</option>
                                        <?php foreach ($students as $student): ?>
                                            <option 
                                                value="<?php echo $student['id']; ?>"
                                                data-schedule="<?php echo $student['active_schedule_id'] !== null ? $student['active_schedule_id'] : 'none'; ?>">
                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small">Current Month</label>
                                    <input type="month" class="form-control form-control-sm" name="current_month" value="<?php echo date('Y-m'); ?>">
                                </div>
                                
                                <button type="submit" class="btn generate-btn w-100">
                                    <i class="fas fa-download me-2"></i>Generate Report
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Development Report -->
                    <div class="col-md-6">
                        <div class="report-card">
                            <div class="report-title">
                                <i class="fas fa-chart-line me-2 text-success"></i>
                                Development Report
                            </div>
                            <div class="report-description">
                                Generate comprehensive development reports for individual students or groups.
                            </div>
                            
                            <form method="POST" target="_blank">
                                <input type="hidden" name="action" value="generate_development">
                                
                                <div class="mb-3">
                                    <label class="form-label small">Development Category</label>
                                    <select class="form-select form-select-sm" name="development_category">
                                        <option value="all">All Categories</option>
                                        <option value="academic">Academic Performance</option>
                                        <option value="social">Social Skills</option>
                                        <option value="physical">Physical Development</option>
                                        <option value="emotional">Emotional Development</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small">Schedule/Section</label>
                                    <select class="form-select form-select-sm" name="schedule_id" id="development_schedule">
                                        <option value="all">All Sections</option>
                                        <?php foreach ($schedules as $schedule): ?>
                                            <option value="<?php echo $schedule['id']; ?>">
                                                <?php echo htmlspecialchars($schedule['schedule_name']); ?> (<?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small">Select Student</label>
                                    <select class="form-select form-select-sm" name="student_id" id="development_student" data-student-select data-schedule-select="development_schedule">
                                        <option value="all" data-schedule="all">Select a Student</option>
                                        <?php foreach ($students as $student): ?>
                                            <option 
                                                value="<?php echo $student['id']; ?>"
                                                data-schedule="<?php echo $student['active_schedule_id'] !== null ? $student['active_schedule_id'] : 'none'; ?>">
                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small">Time Period</label>
                                    <select class="form-select form-select-sm" name="time_period">
                                        <option value="last_month">Past Month</option>
                                        <option value="last_quarter">Past Quarter</option>
                                        <option value="last_year">Past Year</option>
                                        <option value="all">All Time</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn generate-btn w-100">
                                    <i class="fas fa-download me-2"></i>Generate Report
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Enrollment Statistics -->
                    <div class="col-md-6">
                        <div class="report-card">
                            <div class="report-title">
                                <i class="fas fa-users me-2 text-warning"></i>
                                Enrollment Statistics
                            </div>
                            <div class="report-description">
                                Generate enrollment statistics and trends for the daycare center.
                            </div>
                            
                            <form method="POST" target="_blank">
                                <input type="hidden" name="action" value="generate_enrollment">
                                
                                <div class="mb-3">
                                    <label class="form-label small">Period</label>
                                    <select class="form-select form-select-sm" name="period">
                                        <option value="current_month">Current Month</option>
                                        <option value="last_month">Last Month</option>
                                        <option value="current_year">Current Year</option>
                                        <option value="all">All Time</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small">Status</label>
                                    <select class="form-select form-select-sm" name="status">
                                        <option value="all">All Status</option>
                                        <option value="active">Active</option>
                                        <option value="pending">Pending</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small">Schedule/Section</label>
                                    <select class="form-select form-select-sm" name="schedule_id">
                                        <option value="all">All Sections</option>
                                        <?php foreach ($schedules as $schedule): ?>
                                            <option value="<?php echo $schedule['id']; ?>">
                                                <?php echo htmlspecialchars($schedule['schedule_name']); ?> (<?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn generate-btn w-100">
                                    <i class="fas fa-download me-2"></i>Generate Report
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Custom Report -->
                    <div class="col-md-6">
                        <div class="report-card">
                            <div class="report-title">
                                <i class="fas fa-cogs me-2 text-info"></i>
                                Custom Report
                            </div>
                            <div class="report-description">
                                Build a custom report by selecting specific data points to include.
                            </div>
                            
                            <form method="POST" target="_blank">
                                <input type="hidden" name="action" value="generate_custom">
                                
                                <div class="mb-3">
                                    <label class="form-label small">Include Sections</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sections[]" value="student_information" id="student_info">
                                        <label class="form-check-label small" for="student_info">Student Information</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sections[]" value="attendance_information" id="attendance_info">
                                        <label class="form-check-label small" for="attendance_info">Attendance Information</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sections[]" value="development_progress" id="development_progress">
                                        <label class="form-check-label small" for="development_progress">Development Progress</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sections[]" value="financial_information" id="financial_info">
                                        <label class="form-check-label small" for="financial_info">Financial Information</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sections[]" value="parent_information" id="parent_info">
                                        <label class="form-check-label small" for="parent_info">Parent Information</label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small">Output Format</label>
                                    <select class="form-select form-select-sm" name="output_format">
                                        <option value="pdf">PDF Document</option>
                                        <option value="excel">Excel Spreadsheet</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn generate-btn w-100">
                                    <i class="fas fa-download me-2"></i>Generate Report
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script src="../assets/js/mobile_nav.js"></script>

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<?php include 'mobile_nav.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-student-select]').forEach(function(studentSelect) {
        var scheduleSelectId = studentSelect.getAttribute('data-schedule-select');
        var scheduleSelect = document.getElementById(scheduleSelectId);
        if (!scheduleSelect) {
            return;
        }

        var optionData = Array.from(studentSelect.options).map(function(option) {
            return {
                value: option.value,
                text: option.textContent,
                schedule: option.dataset.schedule ? String(option.dataset.schedule) : 'none'
            };
        });

        var rebuildOptions = function(scheduleValue) {
            studentSelect.innerHTML = '';

            optionData.forEach(function(data) {
                if (scheduleValue === 'all' || data.value === 'all' || data.schedule === scheduleValue) {
                    var newOption = document.createElement('option');
                    newOption.value = data.value;
                    newOption.textContent = data.text;
                    newOption.dataset.schedule = data.schedule;
                    studentSelect.appendChild(newOption);
                }
            });

            studentSelect.selectedIndex = 0;
        };

        scheduleSelect.addEventListener('change', function() {
            rebuildOptions(scheduleSelect.value);
        });

        rebuildOptions(scheduleSelect.value);
    });
});
</script>
</body>
</html>
