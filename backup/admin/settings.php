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
            case 'update_enrollment':
                $enrollment_enabled = isset($_POST['enrollment_enabled']) ? '1' : '0';
                $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'enrollment_enabled'");
                $stmt->execute([$enrollment_enabled]);
                header('Location: settings.php?success=enrollment_updated');
                exit();
                break;
                
            case 'update_daycare_info':
                $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'daycare_name'");
                $stmt->execute([$_POST['daycare_name']]);
                
                $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'max_students'");
                $stmt->execute([$_POST['max_students']]);
                
                $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'school_hours_start'");
                $stmt->execute([$_POST['school_hours_start']]);
                
                $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'school_hours_end'");
                $stmt->execute([$_POST['school_hours_end']]);
                
                header('Location: settings.php?success=info_updated');
                exit();
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Get current admin password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND user_type = 'admin'");
                $stmt->execute([$_SESSION['user_id']]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$admin) {
                    header('Location: settings.php?error=user_not_found');
                    exit();
                }
                
                // Verify current password
                if (!password_verify($current_password, $admin['password'])) {
                    header('Location: settings.php?error=wrong_current_password');
                    exit();
                }
                
                // Validate new password
                if (strlen($new_password) < 6) {
                    header('Location: settings.php?error=password_too_short');
                    exit();
                }
                
                if ($new_password !== $confirm_password) {
                    header('Location: settings.php?error=passwords_dont_match');
                    exit();
                }
                
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                
                header('Location: settings.php?success=password_changed');
                exit();
                break;
        }
    }
}

