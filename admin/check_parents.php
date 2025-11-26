<?php
require_once '../config/database.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Query to get parents with their documents
$query = "SELECT id, first_name, last_name, id_proof_path, child_photo_path FROM users WHERE user_type = 'parent' LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Parent Documents Check</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>ID</th><th>Name</th><th>ID Proof Path</th><th>Child Photo Path</th><th>ID Proof Exists</th><th>Child Photo Exists</th></tr>";

foreach ($parents as $parent) {
    $idProofExists = file_exists("../" . $parent['id_proof_path']) ? 'Yes' : 'No';
    $childPhotoExists = file_exists("../" . $parent['child_photo_path']) ? 'Yes' : 'No';
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($parent['id']) . "</td>";
    echo "<td>" . htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($parent['id_proof_path']) . "</td>";
    echo "<td>" . htmlspecialchars($parent['child_photo_path']) . "</td>";
    echo "<td>$idProofExists</td>";
    echo "<td>$childPhotoExists</td>";
    echo "</tr>";
}

echo "</table>";
?>
