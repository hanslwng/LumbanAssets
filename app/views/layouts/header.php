<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'LGU Property Inventory System'; ?></title>
    
    <!-- Stylesheet -->
    <link rel="stylesheet" href="/lgu_invent/assets/css/style.css">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <!-- TODO: Replace with official LGU logo later -->
                    <img src="/lgu_invent/public/assets/logo.png" alt="LGU Logo" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2250%22 height=%2250%22%3E%3Crect width=%2250%22 height=%2250%22 fill=%22%23fff%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-size=%2220%22 fill=%22%23B22222%22%3ELGU%3C/text%3E%3C/svg%3E'">
                </div>
                <div class="system-title">
                    <h1>LGU Property Inventory System</h1>
                </div>
            </div>
            
            <nav class="sidebar-menu">
                <a href="/dashboard" class="menu-item <?php echo ($_SERVER['REQUEST_URI'] == '/dashboard') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="/items" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/items') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory</span>
                </a>
                
                <a href="/issuances" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/issuances') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-file-export"></i>
                    <span>Issuances</span>
                </a>
                
                <a href="/returns" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/returns') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-undo"></i>
                    <span>Returns</span>
                </a>
                
                <a href="/reports" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/reports') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                
                <a href="/forecast" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/forecast') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Forecasting</span>
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <header class="topbar">
                <div class="topbar-title">
                    <h2><?php echo $pageTitle ?? 'Dashboard'; ?></h2>
                </div>
                <div class="user-info">
                    <span class="user-name">
                        <i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                    </span>
                    <a href="/logout" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>
            
            <!-- Content Area -->
            <main class="content">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
