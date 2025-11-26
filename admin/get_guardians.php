<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if parent_id is provided
if (!isset($_GET['parent_id']) || !is_numeric($_GET['parent_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid parent ID']);
    exit();
}

$parent_id = intval($_GET['parent_id']);

try {
    $conn = getDBConnection();
    
    // Get all guardians for the specified parent
    $stmt = $conn->prepare("
        SELECT id, first_name, last_name, relationship, phone, email, 
               photo_path, is_authorized_pickup, created_at
        FROM guardians 
        WHERE parent_id = ?
        ORDER BY is_authorized_pickup DESC, first_name, last_name
    ");
    $stmt->execute([$parent_id]);
    $guardians = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return the guardians as JSON
    header('Content-Type: application/json');
    echo json_encode($guardians);
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Failed to fetch guardians: ' . $e->getMessage()]);
}
?>
