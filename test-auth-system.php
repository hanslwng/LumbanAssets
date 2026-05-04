<?php
/**
 * Test Authentication System
 * Comprehensive test page for login system
 * 
 * Access: http://localhost/lgu_invent/test-auth-system.php
 */

// Load configuration
require_once 'config.php';

$results = [];

// Test 1: Database Connection
try {
    $db = getDBConnection();
    $results[] = ['test' => 'Database Connection', 'status' => 'PASS', 'message' => 'Connected successfully'];
} catch (Exception $e) {
    $results[] = ['test' => 'Database Connection', 'status' => 'FAIL', 'message' => $e->getMessage()];
    $db = null;
}

// Test 2: Users Table Exists
if ($db) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() > 0) {
            $results[] = ['test' => 'Users Table', 'status' => 'PASS', 'message' => 'Table exists'];
        } else {
            $results[] = ['test' => 'Users Table', 'status' => 'FAIL', 'message' => 'Table does not exist'];
        }
    } catch (Exception $e) {
        $results[] = ['test' => 'Users Table', 'status' => 'FAIL', 'message' => $e->getMessage()];
    }
}

// Test 3: Count Users
if ($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch()['count'];
        $results[] = ['test' => 'User Count', 'status' => 'INFO', 'message' => "$count users in database"];
    } catch (Exception $e) {
        $results[] = ['test' => 'User Count', 'status' => 'FAIL', 'message' => $e->getMessage()];
    }
}

// Test 4: Check Test Users
if ($db) {
    try {
        $testEmails = ['admin@gso.local', 'staff1@gso.local', 'staff2@gso.local'];
        $stmt = $db->prepare("SELECT email, full_name, role FROM users WHERE email IN (?, ?, ?)");
        $stmt->execute($testEmails);
        $users = $stmt->fetchAll();
        
        if (count($users) > 0) {
            $userList = implode(', ', array_column($users, 'email'));
            $results[] = ['test' => 'Test Users', 'status' => 'PASS', 'message' => "Found: $userList"];
        } else {
            $results[] = ['test' => 'Test Users', 'status' => 'WARN', 'message' => 'No test users found. Run setup-test-users.php'];
        }
    } catch (Exception $e) {
        $results[] = ['test' => 'Test Users', 'status' => 'FAIL', 'message' => $e->getMessage()];
    }
}

// Test 5: User Model
try {
    require_once 'app/models/User.php';
    $userModel = new User();
    $results[] = ['test' => 'User Model', 'status' => 'PASS', 'message' => 'Model loaded successfully'];
} catch (Exception $e) {
    $results[] = ['test' => 'User Model', 'status' => 'FAIL', 'message' => $e->getMessage()];
}

// Test 6: AuthController
try {
    require_once 'app/controllers/AuthController.php';
    $authController = new AuthController();
    $results[] = ['test' => 'Auth Controller', 'status' => 'PASS', 'message' => 'Controller loaded successfully'];
} catch (Exception $e) {
    $results[] = ['test' => 'Auth Controller', 'status' => 'FAIL', 'message' => $e->getMessage()];
}

// Test 7: Login View File
if (file_exists('app/views/auth/login-modern.php')) {
    $results[] = ['test' => 'Login View', 'status' => 'PASS', 'message' => 'login-modern.php exists'];
} else {
    $results[] = ['test' => 'Login View', 'status' => 'FAIL', 'message' => 'login-modern.php not found'];
}

// Test 8: Auth CSS File
if (file_exists('public/assets/css/auth-modern.css')) {
    $results[] = ['test' => 'Auth CSS', 'status' => 'PASS', 'message' => 'auth-modern.css exists'];
} else {
    $results[] = ['test' => 'Auth CSS', 'status' => 'FAIL', 'message' => 'auth-modern.css not found'];
}

// Test 9: Router File
if (file_exists('router.php')) {
    $results[] = ['test' => 'Router', 'status' => 'PASS', 'message' => 'router.php exists'];
} else {
    $results[] = ['test' => 'Router', 'status' => 'FAIL', 'message' => 'router.php not found'];
}

