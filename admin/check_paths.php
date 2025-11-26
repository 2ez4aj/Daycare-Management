<?php
require_once '../config/database.php';

// Check if user is admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    die('Unauthorized access');
}

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

// Get users with parent role
$stmt = $conn->query("SELECT id, first_name, last_name, id_proof_path, child_photo_path FROM users WHERE user_type = 'parent'");
$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Check File Paths</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Check Parent File Paths</h1>
        <div class="card mt-4">
            <div class="card-header">
                <h3>Parent Files</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>ID Proof Path</th>
                            <th>Child Photo Path</th>
                            <th>ID Proof Exists</th>
                            <th>Child Photo Exists</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parents as $parent): 
                            $idProofExists = !empty($parent['id_proof_path']) && file_exists('../' . $parent['id_proof_path']);
                            $childPhotoExists = !empty($parent['child_photo_path']) && file_exists('../' . $parent['child_photo_path']);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($parent['id']); ?></td>
                                <td><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($parent['id_proof_path']); ?></td>
                                <td><?php echo htmlspecialchars($parent['child_photo_path']); ?></td>
                                <td class="<?php echo $idProofExists ? 'table-success' : 'table-danger'; ?>">
                                    <?php echo $idProofExists ? 'Yes' : 'No'; ?>
                                </td>
                                <td class="<?php echo $childPhotoExists ? 'table-success' : 'table-danger'; ?>">
                                    <?php echo $childPhotoExists ? 'Yes' : 'No'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Upload Directory Check</h3>
            </div>
            <div class="card-body">
                <?php
                $uploadDirs = [
                    'uploads/parents/id_proofs' => 'ID Proofs',
                    'uploads/parents/child_photos' => 'Child Photos'
                ];
                
                foreach ($uploadDirs as $dir => $label):
                    $fullPath = '../' . $dir;
                    $exists = is_dir($fullPath);
                    $writable = $exists && is_writable($fullPath);
                ?>
                    <div class="mb-3">
                        <h5><?php echo htmlspecialchars($label); ?></h5>
                        <p>
                            Path: <code><?php echo htmlspecialchars($fullPath); ?></code><br>
                            Exists: <span class="badge bg-<?php echo $exists ? 'success' : 'danger'; ?>">
                                <?php echo $exists ? 'Yes' : 'No'; ?>
                            </span><br>
                            Writable: <span class="badge bg-<?php echo $writable ? 'success' : 'danger'; ?>">
                                <?php echo $writable ? 'Yes' : 'No'; ?>
                            </span>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
