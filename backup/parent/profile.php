<?php
session_start();
require_once '../config/database.php';
require_once '../config/upload.php';

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
            case 'update_profile':
                $profile_photo_path = null;
                $upload_error = null;
                
                // Handle profile photo upload
                if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                    try {
                        // Initialize FileUpload with proper uploads directory
                        $uploadsDir = dirname(__DIR__) . '/uploads/';
                        $uploader = new FileUpload($uploadsDir);
                        $uploadResult = $uploader->uploadFile($_FILES['profile_photo'], 'profiles');
                        
                        if ($uploadResult['success']) {
                            // Store relative path for database
                            $basePath = str_replace('\\', '/', dirname(__DIR__));
                            $fullPath = str_replace('\\', '/', $uploadResult['path']);
                            $profile_photo_path = str_replace($basePath . '/', '', $fullPath);
                        } else {
                            $upload_error = $uploadResult['error'];
                        }
                    } catch (Exception $e) {
                        $upload_error = "Upload failed: " . $e->getMessage();
                    }
                }
                
                if ($profile_photo_path) {
                    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, profile_photo_path = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['address'],
                        $profile_photo_path,
                        $_SESSION['user_id']
                    ]);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['address'],
                        $_SESSION['user_id']
                    ]);
                }
                
                // Update session data
                $_SESSION['first_name'] = $_POST['first_name'];
                $_SESSION['last_name'] = $_POST['last_name'];
                
                if ($upload_error) {
                    header('Location: profile.php?error=' . urlencode($upload_error));
                } else {
                    header('Location: profile.php?success=profile_updated');
                }
                exit();
                break;
                
            case 'change_password':
                if (password_verify($_POST['current_password'], $_SESSION['password_hash'])) {
                    $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$new_password_hash, $_SESSION['user_id']]);
                    
                    header('Location: profile.php?success=password_changed');
                    exit();
                } else {
                    $error = 'Current password is incorrect';
                }
                break;
                
            case 'add_guardian':
                $guardian_photo_path = null;
                
                // Handle guardian photo upload
                if (isset($_FILES['guardian_photo']) && $_FILES['guardian_photo']['error'] === UPLOAD_ERR_OK) {
                    if (class_exists('FileUpload')) {
                        // Initialize FileUpload with proper uploads directory
                        $uploadsDir = dirname(__DIR__) . '/uploads/';
                        $uploader = new FileUpload($uploadsDir);
                        $uploadResult = $uploader->uploadFile($_FILES['guardian_photo'], 'guardians');
                        
                        if ($uploadResult['success']) {
                            // Store relative path for database
                            $basePath = str_replace('\\', '/', dirname(__DIR__));
                            $fullPath = str_replace('\\', '/', $uploadResult['path']);
                            $guardian_photo_path = str_replace($basePath . '/', '', $fullPath);
                        }
                    }
                }
                
                $stmt = $conn->prepare("INSERT INTO guardians (parent_id, first_name, last_name, relationship, phone, email, address, photo_path, is_authorized_pickup) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $_POST['guardian_first_name'],
                    $_POST['guardian_last_name'],
                    $_POST['relationship'],
                    $_POST['guardian_phone'],
                    $_POST['guardian_email'] ?? null,
                    $_POST['guardian_address'] ?? null,
                    $guardian_photo_path,
                    isset($_POST['is_authorized_pickup']) ? 1 : 0
                ]);
                
                header('Location: profile.php?success=guardian_added');
                exit();
                break;
                
            case 'delete_guardian':
                $stmt = $conn->prepare("DELETE FROM guardians WHERE id = ? AND parent_id = ?");
                $stmt->execute([$_POST['guardian_id'], $_SESSION['user_id']]);
                
                header('Location: profile.php?success=guardian_deleted');
                exit();
                break;
        }
    }
}

