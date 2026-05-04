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
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Remove password from returned data
                unset($user['password']);
                return $user;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create new user
     * 
     * @param string $name
     * @param string $email
     * @param string $username
     * @param string $password
     * @param string $role
     * @return array Result with success status and message
     */
    public function createUser($name, $email, $username, $password, $role = 'User') {
        try {
            // Check if email already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Email already exists'
                ];
            }
            
            // Check if username already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Username already exists'
                ];
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password, full_name, email, role, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            
            $result = $stmt->execute([$username, $hashedPassword, $name, $email, $role]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'User created successfully',
                    'user_id' => $this->db->lastInsertId()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create user'
                ];
            }
        } catch (Exception $e) {
            error_log("User creation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update last login timestamp
     * 
     * @param int $userId
     * @return bool
     */
    public function updateLastLogin($userId) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Update last login error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user by ID
     * 
     * @param int $id
     * @return array|false
     */
    public function getUserById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, full_name, role, office_id, is_active, last_login, created_at 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all users
     * 
     * @return array
     */
    public function getAllUsers() {
        try {
            $stmt = $this->db->query("
                SELECT id, username, email, full_name, role, is_active, last_login, created_at 
                FROM users 
                ORDER BY full_name
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get all users error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user by email
     * 
     * @param string $email
     * @return array|false
     */
    public function getUserByEmail($email) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, full_name, role, office_id, is_active 
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get user by email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user by username
     * 
     * @param string $username
     * @return array|false
     */
    public function getUserByUsername($username) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, full_name, role, office_id, is_active 
                FROM users 
                WHERE username = ?
            ");
            $stmt->execute([$username]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get user by username error: " . $e->getMessage());
            return false;
        }
    }
}