// Get current settings
try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings");
    $stmt->execute();
    $settings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to associative array
    $settings = [];
    foreach ($settings_raw as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
} catch (Exception $e) {
    $settings = [
        'enrollment_enabled' => '1',
        'daycare_name' => 'Gumamela Daycare Center',
        'max_students' => '100',
        'school_hours_start' => '07:00',
        'school_hours_end' => '18:00'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1>System Settings</h1>
            </div>
            
            <div class="row">
                <!-- Enrollment Control -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-user-plus me-2"></i>Enrollment Control</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_enrollment">
                                
                                <div class="enrollment-toggle">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enrollment_enabled" id="enrollmentToggle" 
                                               <?php echo ($settings['enrollment_enabled'] == '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enrollmentToggle">
                                            <strong>Enable Parent Enrollment</strong>
                                        </label>
                                    </div>
                                    
                                    <div class="enrollment-status mt-3">
                                        <div class="status-indicator <?php echo ($settings['enrollment_enabled'] == '1') ? 'status-enabled' : 'status-disabled'; ?>">
                                            <i class="fas <?php echo ($settings['enrollment_enabled'] == '1') ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                            <span>Enrollment is currently <strong><?php echo ($settings['enrollment_enabled'] == '1') ? 'ENABLED' : 'DISABLED'; ?></strong></span>
                                        </div>
                                    </div>
                                    
                                    <div class="enrollment-description mt-3">
                                        <small class="text-muted">
                                            <?php if ($settings['enrollment_enabled'] == '1'): ?>
                                                <i class="fas fa-info-circle"></i> Parents can submit new enrollment requests through the registration form.
                                            <?php else: ?>
                                                <i class="fas fa-exclamation-triangle"></i> Parents cannot submit new enrollment requests. The enrollment form will be hidden.
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Enrollment Setting
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Daycare Information -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-building me-2"></i>Daycare Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_daycare_info">
                                
                                <div class="mb-3">
                                    <label class="form-label">Daycare Name</label>
                                    <input type="text" class="form-control" name="daycare_name" 
                                           value="<?php echo htmlspecialchars($settings['daycare_name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Maximum Students</label>
                                    <input type="number" class="form-control" name="max_students" 
                                           value="<?php echo htmlspecialchars($settings['max_students']); ?>" min="1" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">School Start Time</label>
                                        <input type="time" class="form-control" name="school_hours_start" 
                                               value="<?php echo htmlspecialchars($settings['school_hours_start']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">School End Time</label>
                                        <input type="time" class="form-control" name="school_hours_end" 
                                               value="<?php echo htmlspecialchars($settings['school_hours_end']); ?>" required>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Information
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Password Change Section -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-key me-2"></i>Change Password</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($_GET['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php
                                    switch ($_GET['error']) {
                                        case 'wrong_current_password':
                                            echo 'Current password is incorrect.';
                                            break;
                                        case 'password_too_short':
                                            echo 'New password must be at least 6 characters long.';
                                            break;
                                        case 'passwords_dont_match':
                                            echo 'New password and confirmation do not match.';
                                            break;
                                        case 'user_not_found':
                                            echo 'User not found.';
                                            break;
                                        default:
                                            echo 'An error occurred while changing password.';
                                    }
                                    ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" id="passwordForm">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label class="form-label">Current Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="current_password" id="currentPassword" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('currentPassword', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">New Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="new_password" id="newPassword" required minlength="6">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('newPassword', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Password must be at least 6 characters long.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required minlength="6">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmPassword', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="password-strength mb-3" id="passwordStrength">
                                    <div class="strength-bar">
                                        <div class="strength-fill" id="strengthFill"></div>
                                    </div>
                                    <small class="strength-text" id="strengthText"></small>
                                </div>
                                
                                <button type="submit" class="btn btn-warning" id="changePasswordBtn">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Security Information -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-shield-alt me-2"></i>Security Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="security-info">
                                <div class="security-item">
                                    <div class="security-icon">
                                        <i class="fas fa-lock text-success"></i>
                                    </div>
                                    <div class="security-text">
                                        <h6>Password Protection</h6>
                                        <small class="text-muted">Your password is encrypted and secure</small>
                                    </div>
                                </div>
                                
                                <div class="security-item">
                                    <div class="security-icon">
                                        <i class="fas fa-user-shield text-info"></i>
                                    </div>
                                    <div class="security-text">
                                        <h6>Admin Access</h6>
                                        <small class="text-muted">You have full administrative privileges</small>
                                    </div>
                                </div>
                                
                                <div class="security-item">
                                    <div class="security-icon">
                                        <i class="fas fa-history text-warning"></i>
                                    </div>
                                    <div class="security-text">
                                        <h6>Session Management</h6>
                                        <small class="text-muted">Sessions expire automatically for security</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3 p-3 bg-light rounded">
                                <h6 class="mb-2"><i class="fas fa-lightbulb text-warning me-2"></i>Password Tips</h6>
                                <ul class="mb-0 small">
                                    <li>Use at least 6 characters</li>
                                    <li>Include uppercase and lowercase letters</li>
                                    <li>Add numbers and special characters</li>
                                    <li>Avoid common words or personal information</li>
                                    <li>Change your password regularly</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Status -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-server me-2"></i>System Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="system-status-item">
                                        <div class="status-icon bg-success">
                                            <i class="fas fa-database"></i>
                                        </div>
                                        <div class="status-info">
                                            <h6>Database</h6>
                                            <span class="text-success">Connected</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="system-status-item">
                                        <div class="status-icon bg-success">
                                            <i class="fas fa-shield-alt"></i>
                                        </div>
                                        <div class="status-info">
                                            <h6>Security</h6>
                                            <span class="text-success">Active</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="system-status-item">
                                        <div class="status-icon <?php echo ($settings['enrollment_enabled'] == '1') ? 'bg-success' : 'bg-warning'; ?>">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                        <div class="status-info">
                                            <h6>Enrollment</h6>
                                            <span class="<?php echo ($settings['enrollment_enabled'] == '1') ? 'text-success' : 'text-warning'; ?>">
                                                <?php echo ($settings['enrollment_enabled'] == '1') ? 'Open' : 'Closed'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="system-status-item">
                                        <div class="status-icon bg-info">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="status-info">
                                            <h6>Hours</h6>
                                            <span class="text-info"><?php echo $settings['school_hours_start'] . ' - ' . $settings['school_hours_end']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Handle success messages
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success) {
            let message = '';
            switch (success) {
                case 'enrollment_updated':
                    message = 'Enrollment setting updated successfully!';
                    break;
                case 'info_updated':
                    message = 'Daycare information updated successfully!';
                    break;
                case 'password_changed':
                    message = 'Password changed successfully!';
                    break;
            }
            if (message) {
                showNotification(message, 'success');
            }
        }
        
        // Add visual feedback for enrollment toggle
        document.getElementById('enrollmentToggle').addEventListener('change', function() {
            const statusIndicator = document.querySelector('.enrollment-status .status-indicator');
            const statusIcon = statusIndicator.querySelector('i');
            const statusText = statusIndicator.querySelector('span');
            const description = document.querySelector('.enrollment-description small');
            
            if (this.checked) {
                statusIndicator.className = 'status-indicator status-enabled';
                statusIcon.className = 'fas fa-check-circle';
                statusText.innerHTML = 'Enrollment is currently <strong>ENABLED</strong>';
                description.innerHTML = '<i class="fas fa-info-circle"></i> Parents can submit new enrollment requests through the registration form.';
            } else {
                statusIndicator.className = 'status-indicator status-disabled';
                statusIcon.className = 'fas fa-times-circle';
                statusText.innerHTML = 'Enrollment is currently <strong>DISABLED</strong>';
                description.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Parents cannot submit new enrollment requests. The enrollment form will be hidden.';
            }
        });
        
        // Password visibility toggle
        function togglePassword(fieldId, button) {
            const field = document.getElementById(fieldId);
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        // Password strength checker
        document.getElementById('newPassword').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            if (password.length === 0) {
                strengthDiv.style.display = 'none';
                return;
            }
            
            strengthDiv.style.display = 'block';
            
            let strength = 0;
            let feedback = [];
            
            // Length check
            if (password.length >= 8) strength += 25;
            else if (password.length >= 6) strength += 15;
            else feedback.push('at least 6 characters');
            
            // Uppercase check
            if (/[A-Z]/.test(password)) strength += 25;
            else feedback.push('uppercase letter');
            
            // Lowercase check
            if (/[a-z]/.test(password)) strength += 25;
            else feedback.push('lowercase letter');
            
            // Number or special character check
            if (/[\d\W]/.test(password)) strength += 25;
            else feedback.push('number or special character');
            
            // Update visual indicator
            strengthFill.style.width = strength + '%';
            
            if (strength < 50) {
                strengthFill.className = 'strength-fill weak';
                strengthText.textContent = 'Weak - Add: ' + feedback.join(', ');
                strengthText.className = 'strength-text text-danger';
            } else if (strength < 75) {
                strengthFill.className = 'strength-fill medium';
                strengthText.textContent = 'Medium - Consider adding: ' + feedback.join(', ');
                strengthText.className = 'strength-text text-warning';
            } else {
                strengthFill.className = 'strength-fill strong';
                strengthText.textContent = 'Strong password!';
                strengthText.className = 'strength-text text-success';
            }
        });
        
        // Password confirmation validation
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            }
        });
        
        // Form submission validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            // Confirm password change
            if (!confirm('Are you sure you want to change your password? You will need to use the new password for future logins.')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
    
    <style>
        .enrollment-toggle {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }
        
        .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-radius: 8px;
        }
        
        .status-enabled {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-disabled {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .system-status-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .status-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .status-info h6 {
            margin: 0;
            font-weight: 600;
        }
        
        .status-info span {
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Password Change Styles */
        .password-strength {
            margin-top: 10px;
        }
        
        .strength-bar {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 4px;
        }
        
        .strength-fill.weak {
            background-color: #dc3545;
        }
        
        .strength-fill.medium {
            background-color: #ffc107;
        }
        
        .strength-fill.strong {
            background-color: #28a745;
        }
        
        .security-info {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .security-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid transparent;
        }
        
        .security-item:nth-child(1) {
            border-left-color: #28a745;
        }
        
        .security-item:nth-child(2) {
            border-left-color: #17a2b8;
        }
        
        .security-item:nth-child(3) {
            border-left-color: #ffc107;
        }
        
        .security-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .security-text h6 {
            margin: 0 0 5px 0;
            font-weight: 600;
        }
        
        .security-text small {
            margin: 0;
        }
        
        .input-group .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        
        .is-invalid {
            border-color: #dc3545;
        }
        
        .is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
    </style>
</body>
</html>
