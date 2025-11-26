<?php
/**
 * Application Class
 * 
 * Main application bootstrap and configuration
 */
class Application {
    private static $instance = null;
    private $router;
    private $config;
    private $db;
    
    private function __construct() {
        $this->loadConfig();
        $this->initDatabase();
        $this->initRouter();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadConfig() {
        $this->config = require APPROOT . '/config/config.php';
    }
    
    private function initDatabase() {
        $this->db = new Database($this->config['database']);
    }
    
    private function initRouter() {
        $this->router = new Router();
    }
    
    public function run() {
        $this->router->dispatch();
    }
    
    public function getDatabase() {
        return $this->db;
    }
    
    public function getRouter() {
        return $this->router;
    }
    
    public function getDB() {
        return $this->db;
    }
    
    public function getConfig($key = null) {
        if ($key) {
            return $this->config[$key] ?? null;
        }
        return $this->config;
    }
}
