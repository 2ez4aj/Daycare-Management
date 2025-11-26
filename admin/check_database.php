<?php
require_once '../config/database.php';

// Start session and check if user is admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    die('Unauthorized access');
}

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

// Function to get table structure
function getTableStructure($conn, $tableName) {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM $tableName");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Function to count rows in a table
function countRows($conn, $tableName) {
    try {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM $tableName");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

// Get list of all tables
$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Database Checker</h1>
        <h2>Database: <?php echo $conn->query('SELECT DATABASE()')->fetchColumn(); ?></h2>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Tables in Database</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th>Row Count</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tables as $table): ?>
                        <tr>
                            <td>
                                <a href="#" onclick="toggleTable('<?php echo $table; ?>'); return false;">
                                    <?php echo htmlspecialchars($table); ?>
                                </a>
                                <div id="structure-<?php echo $table; ?>" style="display: none; margin-top: 10px;" class="table-structure">
                                    <h5>Structure:</h5>
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Field</th>
                                                <th>Type</th>
                                                <th>Null</th>
                                                <th>Key</th>
                                                <th>Default</th>
                                                <th>Extra</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $structure = getTableStructure($conn, $table);
                                            foreach ($structure as $column): 
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($column['Field']); ?></td>
                                                <td><?php echo htmlspecialchars($column['Type']); ?></td>
                                                <td><?php echo htmlspecialchars($column['Null']); ?></td>
                                                <td><?php echo htmlspecialchars($column['Key']); ?></td>
                                                <td><?php echo htmlspecialchars($column['Default']); ?></td>
                                                <td><?php echo htmlspecialchars($column['Extra']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                            <td><?php echo countRows($conn, $table); ?></td>
                            <td>
                                <?php 
                                if (count(getTableStructure($conn, $table)) > 0) {
                                    echo '<span class="badge bg-success">OK</span>';
                                } else {
                                    echo '<span class="badge bg-danger">Error</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if (in_array('students', $tables)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h3>Students Data</h3>
            </div>
            <div class="card-body">
                <?php
                try {
                    $stmt = $conn->query("SELECT * FROM students LIMIT 100");
                    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($students) > 0): ?>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($students[0]) as $column): ?>
                                        <th><?php echo htmlspecialchars($column); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <?php foreach ($student as $value): ?>
                                            <td><?php echo htmlspecialchars(substr($value, 0, 50)); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-warning">No students found in the database.</div>
                    <?php endif; ?>
                <?php } catch (Exception $e) {
                    echo '<div class="alert alert-danger">Error fetching students: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleTable(tableName) {
            const element = document.getElementById('structure-' + tableName);
            if (element.style.display === 'none') {
                element.style.display = 'block';
            } else {
                element.style.display = 'none';
            }
        }
    </script>
</body>
</html>
