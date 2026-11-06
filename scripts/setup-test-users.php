<?php
/**
 * Setup Test Users — creates hashed user rows in `users`.
 * Browser: /lgu_invent/scripts/setup-test-users.php
 * Legacy: /lgu_invent/setup-test-users.php (via .htaccess)
 */

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

try {
    $db = getDBConnection();
    echo '<h2>User seed helper</h2>';
    echo '<p style="max-width:52rem;line-height:1.5;">Default accounts are <strong>not</strong> bundled. Use <strong>Register</strong> on the login page, or add rows to <code>$testUsers</code> in this file if you need scripted seeding on your machine only.</p>';

    /**
     * Optional dev-only seeds — leave empty in production-oriented repos.
     * Example shape:
     * ['username'=>'...','password'=>'...','full_name'=>'...','email'=>'...','role'=>'Admin|User']
     */
    $testUsers = [];

    $defaultOfficeStmt = $db->query('SELECT id FROM offices ORDER BY id ASC LIMIT 1');
    $defaultOfficeRow = $defaultOfficeStmt ? $defaultOfficeStmt->fetch(PDO::FETCH_ASSOC) : false;
    $defaultOfficeId = $defaultOfficeRow ? (int) $defaultOfficeRow['id'] : 0;

    if ($defaultOfficeId <= 0 && !empty($testUsers)) {
        echo '<p style="color:#9E5505;">Warning: no rows in <code>offices</code>; User roles will get <code>office_id</code> unset unless you seed offices first.</p>';
    }

    if (!empty($testUsers)) {
        echo '<ul>';

        foreach ($testUsers as $user) {
            $officeIdForUser =
                (($user['role'] ?? '') === 'Admin')
                    ? null
                    : ($defaultOfficeId > 0 ? $defaultOfficeId : null);
            $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);

            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$user['email']]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $db->prepare(
                    'UPDATE users
                SET username = ?, password = ?, full_name = ?, role = ?, office_id = ?, is_active = 1
                WHERE email = ?'
                );
                $stmt->execute([
                    $user['username'],
                    $hashedPassword,
                    $user['full_name'],
                    $user['role'],
                    $officeIdForUser,
                    $user['email'],
                ]);
                echo "<li>✓ Updated user: <strong>{$user['email']}</strong> (Role: {$user['role']})</li>";
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO users (username, password, full_name, email, role, office_id, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
                );
                $stmt->execute([
                    $user['username'],
                    $hashedPassword,
                    $user['full_name'],
                    $user['email'],
                    $user['role'],
                    $officeIdForUser,
                ]);
                echo "<li>✓ Created user: <strong>{$user['email']}</strong> (Role: {$user['role']})</li>";
            }
        }

        echo '</ul>';
        echo "<h3 style='color: green;'>✓ Seed users processed</h3>";
    }

    echo "<p><a href='/lgu_invent/login' style='display: inline-block; background: #B22222; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to login</a></p>";
    
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
    echo "<h3 style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
    echo "<p>Make sure the database 'lgu_inventory' exists and is properly configured.</p>";
    echo "<p>Run the database setup script first: <a href='setup-database.php'>setup-database.php</a></p>";
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
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
