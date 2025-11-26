<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
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
            case 'approve_parent':
                // First get parent details before updating status
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'parent'");
                $stmt->execute([$_POST['parent_id']]);
                $parent = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($parent) {
                    // Update parent status
                    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND user_type = 'parent'");
                    $stmt->execute([$_POST['parent_id']]);
                    
                    // Send approval email
                    require_once __DIR__ . '/../core/Mailer.php';
                    
                    // Get the config array
                    $config = require __DIR__ . '/../config/config.php';
                    $mailer = new Mailer($config);
                    $to = [
                        'email' => $parent['email'],
                        'name' => $parent['first_name'] . ' ' . $parent['last_name']
                    ];
                    
                    $subject = 'Your Gumamela Daycare Account Has Been Approved';
                    
                    $htmlBody = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background-color: #e83e8c; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                            .content { padding: 20px; background-color: #f9f9f9; border: 1px solid #ddd; border-top: none; border-radius: 0 0 5px 5px; }
                            .button {
                                display: inline-block; padding: 10px 20px; margin: 15px 0; background-color: #e83e8c; 
                                color: white; text-decoration: none; border-radius: 5px; font-weight: bold;
                            }
                            .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
                        </style>
                    </head>
                    <body>
                        <div class='header'>
                            <h2>Welcome to Gumamela Daycare Center!</h2>
                        </div>
                        <div class='content'>
                            <p>Dear " . htmlspecialchars($parent['first_name']) . ",</p>
                            <p>We are pleased to inform you that your registration with Gumamela Daycare Center has been approved!</p>
                            <p>You can now log in to your account using the credentials you provided during registration.</p>
                            <div style='text-align: center; margin: 25px 0;'>
                                <a href='" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . "/login.php' class='button'>Login to Your Account</a>
                            </div>
                            <p>If you have any questions or need assistance, please don't hesitate to contact us.</p>
                            <p>Best regards,<br>The Gumamela Daycare Team</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message, please do not reply to this email.</p>
                            <p>© " . date('Y') . " Gumamela Daycare Center. All rights reserved.</p>
                        </div>
                    </body>
                    </html>";
                    
                    // Send the email
                    $mailer->send($to, $subject, $htmlBody);
                }
                
                header('Location: parents.php?success=parent_approved');
                exit();
                break;
                
            case 'reject_parent':
                $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND user_type = 'parent'");
                $stmt->execute([$_POST['parent_id']]);
                header('Location: parents.php?success=parent_rejected');
                exit();
                break;
                
            case 'edit_parent':
                // Handle file upload if a new photo was provided
                $profilePhotoPath = null;
                if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../uploads/parents/profile_photos/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileExtension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                    $fileName = 'parent_' . $_POST['parent_id'] . '_' . time() . '.' . $fileExtension;
                    $targetPath = $uploadDir . $fileName;
                    
                    // Move the uploaded file
                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
                        $profilePhotoPath = 'uploads/parents/profile_photos/' . $fileName;
                        
                        // Delete old photo if exists
                        $stmt = $conn->prepare("SELECT profile_photo_path FROM users WHERE id = ?");
                        $stmt->execute([$_POST['parent_id']]);
                        $oldPhoto = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!empty($oldPhoto['profile_photo_path']) && file_exists('../' . $oldPhoto['profile_photo_path'])) {
                            @unlink('../' . $oldPhoto['profile_photo_path']);
                        }
                    }
                }
                
                // Update parent information
                if ($profilePhotoPath) {
                    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, profile_photo_path = ? WHERE id = ? AND user_type = 'parent'");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['address'],
                        $profilePhotoPath,
                        $_POST['parent_id']
                    ]);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ? AND user_type = 'parent'");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['address'],
                        $_POST['parent_id']
                    ]);
                }
                
                header('Location: parents.php?success=parent_updated');
                exit();
                break;
                
            case 'delete_parent':
                $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND user_type = 'parent'");
                $stmt->execute([$_POST['parent_id']]);
                header('Location: parents.php?success=parent_deleted');
                exit();
                break;
        }
    }
}

