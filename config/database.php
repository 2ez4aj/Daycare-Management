<?php
// Database configuration for Gumamela Daycare Center
class Database {
    private $host = "localhost";
    private $db_name = "gumamela_daycare";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            return null;
        }
        
        return $this->conn;
    }
}

// Global database connection function
function getDBConnection() {
    $database = new Database();
    return $database->getConnection();
}
?>
