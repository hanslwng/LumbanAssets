<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Login'; ?> - LGU Property Inventory</title>
    <link rel="stylesheet" href="/lgu_invent/public/assets/css/auth-modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-body">
    <!-- Animated Background -->
    <div class="auth-background"></div>

    <div class="auth-container">
        <div class="auth-card">
            <!-- Logo Section -->
            <div class="auth-logo">
                <div class="auth-logo-img">
                    <img src="/lgu_invent/public/assets/logo.png" alt="LGU Logo" 
                         onerror="this.style.display='none'; this.parentElement.innerHTML='LGU';">
                </div>
                <h1 class="auth-title">LGU Property Inventory</h1>
                <p class="auth-subtitle">General Services Office</p>
            </div>

            <!-- Tab Switcher -->
            <div class="auth-tabs">
                <button class="auth-tab active" onclick="switchTab('login')" id="loginTab">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
                <button class="auth-tab" onclick="switchTab('register')" id="registerTab">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </div>

            <!-- Error/Success Messages -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="auth-alert auth-alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="auth-alert auth-alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                </div>
            <?php endif; ?>

            <!-- LOGIN FORM -->
            <form action="/lgu_invent/auth/login" method="POST" id="loginForm" class="auth-form">
                <div class="form-group">
                    <label for="login_email" class="form-label">
                        <i class="fas fa-envelope"></i> Email Address <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" 
                               id="login_email" 
                               name="email" 
                               class="form-input" 
                               required 
                               placeholder="Enter your email"
                               autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <label for="login_password" class="form-label">
                        <i class="fas fa-lock"></i> Password <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" 
                               id="login_password" 
                               name="password" 
                               class="form-input" 
                               required 
                               placeholder="Enter your password"
                               autocomplete="current-password">
                        <button type="button" class="toggle-password" onclick="togglePassword('login_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="loginBtn">
                    <span class="btn-text">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </span>
                    <span class="btn-loader">
                        <i class="fas fa-spinner fa-spin"></i> Signing in...
                    </span>
                </button>
            </form>

            <!-- REGISTER FORM -->
            <form action="/lgu_invent/auth/register" method="POST" id="registerForm" class="auth-form" style="display: none;">
                <div class="form-group">
                    <label for="reg_name" class="form-label">
                        <i class="fas fa-user"></i> Full Name <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" 
                               id="reg_name" 
                               name="name" 
                               class="form-input" 
                               required 
                               placeholder="Enter your full name"
                               autocomplete="name">
                    </div>
                </div>

                <div class="form-group">
                    <label for="reg_email" class="form-label">
                        <i class="fas fa-envelope"></i> Email Address <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" 
                               id="reg_email" 
                               name="email" 
                               class="form-input" 
                               required 
                               placeholder="Enter your email"
                               autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <label for="reg_username" class="form-label">
                        <i class="fas fa-at"></i> Username <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-at"></i></span>
                        <input type="text" 
                               id="reg_username" 
                               name="username" 
                               class="form-input" 
                               required 
                               placeholder="Choose a username"
                               autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label for="reg_password" class="form-label">
                        <i class="fas fa-lock"></i> Password <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" 
                               id="reg_password" 
                               name="password" 
                               class="form-input" 
                               required 
                               minlength="8"
                               placeholder="Create a password (min. 8 characters)"
                               autocomplete="new-password">
                        <button type="button" class="toggle-password" onclick="togglePassword('reg_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="reg_confirm_password" class="form-label">
                        <i class="fas fa-lock"></i> Confirm Password <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" 
                               id="reg_confirm_password" 
                               name="confirm_password" 
                               class="form-input" 
                               required 
                               minlength="8"
                               placeholder="Confirm your password"
                               autocomplete="new-password">
                        <button type="button" class="toggle-password" onclick="togglePassword('reg_confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="reg_role" class="form-label">
                        <i class="fas fa-user-tag"></i> Role <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                        <select id="reg_role" name="role" class="form-select" required>
                            <option value="">Select your role</option>
                            <option value="Admin">Admin</option>
                            <option value="User">User</option>
                            <option value="Viewer">Viewer</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="registerBtn">
                    <span class="btn-text">
                        <i class="fas fa-user-plus"></i> Create Account
                    </span>
                    <span class="btn-loader">
                        <i class="fas fa-spinner fa-spin"></i> Creating account...
                    </span>
                </button>
            </form>

            <!-- Default Accounts Info (Only show on login) -->
            <div class="auth-info" id="defaultAccountsInfo">
                <p class="auth-info-title">
                    <i class="fas fa-info-circle"></i> Default Test Accounts
                </p>
                <div class="auth-info-list">
                    <div class="auth-info-item">
                        <i class="fas fa-user-shield"></i>
                        <span>admin@gso.local</span>
                    </div>
                    <div class="auth-info-item">
                        <i class="fas fa-user"></i>
                        <span>staff1@gso.local</span>
                    </div>
                    <div class="auth-info-item">
                        <i class="fas fa-user"></i>
                        <span>staff2@gso.local</span>
                    </div>
                    <div class="auth-info-item">
                        <span>Password: <code>password123</code></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Footer -->
        <div class="auth-page-footer">
            <p>&copy; <?php echo date('Y'); ?> Local Government Unit. All rights reserved.</p>
        </div>
    </div>

    <script>
        // Switch between login and register tabs
        function switchTab(tab) {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const loginTab = document.getElementById('loginTab');
            const registerTab = document.getElementById('registerTab');
            const defaultInfo = document.getElementById('defaultAccountsInfo');

            if (tab === 'login') {
                loginForm.style.display = 'flex';
                registerForm.style.display = 'none';
                loginTab.classList.add('active');
                registerTab.classList.remove('active');
                defaultInfo.style.display = 'block';
            } else {
                loginForm.style.display = 'none';
                registerForm.style.display = 'flex';
                loginTab.classList.remove('active');
                registerTab.classList.add('active');
                defaultInfo.style.display = 'none';
            }
        }

        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentElement.querySelector('.toggle-password i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('reg_password').value;
            const confirmPassword = document.getElementById('reg_confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }

            const btn = document.getElementById('registerBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });

        // Input focus animations
        document.querySelectorAll('.form-input, .form-select').forEach(input => {
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
