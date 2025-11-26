<?php
/**
 * CHILD ENROLLMENT FORM
 * This page allows parents to enroll their children in the daycare
 * 
 * What this file does:
 * 1. Checks if parent is logged in
 * 2. Shows enrollment form with child information
 * 3. Handles file uploads (photos, documents)
 * 4. Saves enrollment data to database
 */

// Start PHP session to track logged-in users
session_start();

// Include database connection and file upload functions
require_once '../config/database.php';  // Database connection
require_once '../config/upload.php';    // File upload handling

// SECURITY CHECK: Make sure user is logged in and is a parent
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'parent') {
    // If not logged in or not a parent, redirect to login page
    header('Location: ../index.php');
    exit(); // Stop running this page
}

// COUNT UNREAD MESSAGES for the sidebar notification badge
$unread_messages = 0; // Start with zero unread messages
$unread_notifications = 0; // Start with zero unread notifications
try {
    // Connect to database
    $conn = getDBConnection();
    
    // Count how many unread messages this parent has
    $stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_messages = $stmt->fetchColumn();
    
    // Count how many unread notifications this parent has
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetchColumn();
    
} catch (Exception $e) {
    // If there's an error, just keep counts as 0
    // This won't break the page if database has issues
}

// CHECK IF ENROLLMENT IS CURRENTLY ALLOWED
try {
    // Connect to database
    $conn = getDBConnection();
    
    // Check if admin has enabled enrollment
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'enrollment_enabled'");
    $stmt->execute();
    $enrollment_enabled = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If enrollment is disabled, redirect parent back to dashboard
    if (!$enrollment_enabled || $enrollment_enabled['setting_value'] != '1') {
        header('Location: dashboard.php?error=enrollment_disabled');
        exit();
    }
    
    // GET AVAILABLE TIME SCHEDULES for enrollment
    // This shows parents what time slots are available for their child
    $stmt = $conn->prepare("
        SELECT s.*, 
               COUNT(st.id) as current_enrolled,
               CONCAT(TIME_FORMAT(s.start_time, '%h:%i %p'), ' - ', TIME_FORMAT(s.end_time, '%h:%i %p')) as time_display
        FROM schedules s 
        LEFT JOIN students st ON s.id = st.schedule_id AND st.status = 'active'
        WHERE s.is_active = 1
        GROUP BY s.id 
        HAVING current_enrolled < s.max_capacity
        ORDER BY s.start_time
    ");
    $stmt->execute();
    $available_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    header('Location: dashboard.php?error=system_error');
    exit();
}

// HANDLE FORM SUBMISSION when parent clicks "Submit Enrollment"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Connect to database
        $conn = getDBConnection();
        
        // SECURITY: Double-check that the parent account is valid
        $userCheck = $conn->prepare("SELECT id, user_type, status FROM users WHERE id = ? AND user_type = 'parent'");
        $userCheck->execute([$_SESSION['user_id']]);
        $user = $userCheck->fetch(PDO::FETCH_ASSOC);
        
        // If user doesn't exist, log them out
        if (!$user) {
            session_destroy();
            header('Location: ../auth/login.php?error=invalid_session');
            exit();
        }
        
        // If user account is not active, show error
        if ($user['status'] !== 'active') {
            throw new Exception("Your account is not active. Please contact the administrator.");
        }
        
        // INITIALIZE FILE PATHS (will store where uploaded files are saved)
        $photo_path = null;              // Child's photo
        $birth_certificate_path = null;  // Birth certificate document
        
        // HANDLE FILE UPLOADS (photos and documents)
        if (class_exists('FileUpload')) {
            // Set up the uploads directory
            $uploadsDir = dirname(__DIR__) . '/uploads/';
            $uploader = new FileUpload($uploadsDir);
            
            // UPLOAD CHILD PHOTO if parent provided one
            if (isset($_FILES['child_photo']) && $_FILES['child_photo']['error'] === UPLOAD_ERR_OK) {
                $photoResult = $uploader->uploadFile($_FILES['child_photo'], 'students');
                
                if ($photoResult['success']) {
                    // Save the file path to store in database
                    $basePath = str_replace('\\', '/', dirname(__DIR__));
                    $fullPath = str_replace('\\', '/', $photoResult['path']);
                    $photo_path = str_replace($basePath . '/', '', $fullPath);
                    error_log("Photo uploaded successfully: " . $photo_path);
                } else {
                    // If upload failed, show error to parent
                    error_log("Photo upload failed: " . $photoResult['error']);
                    throw new Exception("Photo upload failed: " . $photoResult['error']);
                }
            }
            
            // UPLOAD BIRTH CERTIFICATE if parent provided one
            if (isset($_FILES['birth_certificate']) && $_FILES['birth_certificate']['error'] === UPLOAD_ERR_OK) {
                $certResult = $uploader->uploadFile($_FILES['birth_certificate'], 'students');
                
                if ($certResult['success']) {
                    // Save the file path to store in database
                    $basePath = str_replace('\\', '/', dirname(__DIR__));
                    $fullPath = str_replace('\\', '/', $certResult['path']);
                    $birth_certificate_path = str_replace($basePath . '/', '', $fullPath);
                    error_log("Birth certificate uploaded successfully: " . $birth_certificate_path);
                } else {
                    // If upload failed, show error to parent
                    error_log("Birth certificate upload failed: " . $certResult['error']);
                    throw new Exception("Birth certificate upload failed: " . $certResult['error']);
                }
            }
        }
        
        // CHECK IF PARENT ALREADY HAS A CHILD ENROLLED
        // (This daycare only allows one child per parent for now)
        $existingCheck = $conn->prepare("SELECT COUNT(*) FROM students WHERE parent_id = ?");
        $existingCheck->execute([$_SESSION['user_id']]);
        $existingCount = $existingCheck->fetchColumn();
        
        if ($existingCount > 0) {
            throw new Exception("You already have a child enrolled. Please contact the administrator to enroll additional children.");
        }
        
        // PREPARE ENROLLMENT DATA to save to database
        // Get the selected schedule (time slot) for the child
        $schedule_id = !empty($_POST['schedule_id']) ? $_POST['schedule_id'] : null;
        
        // Collect all the form data into an array for database insertion
        $enrollment_data = [
            $_SESSION['user_id'],                              // Parent's ID
            trim($_POST['first_name']),                        // Child's first name
            trim($_POST['middle_name'] ?? ''),                 // Child's middle name
            trim($_POST['last_name']),                         // Child's last name  
            trim($_POST['suffix'] ?? ''),                      // Name suffix (Jr., Sr., etc.)
            $_POST['date_of_birth'],                           // Child's birthday
            $_POST['gender'],                                  // Child's gender
            $schedule_id,                                      // Selected time schedule
            trim($_POST['medical_conditions'] ?? ''),          // Medical conditions
            trim($_POST['allergies'] ?? ''),                  // Known allergies
            trim($_POST['emergency_contact_name'] ?? ''),      // Emergency contact person
            trim($_POST['emergency_contact_phone']),           // Emergency phone number
            $photo_path,                                       // Path to uploaded photo
            $birth_certificate_path,                           // Path to uploaded birth certificate
            date('Y-m-d'),                                     // Today's date as enrollment date
            'pending'                                          // Status: pending admin approval
        ];
        
        // SAVE TO DATABASE: Insert the enrollment information
        $stmt = $conn->prepare("INSERT INTO students (parent_id, first_name, middle_name, last_name, suffix, date_of_birth, gender, schedule_id, medical_conditions, allergies, emergency_contact_name, emergency_contact_phone, photo_path, birth_certificate_path, enrollment_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute($enrollment_data);
        
        // SUCCESS: Redirect parent to dashboard with success message
        header('Location: dashboard.php?success=enrollment_submitted');
        exit();
        
    } catch (Exception $e) {
        // If anything goes wrong, show error message to parent
        $error_message = "Failed to submit enrollment. Error: " . $e->getMessage();
        error_log("Enrollment submission error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Enrollment - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/parent.css" rel="stylesheet">
</head>
<body>
    <div class="parent-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="brand-text">
                    <h4>Gumamela Daycare</h4>
                    <span>Parent Portal</span>
                </div>
                <button class="sidebar-toggle d-lg-none">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="announcements.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcements</span>
                </a>
                <a href="messages.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <span>Message</span>
                    <?php if ($unread_messages > 0): ?>
                        <span class="badge"><?php echo $unread_messages; ?></span>
                    <?php endif; ?>
                </a>
                <a href="enroll.php" class="nav-item active">
                    <i class="fas fa-user-plus"></i>
                    <span>Enroll Child</span>
                </a>
                <a href="attendance.php" class="nav-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
                <a href="learning_activities.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Learning Activities</span>
                </a>
                <a href="progress.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Progress Reports</span>
                </a>
                <a href="events.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Events</span>
                </a>
                <a href="faqs.php" class="nav-item">
                    <i class="fas fa-question-circle"></i>
                    <span>FAQ</span>
                </a>
                <a href="notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span>Notification</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-cog"></i>
                    <span>My Profile</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
                        <span class="user-role">Parent</span>
                    </div>
                </div>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Log out</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1>Child Enrollment</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Enroll Child</li>
                    </ol>
                </nav>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Enrollment Form -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user-plus me-2"></i>Child Enrollment Form</h5>
                    <small class="text-muted">Please fill out all required information for your child's enrollment.</small>
                </div>
                <div class="card-body">
                    <form method="POST" class="enrollment-form" id="enrollmentForm" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="middle_name">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Suffix</label>
                                <select class="form-control" name="suffix">
                                    <option value="">Select Suffix (Optional)</option>
                                    <option value="Jr.">Jr.</option>
                                    <option value="Sr.">Sr.</option>
                                    <option value="II">II</option>
                                    <option value="III">III</option>
                                    <option value="IV">IV</option>
                                    <option value="V">V</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_of_birth" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender <span class="text-danger">*</span></label>
                                <select class="form-control" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Schedule Selection -->
                        <div class="mb-3">
                            <label class="form-label">Preferred Schedule <span class="text-danger">*</span></label>
                            <select class="form-control" name="schedule_id" required>
                                <option value="">Select a schedule...</option>
                                <?php foreach ($available_schedules as $schedule): ?>
                                    <option value="<?php echo $schedule['id']; ?>">
                                        <?php echo htmlspecialchars($schedule['schedule_name']); ?> 
                                        (<?php echo $schedule['time_display']; ?>) 
                                        - <?php echo $schedule['current_enrolled']; ?>/<?php echo $schedule['max_capacity']; ?> students
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i>
                                Choose your preferred time slot. The admin will review and confirm your schedule assignment.
                            </small>
                        </div>
                        
                        <hr class="my-4">
                        <h6 class="mb-3"><i class="fas fa-phone-alt me-2"></i>Emergency Contact Information</h6>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact_name" placeholder="Full name of emergency contact">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="tel" class="form-control" name="emergency_contact_phone" required>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        <h6 class="mb-3"><i class="fas fa-heartbeat me-2"></i>Medical Information</h6>
                        
                        <div class="mb-3">
                            <label class="form-label">Medical Conditions</label>
                            <textarea class="form-control" name="medical_conditions" rows="3" placeholder="Please list any medical conditions, medications, or special medical needs..."></textarea>
                            <small class="form-text text-muted">Include any chronic conditions, medications, or special medical requirements.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Allergies</label>
                            <textarea class="form-control" name="allergies" rows="3" placeholder="Please list any known allergies (food, environmental, medications)..."></textarea>
                            <small class="form-text text-muted">Include food allergies, environmental allergies, and medication allergies.</small>
                        </div>
                        
                        <hr class="my-4">
                        <h6 class="mb-3"><i class="fas fa-upload me-2"></i>Required Documents</h6>
                        
                        <!-- File Upload Section -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Child Photo</label>
                                <input type="file" class="form-control" name="child_photo" id="child_photo" accept="image/*">
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i>
                                    Upload a recent photo of your child (JPG, PNG, GIF). Max size: 5MB
                                </div>
                                <div id="photo-preview" class="mt-2"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Birth Certificate</label>
                                <input type="file" class="form-control" name="birth_certificate" id="birth_certificate" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i>
                                    Upload birth certificate (PDF or image). Max size: 5MB
                                </div>
                                <div id="certificate-preview" class="mt-2"></div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-exclamation-circle"></i>
                            <strong>Optional Documents:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Recent photo of your child (for identification purposes)</li>
                                <li>Official birth certificate (for age verification)</li>
                                <li>Documents can be uploaded later if needed</li>
                                <li>All information provided will be kept confidential</li>
                            </ul>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="enrollment-terms mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> of Gumamela Daycare Center and confirm that all information provided is accurate.
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Enrollment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Enrollment Terms</h6>
                    <ul>
                        <li>All enrollment applications are subject to approval by the daycare administration.</li>
                        <li>Parents must provide accurate and complete information about their child.</li>
                        <li>Medical information must be kept up to date and any changes reported immediately.</li>
                        <li>Emergency contact information must be current and accessible during daycare hours.</li>
                    </ul>
                    
                    <h6>Health and Safety</h6>
                    <ul>
                        <li>Children must be up to date on all required vaccinations.</li>
                        <li>Sick children should not be brought to the daycare.</li>
                        <li>All allergies and medical conditions must be disclosed.</li>
                        <li>Parents are responsible for providing any necessary medications with proper instructions.</li>
                    </ul>
                    
                    <h6>General Policies</h6>
                    <ul>
                        <li>Daycare hours are from 7:00 AM to 6:00 PM, Monday through Friday.</li>
                        <li>Late pickup fees may apply for children picked up after closing time.</li>
                        <li>Parents must notify the daycare of any changes in pickup arrangements.</li>
                        <li>The daycare reserves the right to terminate enrollment for non-compliance with policies.</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/parent.js"></script>
    <script>
        // File upload preview functions
        document.getElementById('child_photo').addEventListener('change', function() {
            previewFile(this, 'photo-preview', 'photo');
        });
        
        document.getElementById('birth_certificate').addEventListener('change', function() {
            previewFile(this, 'certificate-preview', 'document');
        });
        
        function previewFile(input, previewId, type) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            
            if (file) {
                const fileSize = formatFileSize(file.size);
                const fileName = file.name;
                const fileType = file.type;
                
                let iconClass = 'fa-file';
                if (fileType.startsWith('image/')) {
                    iconClass = 'fa-image';
                } else if (fileType === 'application/pdf') {
                    iconClass = 'fa-file-pdf';
                }
                
                preview.innerHTML = `
                    <div class="file-preview-item">
                        <i class="fas ${iconClass} text-primary me-2"></i>
                        <span class="file-name">${fileName}</span>
                        <small class="text-muted ms-2">(${fileSize})</small>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="clearFile('${input.id}', '${previewId}')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                
                // Show image preview for photos
                if (type === 'photo' && fileType.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.innerHTML += `
                            <div class="image-preview mt-2">
                                <img src="${e.target.result}" alt="Child Photo Preview" class="img-thumbnail image-preview-thumbnail">
                            </div>
                        `;
                    };
                    reader.readAsDataURL(file);
                }
            } else {
                preview.innerHTML = '';
            }
        }
        
        function clearFile(inputId, previewId) {
            document.getElementById(inputId).value = '';
            document.getElementById(previewId).innerHTML = '';
        }
        
        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else {
                return bytes + ' bytes';
            }
        }
        
        // Handle success messages
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success) {
            let message = '';
            switch (success) {
                case 'enrollment_submitted':
                    message = 'Enrollment submitted successfully! We will review your application and contact you soon.';
                    break;
            }
            if (message) {
                showNotification(message, 'success');
            }
        }
        
        // Form validation
        document.getElementById('enrollmentForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('input[required], select[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!document.getElementById('terms').checked) {
                showNotification('Please accept the terms and conditions to continue.', 'error');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Please fill in all required fields.', 'error');
            }
        });
        
        // Age calculation
        document.querySelector('input[name="date_of_birth"]').addEventListener('change', function() {
            const birthDate = new Date(this.value);
            const today = new Date();
            const age = Math.floor((today - birthDate) / (365.25 * 24 * 60 * 60 * 1000));
            
            if (age < 1) {
                showNotification('Children must be at least 1 year old for enrollment.', 'warning');
            } else if (age > 6) {
                showNotification('This daycare is designed for children ages 1-6 years.', 'info');
            }
        });
    </script>
    
</body>
</html>
