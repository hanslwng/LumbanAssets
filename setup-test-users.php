<?php
/**
 * Setup Test Users
 * Creates test user accounts with proper password hashing
 * 
 * Run this file once to create test users
 * Access: http://localhost/lgu_invent/setup-test-users.php
 */

// Load configuration
require_once 'config.php';

// Get database connection
try {
    $db = getDBConnection();
    echo "<h2>Setting up test users...</h2>";
    
    // Test users data
    $testUsers = [
        [
            'username' => 'admin',
            'password' => 'password123',
            'full_name' => 'Admin User',
            'email' => 'admin@gso.local',
            'role' => 'Admin'
        ],
        [
            'username' => 'staff1',
            'password' => 'password123',
            'full_name' => 'Staff Member 1',
            'email' => 'staff1@gso.local',
            'role' => 'User'
        ],
        [
            'username' => 'staff2',
            'password' => 'password123',
            'full_name' => 'Staff Member 2',
            'email' => 'staff2@gso.local',
            'role' => 'User'
        ],
        [
            'username' => 'viewer',
            'password' => 'password123',
            'full_name' => 'Viewer User',
            'email' => 'viewer@gso.local',
            'role' => 'Viewer'
        ]
    ];
    
    echo "<ul>";
    
    foreach ($testUsers as $user) {
        // Hash password
        $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);
        
        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$user['email']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing user
            $stmt = $db->prepare("
                UPDATE users 
                SET username = ?, password = ?, full_name = ?, role = ?, is_active = 1 
                WHERE email = ?
            ");
            $stmt->execute([
                $user['username'],
                $hashedPassword,
                $user['full_name'],
                $user['role'],
                $user['email']
            ]);
            echo "<li>✓ Updated user: <strong>{$user['email']}</strong> (Role: {$user['role']})</li>";
        } else {
            // Insert new user
            $stmt = $db->prepare("
                INSERT INTO users (username, password, full_name, email, role, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $user['username'],
                $hashedPassword,
                $user['full_name'],
                $user['email'],
                $user['role']
            ]);
            echo "<li>✓ Created user: <strong>{$user['email']}</strong> (Role: {$user['role']})</li>";
        }
    }
    
    echo "</ul>";
    
    echo "<h3 style='color: green;'>✓ Test users setup complete!</h3>";
    echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>Test Accounts:</h4>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Email</th><th>Password</th><th>Role</th></tr>";
    foreach ($testUsers as $user) {
        echo "<tr>";
        echo "<td><code>{$user['email']}</code></td>";
        echo "<td><code>{$user['password']}</code></td>";
        echo "<td><strong>{$user['role']}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    echo "<p><a href='/lgu_invent/login' style='display: inline-block; background: #B22222; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    
    // Verify users
    echo "<h4>Verification - All Users in Database:</h4>";
    $stmt = $db->query("SELECT id, username, email, full_name, role, is_active, created_at FROM users ORDER BY id");
    $allUsers = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Active</th><th>Created</th></tr>";
    foreach ($allUsers as $u) {
        echo "<tr>";
        echo "<td>{$u['id']}</td>";
        echo "<td>{$u['username']}</td>";
        echo "<td>{$u['email']}</td>";
        echo "<td>{$u['full_name']}</td>";
        echo "<td><strong>{$u['role']}</strong></td>";
        echo "<td>" . ($u['is_active'] ? '✓' : '✗') . "</td>";
        echo "<td>{$u['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error: " . $e->getMessage() . "</h3>";
    echo "<p>Make sure the database 'lgu_inventory' exists and is properly configured.</p>";
    echo "<p>Run the database setup script first: <a href='setup-database.php'>setup-database.php</a></p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Setup Test Users - LGU Inventory</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: linear-gradient(135deg, #f5f5f5, #e0e0e0);
        }
        h2 {
            color: #B22222;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        table {
            width: 100%;
            margin: 20px 0;
        }
        th {
            background: #B22222;
            color: white;
            padding: 10px;
        }
        td {
            background: white;
        }
    </style>
</head>
<body>
</body>
</html>