// Get parents data
try {
    $conn = getDBConnection();
    
    // Get all parents with their children count, profile photos, and verification documents
    $stmt = $conn->prepare("
        SELECT 
            u.*, 
            COUNT(s.id) as children_count,
            GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ') as children_names,
            CONCAT('uploads/', u.id, '/id_proofs/', u.id_proof_path) as full_id_proof_path,
            CONCAT('uploads/', u.id, '/child_photos/', u.child_photo_path) as full_child_photo_path,
            u.id_proof_path as id_proof_filename,
            u.child_photo_path as child_photo_filename
        FROM users u 
        LEFT JOIN students s ON u.id = s.parent_id AND s.status = 'active'
        WHERE u.user_type = 'parent' 
        GROUP BY u.id 
        ORDER BY FIELD(u.status, 'pending', 'active', 'inactive'), u.first_name, u.last_name
    ");
    $stmt->execute();
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending parents count
    $stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM users WHERE user_type = 'parent' AND status = 'pending'");
    $stmt->execute();
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];
    
} catch (Exception $e) {
    $parents = [];
    $pending_count = 0;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Management - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="../assets/css/mobile_nav.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1>Parent Management</h1>
                <?php if ($pending_count > 0): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        You have <?php echo $pending_count; ?> pending parent approval(s)
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Filter Tabs -->
            <ul class="nav nav-tabs mb-4" id="parentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button">
                        All Parents
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button">
                        Pending Approval <?php if ($pending_count > 0): ?><span class="badge bg-warning ms-1"><?php echo $pending_count; ?></span><?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button">
                        Active Parents
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="parentTabContent">
                <!-- All Parents Tab -->
                <div class="tab-pane fade show active" id="all" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5>All Parents</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Photo</th>
                                            <th>Parent Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Children</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($parents as $parent): ?>
                                            <tr>
                                                <td>
                                                    <div class="parent-avatar" <?php if (!empty($parent['profile_photo_path'])): ?>onclick="showPhotoModal('../<?php echo htmlspecialchars($parent['profile_photo_path']); ?>', '<?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>')"<?php endif; ?>>
                                                        <?php if (!empty($parent['profile_photo_path'])): ?>
                                                            <img src="../<?php echo htmlspecialchars($parent['profile_photo_path']); ?>" alt="Profile Photo" class="parent-photo">
                                                        <?php else: ?>
                                                            <i class="fas fa-user"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($parent['email']); ?></td>
                                                <td><?php echo htmlspecialchars($parent['phone'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if ($parent['children_count'] > 0): ?>
                                                        <span class="badge bg-info"><?php echo $parent['children_count']; ?></span>
                                                        <small class="d-block text-muted"><?php echo htmlspecialchars($parent['children_names']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">No children</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($parent['status']) {
                                                        case 'active': $statusClass = 'bg-success'; break;
                                                        case 'pending': $statusClass = 'bg-warning'; break;
                                                        case 'inactive': $statusClass = 'bg-danger'; break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($parent['status']); ?></span>
                                                </td>
                                                <td class="text-nowrap">
                                                    <button class="btn btn-sm btn-info me-1 mb-1" 
                                                            onclick="viewParentDetails(<?php echo htmlspecialchars(json_encode($parent)); ?>)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <?php if ($parent['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-success me-1 mb-1" 
                                                                onclick="approveParent(<?php echo $parent['id']; ?>, '<?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>')">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button class="btn btn-sm btn-danger mb-1" 
                                                                onclick="rejectParent(<?php echo $parent['id']; ?>, '<?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>')">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editParent(<?php echo htmlspecialchars(json_encode($parent)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteParent(<?php echo $parent['id']; ?>, '<?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Parents Tab -->
                <div class="tab-pane fade" id="pending" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5>Pending Approval</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Photo</th>
                                            <th>Parent Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Verification Documents</th>
                                            <th>Registration Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $pendingParents = array_filter($parents, function($parent) {
                                            return $parent['status'] === 'pending';
                                        });
                                        ?>
                                        <?php if (empty($pendingParents)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">
                                                    <i class="fas fa-check-circle fa-3x mb-3 opacity-25"></i>
                                                    <p>No pending approvals</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($pendingParents as $parent): ?>
                                                <tr>
                                                    <td>
                                                        <div class="parent-avatar" <?php if (!empty($parent['profile_photo_path'])): ?>onclick="showPhotoModal('../<?php echo htmlspecialchars($parent['profile_photo_path']); ?>', '<?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>')"<?php endif; ?>>
                                                            <?php if (!empty($parent['profile_photo_path'])): ?>
                                                                <img src="../<?php echo htmlspecialchars($parent['profile_photo_path']); ?>" alt="Profile Photo" class="parent-photo">
                                                            <?php else: ?>
                                                                <i class="fas fa-user"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($parent['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($parent['phone'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <div class="d-flex gap-2">
                                                            <?php if (!empty($parent['id_proof_path'])): ?>
                                                                <div class="position-relative d-inline-block">
                                                                <button type="button" class="btn btn-sm btn-outline-primary document-btn" 
                                                                        onclick="showDocumentInModal('../<?php echo htmlspecialchars($parent['id_proof_path']); ?>', 'ID Proof for <?php echo htmlspecialchars(addslashes($parent['first_name'] . ' ' . $parent['last_name'])); ?>')">
                                                                    <i class="fas fa-id-card"></i> View ID
                                                                </button>
                                                            </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($parent['child_photo_path'])): ?>
                                                                <div class="position-relative d-inline-block">
                                                                <button type="button" class="btn btn-sm btn-outline-secondary document-btn" 
                                                                        onclick="showDocumentModal('../<?php echo htmlspecialchars($parent['child_photo_path']); ?>', 'Child Photo for <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>')">
                                                                    <i class="fas fa-child"></i> Child Photo
                                                                </button>
                                                            </div>
                                                            <?php endif; ?>
                                                            <?php if (empty($parent['id_proof_path']) && empty($parent['child_photo_path'])): ?>
                                                                <span class="text-muted">No documents uploaded</span>
                                                            <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($parent['created_at'])); ?></td>
                                                    <td class="text-nowrap">
                                                        <button class="btn btn-sm btn-info me-1 mb-1" 
                                                                onclick="viewParentDetails(<?php echo htmlspecialchars(json_encode($parent)); ?>)">
                                                            <i class="fas fa-eye"></i> View
                                                        </button>
                                                        <button class="btn btn-sm btn-success me-1 mb-1" 
                                                                onclick="approveParent(<?php echo $parent['id']; ?>, '<?php echo htmlspecialchars(addslashes($parent['first_name'] . ' ' . $parent['last_name'])); ?>')"
                                                                <?php echo empty($parent['id_proof_path']) ? 'disabled title="Cannot approve without ID proof"' : ''; ?>>
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button class="btn btn-sm btn-danger mb-1" 
                                                                onclick="rejectParent(<?php echo $parent['id']; ?>, '<?php echo htmlspecialchars(addslashes($parent['first_name'] . ' ' . $parent['last_name'])); ?>')">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Active Parents Tab -->
                <div class="tab-pane fade" id="active" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5>Active Parents</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Parent Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Status</th>
                                            <th>ID Proof</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $activeParents = array_filter($parents, function($parent) {
                                            return $parent['status'] === 'active';
                                        });
                                        ?>
                                        <?php if (empty($activeParents)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    <i class="fas fa-users fa-3x mb-3 opacity-25"></i>
                                                    <p>No active parents</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($activeParents as $parent): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($parent['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($parent['phone'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <?php if ($parent['children_count'] > 0): ?>
                                                            <span class="badge bg-info"><?php echo $parent['children_count']; ?></span>
                                                            <small class="d-block text-muted"><?php echo htmlspecialchars($parent['children_names']); ?></small>
                                                        <?php else: ?>
                                                            <span class="text-muted">No children</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editParent(<?php echo htmlspecialchars(json_encode($parent)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteParent(<?php echo $parent['id']; ?>, '<?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Parent Modal -->
    <div class="modal fade" id="editParentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Parent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editParentForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit_parent">
                    <input type="hidden" name="parent_id" id="edit_parent_id">
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <div class="position-relative d-inline-block">
                                <img id="currentParentPhoto" src="" class="rounded-circle border mb-2" style="width: 100px; height: 100px; object-fit: cover;" alt="Current Photo">
                                <div class="position-absolute bottom-0 end-0">
                                    <label for="profile_photo" class="btn btn-sm btn-primary rounded-circle p-2" style="cursor: pointer;">
                                        <i class="fas fa-camera"></i>
                                        <input type="file" id="profile_photo" name="profile_photo" accept="image/*" class="d-none" onchange="previewPhoto(this, 'currentParentPhoto')">
                                    </label>
                                </div>
                            </div>
                            <div class="small text-muted">Click the camera icon to change photo</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" id="edit_phone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="edit_address" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Profile Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhoto" src="" alt="Profile Photo" class="img-fluid rounded modal-photo">
                </div>
            </div>
        </div>
    </div>

    <!-- View Parent Details Modal -->
    <div class="modal fade" id="viewParentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Parent Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-3 text-center">
                            <div class="parent-avatar-lg mb-3" id="detailProfilePhoto" style="cursor: pointer;">
                                <i class="fas fa-user fa-5x text-muted"></i>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="#" class="btn btn-sm btn-outline-primary" id="viewIdProof">
                                    <i class="fas fa-id-card me-1"></i> View ID Proof
                                </a>
                                <a href="#" class="btn btn-sm btn-outline-secondary mt-1" id="viewChildPhoto">
                                    <i class="fas fa-child me-1"></i> View Child Photo
                                </a>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <h4 id="detailParentName" class="mb-3"></h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Email:</strong> <span id="detailEmail"></span></p>
                                    <p><strong>Phone:</strong> <span id="detailPhone"></span></p>
                                    <p><strong>Status:</strong> <span id="detailStatus" class="badge"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Registration Date:</strong> <span id="detailRegDate"></span></p>
                                    <p><strong>Children:</strong> <span id="detailChildren" class="badge bg-info"></span></p>
                                    <p><strong>Last Login:</strong> <span id="detailLastLogin"></span></p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <h5>Address</h5>
                                <p id="detailAddress" class="text-muted">No address provided</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Verification Documents</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h6>ID Proof</h6>
                                    <div class="document-preview" id="idProofPreview">
                                        <p class="text-muted">No ID proof uploaded</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Child Photo</h6>
                                    <div class="document-preview" id="childPhotoPreview">
                                        <p class="text-muted">No child photo uploaded</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="approveFromModal">
                        <i class="fas fa-check me-1"></i> Approve Parent
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Modal -->
    <div class="modal fade" id="documentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentModalTitle">Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="documentError" class="alert alert-danger" style="display: none; margin-bottom: 20px;"></div>
                    <img id="documentImage" src="" alt="Document" class="img-fluid mb-3" style="max-height: 70vh; display: none;">
                    <div id="documentPdf" style="display: none; width: 100%; height: 70vh;">
                        <iframe id="pdfViewer" src="" width="100%" height="100%" style="border: none;"></iframe>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="downloadDocument" class="btn btn-primary" download style="display: none;">
                        <i class="fas fa-download me-1"></i> Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Function to check if file exists
        function checkFileExists(url, callback) {
            fetch(url, { method: 'HEAD' })
                .then(response => callback(response.ok))
                .catch(() => callback(false));
        }

        // Function to show document in modal
        function showDocumentInModal(filePath, title) {
            const modal = new bootstrap.Modal(document.getElementById('documentModal'));
            const modalTitle = document.getElementById('documentModalTitle');
            const documentImage = document.getElementById('documentImage');
            const documentPdf = document.getElementById('documentPdf');
            const pdfViewer = document.getElementById('pdfViewer');
            const downloadLink = document.getElementById('downloadDocument');
            const errorMessage = document.getElementById('documentError');

            // Show loading state
            modalTitle.textContent = 'Loading...';
            documentImage.style.display = 'none';
            documentPdf.style.display = 'none';
            errorMessage.style.display = 'none';
            
            // Check if file exists
            checkFileExists(filePath, (exists) => {
                if (!exists) {
                    modalTitle.textContent = 'Document Not Found';
                    errorMessage.textContent = 'The requested document could not be found at: ' + filePath;
                    errorMessage.style.display = 'block';
                    downloadLink.style.display = 'none';
                    modal.show();
                    return;
                }
                
                // File exists, proceed to display
                modalTitle.textContent = title;
                
                // Check file extension
                const ext = filePath.split('.').pop().toLowerCase();
                
                if (ext === 'pdf') {
                    // For PDF files
                    pdfViewer.src = filePath + (filePath.includes('?') ? '&' : '?') + 't=' + new Date().getTime();
                    documentPdf.style.display = 'block';
                } else if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
                    // For image files
                    documentImage.src = filePath + (filePath.includes('?') ? '&' : '?') + 't=' + new Date().getTime();
                    documentImage.style.display = 'block';
                    documentImage.onerror = function() {
                        this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTkgM0g1QTMgMyAwIDAwMiA2VjE4QTMgMyAwIDAwNSAyMUgxOUEzIDMgMCAwMDIyIDE4VjlaTTkgMTlINVYxNEg5VjE5Wk05IDEySDVWOEg5VjEyWk0xMyAxOUgxMVYxN0gxM1YxOVpNMTMgMTdIMTVWMTVIMTNWMTdaTTEzIDE1SDExVjEzSDEzVjE1Wk0xMyAxM0gxMVYxMUgxM1YxM1pNMTkgMTlIMTVWMTdIMTlWMTlaTTE5IDE3SDE1VjE1SDE5VjE3Wk0xOSAxNUgxNVYxM0gxOVYxNVpNMTkgMTNIMTVWMTFIMTlWMTNaTTEzIDlIMTFWN0gxM1Y5Wk0xOSA5SDE1VjdIMTlWOVpNMTcgOUgxNVY3SDE3VjlaTTEzIDZIMTFWNEgxM1Y2Wk0xNSA2SDEzVjRIMTVWNlpNMTcgNkgxNVY0SDE3VjZaTTE5IDZIMTlWNEgxOVY2Wk0yMSA2SDE5VjRIMjFWNloiIGZpbGw9IiM2QzZDNkMiLz4KPC9zdmc+Cg==';
                        errorMessage.textContent = 'Could not load image: ' + filePath;
                        errorMessage.style.display = 'block';
                    };
                } else {
                    // For other file types, show a download link
                    documentImage.style.display = 'block';
                    documentImage.alt = 'Document preview not available';
                    documentImage.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTkgM0g1QTMgMyAwIDAwMiA2VjE4QTMgMyAwIDAwNSAyMUgxOUEzIDMgMCAwMDIyIDE4VjlaTTkgMTlINVYxNEg5VjE5Wk05IDEySDVWOEg5VjEyWk0xMyAxOUgxMVYxN0gxM1YxOVpNMTMgMTdIMTVWMTVIMTNWMTdaTTEzIDE1SDExVjEzSDEzVjE1Wk0xMyAxM0gxMVYxMUgxM1YxM1pNMTkgMTlIMTVWMTdIMTlWMTlaTTE5IDE3SDE1VjE1SDE5VjE3Wk0xOSAxNUgxNVYxM0gxOVYxNVpNMTkgMTNIMTVWMTFIMTlWMTNaTTEzIDlIMTFWN0gxM1Y5Wk0xOSA5SDE1VjdIMTlWOVpNMTcgOUgxNVY3SDE3VjlaTTEzIDZIMTFWNEgxM1Y2Wk0xNSA2SDEzVjRIMTVWNlpNMTcgNkgxNVY0SDE3VjZaTTE5IDZIMTlWNEgxOVY2Wk0yMSA2SDE5VjRIMjFWNloiIGZpbGw9IiM2QzZDNkMiLz4KPC9zdmc+Cg==';
                }
                
                // Set up download link
                downloadLink.href = filePath;
                downloadLink.download = filePath.split('/').pop();
                downloadLink.style.display = 'inline-block';
                
                // Show the modal
                modal.show();
            });
        }

        function approveParent(parentId, parentName) {
            if (confirm(`Are you sure you want to approve ${parentName}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_parent">
                    <input type="hidden" name="parent_id" value="${parentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function rejectParent(parentId, parentName) {
            if (confirm(`Are you sure you want to reject ${parentName}? This will deactivate their account.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reject_parent">
                    <input type="hidden" name="parent_id" value="${parentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function editParent(parent) {
            document.getElementById('edit_parent_id').value = parent.id;
            document.getElementById('edit_first_name').value = parent.first_name;
            document.getElementById('edit_last_name').value = parent.last_name;
            document.getElementById('edit_email').value = parent.email;
            document.getElementById('edit_phone').value = parent.phone || '';
            document.getElementById('edit_address').value = parent.address || '';
            
            // Set current photo or default avatar
            const currentPhoto = document.getElementById('currentParentPhoto');
            if (parent.profile_photo_path) {
                currentPhoto.src = '../' + parent.profile_photo_path;
            } else {
                currentPhoto.src = 'https://ui-avatars.com/api/?name=' + 
                    encodeURIComponent(parent.first_name + ' ' + parent.last_name) + 
                    '&size=200&background=random';
            }
            
            new bootstrap.Modal(document.getElementById('editParentModal')).show();
        }
        
        function previewPhoto(input, imgId) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(imgId).src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }
        
        function viewParentDetails(parent) {
            // Update parent photo in view modal
            const parentPhoto = document.getElementById('detailProfilePhoto');
            if (parent.profile_photo_path) {
                parentPhoto.innerHTML = `<img src="../${parent.profile_photo_path}" 
                    class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">`;
            } else {
                parentPhoto.innerHTML = '<i class="fas fa-user fa-5x text-muted"></i>';
            }
            // Format registration date
            const regDate = new Date(parent.created_at);
            const formattedDate = regDate.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
            
            // Update modal with parent data
            document.getElementById('detailParentName').textContent = `${parent.first_name} ${parent.last_name}`;
            document.getElementById('detailEmail').textContent = parent.email || 'N/A';
            document.getElementById('detailPhone').textContent = parent.phone || 'N/A';
            document.getElementById('detailAddress').textContent = parent.address || 'No address provided';
            document.getElementById('detailRegDate').textContent = formattedDate;
            document.getElementById('detailChildren').textContent = parent.children_count || 0;
            
            // Set status badge
            const statusBadge = document.getElementById('detailStatus');
            statusBadge.textContent = parent.status.charAt(0).toUpperCase() + parent.status.slice(1);
            statusBadge.className = 'badge ' + (
                parent.status === 'active' ? 'bg-success' : 
                parent.status === 'pending' ? 'bg-warning' : 'bg-secondary'
            );
            
            // Profile photo already handled at the beginning of the function
            
            // Handle document previews
            const idProofPreview = document.getElementById('idProofPreview');
            const childPhotoPreview = document.getElementById('childPhotoPreview');
            
            // Function to check if file exists
            function checkFileExists(url, callback) {
                fetch(url, { method: 'HEAD' })
                    .then(response => callback(response.ok))
                    .catch(() => callback(false));
            }
            
            // Handle ID Proof
            if (parent.id_proof_path) {
                // Construct the correct path for the ID proof
                const idProofPath = `../${parent.id_proof_path}`.replace(/\\/g, '/');
                const ext = parent.id_proof_path.split('.').pop().toLowerCase();
                
                checkFileExists(idProofPath, (exists) => {
                    if (exists) {
                        if (['jpg', 'jpeg', 'png', 'gif', 'pdf'].includes(ext)) {
                            idProofPreview.innerHTML = `
                                <div style="cursor: pointer;" onclick="showDocumentInModal('${idProofPath}', 'ID Proof')">
                                    <img src="${ext === 'pdf' ? '../assets/images/pdf-icon.png' : idProofPath}" class="img-thumbnail" style="max-height: 200px;" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'alert alert-warning\'>Failed to load preview</div>';" />
                                </div>
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); showDocumentInModal('${idProofPath}', 'ID Proof')">
                                        <i class="fas fa-expand me-1"></i> View Full Size
                                    </button>
                                    <a href="${idProofPath}" class="btn btn-sm btn-outline-secondary" download>
                                        <i class="fas fa-download me-1"></i> Download
                                    </a>
                                </div>`;
                        } else {
                            idProofPreview.innerHTML = `
                                <div class="alert alert-info">
                                    <i class="fas fa-file me-2"></i> ID Proof (${ext.toUpperCase()})
                                    <a href="${idProofPath}" class="btn btn-sm btn-outline-primary float-end" download>
                                        <i class="fas fa-download me-1"></i> Download
                                    </a>
                                    <button class="btn btn-sm btn-outline-secondary float-end me-2" onclick="showDocumentInModal('${idProofPath}', 'ID Proof')">
                                        <i class="fas fa-eye me-1"></i> View
                                    </button>
                                </div>`;
                        }
                    } else {
                        idProofPreview.innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i> ID Proof file not found
                            </div>`;
                    }
                    
                    // Update the view ID proof button
                    document.getElementById('viewIdProof').onclick = (e) => {
                        e.preventDefault();
                        if (exists) {
                            showDocumentInModal(idProofPath, 'ID Proof');
                        }
                    };
                    
                    // Make ID proof preview clickable
                    const idProofImg = idProofPreview.querySelector('img');
                    if (idProofImg) {
                        idProofImg.style.cursor = 'pointer';
                        idProofImg.addEventListener('click', (e) => {
                            e.preventDefault();
                            showDocumentInModal(idProofPath, 'ID Proof');
                        });
                    }
                });
            } else {
                idProofPreview.innerHTML = '<div class="alert alert-secondary">No ID proof uploaded</div>';
                document.getElementById('viewIdProof').classList.add('disabled');
            }
            
            // Handle Child Photo
            if (parent.child_photo_path) {
                // Construct the correct path for the child photo
                const childPhotoPath = `../${parent.child_photo_path}`.replace(/\\/g, '/');
                
                checkFileExists(childPhotoPath, (exists) => {
                    if (exists) {
                        const ext = childPhotoPath.split('.').pop().toLowerCase();
                        childPhotoPreview.innerHTML = `
                            <div style="cursor: pointer;" onclick="showDocumentInModal('${childPhotoPath}', 'Child Photo')">
                                <img src="${childPhotoPath}" class="img-thumbnail" style="max-height: 200px;" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'alert alert-warning\'>Failed to load preview</div>';" />
                            </div>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); showDocumentInModal('${childPhotoPath}', 'Child Photo')">
                                    <i class="fas fa-expand me-1"></i> View Full Size
                                </button>
                                <a href="${childPhotoPath}" class="btn btn-sm btn-outline-secondary" download>
                                    <i class="fas fa-download me-1"></i> Download
                                </a>
                            </div>`;
                    } else {
                        childPhotoPreview.innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i> Child photo not found
                            </div>`;
                    }
                    
                    // Update the view child photo button
                    document.getElementById('viewChildPhoto').onclick = (e) => {
                        e.preventDefault();
                        if (exists) {
                            showDocumentInModal(childPhotoPath, 'Child Photo');
                        }
                    };
                    
                    // Make child photo preview clickable
                    const childPhotoImg = childPhotoPreview.querySelector('img');
                    if (childPhotoImg) {
                        childPhotoImg.style.cursor = 'pointer';
                        childPhotoImg.addEventListener('click', (e) => {
                            e.preventDefault();
                            showDocumentInModal(childPhotoPath, 'Child Photo');
                        });
                    }
                });
            }
            
            // Set up approve button
            document.getElementById('approveFromModal').onclick = () => {
                if (confirm(`Are you sure you want to approve ${parent.first_name} ${parent.last_name}?`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="approve_parent">
                        <input type="hidden" name="parent_id" value="${parent.id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            };
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('viewParentModal'));
            modal.show();
        }
        
        function deleteParent(parentId, parentName) {
            if (confirm(`Are you sure you want to delete ${parentName}? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_parent">
                    <input type="hidden" name="parent_id" value="${parentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Photo modal function
        function showPhotoModal(photoUrl, title) {
            const modal = new bootstrap.Modal(document.getElementById('photoModal'));
            document.getElementById('photoModalLabel').textContent = title;
            document.getElementById('modalPhoto').src = photoUrl;
            modal.show();
        }
        
        function showDocumentModal(docUrl, title) {
            const modal = new bootstrap.Modal(document.getElementById('documentModal'));
            const documentImage = document.getElementById('documentImage');
            const documentPdf = document.getElementById('documentPdf');
            const pdfViewer = document.getElementById('pdfViewer');
            const downloadBtn = document.getElementById('downloadDocument');
            
            document.getElementById('documentModalTitle').textContent = title;
            downloadBtn.href = docUrl;
            
            // Check if it's a PDF
            if (docUrl.toLowerCase().endsWith('.pdf')) {
                documentImage.classList.add('d-none');
                documentPdf.classList.remove('d-none');
                pdfViewer.src = docUrl;
            } else {
                // It's an image
                documentPdf.classList.add('d-none');
                documentImage.classList.remove('d-none');
                documentImage.src = docUrl;
            }
            
            modal.show();
        }
        
        // Handle success messages
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success) {
            let message = '';
            switch (success) {
                case 'parent_approved':
                    message = 'Parent approved successfully!';
                    break;
                case 'parent_rejected':
                    message = 'Parent rejected successfully!';
                    break;
                case 'parent_updated':
                    message = 'Parent updated successfully!';
                    break;
                case 'parent_deleted':
                    message = 'Parent deleted successfully!';
                    break;
            }
            if (message) {
                showNotification(message, 'success');
            }
        }
    </script>
    <!-- Document Viewer Modal -->
    <div class="modal fade" id="documentViewerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="documentViewerTitle">Document Viewer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="documentViewerContent">
                        <img id="documentImage" src="" class="img-fluid" alt="Document" style="max-height: 70vh;">
                        <div id="documentLoading" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading document...</p>
                        </div>
                        <div id="documentError" class="text-danger d-none">
                            <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                            <p>Error loading document. Please try again.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a id="downloadDocument" href="#" class="btn btn-primary" download>
                        <i class="fas fa-download me-2"></i>Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Document viewer function
    function viewDocument(documentUrl, title, documentType = 'image') {
        const modal = new bootstrap.Modal(document.getElementById('documentViewerModal'));
        const modalTitle = document.getElementById('documentViewerTitle');
        const documentImage = document.getElementById('documentImage');
        const downloadBtn = document.getElementById('downloadDocument');
        const loadingEl = document.getElementById('documentLoading');
        const errorEl = document.getElementById('documentError');
        
        // Reset modal state
        loadingEl.style.display = 'block';
        errorEl.classList.add('d-none');
        documentImage.style.display = 'none';
        
        // Set modal title
        modalTitle.textContent = title || 'Document Viewer';
        
        // Set download link
        downloadBtn.href = documentUrl;
        downloadBtn.download = documentUrl.split('/').pop() || 'document';
        
        // Handle different document types
        if (documentType === 'image') {
            documentImage.onload = function() {
                loadingEl.style.display = 'none';
                documentImage.style.display = 'block';
            };
            
            documentImage.onerror = function() {
                loadingEl.style.display = 'none';
                errorEl.classList.remove('d-none');
            };
            
            documentImage.src = documentUrl;
        } else {
            // Handle other document types (PDF, etc.) if needed
            loadingEl.style.display = 'none';
            errorEl.classList.remove('d-none');
            errorEl.querySelector('p').textContent = 'Unsupported document type';
        }
        
        // Show the modal
        modal.show();
    }

    // Update the viewIdProof and viewChildProof functions
    function viewIdProof(imageUrl, parentName) {
        if (!imageUrl || imageUrl === '#') {
            alert('No ID proof available for ' + parentName);
            return;
        }
        viewDocument(imageUrl, 'ID Proof - ' + parentName);
    }

    function viewChildProof(imageUrl, childName) {
        if (!imageUrl || imageUrl === '#') {
            alert('No child proof available for ' + childName);
            return;
        }
        viewDocument(imageUrl, 'Child Proof - ' + childName);
    }
    </script>

    <script src="../assets/js/mobile_nav.js"></script>
    <?php include 'mobile_nav.php'; ?>
</body>
</html>
