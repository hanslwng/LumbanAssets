<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSS Access Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .path {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            margin: 10px 0;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>🔍 CSS File Access Test</h1>
    
    <div class="test-box">
        <h2>File System Check</h2>
        <?php
        $cssFile = 'public/assets/css/auth-modern.css';
        if (file_exists($cssFile)) {
            echo '<p class="success">✓ File exists on server</p>';
            echo '<p class="path">Path: ' . realpath($cssFile) . '</p>';
            echo '<p>File size: ' . filesize($cssFile) . ' bytes</p>';
            echo '<p>Last modified: ' . date('Y-m-d H:i:s', filemtime($cssFile)) . '</p>';
        } else {
            echo '<p class="error">✗ File NOT found on server</p>';
            echo '<p class="path">Looking for: ' . $cssFile . '</p>';
        }
        ?>
    </div>
    
    <div class="test-box">
        <h2>HTTP Access Test</h2>
        <p>Try accessing the CSS file directly:</p>
        <p class="path">
            <a href="/lgu_invent/public/assets/css/auth-modern.css" target="_blank">
                /lgu_invent/public/assets/css/auth-modern.css
            </a>
        </p>
        <p><small>Click the link above. If it downloads or shows CSS code, it's working!</small></p>
    </div>
    
    <div class="test-box">
        <h2>Alternative Paths</h2>
        <p>If the above doesn't work, try these:</p>
        <ul>
            <li><a href="/lgu_invent/public/assets/css/auth-modern.css" target="_blank">Full path with /lgu_invent/</a></li>
            <li><a href="public/assets/css/auth-modern.css" target="_blank">Relative path</a></li>
            <li><a href="/public/assets/css/auth-modern.css" target="_blank">Path without /lgu_invent/</a></li>
        </ul>
    </div>
    
    <div class="test-box">
        <h2>All CSS Files Available</h2>
        <?php
        $cssDir = 'public/assets/css';
        if (is_dir($cssDir)) {
            $files = scandir($cssDir);
            echo '<ul>';
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    $fullPath = $cssDir . '/' . $file;
                    $size = filesize($fullPath);
                    echo '<li>';
                    echo '<strong>' . $file . '</strong> (' . $size . ' bytes)';
                    echo ' - <a href="/lgu_invent/public/assets/css/' . $file . '" target="_blank">Test Access</a>';
                    echo '</li>';
                }
            }
            echo '</ul>';
        } else {
            echo '<p class="error">CSS directory not found</p>';
        }
        ?>
    </div>
    
    <div class="test-box">
        <h2>Apache Configuration</h2>
        <p><strong>mod_rewrite status:</strong></p>
        <?php
        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            if (in_array('mod_rewrite', $modules)) {
                echo '<p class="success">✓ mod_rewrite is enabled</p>';
            } else {
                echo '<p class="error">✗ mod_rewrite is NOT enabled</p>';
            }
        } else {
            echo '<p>Cannot check (apache_get_modules not available)</p>';
        }
        ?>
        
        <p><strong>.htaccess files:</strong></p>
        <ul>
            <li>Root .htaccess: <?php echo file_exists('.htaccess') ? '<span class="success">✓ Exists</span>' : '<span class="error">✗ Missing</span>'; ?></li>
            <li>Public .htaccess: <?php echo file_exists('public/.htaccess') ? '<span class="success">✓ Exists</span>' : '<span class="error">✗ Missing</span>'; ?></li>
        </ul>
    </div>
    
    <div class="test-box">
        <h2>Quick Actions</h2>
        <p>
            <a href="/lgu_invent/login" style="display: inline-block; background: #B22222; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">
                Go to Login Page
            </a>
        </p>
        <p>
            <a href="/lgu_invent/test-auth-system.php" style="display: inline-block; background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">
                Run Full System Test
            </a>
        </p>
    </div>
    
    <div class="test-box">
        <h2>Browser Console Check</h2>
        <p>Open your browser's Developer Tools (F12) and check the Console tab for any errors.</p>
        <p>Also check the Network tab to see if the CSS file is being requested and what status code it returns.</p>
    </div>
</body>
</html>
