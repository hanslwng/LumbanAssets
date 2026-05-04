<?php
/**
 * User Model
 * 
 * Handles user authentication and user data management
 */

class User {
    private $db;
    
    public function __construct() {
        $this->db = getDBConnection();
    }
    
    /**
     * Authenticate user by email and password
     * 
     * @param string $email
     * @param string $password
     * @return array|false User data or false on failure
     */
    public function authenticate($email, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Remove password from returned data
            unset($user['password']);
            return $user;
        }
        
        return false;
    }
    
    /**
     * Get user by ID
     * 
     * @param int $id
     * @return array|false
     */
    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT id, email, name, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get all users
     * 
     * @return array
     */
    public function getAllUsers() {
        $stmt = $this->db->query("SELECT id, email, name, role, created_at FROM users ORDER BY name");
        return $stmt->fetchAll();
    }
}