// Test 10: .htaccess File
if (file_exists('.htaccess')) {
    $results[] = ['test' => '.htaccess', 'status' => 'PASS', 'message' => '.htaccess exists'];
} else {
    $results[] = ['test' => '.htaccess', 'status' => 'FAIL', 'message' => '.htaccess not found'];
}

// Test 11: mod_rewrite Check
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        $results[] = ['test' => 'mod_rewrite', 'status' => 'PASS', 'message' => 'mod_rewrite is enabled'];
    } else {
        $results[] = ['test' => 'mod_rewrite', 'status' => 'FAIL', 'message' => 'mod_rewrite is NOT enabled'];
    }
} else {
    $results[] = ['test' => 'mod_rewrite', 'status' => 'WARN', 'message' => 'Cannot check (apache_get_modules not available)'];
}

// Test 12: Session
if (session_status() === PHP_SESSION_ACTIVE) {
    $results[] = ['test' => 'Session', 'status' => 'PASS', 'message' => 'Session is active'];
} else {
    $results[] = ['test' => 'Session', 'status' => 'FAIL', 'message' => 'Session is not active'];
}

// Test 13: Password Hashing
$testPassword = 'password123';
$hash = password_hash($testPassword, PASSWORD_DEFAULT);
if (password_verify($testPassword, $hash)) {
    $results[] = ['test' => 'Password Hashing', 'status' => 'PASS', 'message' => 'Password hashing works correctly'];
} else {
    $results[] = ['test' => 'Password Hashing', 'status' => 'FAIL', 'message' => 'Password hashing failed'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication System Test - LGU Inventory</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #B22222, #8B0000);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #B22222, #8B0000);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .test-grid {
            display: grid;
            gap: 15px;
        }
        
        .test-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid;
            background: #f9f9f9;
        }
        
        .test-item.pass {
            border-color: #28a745;
            background: #f0fff4;
        }
        
        .test-item.fail {
            border-color: #dc3545;
            background: #fff5f5;
        }
        
        .test-item.warn {
            border-color: #ffc107;
            background: #fffbf0;
        }
        
        .test-item.info {
            border-color: #17a2b8;
            background: #f0f9ff;
        }
        
        .test-icon {
            font-size: 24px;
            margin-right: 15px;
            width: 30px;
            text-align: center;
        }
        
        .test-item.pass .test-icon {
            color: #28a745;
        }
        
        .test-item.fail .test-icon {
            color: #dc3545;
        }
        
        .test-item.warn .test-icon {
            color: #ffc107;
        }
        
        .test-item.info .test-icon {
            color: #17a2b8;
        }
        
        .test-details {
            flex: 1;
        }
        
        .test-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .test-message {
            color: #666;
            font-size: 14px;
        }
        
        .summary {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .summary-card {
            flex: 1;
            min-width: 150px;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            color: white;
        }
        
        .summary-card.pass {
            background: #28a745;
        }
        
        .summary-card.fail {
            background: #dc3545;
        }
        
        .summary-card.warn {
            background: #ffc107;
        }
        
        .summary-card.info {
            background: #17a2b8;
        }
        
        .summary-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .summary-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .actions {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #eee;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #B22222;
            color: white;
        }
        
        .btn-primary:hover {
            background: #8B0000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(178, 34, 34, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> Authentication System Test</h1>
            <p>LGU Property Inventory Management System</p>
        </div>
        
        <div class="content">
            <?php
            // Calculate summary
            $passCount = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
            $failCount = count(array_filter($results, fn($r) => $r['status'] === 'FAIL'));
            $warnCount = count(array_filter($results, fn($r) => $r['status'] === 'WARN'));
            $infoCount = count(array_filter($results, fn($r) => $r['status'] === 'INFO'));
            $totalTests = count($results);
            ?>
            
            <div class="summary">
                <div class="summary-card pass">
                    <div class="summary-number"><?php echo $passCount; ?></div>
                    <div class="summary-label">PASSED</div>
                </div>
                <div class="summary-card fail">
                    <div class="summary-number"><?php echo $failCount; ?></div>
                    <div class="summary-label">FAILED</div>
                </div>
                <div class="summary-card warn">
                    <div class="summary-number"><?php echo $warnCount; ?></div>
                    <div class="summary-label">WARNINGS</div>
                </div>
                <div class="summary-card info">
                    <div class="summary-number"><?php echo $infoCount; ?></div>
                    <div class="summary-label">INFO</div>
                </div>
            </div>
            
            <h2 style="margin-bottom: 20px; color: #333;">Test Results</h2>
            
            <div class="test-grid">
                <?php foreach ($results as $result): ?>
                    <div class="test-item <?php echo strtolower($result['status']); ?>">
                        <div class="test-icon">
                            <?php
                            switch ($result['status']) {
                                case 'PASS':
                                    echo '<i class="fas fa-check-circle"></i>';
                                    break;
                                case 'FAIL':
                                    echo '<i class="fas fa-times-circle"></i>';
                                    break;
                                case 'WARN':
                                    echo '<i class="fas fa-exclamation-triangle"></i>';
                                    break;
                                case 'INFO':
                                    echo '<i class="fas fa-info-circle"></i>';
                                    break;
                            }
                            ?>
                        </div>
                        <div class="test-details">
                            <div class="test-name"><?php echo htmlspecialchars($result['test']); ?></div>
                            <div class="test-message"><?php echo htmlspecialchars($result['message']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="actions">
                <a href="/lgu_invent/login" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Go to Login Page
                </a>
                <a href="/lgu_invent/setup-test-users.php" class="btn btn-success">
                    <i class="fas fa-users"></i> Setup Test Users
                </a>
                <a href="/lgu_invent/test-auth-system.php" class="btn btn-secondary">
                    <i class="fas fa-sync"></i> Refresh Tests
                </a>
            </div>
            
            <?php if ($failCount > 0): ?>
                <div style="margin-top: 30px; padding: 20px; background: #fff5f5; border-left: 4px solid #dc3545; border-radius: 8px;">
                    <h3 style="color: #dc3545; margin-bottom: 10px;">
                        <i class="fas fa-exclamation-circle"></i> Action Required
                    </h3>
                    <p style="color: #666; margin-bottom: 10px;">Some tests failed. Please fix the issues before using the login system:</p>
                    <ul style="color: #666; margin-left: 20px;">
                        <?php foreach ($results as $result): ?>
                            <?php if ($result['status'] === 'FAIL'): ?>
                                <li><strong><?php echo htmlspecialchars($result['test']); ?>:</strong> <?php echo htmlspecialchars($result['message']); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif ($warnCount > 0): ?>
                <div style="margin-top: 30px; padding: 20px; background: #fffbf0; border-left: 4px solid #ffc107; border-radius: 8px;">
                    <h3 style="color: #ffc107; margin-bottom: 10px;">
                        <i class="fas fa-exclamation-triangle"></i> Warnings
                    </h3>
                    <p style="color: #666;">Some tests have warnings. The system may work but consider addressing these:</p>
                    <ul style="color: #666; margin-left: 20px; margin-top: 10px;">
                        <?php foreach ($results as $result): ?>
                            <?php if ($result['status'] === 'WARN'): ?>
                                <li><strong><?php echo htmlspecialchars($result['test']); ?>:</strong> <?php echo htmlspecialchars($result['message']); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div style="margin-top: 30px; padding: 20px; background: #f0fff4; border-left: 4px solid #28a745; border-radius: 8px;">
                    <h3 style="color: #28a745; margin-bottom: 10px;">
                        <i class="fas fa-check-circle"></i> All Tests Passed!
                    </h3>
                    <p style="color: #666;">The authentication system is ready to use. You can now proceed to the login page.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
