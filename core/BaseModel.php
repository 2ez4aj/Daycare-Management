<?php

class BaseModel {
    protected $db;

    /**
     * Constructor for the base model.
     * It initializes the database connection.
     */
    public function __construct() {
        // The getDBConnection() function is defined in config/database.php
        $this->db = getDBConnection();

        if ($this->db === null) {
            // Handle the case where the database connection fails
            // You might want to log this error or show a user-friendly message
            die("Error: Could not connect to the database.");
        }
    }
}
