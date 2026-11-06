<?php
/**
 * Automated Database Setup Script
 * Browser: /lgu_invent/scripts/setup-database.php
 * Legacy: /lgu_invent/setup-database.php (via .htaccess)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$projectRoot = dirname(__DIR__);

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #16150E; }
        .success { color: #16733A; padding: 10px; background: #EDFAF3; border-left: 4px solid #16733A; margin: 10px 0; }
        .error { color: #BE1D1D; padding: 10px; background: #FEF0F0; border-left: 4px solid #BE1D1D; margin: 10px 0; }
        .warning { color: #9E5505; padding: 10px; background: #FEF6E7; border-left: 4px solid #9E5505; margin: 10px 0; }
        .info { color: #1849B4; padding: 10px; background: #EBF0FC; border-left: 4px solid #1849B4; margin: 10px 0; }
        .step { margin: 20px 0; padding: 15px; background: #f8f8f8; border-radius: 5px; }
        .step-title { font-weight: bold; margin-bottom: 10px; }
        button { padding: 10px 20px; background: #16150E; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #2a2a20; }
        pre { background: #f8f8f8; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .progress { margin: 20px 0; }
        .progress-bar { width: 100%; height: 30px; background: #e0e0e0; border-radius: 15px; overflow: hidden; }
        .progress-fill { height: 100%; background: #16733A; transition: width 0.3s; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 LGU Inventory System - Database Setup</h1>";

$config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'db' => 'lgu_inventory'
];

$bootstrapFile = $projectRoot . DIRECTORY_SEPARATOR . 'bootstrap.php';
if (file_exists($bootstrapFile)) {
    echo "<div class='info'>✓ Found bootstrap.php, loading credentials from theme/config...</div>";
    require_once $bootstrapFile;
    if (defined('DB_HOST')) {
        $config['host'] = DB_HOST;
    }
    if (defined('DB_USER')) {
        $config['user'] = DB_USER;
    }
    if (defined('DB_PASS')) {
        $config['pass'] = DB_PASS;
    }
    if (defined('DB_NAME')) {
        $config['db'] = DB_NAME;
    }
}

echo "<div class='step'>";
echo "<div class='step-title'>Database Configuration:</div>";
echo "<pre>";
echo "Host: " . $config['host'] . "\n";
echo "User: " . $config['user'] . "\n";
echo "Password: " . (empty($config['pass']) ? '(empty)' : '***') . "\n";
echo "Database: " . $config['db'];
echo "</pre>";
echo "</div>";

echo "<div class='step'>";
echo "<div class='step-title'>Step 1: Connecting to MySQL Server...</div>";

try {
    $conn = new mysqli($config['host'], $config['user'], $config['pass']);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<div class='success'>✓ Connected to MySQL server successfully!</div>";
} catch (Exception $e) {
    echo "<div class='error'>✗ Failed to connect to MySQL: " . $e->getMessage() . "</div>";
    echo "<div class='warning'>";
    echo "<strong>Troubleshooting:</strong><br>";
    echo "1. Make sure MySQL/MariaDB is running<br>";
    echo "2. Check your credentials in theme/config.php<br>";
    echo "3. Verify MySQL service is started<br>";
    echo "</div>";
    echo "</div></div></body></html>";
    exit;
}
echo "</div>";

echo "<div class='step'>";
echo "<div class='step-title'>Step 2: Creating Database...</div>";

$sql = "CREATE DATABASE IF NOT EXISTS `{$config['db']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === true) {
    echo "<div class='success'>✓ Database '{$config['db']}' created or already exists</div>";
} else {
    echo "<div class='error'>✗ Error creating database: " . $conn->error . "</div>";
}

$conn->select_db($config['db']);
echo "</div>";

echo "<div class='step'>";
echo "<div class='step-title'>Step 3: Creating Tables...</div>";

$tables = [
    'offices' => "CREATE TABLE IF NOT EXISTS offices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'property_items' => "CREATE TABLE IF NOT EXISTS property_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_number VARCHAR(50) UNIQUE NOT NULL,
        article VARCHAR(255) NULL DEFAULT NULL COMMENT 'Article / item name (GSO)',
        description TEXT NOT NULL,
        unit_of_measure VARCHAR(50) NULL DEFAULT 'pc' COMMENT 'Unit of measure',
        category ENUM('SE', 'PPE') NOT NULL COMMENT 'SE=Semi-Expendable, PPE=Property Plant Equipment',
        item_group VARCHAR(255) NULL DEFAULT NULL COMMENT 'Report section e.g. OFFICE FIXTURES & FURNITURES',
        sort_order INT NOT NULL DEFAULT 0 COMMENT 'Order within office and section',
        unit_value DECIMAL(15,2) NOT NULL,
        quantity INT NOT NULL DEFAULT 1 COMMENT 'Quantity per property card',
        quantity_physical_count INT NULL DEFAULT NULL COMMENT 'Physical count; NULL = same as property card for variance',
        office_id INT NOT NULL,
        status ENUM('Active', 'Issued', 'Unserviceable', 'Disposed', 'Transferred') DEFAULT 'Active',
        condition_status ENUM('Good', 'Fair', 'Damaged') DEFAULT 'Good',
        acquisition_date DATE,
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE RESTRICT,
        INDEX idx_category (category),
        INDEX idx_status (status),
        INDEX idx_office (office_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'issuance_records' => "CREATE TABLE IF NOT EXISTS issuance_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        form_type ENUM('PAR', 'ICS') NOT NULL COMMENT 'PAR=Property Acknowledgement Receipt, ICS=Inventory Custodian Slip',
        form_number VARCHAR(50) UNIQUE NOT NULL,
        property_item_id INT NOT NULL,
        office_id INT NOT NULL,
        issued_to VARCHAR(255) NOT NULL,
        position VARCHAR(255),
        issue_date DATE NOT NULL,
        received_by VARCHAR(255),
        received_date DATE,
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (property_item_id) REFERENCES property_items(id) ON DELETE RESTRICT,
        FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE RESTRICT,
        INDEX idx_form_type (form_type),
        INDEX idx_office (office_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'issuance_items' => "CREATE TABLE IF NOT EXISTS issuance_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        issuance_id INT NOT NULL,
        property_item_id INT NOT NULL,
        proc_pr_line_id INT DEFAULT NULL,
        quantity DECIMAL(12,4) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_issuance_property_item (property_item_id),
        KEY idx_issuance_id (issuance_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'return_records' => "CREATE TABLE IF NOT EXISTS return_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        issuance_id INT NOT NULL,
        property_item_id INT NOT NULL,
        return_date DATE NOT NULL,
        returned_by VARCHAR(255) NOT NULL,
        return_action ENUM('Unserviceable', 'Transfer', 'Returned') NOT NULL,
        transfer_to_office_id INT NULL,
        condition_on_return ENUM('Good', 'Fair', 'Damaged') NOT NULL,
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (issuance_id) REFERENCES issuance_records(id) ON DELETE RESTRICT,
        FOREIGN KEY (property_item_id) REFERENCES property_items(id) ON DELETE RESTRICT,
        FOREIGN KEY (transfer_to_office_id) REFERENCES offices(id) ON DELETE SET NULL,
        INDEX idx_return_action (return_action)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'unserviceable_records' => "CREATE TABLE IF NOT EXISTS unserviceable_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_item_id INT NOT NULL,
        marked_date DATE NOT NULL,
        reason TEXT NOT NULL,
        marked_by VARCHAR(255),
        disposal_status ENUM('Pending', 'For Appraisal', 'Disposed') DEFAULT 'Pending',
        repair_status ENUM('Pending', 'Serviceable', 'Defective') DEFAULT 'Pending',
        disposal_date DATE NULL,
        disposal_method VARCHAR(255) NULL,
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_item_id) REFERENCES property_items(id) ON DELETE RESTRICT,
        INDEX idx_disposal_status (disposal_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'disposal_records' => "CREATE TABLE IF NOT EXISTS disposal_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unserviceable_id INT NOT NULL,
        property_item_id INT NOT NULL,
        disposal_date DATE NOT NULL,
        disposal_method VARCHAR(255) NOT NULL,
        appraised_value DECIMAL(15,2),
        approved_by VARCHAR(255),
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unserviceable_id) REFERENCES unserviceable_records(id) ON DELETE RESTRICT,
        FOREIGN KEY (property_item_id) REFERENCES property_items(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($tables as $table => $tableSql) {
    if ($conn->query($tableSql) === true) {
        echo "<div class='success'>✓ Table '$table' created successfully</div>";
    } else {
        echo "<div class='error'>✗ Error creating table '$table': " . $conn->error . "</div>";
    }
}
echo "</div>";

echo "<div class='step'>";
echo "<div class='step-title'>Step 4: Inserting Sample Data...</div>";

$result = $conn->query("SELECT COUNT(*) as count FROM offices");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $offices = [
        ['Office of the Mayor', 'MAYOR'],
        ['Office of the Vice Mayor', 'VMAY'],
        ['Accounting Office', 'ACCT'],
        ['Budget Office', 'BUDG'],
        ['Treasury Office', 'TRES'],
        ['Engineering Office', 'ENGR'],
        ['Health Office', 'HLTH'],
        ['Social Welfare Office', 'SWDO'],
        ['Planning Office', 'PLAN'],
        ['General Services Office', 'GSO']
    ];
    
    $stmt = $conn->prepare("INSERT INTO offices (name, code) VALUES (?, ?)");
    $inserted = 0;
    
    foreach ($offices as $office) {
        $stmt->bind_param('ss', $office[0], $office[1]);
        if ($stmt->execute()) {
            $inserted++;
        }
    }
    
    echo "<div class='success'>✓ Inserted $inserted sample offices</div>";
} else {
    echo "<div class='info'>✓ Sample offices already exist ({$row['count']} offices found)</div>";
}
echo "</div>";

echo "<div class='step'>";
echo "<div class='step-title'>Step 5: Verifying Installation...</div>";

$verification = [
    'offices' => 0,
    'property_items' => 0,
    'issuance_records' => 0,
    'return_records' => 0,
    'unserviceable_records' => 0,
    'disposal_records' => 0
];

foreach ($verification as $table => $count) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $row = $result->fetch_assoc();
        $verification[$table] = $row['count'];
        echo "<div class='success'>✓ Table '$table': {$row['count']} records</div>";
    }
}
echo "</div>";

$conn->close();

echo "<div class='step' style='background: #EDFAF3; border: 2px solid #16733A;'>";
echo "<h2 style='color: #16733A; margin-top: 0;'>✅ Database Setup Complete!</h2>";
echo "<p><strong>Database Name:</strong> {$config['db']}</p>";
echo "<p><strong>Tables Created:</strong> 6 tables</p>";
echo "<p><strong>Sample Offices:</strong> {$verification['offices']} offices</p>";
echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>✓ Database is ready to use</li>";
echo "<li>✓ Sample offices are loaded</li>";
echo "<li>Open the main application: <a href='../' style='color: #1849B4; font-weight: bold;'>Application home</a></li>";
echo "<li>Create login users once: <a href='setup-test-users.php' style='color: #1849B4; font-weight: bold;'>setup-test-users.php</a></li>";
echo "</ol>";
echo "</div>";

echo "<div style='text-align: center; margin-top: 30px;'>";
echo "<a href='../' style='display: inline-block; padding: 15px 30px; background: #16150E; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;'>Open Application →</a>";
echo "</div>";

echo "</div></body></html>";
