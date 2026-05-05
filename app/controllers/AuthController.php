<?php
/**
 * Authentication Controller
 * 
 * Handles user login, registration, and logout
 */

class AuthController {
    
    /**
     * Show modern login/register page
     */
    public function showLogin() {
        // If already logged in, redirect to dashboard
        if (isset($_SESSION['user_id'])) {
            header('Location: /lgu_invent/');
            exit;
        }
        
        $pageTitle = 'Sign In';
        
        // Check if VIEW_PATH is defined
        if (!defined('VIEW_PATH')) {
            define('VIEW_PATH', __DIR__ . '/../views');
        }
        
        require VIEW_PATH . '/auth/login-modern.php';
    }
    
    /**
     * Process login
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /lgu_invent/login');
            exit;
        }
        
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        
        // Validation
        if (empty($email) || empty($password)) {
            $_SESSION['error'] = 'Email and password are required';
            header('Location: /lgu_invent/login');
            exit;
        }
        
        // Authenticate
        $userModel = new User();
        $user = $userModel->authenticate($email, $password);
        
        if ($user) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['full_name'];
            // Convert role to lowercase and map 'User' to 'staff'
            $role = strtolower($user['role']);
            if ($role === 'user') {
                $role = 'staff';
            }
            $_SESSION['user_role'] = $role;
            $_SESSION['last_activity'] = time();
            
            // Update last login
            $userModel->updateLastLogin($user['id']);
            
            header('Location: /lgu_invent/');
            exit;
        } else {
            $_SESSION['error'] = 'Invalid email or password';
            header('Location: /lgu_invent/login');
            exit;
        }
    }
    
    /**
     * Process registration
     */
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /lgu_invent/login');
            exit;
        }
        
        // Get and sanitize input
        $name = trim($_POST['name'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'User';
        
        // Validation
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Full name is required';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }
        
        if (empty($username)) {
            $errors[] = 'Username is required';
        } elseif (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        }
        
        if (empty($password)) {
            $errors[] = 'Password is required';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }
        
        if (!in_array($role, ['Admin', 'User', 'Viewer'])) {
            $errors[] = 'Invalid role selected';
        }
        
        // If there are errors, return to form
        if (!empty($errors)) {
            $_SESSION['error'] = implode('. ', $errors);
            header('Location: /lgu_invent/login');
            exit;
        }
        
        // Create user
        $userModel = new User();
        $result = $userModel->createUser($name, $email, $username, $password, $role);
        
        if ($result['success']) {
            $_SESSION['success'] = 'Account created successfully! You can now sign in.';
            header('Location: /lgu_invent/login');
            exit;
        } else {
            $_SESSION['error'] = $result['message'];
            header('Location: /lgu_invent/login');
            exit;
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        session_unset();
        session_destroy();
        header('Location: /lgu_invent/login');
        exit;
    }
}
