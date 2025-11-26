<?php
session_start();
require_once '../config/database.php';
require_once '../core/Mailer.php';
$appConfig = require '../config/config.php';

function sendParentApprovalEmail($parent, $config) {
    $appName = $config['app_name'] ?? 'Gumamela Daycare Center';
    $appUrl = rtrim($config['app_url'] ?? 'http://localhost', '/');
    $loginLink = $appUrl . '/index.php';
    
    $subject = $appName . ' Registration Approved';
    
    $html = "
        <html>
        <body style=\"font-family: Arial, sans-serif; color: #333;\">
            <h2 style=\"color:#E83E8C;\">Hi {$parent['first_name']} {$parent['last_name']},</h2>
            <p>Great news! Your parent account with <strong>{$appName}</strong> has been reviewed and approved by our administrator.</p>
            <p>You can now log in using the credentials you created during registration to access the parent portal, track your child's progress, view schedules, and stay updated with announcements.</p>
            <p style=\"margin: 25px 0;\">
                <a href=\"{$loginLink}\" style=\"background-color:#6f42c1;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none;\">Log in to your account</a>
            </p>
            <p>If you have any questions or notice anything incorrect with your profile, please reply to this email so we can assist you right away.</p>
            <p>Welcome to the Gumamela Daycare family!</p>
            <p>Warm regards,<br>The {$appName} Team</p>
        </body>
        </html>
    ";
    
    $text = "Hi {$parent['first_name']} {$parent['last_name']},\n\n"
        . "Great news! Your parent account with {$appName} has been reviewed and approved by our administrator.\n\n"
        . "You can now log in using the credentials you created during registration: {$loginLink}\n\n"
        . "If you have any questions or notice anything incorrect with your profile, please reply to this email so we can assist you right away.\n\n"
        . "Welcome to the Gumamela Daycare family!\n\n"
        . "The {$appName} Team";
    
    $mailer = new Mailer($config);
    return $mailer->send(
        ['email' => $parent['email'], 'name' => $parent['first_name'] . ' ' . $parent['last_name']],
        $subject,
        $html,
        $text
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $conn = getDBConnection();
    $redirectParentId = isset($_POST['return_to']) ? (int)$_POST['return_to'] : null;
    $redirectUrl = 'parent_verification.php' . ($redirectParentId ? '?parent_id=' . $redirectParentId : '');
    
    if (!$conn) {
        $_SESSION['verification_error'] = 'Unable to connect to the database. Please try again.';
        header('Location: ' . $redirectUrl);
        exit();
    }
    
    switch ($_POST['action']) {
        case 'approve_parent':
            $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
            
            if (!$parentId) {
                $_SESSION['verification_error'] = 'Invalid parent specified.';
                break;
            }
            
            $stmt = $conn->prepare("SELECT id, first_name, last_name, email, status FROM users WHERE id = ? AND user_type = 'parent'");
            $stmt->execute([$parentId]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$parent) {
                $_SESSION['verification_error'] = 'Parent record was not found.';
                break;
            }
            
            if ($parent['status'] === 'active') {
                $_SESSION['verification_info'] = 'This parent account is already active.';
                break;
            }
            
            $update = $conn->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
            if ($update->execute([$parentId])) {
                $emailSent = sendParentApprovalEmail($parent, $appConfig);
                if ($emailSent) {
                    $_SESSION['verification_success'] = 'Parent account for ' . $parent['first_name'] . ' ' . $parent['last_name'] . ' was approved and an email notification was sent.';
                } else {
                    $_SESSION['verification_success'] = 'Parent account for ' . $parent['first_name'] . ' ' . $parent['last_name'] . ' was approved.';
                    $_SESSION['verification_warning'] = 'Account approved, but we could not send the notification email automatically. Please notify the parent manually.';
                }
            } else {
                $_SESSION['verification_error'] = 'Unable to update the parent status. Please try again.';
            }
            break;
    }
    
    header('Location: ' . $redirectUrl);
    exit();
}
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$flashSuccess = $_SESSION['verification_success'] ?? null;
$flashError = $_SESSION['verification_error'] ?? null;
$flashWarning = $_SESSION['verification_warning'] ?? null;
$flashInfo = $_SESSION['verification_info'] ?? null;
unset($_SESSION['verification_success'], $_SESSION['verification_error'], $_SESSION['verification_warning'], $_SESSION['verification_info']);

// Get search parameters
$search = $_GET['search'] ?? '';
$parent_id = $_GET['parent_id'] ?? '';

try {
    $conn = getDBConnection();
    
    // Get all parents with their children and guardians
    if ($parent_id) {
        // Get specific parent details with profile photo
        $stmt = $conn->prepare("
            SELECT u.*, 
                   COUNT(DISTINCT s.id) as children_count,
                   COUNT(DISTINCT g.id) as guardians_count
            FROM users u 
            LEFT JOIN students s ON u.id = s.parent_id AND s.status = 'active'
            LEFT JOIN guardians g ON u.id = g.parent_id
            WHERE u.user_type = 'parent' AND u.id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$parent_id]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get children details
        $stmt = $conn->prepare("SELECT * FROM students WHERE parent_id = ? ORDER BY first_name");
        $stmt->execute([$parent_id]);
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get guardians details
        $stmt = $conn->prepare("SELECT * FROM guardians WHERE parent_id = ? ORDER BY first_name");
        $stmt->execute([$parent_id]);
        $guardians = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Get all parents for listing
        $whereClause = "WHERE u.user_type = 'parent'";
        $params = [];
        
        if ($search) {
            $whereClause .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $searchParam = "%$search%";
            $params = [$searchParam, $searchParam, $searchParam, $searchParam];
        }
        
        $stmt = $conn->prepare("
            SELECT u.*, 
                   COUNT(DISTINCT s.id) as children_count,
                   COUNT(DISTINCT g.id) as guardians_count
            FROM users u 
            LEFT JOIN students s ON u.id = s.parent_id AND s.status = 'active'
            LEFT JOIN guardians g ON u.id = g.parent_id
            $whereClause
            GROUP BY u.id
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute($params);
        $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $parents = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Verification - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <link href="../assets/css/mobile_nav.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-user-check me-2"></i>Parent Verification</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Parent Verification</li>
                    </ol>
                </nav>
            </div>
            
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($flashSuccess); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($flashWarning): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($flashWarning); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($flashInfo): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($flashInfo); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($flashError): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-times-circle me-2"></i><?php echo htmlspecialchars($flashError); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($parent_id && isset($parent)): ?>
                <!-- Parent Details View -->
                <div class="row">
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-user me-2"></i>Parent Information</h5>
                                <a href="parent_verification.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to List
                                </a>
                            </div>
                            <div class="card-body text-center">
                                <div class="verification-parent-avatar mb-3">
                                    <?php if (!empty($parent['profile_photo_path'])): ?>
                                        <img src="../<?php echo htmlspecialchars($parent['profile_photo_path']); ?>" alt="Parent Photo" class="verification-parent-photo">
                                    <?php else: ?>
                                        <div class="verification-parent-photo-placeholder">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <h4><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></h4>
                                <p class="text-muted">Parent</p>
                                <div class="parent-contact-info">
                                    <p><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($parent['email']); ?></p>
                                    <p><i class="fas fa-phone me-2"></i> <?php echo !empty($parent['phone']) ? htmlspecialchars($parent['phone']) : 'Not provided'; ?></p>
                                </div>
                                
                                <!-- ID Proof Section -->
                                <div class="mt-4">
                                    <h6><i class="fas fa-id-card me-2"></i>ID Proof</h6>
                                    <?php if (!empty($parent['id_proof_path'])): 
                                        $idProofExt = strtolower(pathinfo($parent['id_proof_path'], PATHINFO_EXTENSION));
                                        $isImage = in_array($idProofExt, ['jpg', 'jpeg', 'png', 'gif']);
                                    ?>
                                        <div class="document-preview">
                                            <?php if ($isImage): ?>
                                                <?php 
                                                    $idProofPath = ltrim($parent['id_proof_path'], '/');
                                                    $idProofPath = str_replace('../', '', $idProofPath);
                                                ?>
                                                <img src="../<?php echo htmlspecialchars($idProofPath); ?>" 
                                                     class="img-thumbnail mb-2" 
                                                     style="max-height: 150px; cursor: pointer;"
                                                     onclick="showDocumentModal('/<?php echo htmlspecialchars($idProofPath); ?>', 'ID Proof')">
                                            <?php else: ?>
                                                <div class="alert alert-info p-2">
                                                    <i class="fas fa-file-pdf fa-2x mb-2"></i>
                                                    <p class="mb-1">Document uploaded</p>
                                                </div>
                                            <?php endif; ?>
                                            <a href="/<?php echo htmlspecialchars($idProofPath); ?>" 
                                               target="_blank" 
                                               class="btn btn-sm btn-outline-primary w-100">
                                                <i class="fas <?php echo $isImage ? 'fa-expand' : 'fa-download'; ?> me-1"></i>
                                                <?php echo $isImage ? 'View Full Size' : 'Download'; ?>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning p-2">
                                            <i class="fas fa-exclamation-triangle me-1"></i> No ID proof uploaded
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Child Photo Section -->
                                <div class="mt-4">
                                    <h6><i class="fas fa-child me-2"></i>Child Photo</h6>
                                    <?php if (!empty($parent['child_photo_path'])): ?>
                                        <div class="document-preview">
                                            <?php 
                                                $childPhotoPath = ltrim($parent['child_photo_path'], '/');
                                                $childPhotoPath = str_replace('../', '', $childPhotoPath);
                                            ?>
                                            <img src="/<?php echo htmlspecialchars($childPhotoPath); ?>" 
                                                 class="img-thumbnail mb-2" 
                                                 style="max-height: 150px; cursor: pointer;"
                                                 onerror="this.onerror=null; this.src='../assets/img/placeholder.jpg'"
                                                 onclick="showDocumentModal('/<?php echo htmlspecialchars($childPhotoPath); ?>', 'Child Photo')">
                                            <a href="/<?php echo htmlspecialchars($childPhotoPath); ?>" 
                                               target="_blank" 
                                               class="btn btn-sm btn-outline-primary w-100">
                                                <i class="fas fa-expand me-1"></i> View Full Size
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning p-2">
                                            <i class="fas fa-exclamation-triangle me-1"></i> No child photo uploaded
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="parent-details mt-3">
                                    <div class="detail-item">
                                        <strong>Phone:</strong>
                                        <span><?php echo htmlspecialchars($parent['phone'] ?? 'Not provided'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <strong>Address:</strong>
                                        <span><?php echo htmlspecialchars($parent['address'] ?? 'Not provided'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <strong>Status:</strong>
                                        <span class="badge <?php echo $parent['status'] == 'active' ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo ucfirst($parent['status']); ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <strong>Member Since:</strong>
                                        <span><?php echo date('M d, Y', strtotime($parent['created_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <!-- ID Picture Section -->
                                <div class="id-picture-section mt-4">
                                    <h6><i class="fas fa-id-card me-2"></i>Identification Document</h6>
                                    <?php if (!empty($parent['id_picture_path'])): ?>
                                        <div class="id-picture-container">
                                            <img src="../<?php echo htmlspecialchars($parent['id_picture_path']); ?>" alt="ID Picture" class="id-picture">
                                            <button class="btn btn-sm btn-outline-primary mt-2" onclick="viewFullImage('../<?php echo htmlspecialchars($parent['id_picture_path']); ?>')">
                                                <i class="fas fa-expand me-1"></i>View Full Size
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-id-picture">
                                            <i class="fas fa-id-card fa-3x text-muted mb-2"></i>
                                            <p class="text-muted">No ID picture uploaded</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($parent['status'] !== 'active'): ?>
                                    <form method="POST" class="mt-4">
                                        <input type="hidden" name="action" value="approve_parent">
                                        <input type="hidden" name="parent_id" value="<?php echo $parent['id']; ?>">
                                        <input type="hidden" name="return_to" value="<?php echo $parent['id']; ?>">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fas fa-check-circle me-2"></i>Approve Parent Account
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-success mt-4" role="alert">
                                        <i class="fas fa-shield-check me-2"></i>This parent account has already been verified.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-8">
                        <!-- Children Section -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-child me-2"></i>Children (<?php echo count($children); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($children)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-child fa-2x mb-2 opacity-25"></i>
                                        <p>No children enrolled</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($children as $child): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="child-verification-card">
                                                    <div class="child-avatar">
                                                        <?php if (!empty($child['photo_path'])): ?>
                                                            <img src="../<?php echo htmlspecialchars($child['photo_path']); ?>" alt="Child Photo" class="child-photo">
                                                        <?php else: ?>
                                                            <i class="fas fa-child"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="child-info">
                                                        <h6><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h6>
                                                        <p class="mb-1">
                                                            <small class="text-muted">Age: <?php echo date('Y') - date('Y', strtotime($child['date_of_birth'])); ?> years</small>
                                                        </p>
                                                        <p class="mb-1">
                                                            <small class="text-muted">DOB: <?php echo date('M d, Y', strtotime($child['date_of_birth'])); ?></small>
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
                        
                        <!-- Guardians Section -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-users me-2"></i>Authorized Guardians (<?php echo count($guardians); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($guardians)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-users fa-2x mb-2 opacity-25"></i>
                                        <p>No guardians added</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($guardians as $guardian): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="guardian-verification-card">
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
                                                        <span class="badge <?php echo $guardian['is_authorized_pickup'] ? 'bg-success' : 'bg-warning'; ?>">
                                                            <?php echo $guardian['is_authorized_pickup'] ? 'Authorized Pickup' : 'Contact Only'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Parents List View -->
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5><i class="fas fa-users me-2"></i>Parent Directory</h5>
                                <small class="text-muted">Click on a parent to view their profile and authorized guardians</small>
                            </div>
                            <div class="col-auto">
                                <form method="GET" class="d-flex">
                                    <input type="text" class="form-control me-2" name="search" placeholder="Search parents..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($parents)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-users fa-3x mb-3 opacity-25"></i>
                                <h5>No parents found</h5>
                                <?php if ($search): ?>
                                    <p>Try adjusting your search criteria</p>
                                    <a href="parent_verification.php" class="btn btn-outline-primary">Clear Search</a>
                                <?php else: ?>
                                    <p>No parents are registered yet</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($parents as $parent): ?>
                                    <div class="col-lg-6 col-xl-4 mb-3">
                                        <div class="parent-card" onclick="window.location.href='parent_verification.php?parent_id=<?php echo $parent['id']; ?>'">
                                            <div class="parent-avatar">
                                                <?php if (!empty($parent['profile_photo_path'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($parent['profile_photo_path']); ?>" alt="Parent Photo" class="parent-photo">
                                                <?php else: ?>
                                                    <i class="fas fa-user"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="parent-info">
                                                <h6><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></h6>
                                                <p class="mb-1">
                                                    <small class="text-muted"><?php echo htmlspecialchars($parent['email']); ?></small>
                                                </p>
                                                <p class="mb-1">
                                                    <small class="text-muted"><?php echo htmlspecialchars($parent['phone'] ?? 'No phone'); ?></small>
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <span class="badge <?php echo $parent['status'] == 'active' ? 'bg-success' : 'bg-warning'; ?>">
                                                        <?php echo ucfirst($parent['status']); ?>
                                                    </span>
                                                    <div class="parent-stats">
                                                        <small class="text-muted">
                                                            <?php echo $parent['children_count']; ?> child<?php echo $parent['children_count'] != 1 ? 'ren' : ''; ?> • 
                                                            <?php echo $parent['guardians_count']; ?> guardian<?php echo $parent['guardians_count'] != 1 ? 's' : ''; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
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
                    <img id="documentImage" src="" class="img-fluid" style="max-height: 70vh; display: none;">
                    <iframe id="documentPdf" src="" style="width: 100%; height: 70vh; display: none;" frameborder="0"></iframe>
                    <div id="documentUnsupported" class="alert alert-info" style="display: none;">
                        <p>This document type is not supported for preview. Please download the file to view it.</p>
                        <a id="downloadDocument" href="#" class="btn btn-primary">
                            <i class="fas fa-download me-2"></i>Download Document
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <a id="documentDownloadBtn" href="#" class="btn btn-outline-primary me-auto">
                        <i class="fas fa-download me-2"></i>Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Document modal functionality
        function showDocumentModal(relativePath, title) {
            const modal = new bootstrap.Modal(document.getElementById('documentModal'));
            const modalTitle = document.getElementById('documentModalTitle');
            const documentImage = document.getElementById('documentImage');
            const documentPdf = document.getElementById('documentPdf');
            const documentUnsupported = document.getElementById('documentUnsupported');
            const downloadBtn = document.getElementById('documentDownloadBtn');
            const downloadLink = document.getElementById('downloadDocument');
            
            // Ensure the path is properly formatted
            let fileUrl = relativePath.startsWith('http') ? relativePath : 
                         relativePath.startsWith('/') ? relativePath : 
                         '/' + relativePath.replace(/^\.\.\//, '');
            
            // Set title
            modalTitle.textContent = title;
            
            // Reset display
            documentImage.style.display = 'none';
            documentPdf.style.display = 'none';
            documentUnsupported.style.display = 'none';
            
            // Set download links with proper URL encoding
            const fullDownloadUrl = fileUrl.startsWith('http') ? fileUrl : '..' + fileUrl;
            downloadBtn.href = fullDownloadUrl;
            if (downloadLink) downloadLink.href = fullDownloadUrl;
            
            // Check file type and display accordingly
            const cleanPath = fileUrl.split('?')[0]; // Remove query parameters
            const ext = cleanPath.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(ext);
            const isPdf = ext === 'pdf';
            
            // Create a timestamp to prevent caching issues
            const timestamp = new Date().getTime();
            const cacheBuster = fileUrl.includes('?') ? '&' : '?';
            
            if (isImage) {
                const imageUrl = fileUrl + cacheBuster + 't=' + timestamp;
                documentImage.src = imageUrl;
                documentImage.style.display = 'inline-block';
                documentImage.onerror = function() {
                    console.error('Failed to load image:', imageUrl);
                    documentImage.style.display = 'none';
                    documentUnsupported.style.display = 'block';
                    documentUnsupported.innerHTML = `
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <p>Unable to load the document. The file may have been moved or deleted.</p>
                        <a href="${fullDownloadUrl}" class="btn btn-primary">
                            <i class="fas fa-download me-2"></i>Try Downloading Instead
                        </a>
                    `;
                };
            } else if (isPdf) {
                documentPdf.src = fileUrl + '#view=FitH' + cacheBuster + 't=' + timestamp;
                documentPdf.style.display = 'block';
            } else {
                documentUnsupported.style.display = 'block';
            }
            
            // Show the modal
            modal.show();
            
            // Log for debugging
            console.log('Opening document:', {
                title: title,
                path: fileUrl,
                type: isImage ? 'image' : (isPdf ? 'pdf' : 'unknown'),
                fullUrl: fullDownloadUrl
            });
        }
        
        // Make sure the modal is hidden when closed
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('documentModal');
            modal.addEventListener('hidden.bs.modal', function () {
                // Clear the PDF iframe source to stop any ongoing loading
                document.getElementById('documentPdf').src = '';
            });
        });
    </script>
    
    <script>
        function viewFullImage(imageSrc) {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">ID Document - Full Size</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="${imageSrc}" alt="ID Document" style="max-width: 100%; max-height: 80vh; object-fit: contain;">
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            modal.addEventListener('hidden.bs.modal', () => {
                document.body.removeChild(modal);
            });
        }
    </script>
    
    <style>
        .parent-card {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 15px;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .parent-card:hover {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .parent-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #FF6B9D, #FF8E9B);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .parent-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .parent-info {
            flex: 1;
        }
        
        .child-verification-card, .guardian-verification-card {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 15px;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            height: 100%;
        }
        
        .child-avatar, .guardian-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .child-avatar {
            background: linear-gradient(135deg, #4ECDC4, #44A08D);
        }
        
        .guardian-avatar {
            background: linear-gradient(135deg, #FF6B9D, #FF8E9B);
        }
        
        .child-photo, .guardian-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .child-info, .guardian-info {
            flex: 1;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-item strong {
            color: #495057;
            font-weight: 600;
        }
        
        .id-picture-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .id-picture-container {
            text-align: center;
        }
        
        .id-picture {
            max-width: 300px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 2px solid #e9ecef;
        }
        
        .no-id-picture {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
        
        .no-id-picture i {
            color: #dee2e6;
        }
    </style>
    <script src="../assets/js/mobile_nav.js"></script>
    <?php include 'mobile_nav.php'; ?>
</body>
</html>
