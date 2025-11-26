<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $conn = getDBConnection();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['mark_all_read']) && $input['mark_all_read']) {
        // Mark all notifications as read for this user
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
        
    } elseif (isset($input['id'])) {
        // Mark a single notification as read
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = ? 
            WHERE id = ? AND user_id = ?
        ");
        
        $success = $stmt->execute([
            $input['is_read'] ? 1 : 0,
            $input['id'],
            $_SESSION['user_id']
        ]);
        
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification updated'
            ]);
        } else {
            throw new Exception('Failed to update notification');
        }
    } else {
        throw new Exception('Invalid request');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error updating notification',
        'error' => $e->getMessage()
    ]);
}
?>
