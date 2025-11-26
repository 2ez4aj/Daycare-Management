<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if report ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid report ID']);
    exit();
}

$reportId = (int)$_GET['id'];

try {
    $conn = getDBConnection();
    
    // Get the report details with student and teacher information
    $stmt = $conn->prepare("
        SELECT 
            pr.*, 
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            CONCAT(u.first_name, ' ', u.last_name) as teacher_name
        FROM progress_reports pr
        LEFT JOIN students s ON pr.student_id = s.id
        LEFT JOIN users u ON pr.created_by = u.id
        WHERE pr.id = ?
    ");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
        exit();
    }
    
    // Return the report data as JSON
    header('Content-Type: application/json');
    echo json_encode($report);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
