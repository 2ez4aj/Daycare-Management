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
            case 'approve_parent':
                $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND user_type = 'parent'");
                $stmt->execute([$_POST['parent_id']]);
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
                $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ? AND user_type = 'parent'");
                $stmt->execute([
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['address'],
                    $_POST['parent_id']
                ]);
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
    
    // Get all parents with their children count and profile photos
    $stmt = $conn->prepare("
        SELECT u.*, 
               COUNT(s.id) as children_count,
               GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ') as children_names
        FROM users u 
        LEFT JOIN students s ON u.id = s.parent_id AND s.status = 'active'
        WHERE u.user_type = 'parent' 
        GROUP BY u.id 
        ORDER BY u.status, u.first_name, u.last_name
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
                                                <td>
                                                    <?php if ($parent['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-success" onclick="approveParent(<?php echo $parent['id']; ?>, '<?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>')">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="rejectParent(<?php echo $parent['id']; ?>, '<?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>')">
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
                                                <td colspan="6" class="text-center text-muted py-4">
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
                                                    <td><?php echo date('M d, Y', strtotime($parent['created_at'])); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-success" onclick="approveParent(<?php echo $parent['id']; ?>, '<?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>')">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="rejectParent(<?php echo $parent['id']; ?>, '<?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>')">
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
                                            <th>Children</th>
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
                <form method="POST" id="editParentForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_parent">
                        <input type="hidden" name="parent_id" id="edit_parent_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                            </div>
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
                        <button type="submit" class="btn btn-primary">Update Parent</button>
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

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
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
            
            new bootstrap.Modal(document.getElementById('editParentModal')).show();
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
        function showPhotoModal(photoPath, parentName) {
            const modal = document.getElementById('photoModal');
            const modalImg = document.getElementById('modalPhoto');
            const modalTitle = document.getElementById('modalTitle');
            
            modalImg.src = photoPath;
            modalTitle.textContent = parentName + "'s Profile Photo";
            
            new bootstrap.Modal(modal).show();
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
</body>
</html>
