<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type'] ?? '';
    
    if (empty($username) || empty($password) || empty($user_type)) {
        header('Location: ../index.php?error=empty_fields');
        exit();
    }
    
    // Validate user_type is either 'parent' or 'admin'
    if (!in_array($user_type, ['parent', 'admin'])) {
        header('Location: ../index.php?error=invalid_user_type');
        exit();
    }
    
    try {
        $conn = getDBConnection();
        
        if (!$conn) {
            header('Location: ../index.php?error=database_connection');
            exit();
        }
        
        $stmt = $conn->prepare("SELECT id, username, password, first_name, last_name, user_type, status, profile_photo_path FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                header('Location: ../index.php?error=account_inactive');
                exit();
            }
            
            // Check if user is trying to login through the correct portal
            if ($user['user_type'] !== $user_type) {
                $error_message = $user['user_type'] === 'admin' 
                    ? 'error=admin_using_parent_portal' 
                    : 'error=parent_using_admin_portal';
                header('Location: ../index.php?' . $error_message);
                exit();
            }
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['profile_photo_path'] = $user['profile_photo_path'] ?? null;
            
            // Redirect based on user type
            if ($user['user_type'] === 'admin') {
                header('Location: ../admin/dashboard.php');
            } else {
                header('Location: ../parent/dashboard.php');
            }
            exit();
        } else {
            header('Location: ../index.php?error=invalid_credentials');
            exit();
        }
    } catch (Exception $e) {
        header('Location: ../index.php?error=system_error');
        exit();
    }
} else {
    header('Location: ../index.php');
    exit();
}
?>
