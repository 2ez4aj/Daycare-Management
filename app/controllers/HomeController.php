<?php

class HomeController {
    /**
     * Default method that gets called when no specific method is requested
     */
    public function index() {
        // Redirect to the admin dashboard if user is logged in as admin
        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
            header('Location: /NewDaycare/admin/dashboard.php');
            exit();
        }
        // Redirect to parent dashboard if user is logged in as parent
        elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'parent') {
            header('Location: /NewDaycare/parent/dashboard.php');
            exit();
        }
        // Otherwise, redirect to the login page
        else {
            header('Location: /NewDaycare/auth/login.php');
            exit();
        }
    }
}