// Get user data
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get guardians data
    $stmt = $conn->prepare("SELECT * FROM guardians WHERE parent_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $guardians = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get children data
    $stmt = $conn->prepare("SELECT * FROM students WHERE parent_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    $children = [];
    $guardians = [];
    $unread_messages = 0;
    $unread_notifications = 0;
}

// Initialize default values if user data is not found
if (!$user) {
    $user = [
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
        'address' => '',
        'profile_photo_path' => '',
        'created_at' => date('Y-m-d H:i:s')
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Gumamela Daycare Center</title>
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
                <h1>My Profile</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">My Profile</li>
                    </ol>
                </nav>
            </div>
            
            <!-- Profile Overview -->
            <div class="row mb-4">
                <div class="col-lg-4">
                    <div class="card profile-card">
                        <div class="card-body text-center">
                            <div class="profile-avatar mb-3">
                                <?php if (!empty($user['profile_photo_path'])): ?>
                                    <img src="../<?php echo htmlspecialchars($user['profile_photo_path']); ?>" alt="Profile Photo" class="profile-photo">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <h4><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h4>
                            <p class="text-muted">Parent Account</p>
                            <div class="profile-stats">
                                <div class="stat">
                                    <h5><?php echo count($children); ?></h5>
                                    <small>Children Enrolled</small>
                                </div>
                                <div class="stat">
                                    <h5><?php echo date('Y', strtotime($user['created_at'] ?? date('Y-m-d'))); ?></h5>
                                    <small>Member Since</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-user-edit me-2"></i>Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($_GET['error'])): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">First Name</label>
                                        <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Profile Photo</label>
                                    <input type="file" class="form-control" name="profile_photo" accept=".jpg,.jpeg,.png,.gif" id="profilePhotoInput">
                                    <small class="form-text text-muted">Upload a clear photo for identification purposes. Max size: 5MB</small>
                                    <div id="profilePhotoPreview" class="mt-2"></div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- My Children -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-child me-2"></i>My Children</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($children)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-child fa-3x mb-3 opacity-25"></i>
                            <h5>No children enrolled yet</h5>
                            <p>Start by enrolling your first child</p>
                            <a href="enroll.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Enroll Child
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($children as $child): ?>
                                <div class="col-lg-6 mb-3">
                                    <div class="child-card">
                                        <div class="child-avatar">
                                            <i class="fas fa-child"></i>
                                        </div>
                                        <div class="child-info">
                                            <h6><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h6>
                                            <p class="mb-1">
                                                <small class="text-muted">Age: <?php echo date('Y') - date('Y', strtotime($child['date_of_birth'])); ?> years</small>
                                            </p>
                                            <p class="mb-1">
                                                <small class="text-muted">Date of Birth: <?php echo date('M d, Y', strtotime($child['date_of_birth'])); ?></small>
                                            </p>
                                            <span class="badge <?php echo $child['status'] == 'active' ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo ucfirst($child['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Guardians/Alternative Contacts -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-users me-2"></i>Guardians & Alternative Contacts</h5>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addGuardianModal">
                        <i class="fas fa-plus"></i> Add Guardian
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($guardians)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-users fa-3x mb-3 opacity-25"></i>
                            <h5>No guardians added yet</h5>
                            <p>Add alternative contacts who can pick up your child when you're not available</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($guardians as $guardian): ?>
                                <div class="col-lg-6 mb-3">
                                    <div class="guardian-card">
                                        <div class="guardian-avatar">
                                            <?php if (!empty($guardian['photo_path'])): ?>
                                                <img src="../<?php echo htmlspecialchars($guardian['photo_path']); ?>" alt="Guardian Photo" class="guardian-photo">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="guardian-info">
                                            <h6><?php echo htmlspecialchars($guardian['first_name'] . ' ' . $guardian['last_name']); ?></h6>
                                            <p class="mb-1">
                                                <small class="text-muted">Relationship: <?php echo htmlspecialchars($guardian['relationship']); ?></small>
                                            </p>
                                            <p class="mb-1">
                                                <small class="text-muted">Phone: <?php echo htmlspecialchars($guardian['phone']); ?></small>
                                            </p>
                                            <?php if ($guardian['email']): ?>
                                                <p class="mb-1">
                                                    <small class="text-muted">Email: <?php echo htmlspecialchars($guardian['email']); ?></small>
                                                </p>
                                            <?php endif; ?>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <span class="badge <?php echo $guardian['is_authorized_pickup'] ? 'bg-success' : 'bg-warning'; ?>">
                                                    <?php echo $guardian['is_authorized_pickup'] ? 'Authorized Pickup' : 'Contact Only'; ?>
                                                </span>
                                                <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to remove this guardian?')">
                                                    <input type="hidden" name="action" value="delete_guardian">
                                                    <input type="hidden" name="guardian_id" value="<?php echo $guardian['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Security Settings -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-shield-alt me-2"></i>Security Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" id="new_password" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Password Requirements:</strong>
                            <ul class="mb-0 mt-2">
                                <li>At least 8 characters long</li>
                                <li>Include uppercase and lowercase letters</li>
                                <li>Include at least one number</li>
                            </ul>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Guardian Modal -->
    <div class="modal fade" id="addGuardianModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add Guardian/Alternative Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_guardian">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="guardian_first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="guardian_last_name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Relationship *</label>
                                <select class="form-control" name="relationship" required>
                                    <option value="">Select Relationship</option>
                                    <option value="Grandparent">Grandparent</option>
                                    <option value="Aunt/Uncle">Aunt/Uncle</option>
                                    <option value="Sibling">Sibling</option>
                                    <option value="Family Friend">Family Friend</option>
                                    <option value="Babysitter">Babysitter</option>
                                    <option value="Other Relative">Other Relative</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" name="guardian_phone" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="guardian_email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Guardian Photo</label>
                                <input type="file" class="form-control" name="guardian_photo" accept=".jpg,.jpeg,.png,.gif" id="guardianPhotoInput">
                                <small class="form-text text-muted">Upload a clear photo for identification. Max size: 5MB</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="guardian_address" rows="2"></textarea>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_authorized_pickup" id="authorizedPickup" checked>
                            <label class="form-check-label" for="authorizedPickup">
                                <strong>Authorized for Child Pickup</strong>
                                <small class="d-block text-muted">Check this if this person is allowed to pick up your child from daycare</small>
                            </label>
                        </div>
                        
                        <div id="guardianPhotoPreview" class="mt-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Guardian
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/parent.js"></script>
    <script>
        // Password validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                showNotification('Passwords do not match!', 'error');
                return;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                showNotification('Password must be at least 8 characters long!', 'error');
                return;
            }
        });
        
        // Handle success messages
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success) {
            let message = '';
            switch (success) {
                case 'profile_updated':
                    message = 'Profile updated successfully!';
                    break;
                case 'password_changed':
                    message = 'Password changed successfully!';
                    break;
                case 'guardian_added':
                    message = 'Guardian added successfully!';
                    break;
                case 'guardian_deleted':
                    message = 'Guardian removed successfully!';
                    break;
            }
            if (message) {
                showNotification(message, 'success');
            }
        }
        
        // Profile photo preview
        document.getElementById('profilePhotoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('profilePhotoPreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <div class="file-preview">
                            <img src="${e.target.result}" alt="Profile Preview" class="file-preview-image">
                            <p class="mt-2 mb-0"><small class="text-muted">${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)</small></p>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });
        
        // Guardian photo preview
        document.getElementById('guardianPhotoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('guardianPhotoPreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <div class="file-preview">
                            <img src="${e.target.result}" alt="Guardian Preview" class="file-preview-image">
                            <p class="mt-2 mb-0"><small class="text-muted">${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)</small></p>
                            <p class="mt-2 mb-0"><small class="text-muted">${file.type}</small></p>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });
    </script>
</body>
</html>
