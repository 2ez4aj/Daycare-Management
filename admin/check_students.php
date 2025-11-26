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
    die("Database connection failed: " . print_r($conn->errorInfo(), true));
}

// Check if students table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'students'");
if ($tableCheck->rowCount() === 0) {
    die("The 'students' table does not exist in the database.");
}

// Get all students
$query = "SELECT s.*, u.first_name as parent_first_name, u.last_name as parent_last_name 
          FROM students s 
          LEFT JOIN users u ON s.parent_id = u.id 
          ORDER BY s.status, s.first_name, s.last_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Display results
echo "<h2>Students in Database (Total: " . count($students) . ")</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>Status</th><th>Parent</th><th>Enrollment Date</th><th>Schedule ID</th></tr>";

foreach ($students as $student) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($student['id']) . "</td>";
    echo "<td>" . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($student['status'] ?? 'unknown') . "</td>";
    echo "<td>" . htmlspecialchars(($student['parent_first_name'] ?? '') . ' ' . ($student['parent_last_name'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars($student['enrollment_date'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($student['schedule_id'] ?? '') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Debug info
echo "<h3>Debug Info:</h3>";
echo "<pre>Database: gumamela_daycare</pre>";
echo "<pre>Table: students</pre>";
echo "<pre>Query: " . htmlspecialchars($query) . "</pre>";

// Show raw data
echo "<h3>Raw Data:</h3>";
echo "<pre>";
print_r($students);
echo "</pre>";
?>
