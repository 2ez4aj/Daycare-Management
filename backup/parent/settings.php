<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is parent
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'parent') {
    header('Location: ../index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_preferences':
                // Update user preferences (we'll store in a JSON column or separate table)
                $preferences = [
                    'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
                    'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0,
                    'announcement_notifications' => isset($_POST['announcement_notifications']) ? 1 : 0,
                    'message_notifications' => isset($_POST['message_notifications']) ? 1 : 0,
                    'attendance_notifications' => isset($_POST['attendance_notifications']) ? 1 : 0,
                    'payment_notifications' => isset($_POST['payment_notifications']) ? 1 : 0,
                    'language' => $_POST['language'],
                    'timezone' => $_POST['timezone']
                ];
                
                $stmt = $conn->prepare("UPDATE users SET preferences = ? WHERE id = ?");
                $stmt->execute([json_encode($preferences), $_SESSION['user_id']]);
                
                header('Location: settings.php?success=preferences_updated');
                exit();
                break;
                
            case 'update_emergency_contact':
                $stmt = $conn->prepare("UPDATE users SET emergency_contact_name = ?, emergency_contact_phone = ?, emergency_contact_relationship = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['emergency_contact_name'],
                    $_POST['emergency_contact_phone'],
                    $_POST['emergency_contact_relationship'],
                    $_SESSION['user_id']
                ]);
                
                header('Location: settings.php?success=emergency_contact_updated');
                exit();
                break;
        }
    }
}

