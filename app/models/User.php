<?php
/**
 * User Model
 * 
 * Handles user authentication and user data management
 */

class User {
    public const MAX_ACCOUNTS_PER_DEPARTMENT = 3;

    private $db;
    
    public function __construct() {
        $this->db = getDBConnection();
    }

    /**
     * Active User/Viewer accounts assigned to a department.
     * The Administrator login is counted on GSO separately and does not use one of the 3 slots.
     */
    public function countActiveByOffice(int $officeId): int {
        if ($officeId <= 0) {
            return 0;
        }
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS c FROM users
                 WHERE office_id = ? AND is_active = 1
                   AND LOWER(TRIM(role)) <> 'admin'"
            );
            $stmt->execute([$officeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int) ($row['c'] ?? 0);
        } catch (Exception $e) {
            error_log('Count users by office error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Offices with staff (User/Viewer) counts and Admin counts.
     * Unscoped Admin accounts are counted on GSO.
     */
    public function listOfficesWithAccountCounts(): array {
        try {
            $st = $this->db->query(
                "SELECT o.id, o.name, o.code,
                        (SELECT COUNT(*) FROM users u
                          WHERE u.office_id = o.id AND u.is_active = 1
                            AND LOWER(TRIM(u.role)) <> 'admin') AS user_count,
                        (SELECT COUNT(*) FROM users u
                          WHERE u.is_active = 1 AND LOWER(TRIM(u.role)) = 'admin'
                            AND (
                                u.office_id = o.id
                                OR (
                                    u.office_id IS NULL
                                    AND (UPPER(TRIM(o.code)) = 'GSO' OR o.name LIKE '%General Services%')
                                )
                            )) AS admin_count
                 FROM offices o
                 ORDER BY o.name ASC"
            );

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log('List offices with account counts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * GSO is the Administrator office — not offered on public registration.
     *
     * @param array<string, mixed> $office
     */
    public static function isGsoOffice(array $office): bool {
        $code = strtoupper(trim((string) ($office['code'] ?? '')));
        if ($code === 'GSO') {
            return true;
        }
        $name = (string) ($office['name'] ?? $office['office_name'] ?? '');

        return stripos($name, 'General Services') !== false;
    }

    /**
     * Department picker for self-registration (GSO excluded).
     *
     * @return list<array<string, mixed>>
     */
    public function listRegisterOfficesWithAccountCounts(): array {
        $out = [];
        foreach ($this->listOfficesWithAccountCounts() as $office) {
            if (self::isGsoOffice($office)) {
                continue;
            }
            $out[] = $office;
        }

        return $out;
    }

    public function officeAtAccountLimit(int $officeId): bool {
        return $this->countActiveByOffice($officeId) >= self::MAX_ACCOUNTS_PER_DEPARTMENT;
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
            $stmt = $this->db->prepare(
                'SELECT u.*, o.name AS office_name
                 FROM users u
                 LEFT JOIN offices o ON o.id = u.office_id
                 WHERE u.email = ? AND u.is_active = 1'
            );
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
    public function createUser($name, $email, $username, $password, $role = 'User', ?int $office_id = null) {
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

            if ($role !== 'Admin' && $office_id !== null && $office_id > 0 && $this->officeAtAccountLimit($office_id)) {
                return [
                    'success' => false,
                    'message' => 'This department has reached the maximum limit of 3 user accounts.'
                ];
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $this->db->prepare(
                'INSERT INTO users (username, password, full_name, email, role, office_id, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
            );

            $result = $stmt->execute([$username, $hashedPassword, $name, $email, $role, $office_id]);
            
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
     * Insert a department user after email verification. Password is already hashed.
     */
    public function insertVerifiedStaff(string $name, string $email, string $username, string $passwordHash, int $officeId): array
    {
        $email = strtolower(trim($email));
        $username = trim($username);
        $name = trim($name);
        if ($name === '' || $email === '' || $username === '' || $passwordHash === '' || $officeId <= 0) {
            return ['success' => false, 'message' => 'Incomplete registration data. Request a new code.'];
        }
        try {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'This email is already registered. Sign in or use Forgot password.'];
            }
            $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username already exists'];
            }
            if ($this->officeAtAccountLimit($officeId)) {
                return ['success' => false, 'message' => 'This department has reached the maximum limit of 3 user accounts.'];
            }
            $stmt = $this->db->prepare(
                'INSERT INTO users (username, password, full_name, email, role, office_id, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
            );
            if ($stmt->execute([$username, $passwordHash, $name, $email, 'User', $officeId])) {
                return [
                    'success' => true,
                    'message' => 'User created successfully',
                    'user_id' => $this->db->lastInsertId(),
                ];
            }

            return ['success' => false, 'message' => 'Failed to create user'];
        } catch (Exception $e) {
            error_log('Verified user creation error: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Could not create the account. Try again.'];
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
     * Hash and store a new password. Never persist plaintext.
     */
    public function updatePassword(int $userId, string $plainPassword): bool
    {
        if ($userId <= 0 || $plainPassword === '') {
            return false;
        }
        try {
            $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare('UPDATE users SET password = ? WHERE id = ?');

            return $stmt->execute([$hash, $userId]);
        } catch (Exception $e) {
            error_log('Update password error: ' . $e->getMessage());

            return false;
        }
    }

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
