<?php
session_start();
require_once '../config/database.php';

// Function to handle file uploads
function uploadFile($file, $uploadDir, $allowedTypes, $maxSize = 5 * 1024 * 1024) {
    // Create uploads directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = uniqid() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;
    $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
    
    // Check file size
    if ($file['size'] > $maxSize) {
        throw new Exception("File size exceeds the maximum limit of 5MB");
    }
    
    // Check file type
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception("Sorry, only " . implode(', ', $allowedTypes) . " files are allowed");
    }
    
    // Upload file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception("Sorry, there was an error uploading your file");
    }
    
    return $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic form data
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $sticky_data = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'username' => $username,
        'email' => $email,
        'phone' => $phone,
        'address' => $address
    ];
    
    // Validation
    $errors = [];
    
    if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password)) {
        $errors[] = 'All fields are required';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long';
    }
    
    // File upload validation
    $id_proof = $_FILES['id_proof'] ?? null;
    $child_photo = $_FILES['child_photo'] ?? null;
    
    if (!$id_proof || $id_proof['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Valid ID proof is required';
    }
    
    if (!$child_photo || $child_photo['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Child photo is required';
    }
    
    // If there are validation errors, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['register_errors'] = $errors;
        $_SESSION['register_form_data'] = $sticky_data;
        header('Location: ../register.php');
        exit();
    }
    
    try {
        $conn = getDBConnection();
        $conn->beginTransaction();
        
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            throw new Exception('Username or email already exists');
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Create uploads directory first
        $user_id = uniqid('user_', true); // Temporary ID for directory
        $uploadsDir = '../uploads/' . $user_id . '/';
        
        // Create directories if they don't exist
        if (!is_dir($uploadsDir . 'id_proofs/')) {
            mkdir($uploadsDir . 'id_proofs/', 0755, true);
        }
        if (!is_dir($uploadsDir . 'child_photos/')) {
            mkdir($uploadsDir . 'child_photos/', 0755, true);
        }
        
        // Upload ID proof
        $idProofPath = uploadFile(
            $id_proof,
            $uploadsDir . 'id_proofs/',
            ['jpg', 'jpeg', 'png', 'pdf']
        );
        
        // Upload child photo
        $childPhotoPath = uploadFile(
            $child_photo,
            $uploadsDir . 'child_photos/',
            ['jpg', 'jpeg', 'png']
        );
        
        // Now insert the user with file paths
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, username, email, phone, address, password, user_type, status, id_proof_path, child_photo_path) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, 'parent', 'pending', ?, ?)");
        
        if (!$stmt->execute([$first_name, $last_name, $username, $email, $phone, $address, $hashed_password, $idProofPath, $childPhotoPath])) {
            throw new Exception('Failed to create user account');
        }
        
        $user_id = $conn->lastInsertId();
        
        // Store user ID and ID proof path in session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['id_proof_path'] = $idProofPath;
        
        // Store child photo path in session for the enrollment form
        $_SESSION['child_photo_path'] = $childPhotoPath;
        unset($_SESSION['register_form_data']);
        
        $conn->commit();
        
        // Redirect to success page or next step
        header('Location: ../registration_success.php');
        exit();
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // Clean up any uploaded files if something went wrong
        if (isset($uploadsDir) && is_dir($uploadsDir)) {
            // In a production environment, you might want to clean up partial uploads
        }
        
        $_SESSION['register_errors'] = ['An error occurred: ' . $e->getMessage()];
        $_SESSION['register_form_data'] = $sticky_data;
        header('Location: ../register.php');
        exit();
    }
} else {
    header('Location: ../register.php');
    exit();
}
?>
