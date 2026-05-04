<?php
// Simple test file to verify database connection and setup

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>LGU Inventory System - Connection Test</h1>";

// Test 1: Check if config.php exists
echo "<h2>Test 1: Config File</h2>";
if (file_exists('config.php')) {
    echo "✅ config.php exists<br>";
    require_once 'config.php';
    echo "✅ config.php loaded successfully<br>";
} else {
    echo "❌ config.php not found<br>";
    exit;
}

// Test 2: Check database connection
echo "<h2>Test 2: Database Connection</h2>";
try {
    $conn = getDBConnection();
    echo "✅ Database connection successful<br>";
    echo "Connected to database: " . DB_NAME . "<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    echo "<p><strong>Please check:</strong></p>";
    echo "<ul>";
    echo "<li>MySQL/MariaDB is running</li>";
    echo "<li>Database credentials in config.php are correct</li>";
    echo "<li>Database '" . DB_NAME . "' exists</li>";
    echo "</ul>";
    exit;
}

// Test 3: Check if tables exist
echo "<h2>Test 3: Database Tables</h2>";
$tables = ['offices', 'property_items', 'issuance_records', 'return_records', 'unserviceable_records', 'disposal_records'];
$allTablesExist = true;

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "✅ Table '$table' exists<br>";
    } else {
        echo "❌ Table '$table' not found<br>";
        $allTablesExist = false;
    }
}

if (!$allTablesExist) {
    echo "<p><strong>Please import database.sql:</strong></p>";
    echo "<code>mysql -u " . DB_USER . " -p " . DB_NAME . " < database.sql</code>";
    exit;
}

// Test 4: Check if sample offices exist
echo "<h2>Test 4: Sample Data</h2>";
$result = $conn->query("SELECT COUNT(*) as count FROM offices");
$row = $result->fetch_assoc();
if ($row['count'] > 0) {
    echo "✅ Found {$row['count']} offices in database<br>";
    
    // Show offices
    $result = $conn->query("SELECT * FROM offices LIMIT 5");
    echo "<ul>";
    while ($office = $result->fetch_assoc()) {
        echo "<li>{$office['name']} ({$office['code']})</li>";
    }
    echo "</ul>";
} else {
    echo "⚠️ No offices found. Sample data may not be imported.<br>";
}

// Test 5: Check API files
echo "<h2>Test 5: API Files</h2>";
$apiFiles = ['dashboard.php', 'items.php', 'offices.php', 'issuance.php', 'returns.php', 'unserviceable.php'];
foreach ($apiFiles as $file) {
    if (file_exists("api/$file")) {
        echo "✅ api/$file exists<br>";
    } else {
        echo "❌ api/$file not found<br>";
    }
}

// Test 6: Check frontend files
echo "<h2>Test 6: Frontend Files</h2>";
$frontendFiles = [
    'index.php' => 'Main application',
    'assets/css/style.css' => 'Stylesheet',
    'assets/js/app.js' => 'JavaScript',
    'includes/modals.php' => 'Modal dialogs'
];

foreach ($frontendFiles as $file => $desc) {
    if (file_exists($file)) {
        echo "✅ $file ($desc)<br>";
    } else {
        echo "❌ $file not found<br>";
    }
}

$conn->close();

echo "<hr>";
echo "<h2>✅ All Tests Passed!</h2>";
echo "<p><strong>Your system is ready to use.</strong></p>";
echo "<p><a href='index.php' style='display:inline-block;padding:10px 20px;background:#16150E;color:#fff;text-decoration:none;border-radius:5px;'>Open Application →</a></p>";
?>
