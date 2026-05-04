<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LGU Property Inventory System</title>
    <link rel="stylesheet" href="/lgu_invent/public/assets/css/style.css">
    <link rel="stylesheet" href="/lgu_invent/public/assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-body">
    <!-- Background Pattern -->
    <div class="login-background">
        <div class="pattern-overlay"></div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <!-- Logo Section -->
            <div class="login-header">
                <div class="logo-wrapper">
                    <!-- TODO: Replace with official LGU logo -->
                    <img src="/lgu_invent/public/assets/logo.png" alt="LGU Logo" class="login-logo" 
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2248%22 fill=%22%23B22222%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-size=%2236%22 font-weight=%22bold%22 fill=%22%23fff%22%3ELGU%3C/text%3E%3C/svg%3E'">
                </div>
                <h1 class="system-title">LGU Property Inventory</h1>
                <p class="system-subtitle">General Services Office</p>
                <div class="title-divider"></div>
            </div>
            
            <!-- Error Alert -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error animate-shake">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form action="/login" method="POST" id="loginForm" class="login-form">
                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i> Email Address
                    </label>
                    <div class="input-wrapper">
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               required 
                               placeholder="Enter your email address"
                               autocomplete="email">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div class="input-wrapper">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-input" 
                               required 
                               placeholder="Enter your password"
                               autocomplete="current-password">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-text">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </span>
                    <span class="btn-loader" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i> Signing in...
                    </span>
                </button>
            </form>
            
            <!-- Default Accounts Info -->
            <div class="login-footer">
                <div class="info-box">
                    <p class="info-title"><i class="fas fa-info-circle"></i> Default Test Accounts</p>
                    <div class="account-list">
                        <div class="account-item">
                            <i class="fas fa-user-shield"></i>
                            <span>admin@gso.local</span>
                        </div>
                        <div class="account-item">
                            <i class="fas fa-user"></i>
                            <span>staff1@gso.local</span>
                        </div>
                        <div class="account-item">
                            <i class="fas fa-user"></i>
                            <span>staff2@gso.local</span>
                        </div>
                    </div>
                    <p class="info-note">Password: <code>password123</code></p>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="login-page-footer">
            <p>&copy; <?php echo date('Y'); ?> Local Government Unit. All rights reserved.</p>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnLoader = btn.querySelector('.btn-loader');
            
            btn.disabled = true;
            btnText.style.display = 'none';
            btnLoader.style.display = 'inline-block';
        });
        
        // Input focus animations
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
        });
    </script>
</body>
</html>