// Get user data and preferences
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Parse preferences
    $preferences = json_decode($user['preferences'] ?? '{}', true);
    $default_preferences = [
        'email_notifications' => 1,
        'sms_notifications' => 0,
        'announcement_notifications' => 1,
        'message_notifications' => 1,
        'attendance_notifications' => 1,
        'payment_notifications' => 1,
        'language' => 'en',
        'timezone' => 'Asia/Manila'
    ];
    $preferences = array_merge($default_preferences, $preferences);
    
    // Get unread messages count
    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_messages = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
    
    // Get unread notifications count for sidebar badge
    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
    
} catch (Exception $e) {
    $user = [];
    $preferences = $default_preferences;
    $unread_messages = 0;
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/parent.css" rel="stylesheet">
</head>
<body>
    <div class="parent-container">
        <?php include 'parent_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1>Settings</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Settings</li>
                    </ol>
                </nav>
            </div>
            
            <!-- Settings Navigation -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="settings-nav">
                        <button class="settings-nav-item active" onclick="showSettingsSection('notifications')">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </button>
                        <button class="settings-nav-item" onclick="showSettingsSection('preferences')">
                            <i class="fas fa-user-cog"></i>
                            <span>Preferences</span>
                        </button>
                        <button class="settings-nav-item" onclick="showSettingsSection('emergency')">
                            <i class="fas fa-phone"></i>
                            <span>Emergency Contact</span>
                        </button>
                        <button class="settings-nav-item" onclick="showSettingsSection('privacy')">
                            <i class="fas fa-shield-alt"></i>
                            <span>Privacy</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Notification Settings -->
            <div class="settings-section" id="notifications-section">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-bell me-2"></i>Notification Preferences</h5>
                        <p class="text-muted mb-0">Choose how you want to receive notifications from Gumamela Daycare Center</p>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_preferences">
                            
                            <div class="row">
                                <div class="col-lg-6">
                                    <h6 class="mb-3">Notification Methods</h6>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="email_notifications" id="email_notifications" <?php echo $preferences['email_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_notifications">
                                            <i class="fas fa-envelope text-primary me-2"></i>
                                            <strong>Email Notifications</strong>
                                            <br><small class="text-muted">Receive notifications via email</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="sms_notifications" id="sms_notifications" <?php echo $preferences['sms_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="sms_notifications">
                                            <i class="fas fa-sms text-success me-2"></i>
                                            <strong>SMS Notifications</strong>
                                            <br><small class="text-muted">Receive urgent notifications via SMS</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-lg-6">
                                    <h6 class="mb-3">Notification Types</h6>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="announcement_notifications" id="announcement_notifications" <?php echo $preferences['announcement_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="announcement_notifications">
                                            <i class="fas fa-bullhorn text-info me-2"></i>
                                            <strong>Announcements</strong>
                                            <br><small class="text-muted">General daycare announcements</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="message_notifications" id="message_notifications" <?php echo $preferences['message_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="message_notifications">
                                            <i class="fas fa-envelope text-primary me-2"></i>
                                            <strong>Messages</strong>
                                            <br><small class="text-muted">Direct messages from staff</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="attendance_notifications" id="attendance_notifications" <?php echo $preferences['attendance_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="attendance_notifications">
                                            <i class="fas fa-calendar-check text-warning me-2"></i>
                                            <strong>Attendance Updates</strong>
                                            <br><small class="text-muted">Daily attendance confirmations</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="payment_notifications" id="payment_notifications" <?php echo $preferences['payment_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="payment_notifications">
                                            <i class="fas fa-credit-card text-danger me-2"></i>
                                            <strong>Payment Reminders</strong>
                                            <br><small class="text-muted">Fee payments and reminders</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label">Language</label>
                                    <select class="form-control" name="language">
                                        <option value="en" <?php echo $preferences['language'] == 'en' ? 'selected' : ''; ?>>English</option>
                                        <option value="fil" <?php echo $preferences['language'] == 'fil' ? 'selected' : ''; ?>>Filipino</option>
                                        <option value="ceb" <?php echo $preferences['language'] == 'ceb' ? 'selected' : ''; ?>>Cebuano</option>
                                    </select>
                                </div>
                                
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label">Timezone</label>
                                    <select class="form-control" name="timezone">
                                        <option value="Asia/Manila" <?php echo $preferences['timezone'] == 'Asia/Manila' ? 'selected' : ''; ?>>Philippines (GMT+8)</option>
                                        <option value="UTC" <?php echo $preferences['timezone'] == 'UTC' ? 'selected' : ''; ?>>UTC (GMT+0)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- General Preferences -->
            <div class="settings-section d-none" id="preferences-section">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user-cog me-2"></i>General Preferences</h5>
                        <p class="text-muted mb-0">Customize your experience</p>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6">
                                <h6 class="mb-3">Display Settings</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label">Theme</label>
                                    <select class="form-control">
                                        <option value="light">Light Theme</option>
                                        <option value="dark">Dark Theme</option>
                                        <option value="auto">Auto (System)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Dashboard Layout</label>
                                    <select class="form-control">
                                        <option value="cards">Card Layout</option>
                                        <option value="list">List Layout</option>
                                        <option value="compact">Compact Layout</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-lg-6">
                                <h6 class="mb-3">Communication Preferences</h6>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="auto_read_receipts" checked>
                                    <label class="form-check-label" for="auto_read_receipts">
                                        <strong>Auto Read Receipts</strong>
                                        <br><small class="text-muted">Automatically mark messages as read when viewed</small>
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="show_online_status">
                                    <label class="form-check-label" for="show_online_status">
                                        <strong>Show Online Status</strong>
                                        <br><small class="text-muted">Let staff know when you're online</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Emergency Contact -->
            <div class="settings-section d-none" id="emergency-section">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-phone me-2"></i>Emergency Contact Information</h5>
                        <p class="text-muted mb-0">Provide emergency contact details for your child's safety</p>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_emergency_contact">
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Important:</strong> This contact will be reached in case of emergencies when you're not available.
                            </div>
                            
                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label">Emergency Contact Name</label>
                                    <input type="text" class="form-control" name="emergency_contact_name" value="<?php echo htmlspecialchars($user['emergency_contact_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label">Emergency Contact Phone</label>
                                    <input type="tel" class="form-control" name="emergency_contact_phone" value="<?php echo htmlspecialchars($user['emergency_contact_phone'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Relationship to Child</label>
                                <select class="form-control" name="emergency_contact_relationship" required>
                                    <option value="">Select relationship</option>
                                    <option value="grandparent" <?php echo ($user['emergency_contact_relationship'] ?? '') == 'grandparent' ? 'selected' : ''; ?>>Grandparent</option>
                                    <option value="aunt_uncle" <?php echo ($user['emergency_contact_relationship'] ?? '') == 'aunt_uncle' ? 'selected' : ''; ?>>Aunt/Uncle</option>
                                    <option value="sibling" <?php echo ($user['emergency_contact_relationship'] ?? '') == 'sibling' ? 'selected' : ''; ?>>Sibling</option>
                                    <option value="family_friend" <?php echo ($user['emergency_contact_relationship'] ?? '') == 'family_friend' ? 'selected' : ''; ?>>Family Friend</option>
                                    <option value="neighbor" <?php echo ($user['emergency_contact_relationship'] ?? '') == 'neighbor' ? 'selected' : ''; ?>>Neighbor</option>
                                    <option value="other" <?php echo ($user['emergency_contact_relationship'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Emergency Contact
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Privacy Settings -->
            <div class="settings-section d-none" id="privacy-section">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-shield-alt me-2"></i>Privacy & Security</h5>
                        <p class="text-muted mb-0">Manage your privacy and security settings</p>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6">
                                <h6 class="mb-3">Privacy Settings</h6>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="profile_visibility" checked>
                                    <label class="form-check-label" for="profile_visibility">
                                        <strong>Profile Visibility</strong>
                                        <br><small class="text-muted">Allow staff to view your profile information</small>
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="activity_tracking">
                                    <label class="form-check-label" for="activity_tracking">
                                        <strong>Activity Tracking</strong>
                                        <br><small class="text-muted">Track login activity for security</small>
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="data_sharing">
                                    <label class="form-check-label" for="data_sharing">
                                        <strong>Anonymous Data Sharing</strong>
                                        <br><small class="text-muted">Help improve our services with anonymous usage data</small>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-lg-6">
                                <h6 class="mb-3">Security Options</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label">Session Timeout</label>
                                    <select class="form-control">
                                        <option value="30">30 minutes</option>
                                        <option value="60" selected>1 hour</option>
                                        <option value="120">2 hours</option>
                                        <option value="480">8 hours</option>
                                    </select>
                                    <small class="form-text text-muted">Automatically log out after inactivity</small>
                                </div>
                                
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-key"></i> Change Password
                                    </button>
                                    <small class="form-text text-muted d-block mt-1">Last changed: Never</small>
                                </div>
                                
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-sign-out-alt"></i> Log Out All Devices
                                    </button>
                                    <small class="form-text text-muted d-block mt-1">Sign out from all devices</small>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Data Deletion:</strong> If you wish to delete your account and all associated data, please contact the daycare administration directly.
                        </div>
                        
                        <button type="button" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Privacy Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/parent.js"></script>
    <script>
        function showSettingsSection(section) {
            // Hide all sections
            document.querySelectorAll('.settings-section').forEach(el => {
                el.classList.add('d-none');
            });
            
            // Remove active class from all nav items
            document.querySelectorAll('.settings-nav-item').forEach(el => {
                el.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(section + '-section').classList.remove('d-none');
            
            // Add active class to clicked nav item
            event.target.closest('.settings-nav-item').classList.add('active');
        }
        
        // Handle success messages
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success) {
            let message = '';
            switch (success) {
                case 'preferences_updated':
                    message = 'Notification preferences updated successfully!';
                    break;
                case 'emergency_contact_updated':
                    message = 'Emergency contact information updated successfully!';
                    break;
            }
            if (message) {
                showNotification(message, 'success');
            }
        }
    </script>
    
</body>
</html>
