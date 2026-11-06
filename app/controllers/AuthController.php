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
            require_once __DIR__ . '/../helpers/PermissionHelper.php';
            header('Location: ' . (PermissionHelper::isAdmin() ? lgue_url('/') : lgue_url('staff')));
            exit;
        }
        
        $pageTitle = 'Sign In';
        
        // Check if VIEW_PATH is defined
        if (!defined('VIEW_PATH')) {
            define('VIEW_PATH', __DIR__ . '/../views');
        }

        $registerOffices = [];
        try {
            $registerOffices = (new User())->listRegisterOfficesWithAccountCounts();
        } catch (Exception $e) {
            $registerOffices = [];
        }

        require VIEW_PATH . '/auth/login-modern.php';
    }
    
    /**
     * Process login
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . lgue_url('login'));
            exit;
        }
        
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        require_once dirname(__DIR__, 2) . '/includes/login_guard.php';
        
        // Validation
        if (empty($email) || empty($password)) {
            $_SESSION['error'] = 'Email and password are required';
            header('Location: ' . lgue_url('login'));
            exit;
        }

        if (loginGuardLocked((string) $email)) {
            $_SESSION['error'] = loginGuardLockedMessage();
            header('Location: ' . lgue_url('login'));
            exit;
        }
        
        // Authenticate
        $userModel = new User();
        $user = $userModel->authenticate($email, $password);
        
        if ($user) {
            loginGuardClear((string) $email);
            session_regenerate_id(true);
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

            $officeId = isset($user['office_id']) && $user['office_id'] !== null && $user['office_id'] !== ''
                ? (int) $user['office_id'] : null;
            $_SESSION['user_office_id'] = $officeId;
            $_SESSION['user_office_name'] = isset($user['office_name'])
                ? (string) $user['office_name'] : '';

            // Update last login
            $userModel->updateLastLogin($user['id']);
            $this->logAuthActivity('Signed in', [
                'user_id' => (int) $user['id'],
                'changed_by' => (string) ($user['full_name'] ?? 'User'),
                'office_id' => $officeId,
                'entity_type' => 'session',
                'entity_id' => (int) $user['id'],
                'entity_label' => (string) ($user['email'] ?? $email),
                'new_value' => $role === 'admin' ? 'Administrator' : 'Department user',
            ]);

            $redirect = ($role === 'admin') ? lgue_url('/') : lgue_url('staff');
            header('Location: ' . $redirect);
            exit;
        } else {
            loginGuardFail((string) $email);
            $this->logAuthActivity('Failed sign-in', [
                'user_id' => null,
                'changed_by' => 'Guest',
                'office_id' => null,
                'entity_type' => 'session',
                'entity_label' => (string) $email,
                'new_value' => loginGuardLocked((string) $email) ? 'Locked' : 'Invalid credentials',
            ]);
            $_SESSION['error'] = loginGuardLocked((string) $email)
                ? loginGuardLockedMessage()
                : 'Invalid email or password';
            header('Location: ' . lgue_url('login'));
            exit;
        }
    }
    
    /**
     * Start registration: send a 6-digit email code. Does not create the user yet.
     */
    public function register()
    {
        $this->handleRegisterStart(false);
    }

    public function registerStart()
    {
        $this->handleRegisterStart(true);
    }

    public function registerVerify()
    {
        require_once dirname(__DIR__, 2) . '/includes/registration_verify.php';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed']);
        }
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)
            ?: (string) ($_POST['email'] ?? '');
        $code = (string) ($_POST['code'] ?? '');
        try {
            jsonResponse(registrationVerify(getDBConnection(), $email, $code));
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'message' => 'Could not verify the code.']);
        }
    }

    private function handleRegisterStart(bool $forceJson): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($forceJson) {
                jsonResponse(['success' => false, 'message' => 'Method not allowed']);
            }
            header('Location: ' . lgue_url('login'));
            exit;
        }

        require_once dirname(__DIR__, 2) . '/includes/registration_verify.php';
        try {
            $result = registrationStart(getDBConnection(), $_POST);
        } catch (Throwable $e) {
            $result = ['success' => false, 'message' => 'Could not start registration.'];
        }

        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        if ($forceJson || str_contains($accept, 'application/json')) {
            jsonResponse($result);
        }

        if (!empty($result['success'])) {
            $_SESSION['success'] = (string) ($result['message'] ?? 'A verification code was sent to your email.');
        } else {
            $_SESSION['error'] = (string) ($result['message'] ?? 'Could not start registration.');
        }
        header('Location: ' . lgue_url('login'));
        exit;
    }
    
    /**
     * Strong password: min 8 chars, upper, lower, digit, special.
     */
    private function isStrongPassword(string $password): bool {
        if (strlen($password) < 8) {
            return false;
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return false;
        }

        return true;
    }

    public function forgotRequest()
    {
        require_once dirname(__DIR__, 2) . '/includes/password_reset.php';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed']);
        }
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)
            ?: (string) ($_POST['email'] ?? '');
        try {
            $conn = getDBConnection();
            jsonResponse(passwordResetSendCode($conn, $email));
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'message' => 'Could not start password reset.']);
        }
    }

    public function forgotVerify()
    {
        require_once dirname(__DIR__, 2) . '/includes/password_reset.php';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed']);
        }
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)
            ?: (string) ($_POST['email'] ?? '');
        $code = (string) ($_POST['code'] ?? '');
        try {
            $conn = getDBConnection();
            jsonResponse(passwordResetVerifyCode($conn, $email, $code));
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'message' => 'Could not verify the code.']);
        }
    }

    public function forgotReset()
    {
        require_once dirname(__DIR__, 2) . '/includes/password_reset.php';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed']);
        }
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        try {
            $conn = getDBConnection();
            jsonResponse(passwordResetChangePassword($conn, $password, $confirm));
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'message' => 'Could not change the password.']);
        }
    }

    /**
     * Logout user
     */
    public function logout() {
        $this->logAuthActivity('Signed out', [
            'entity_type' => 'session',
            'entity_id' => (int) ($_SESSION['user_id'] ?? 0),
            'entity_label' => (string) ($_SESSION['user_email'] ?? ''),
        ]);
        session_unset();
        session_destroy();
        header('Location: ' . lgue_url('login'));
        exit;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function logAuthActivity(string $action, array $extra = []): void
    {
        try {
            require_once dirname(__DIR__, 2) . '/includes/audit_log.php';
            activityLog(getDBConnection(), 'auth', $action, $extra);
        } catch (Throwable $e) {
            // Never block sign-in or sign-out.
        }
    }
}
