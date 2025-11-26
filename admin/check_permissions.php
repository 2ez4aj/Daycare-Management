<?php
// Check if user is admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    die('Unauthorized access');
}

// Get the base path of the application
$basePath = dirname(dirname(__FILE__));

// Directories to check
$directories = [
    'uploads',
    'uploads/parents',
    'uploads/parents/id_proofs',
    'uploads/parents/child_photos',
    'assets/img'
];

// Function to check if a directory exists and is writable
function checkDirectory($basePath, $dir) {
    $fullPath = $basePath . '/' . $dir;
    $exists = file_exists($fullPath);
    $isDir = $exists ? is_dir($fullPath) : false;
    $writable = $isDir ? is_writable($fullPath) : false;
    
    return [
        'path' => $dir,
        'exists' => $exists,
        'is_directory' => $isDir,
        'writable' => $writable,
        'permissions' => $exists ? substr(sprintf('%o', fileperms($fullPath)), -4) : 'N/A'
    ];
}

// Check all directories
$results = [];
foreach ($directories as $dir) {
    $results[] = checkDirectory($basePath, $dir);
}

// Get sample files from the database
$sampleFiles = [];
try {
    require_once '../config/database.php';
    $conn = getDBConnection();
    
    // Get a sample parent with documents
    $stmt = $conn->query("SELECT id_proof_path, child_photo_path FROM users WHERE id_proof_path IS NOT NULL OR child_photo_path IS NOT NULL LIMIT 1");
    $sampleParent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sampleParent) {
        if (!empty($sampleParent['id_proof_path'])) {
            $sampleFiles[] = [
                'type' => 'ID Proof',
                'path' => $sampleParent['id_proof_path'],
                'exists' => file_exists($basePath . '/' . $sampleParent['id_proof_path'])
            ];
        }
        if (!empty($sampleParent['child_photo_path'])) {
            $sampleFiles[] = [
                'type' => 'Child Photo',
                'path' => $sampleParent['child_photo_path'],
                'exists' => file_exists($basePath . '/' . $sampleParent['child_photo_path'])
            ];
        }
    }
} catch (Exception $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Permission Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.9em;
            padding: 0.35em 0.65em;
        }
        .table th, .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>File System Permissions Check</h1>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Directory Permissions</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Directory</th>
                            <th>Status</th>
                            <th>Permissions</th>
                            <th>Writable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['path']); ?></td>
                                <td>
                                    <?php if ($result['exists'] && $result['is_directory']): ?>
                                        <span class="badge bg-success status-badge">Exists</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger status-badge">Missing</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo htmlspecialchars($result['permissions']); ?></code></td>
                                <td>
                                    <?php if ($result['writable']): ?>
                                        <span class="badge bg-success status-badge">Writable</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger status-badge">Not Writable</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if (!empty($sampleFiles)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h3>Sample Files Check</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>File Type</th>
                            <th>Path</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sampleFiles as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file['type']); ?></td>
                                <td><code><?php echo htmlspecialchars($file['path']); ?></code></td>
                                <td>
                                    <?php if ($file['exists']): ?>
                                        <span class="badge bg-success status-badge">Found</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger status-badge">Not Found</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (isset($dbError)): ?>
                    <div class="alert alert-danger mt-3">
                        <strong>Database Error:</strong> <?php echo htmlspecialchars($dbError); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Next Steps</h3>
            </div>
            <div class="card-body">
                <h5>If directories are missing or not writable:</h5>
                <ol>
                    <li>Create any missing directories listed above</li>
                    <li>Set proper permissions (recommended: 755 for directories, 644 for files)</li>
                    <li>Ensure the web server user (e.g., www-data, apache) has write access</li>
                </ol>
                
                <h5 class="mt-4">If files are not found:</h5>
                <ol>
                    <li>Verify the files exist in the specified locations</li>
                    <li>Check the database for correct file paths</li>
                    <li>Ensure the files were uploaded successfully during registration</li>
                </ol>
                
                <div class="alert alert-info mt-4">
                    <strong>Note:</strong> After fixing any issues, clear your browser cache and try accessing the parent verification page again.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
