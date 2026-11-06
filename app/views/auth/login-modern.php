<?php
if (!function_exists('lgue_url')) {
    require_once dirname(__DIR__, 3) . '/bootstrap.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="lgue-web-base" content="<?php echo htmlspecialchars(LGUE_WEB_BASE, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Login'; ?> · LGU Property Inventory</title>
    <?php require __DIR__ . '/../../../includes/favicon-links.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(lgue_url('assets/css/auth-modern.css', SYSTEM_VERSION), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-body">
    <div class="auth-visual-bg" aria-hidden="true"></div>
    <div class="auth-noise" aria-hidden="true"></div>

    <div class="auth-shell">
        <aside class="auth-hero">
            <div class="auth-hero-inner">
                <div class="auth-hero-logo">
                    <img src="<?php echo htmlspecialchars(lgue_url('public/assets/logo-lumban.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="Municipality of Lumban logo">
                </div>
                <h2 class="auth-hero-title">Inventory &amp; procurement in one secure workspace.</h2>
                <p class="auth-hero-lead">Track semi-expendables and PPE, manage issuance, and run departmental procurement — aligned with your office structure.</p>
                <ul class="auth-hero-points">
                    <li><i class="fas fa-check"></i><span>Role-based access</span></li>
                    <li><i class="fas fa-check"></i><span>Office-scoped procurement</span></li>
                    <li><i class="fas fa-check"></i><span>Print-ready PAR and PR workflows</span></li>
                </ul>
            </div>
            <div class="auth-hero-glow" aria-hidden="true"></div>
        </aside>

        <main class="auth-main">
            <div class="auth-card-float">
                <div class="auth-card-topbar"></div>

                <div class="auth-brand-row">
                    <div class="auth-logo-ring">
                        <div class="auth-logo-img">
                            <img src="<?php echo htmlspecialchars(lgue_url('public/assets/logo-lumban.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="Municipality of Lumban logo"
                                 onerror="this.style.display='none'; this.parentElement.classList.add('auth-logo-fallback');">
                        </div>
                    </div>
                    <div class="auth-brand-text">
                        <h1 class="auth-title">Property Inventory</h1>
                        <p class="auth-subtitle">General Services Office · Sign in or register</p>
                    </div>
                </div>

                <div class="auth-segmented" role="tablist">
                    <button type="button" class="auth-seg-btn active" onclick="switchTab('login')" id="loginTab" role="tab" aria-selected="true">
                        <i class="fas fa-right-to-bracket"></i><span>Sign in</span>
                    </button>
                    <button type="button" class="auth-seg-btn" onclick="switchTab('register')" id="registerTab" role="tab" aria-selected="false">
                        <i class="fas fa-user-plus"></i><span>Register</span>
                    </button>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="auth-alert auth-alert-error">
                        <i class="fas fa-circle-exclamation"></i>
                        <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="auth-alert auth-alert-success">
                        <i class="fas fa-circle-check"></i>
                        <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars(lgue_url('auth/login'), ENT_QUOTES, 'UTF-8'); ?>" method="POST" id="loginForm" class="auth-form auth-form-panel">
                    <div class="form-group">
                        <label for="login_email" class="form-label">Email <span class="required">*</span></label>
                        <div class="input-shell">
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" id="login_email" name="email" class="form-input" required
                                   placeholder="you@agency.gov.ph" autocomplete="email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="login_password" class="form-label">Password <span class="required">*</span></label>
                        <div class="input-shell">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="login_password" name="password" class="form-input" required
                                   placeholder="••••••••" autocomplete="current-password">
                            <button type="button" class="toggle-password" onclick="togglePassword('login_password')" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit" id="loginBtn">
                        <span class="btn-text"><i class="fas fa-arrow-right-to-bracket"></i> Sign in</span>
                        <span class="btn-loader"><i class="fas fa-spinner fa-spin"></i> Signing in…</span>
                    </button>
                    <p class="auth-forgot-wrap">
                        <button type="button" class="auth-forgot-link" onclick="openForgotPassword()">Forgot password?</button>
                    </p>
                </form>

                <div id="forgotPanel" class="auth-form auth-form-panel auth-forgot-panel" hidden>
                    <button type="button" class="auth-forgot-back" onclick="closeForgotPassword()">
                        <i class="fas fa-arrow-left"></i> Back to sign in
                    </button>
                    <p class="auth-forgot-msg" id="fp-msg" hidden></p>

                    <div id="fp-step-email">
                        <h2 class="auth-forgot-title">Reset password</h2>
                        <p class="auth-forgot-lead">Enter the email on your account. We will send a 6-digit code that expires in 5 minutes.</p>
                        <div class="form-group">
                            <label for="fp_email" class="form-label">Email <span class="required">*</span></label>
                            <div class="input-shell">
                                <span class="input-icon"><i class="fas fa-envelope"></i></span>
                                <input type="email" id="fp_email" class="form-input" autocomplete="email" placeholder="you@agency.gov.ph">
                            </div>
                        </div>
                        <button type="button" class="btn-submit" id="fpSendBtn" onclick="fpSendCode()">
                            <span class="btn-text"><i class="fas fa-paper-plane"></i> Send verification code</span>
                            <span class="btn-loader"><i class="fas fa-spinner fa-spin"></i> Sending…</span>
                        </button>
                    </div>

                    <div id="fp-step-code" hidden>
                        <h2 class="auth-forgot-title">Enter verification code</h2>
                        <p class="auth-forgot-lead">A 6-digit code was sent if this email is registered. Valid for <strong>5 minutes only</strong>.</p>
                        <p class="auth-forgot-timer" id="fp-timer">Code expires in 5:00</p>
                        <div class="form-group">
                            <label for="fp_code" class="form-label">Verification code <span class="required">*</span></label>
                            <div class="input-shell">
                                <span class="input-icon"><i class="fas fa-key"></i></span>
                                <input type="text" id="fp_code" class="form-input" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000">
                            </div>
                        </div>
                        <button type="button" class="btn-submit" id="fpVerifyBtn" onclick="fpVerifyCode()">
                            <span class="btn-text"><i class="fas fa-check"></i> Verify code</span>
                            <span class="btn-loader"><i class="fas fa-spinner fa-spin"></i> Checking…</span>
                        </button>
                        <p class="auth-forgot-wrap">
                            <button type="button" class="auth-forgot-link" onclick="fpSendCode()">Resend code</button>
                        </p>
                    </div>

                    <div id="fp-step-password" hidden>
                        <h2 class="auth-forgot-title">Create new password</h2>
                        <p class="auth-forgot-lead">Enter a new password, then retype it to confirm.</p>
                        <div class="form-group">
                            <label for="fp_password" class="form-label">Create new password <span class="required">*</span></label>
                            <div class="input-shell">
                                <span class="input-icon"><i class="fas fa-lock"></i></span>
                                <input type="password" id="fp_password" class="form-input" autocomplete="new-password" placeholder="Strong password">
                                <button type="button" class="toggle-password" onclick="togglePassword('fp_password')" aria-label="Show password"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="fp_confirm" class="form-label">Retype new password <span class="required">*</span></label>
                            <div class="input-shell">
                                <span class="input-icon"><i class="fas fa-lock"></i></span>
                                <input type="password" id="fp_confirm" class="form-input" autocomplete="new-password" placeholder="Repeat password">
                                <button type="button" class="toggle-password" onclick="togglePassword('fp_confirm')" aria-label="Show password"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <ul class="pw-req-list" id="fpPwReqList" aria-live="polite">
                            <li data-rule="len">Minimum 8 characters</li>
                            <li data-rule="upper">At least 1 uppercase letter</li>
                            <li data-rule="lower">At least 1 lowercase letter</li>
                            <li data-rule="num">At least 1 number</li>
                            <li data-rule="special">At least 1 special character</li>
                            <li data-rule="match">Passwords match</li>
                        </ul>
                        <button type="button" class="btn-submit" id="fpResetBtn" onclick="fpChangePassword()">
                            <span class="btn-text"><i class="fas fa-key"></i> Change password</span>
                            <span class="btn-loader"><i class="fas fa-spinner fa-spin"></i> Saving…</span>
                        </button>
                    </div>

                    <div id="fp-step-done" hidden>
                        <div class="auth-forgot-success">
                            <i class="fas fa-circle-check"></i>
                            <h2 class="auth-forgot-title">Password changed</h2>
                            <p class="auth-forgot-lead" id="fp-done-text">Your password has been changed. You can now sign in with the new password.</p>
                            <button type="button" class="btn-submit" onclick="closeForgotPassword()">
                                <span class="btn-text"><i class="fas fa-arrow-right-to-bracket"></i> Return to sign in</span>
                            </button>
                        </div>
                    </div>
                </div>

                <?php $__regOffices = isset($registerOffices) && is_array($registerOffices) ? $registerOffices : []; ?>
                <?php
                    $regPendingEmail = isset($_SESSION['reg_verify_email']) ? (string) $_SESSION['reg_verify_email'] : '';
                    $regPendingLeft = 0;
                    if ($regPendingEmail !== '' && !empty($_SESSION['reg_verify_expires'])) {
                        $regPendingLeft = max(0, (int) $_SESSION['reg_verify_expires'] - time());
                    }
                ?>

                <form action="<?php echo htmlspecialchars(lgue_url('auth/register'), ENT_QUOTES, 'UTF-8'); ?>" method="POST" id="registerForm" class="auth-form auth-form-panel auth-form-register" style="display:none;" novalidate>
                    <input type="hidden" name="office_id" id="reg_office_id" value="">
                    <input type="hidden" name="role" id="reg_role" value="User">
                    <p class="auth-forgot-msg" id="reg-msg" hidden></p>

                    <div id="reg-step-details">
                    <?php if (empty($__regOffices)): ?>
                        <div class="auth-alert auth-alert-error auth-alert-inline auth-reg-alert-wide">
                            <i class="fas fa-triangle-exclamation"></i>
                            <span>No departments in the database yet. Ask an administrator to add offices before registering as a department user.</span>
                        </div>
                    <?php endif; ?>

                    <div class="auth-reg-grid" role="group" aria-label="Registration fields">
                        <div class="auth-reg-cell auth-reg-span-2">
                            <label for="reg_name" class="form-label">Full name <span class="required">*</span></label>
                            <div class="input-shell">
                                <span class="input-icon"><i class="fas fa-user"></i></span>
                                <input type="text" id="reg_name" name="name" class="form-input" required maxlength="120"
                                       placeholder="Juan Dela Cruz" autocomplete="name"
                                       title="Use your real name — include at least one letter (not numbers only).">
                            </div>
                        </div>
                        <div class="auth-reg-cell">
                            <label for="reg_email" class="form-label">Email <span class="required">*</span></label>
                            <div class="input-shell">
                                <span class="input-icon"><i class="fas fa-envelope"></i></span>
                                <input type="email" id="reg_email" name="email" class="form-input" required maxlength="254"
                                       placeholder="you@agency.gov.ph" autocomplete="email"
                                       title="Include letters in the part before @ — not numbers only.">
                            </div>
                        </div>
                        <div class="auth-reg-cell">
                            <label for="reg_username" class="form-label">Username <span class="required">*</span></label>
                            <div class="input-shell">
                                <span class="input-icon"><i class="fas fa-at"></i></span>
                                <input type="text" id="reg_username" name="username" class="form-input" required maxlength="64" autocomplete="username"
                                       placeholder="juandelacruz"
                                       pattern="[A-Za-z][A-Za-z0-9_]{2,63}"
                                       title="Start with a letter; letters, numbers, and underscores only (3–64 characters).">
                            </div>
                        </div>

                        <div class="auth-reg-cell auth-reg-picker-cell">
                            <span id="lbl_reg_role" class="sr-only">Account type</span>
                            <div class="picker-trigger picker-trigger-reg picker-trigger-reg-static picked-valid" id="reg_role_btn" aria-labelledby="lbl_reg_role">
                                <span class="picker-trigger-reg-icon" aria-hidden="true"><i class="fas fa-building-user"></i></span>
                                <span class="picker-trigger-reg-main">
                                    <span class="picker-trigger-value" id="reg_role_display">Department user</span>
                                </span>
                            </div>
                        </div>

                        <div class="auth-reg-cell auth-reg-picker-cell" id="reg_office_group">
                            <span id="lbl_reg_office" class="sr-only">Department (required for users)</span>
                            <button type="button" class="picker-trigger picker-trigger-reg" id="reg_office_btn" onclick="openOfficePicker()" aria-labelledby="lbl_reg_office" aria-haspopup="dialog"
                                    <?php echo empty($__regOffices) ? 'disabled' : ''; ?>>
                                <span class="picker-trigger-reg-icon" aria-hidden="true"><i class="fas fa-building"></i></span>
                                <span class="picker-trigger-reg-main">
                                    <span class="picker-trigger-value picker-value-placeholder" id="reg_office_display">Department</span>
                                    <span class="picker-trigger-reg-star" id="reg_office_star_wrap" aria-hidden="true"><span class="required" id="reg_office_star">*</span></span>
                                </span>
                                <i class="fas fa-chevron-right picker-trigger-chevron"></i>
                            </button>
                        </div>

                        <p class="auth-reg-hint auth-reg-span-2" id="reg_office_hint">Required. Up to 3 staff accounts per department. GSO is the Administrator office and is not listed here.</p>

                        <div class="auth-reg-cell">
                            <label for="reg_password" class="form-label">Password <span class="required">*</span></label>
                            <div class="input-shell">
                                <span class="input-icon"><i class="fas fa-lock"></i></span>
                                <input type="password" id="reg_password" name="password" class="form-input" required minlength="8"
                                       placeholder="Strong password" autocomplete="new-password"
                                       title="At least 8 characters with uppercase, lowercase, a number, and a special character.">
                                <button type="button" class="toggle-password" onclick="togglePassword('reg_password')" aria-label="Show password"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="auth-reg-cell">
                            <label for="reg_confirm_password" class="form-label">Confirm <span class="required">*</span></label>
                            <div class="input-shell">
                                <span class="input-icon"><i class="fas fa-lock"></i></span>
                                <input type="password" id="reg_confirm_password" name="confirm_password" class="form-input" required minlength="8"
                                       placeholder="Repeat password" autocomplete="new-password">
                                <button type="button" class="toggle-password" onclick="togglePassword('reg_confirm_password')" aria-label="Show password"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <ul class="pw-req-list auth-reg-span-2" id="pwReqList" aria-live="polite">
                            <li data-rule="len">Minimum 8 characters</li>
                            <li data-rule="upper">At least 1 uppercase letter</li>
                            <li data-rule="lower">At least 1 lowercase letter</li>
                            <li data-rule="num">At least 1 number</li>
                            <li data-rule="special">At least 1 special character</li>
                        </ul>
                    </div>

                    <button type="submit" class="btn-submit btn-submit-alt btn-submit-reg" id="registerBtn">
                        <span class="btn-text"><i class="fas fa-paper-plane"></i> Send verification code</span>
                        <span class="btn-loader"><i class="fas fa-spinner fa-spin"></i> Sending…</span>
                    </button>
                    <p class="auth-reg-hint auth-reg-span-2" style="margin-top:12px;">We email a 6-digit code that expires in 5 minutes. The account is created only after you enter it.</p>
                    </div>

                    <div id="reg-step-code" hidden>
                        <button type="button" class="auth-forgot-back" onclick="regBackToDetails()">
                            <i class="fas fa-arrow-left"></i> Change details
                        </button>
                        <h2 class="auth-forgot-title">Verify your email</h2>
                        <p class="auth-forgot-lead">A 6-digit code was sent to <strong id="reg-code-email"></strong>. Valid for <strong>5 minutes only</strong>. Your account is created only after this code is entered.</p>
                        <p class="auth-forgot-timer" id="reg-timer">Code expires in 5:00</p>
                        <div class="form-group">
                            <label for="reg_code" class="form-label">Verification code <span class="required">*</span></label>
                            <div class="input-shell">
                                <span class="input-icon"><i class="fas fa-key"></i></span>
                                <input type="text" id="reg_code" class="form-input" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000">
                            </div>
                        </div>
                        <button type="button" class="btn-submit btn-submit-alt" id="regVerifyBtn" onclick="regVerifyCode()">
                            <span class="btn-text"><i class="fas fa-check"></i> Verify and create account</span>
                            <span class="btn-loader"><i class="fas fa-spinner fa-spin"></i> Checking…</span>
                        </button>
                        <p class="auth-forgot-wrap">
                            <button type="button" class="auth-forgot-link" onclick="regResendCode()">Resend code</button>
                        </p>
                    </div>

                    <div id="reg-step-done" hidden>
                        <div class="auth-forgot-success">
                            <i class="fas fa-circle-check"></i>
                            <h2 class="auth-forgot-title">Account created</h2>
                            <p class="auth-forgot-lead" id="reg-done-text">Your email is verified and the account is ready. You can now sign in.</p>
                            <button type="button" class="btn-submit" onclick="switchTab('login')">
                                <span class="btn-text"><i class="fas fa-arrow-right-to-bracket"></i> Sign in</span>
                            </button>
                        </div>
                    </div>
                </form>

                <p class="auth-foot-note">&copy; <?php echo date('Y'); ?> Local Government Unit · Internal use</p>
            </div>
        </main>
    </div>

    <!-- Office picker modal -->
    <div class="auth-modal-overlay" id="officePickerOverlay" hidden aria-hidden="true">
        <div class="auth-modal-sheet auth-modal-sheet-wide" role="dialog" aria-modal="true" aria-labelledby="officePickerTitle">
            <div class="auth-modal-head">
                <h3 id="officePickerTitle" class="auth-modal-title">Choose department</h3>
                <button type="button" class="auth-modal-close" onclick="closeOfficePicker()" aria-label="Close"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="office-search-wrap">
                <i class="fas fa-magnifying-glass"></i>
                <input type="search" id="officePickerSearch" class="office-search-input" placeholder="Filter by name or code…" autocomplete="off">
            </div>
            <div class="office-picker-list" id="officePickerList">
                <?php foreach ($__regOffices as $o):
                    $oid = (int) ($o['id'] ?? 0);
                    $ucount = (int) ($o['user_count'] ?? 0);
                    $acount = (int) ($o['admin_count'] ?? 0);
                    $atLimit = $ucount >= 3;
                    $meta = $ucount . '/3';
                    if ($acount > 0) {
                        $meta .= ' · ' . $acount . ' Admin';
                    }
                    $label = trim(($o['name'] ?? '') . (isset($o['code']) && $o['code'] !== '' ? ' · ' . $o['code'] : ''));
                    $searchBlob = strtolower($label);
                ?>
                    <button type="button" class="office-picker-item<?php echo $atLimit ? ' at-limit' : ''; ?>" data-id="<?php echo $oid; ?>"
                            data-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"
                            data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>"
                            data-user-count="<?php echo $ucount; ?>"
                            data-admin-count="<?php echo $acount; ?>"
                            data-at-limit="<?php echo $atLimit ? '1' : '0'; ?>">
                        <span class="office-picker-icon"><i class="fas fa-building"></i></span>
                        <span class="office-picker-text"><?php echo htmlspecialchars($label); ?></span>
                        <span class="office-picker-meta"><?php echo htmlspecialchars($meta); ?></span>
                        <i class="fas fa-check office-picker-check"></i>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php if (empty($__regOffices)): ?>
                <p class="office-picker-empty">No offices available.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Department account-limit modal (departments stay listed; selecting a full one shows this) -->
    <div class="auth-modal-overlay auth-limit-overlay" id="deptLimitOverlay" hidden aria-hidden="true">
        <div class="auth-modal-sheet" role="dialog" aria-modal="true" aria-labelledby="deptLimitTitle">
            <div class="auth-modal-head">
                <h3 id="deptLimitTitle" class="auth-modal-title">Department limit reached</h3>
                <button type="button" class="auth-modal-close" onclick="closeDeptLimitModal()" aria-label="Close"><i class="fas fa-xmark"></i></button>
            </div>
            <p class="auth-modal-desc" id="deptLimitMessage">This department has reached the maximum limit of 3 user accounts.</p>
            <button type="button" class="btn-submit btn-submit-alt" onclick="closeDeptLimitModal()">
                <span class="btn-text">OK</span>
            </button>
        </div>
    </div>

    <script>
        function refreshAuthModalScrollLock() {
            var anyOpen = document.querySelectorAll('.auth-modal-overlay.open').length > 0;
            document.body.classList.toggle('auth-modal-open', anyOpen);
        }

        function switchTab(tab) {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const loginTab = document.getElementById('loginTab');
            const registerTab = document.getElementById('registerTab');
            const forgotPanel = document.getElementById('forgotPanel');
            document.body.classList.toggle('auth-register-active', tab === 'register');
            if (forgotPanel) forgotPanel.hidden = true;

            if (tab === 'login') {
                closeOfficePicker();
                closeDeptLimitModal();
                loginForm.style.display = 'flex';
                registerForm.style.display = 'none';
                loginTab.classList.add('active');
                registerTab.classList.remove('active');
                loginTab.setAttribute('aria-selected', 'true');
                registerTab.setAttribute('aria-selected', 'false');
                refreshAuthModalScrollLock();
            } else {
                loginForm.style.display = 'none';
                registerForm.style.display = 'flex';
                loginTab.classList.remove('active');
                registerTab.classList.add('active');
                loginTab.setAttribute('aria-selected', 'false');
                registerTab.setAttribute('aria-selected', 'true');
                syncRegOfficeRequired();
                refreshAuthModalScrollLock();
            }
        }

        var regTimerId = null;
        var regEndsAt = 0;

        function regShowStep(step) {
            var details = document.getElementById('reg-step-details');
            var code = document.getElementById('reg-step-code');
            var done = document.getElementById('reg-step-done');
            if (details) details.hidden = step !== 'details';
            if (code) code.hidden = step !== 'code';
            if (done) done.hidden = step !== 'done';
        }

        function regSetMsg(text, kind) {
            var el = document.getElementById('reg-msg');
            if (!el) return;
            if (!text) {
                el.hidden = true;
                el.textContent = '';
                el.className = 'auth-forgot-msg';
                return;
            }
            el.hidden = false;
            el.textContent = text;
            el.className = 'auth-forgot-msg ' + (kind === 'ok' ? 'is-ok' : 'is-err');
        }

        function regTick() {
            var left = Math.max(0, Math.floor((regEndsAt - Date.now()) / 1000));
            var m = String(Math.floor(left / 60));
            var s = String(left % 60).padStart(2, '0');
            var t = document.getElementById('reg-timer');
            if (t) t.textContent = left > 0 ? ('Code expires in ' + m + ':' + s) : 'This code has expired. Request a new code.';
            if (left <= 0 && regTimerId) {
                clearInterval(regTimerId);
                regTimerId = null;
            }
        }

        function regStartTimer(seconds) {
            regEndsAt = Date.now() + (seconds * 1000);
            if (regTimerId) clearInterval(regTimerId);
            regTick();
            regTimerId = setInterval(regTick, 1000);
        }

        function regBackToDetails() {
            if (regTimerId) {
                clearInterval(regTimerId);
                regTimerId = null;
            }
            regSetMsg('');
            regShowStep('details');
        }

        function collectRegisterFields() {
            return {
                name: (document.getElementById('reg_name')?.value || '').trim(),
                email: (document.getElementById('reg_email')?.value || '').trim(),
                username: (document.getElementById('reg_username')?.value || '').trim(),
                password: document.getElementById('reg_password')?.value || '',
                confirm_password: document.getElementById('reg_confirm_password')?.value || '',
                office_id: document.getElementById('reg_office_id')?.value || '',
                role: 'User'
            };
        }

        async function regSendCode() {
            var data = collectRegisterFields();
            document.getElementById('reg-code-email').textContent = data.email;
            fpBusy('registerBtn', true);
            fpBusy('regVerifyBtn', true);
            regSetMsg('');
            try {
                var r = await fpPost('auth/register-start', data);
                if (!r.success) {
                    regSetMsg(r.message || 'Could not send the verification code.', 'err');
                    return false;
                }
                regSetMsg(r.message || 'A verification code was sent to your email.', 'ok');
                regShowStep('code');
                regStartTimer(r.expires_in || 300);
                document.getElementById('reg_code')?.focus();
                return true;
            } catch (err) {
                regSetMsg('Could not send the verification code. Check your connection.', 'err');
                return false;
            } finally {
                fpBusy('registerBtn', false);
                fpBusy('regVerifyBtn', false);
            }
        }

        async function regResendCode() {
            var data = collectRegisterFields();
            if (!data.password || !data.email) {
                regSetMsg('Fill in the registration form again to resend a code.', 'err');
                regShowStep('details');
                return;
            }
            await regSendCode();
        }

        async function regVerifyCode() {
            var email = (document.getElementById('reg_email')?.value || '').trim()
                || (document.getElementById('reg-code-email')?.textContent || '').trim();
            var code = (document.getElementById('reg_code')?.value || '').replace(/\D/g, '');
            if (code.length !== 6) {
                regSetMsg('Enter the 6-digit verification code.', 'err');
                return;
            }
            fpBusy('regVerifyBtn', true);
            regSetMsg('');
            try {
                var r = await fpPost('auth/register-verify', { email: email, code: code });
                if (!r.success) {
                    regSetMsg(r.message || 'Invalid code.', 'err');
                    return;
                }
                if (regTimerId) {
                    clearInterval(regTimerId);
                    regTimerId = null;
                }
                document.getElementById('reg-done-text').textContent = r.message || 'Your email is verified and the account is ready.';
                regSetMsg('');
                regShowStep('done');
            } catch (err) {
                regSetMsg('Could not verify the code.', 'err');
            } finally {
                fpBusy('regVerifyBtn', false);
            }
        }

        var fpTimerId = null;
        var fpEndsAt = 0;
        var fpBase = document.querySelector('meta[name="lgue-web-base"]')?.getAttribute('content') || '';

        function fpUrl(path) {
            return (fpBase.replace(/\/$/, '') || '') + '/' + path.replace(/^\//, '');
        }

        function fpShowStep(id) {
            ['fp-step-email', 'fp-step-code', 'fp-step-password', 'fp-step-done'].forEach(function (sid) {
                var el = document.getElementById(sid);
                if (el) el.hidden = sid !== id;
            });
        }

        function fpSetMsg(text, kind) {
            var el = document.getElementById('fp-msg');
            if (!el) return;
            if (!text) {
                el.hidden = true;
                el.textContent = '';
                el.className = 'auth-forgot-msg';
                return;
            }
            el.hidden = false;
            el.textContent = text;
            el.className = 'auth-forgot-msg ' + (kind === 'ok' ? 'is-ok' : 'is-err');
        }

        function fpBusy(btnId, on) {
            var btn = document.getElementById(btnId);
            if (!btn) return;
            btn.classList.toggle('loading', !!on);
            btn.disabled = !!on;
        }

        function openForgotPassword() {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('registerForm').style.display = 'none';
            document.getElementById('forgotPanel').hidden = false;
            document.getElementById('loginTab')?.classList.remove('active');
            document.getElementById('registerTab')?.classList.remove('active');
            var loginEmail = document.getElementById('login_email')?.value || '';
            if (loginEmail && !document.getElementById('fp_email').value) {
                document.getElementById('fp_email').value = loginEmail;
            }
            fpShowStep('fp-step-email');
            fpSetMsg('');
        }

        function closeForgotPassword() {
            if (fpTimerId) {
                clearInterval(fpTimerId);
                fpTimerId = null;
            }
            document.getElementById('forgotPanel').hidden = true;
            switchTab('login');
        }

        function fpTick() {
            var left = Math.max(0, Math.floor((fpEndsAt - Date.now()) / 1000));
            var m = String(Math.floor(left / 60));
            var s = String(left % 60).padStart(2, '0');
            var t = document.getElementById('fp-timer');
            if (t) t.textContent = left > 0 ? ('Code expires in ' + m + ':' + s) : 'This code has expired. Request a new code.';
            if (left <= 0 && fpTimerId) {
                clearInterval(fpTimerId);
                fpTimerId = null;
            }
        }

        function fpStartTimer(seconds) {
            fpEndsAt = Date.now() + (seconds * 1000);
            if (fpTimerId) clearInterval(fpTimerId);
            fpTick();
            fpTimerId = setInterval(fpTick, 1000);
        }

        async function fpPost(path, data) {
            var body = new URLSearchParams(data);
            var res = await fetch(fpUrl(path), {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: body,
                credentials: 'same-origin'
            });
            var json = await res.json().catch(function () { return { success: false, message: 'Unexpected server response' }; });
            return json;
        }

        async function fpSendCode() {
            var email = (document.getElementById('fp_email').value || '').trim();
            if (!email) {
                fpSetMsg('Enter your registered email address.', 'err');
                return;
            }
            fpBusy('fpSendBtn', true);
            fpSetMsg('');
            try {
                var r = await fpPost('auth/forgot-request', { email: email });
                if (!r.success) {
                    fpSetMsg(r.message || 'Could not send the code.', 'err');
                    return;
                }
                fpSetMsg(r.message || 'If this email is registered, a code was sent.', 'ok');
                fpShowStep('fp-step-code');
                fpStartTimer(r.expires_in || 300);
                document.getElementById('fp_code')?.focus();
            } catch (e) {
                fpSetMsg('Could not send the code. Check your connection.', 'err');
            } finally {
                fpBusy('fpSendBtn', false);
            }
        }

        async function fpVerifyCode() {
            var email = (document.getElementById('fp_email').value || '').trim();
            var code = (document.getElementById('fp_code').value || '').replace(/\D/g, '');
            if (code.length !== 6) {
                fpSetMsg('Enter the 6-digit verification code.', 'err');
                return;
            }
            fpBusy('fpVerifyBtn', true);
            fpSetMsg('');
            try {
                var r = await fpPost('auth/forgot-verify', { email: email, code: code });
                if (!r.success) {
                    fpSetMsg(r.message || 'Invalid code.', 'err');
                    return;
                }
                if (fpTimerId) {
                    clearInterval(fpTimerId);
                    fpTimerId = null;
                }
                fpSetMsg(r.message || 'Code verified.', 'ok');
                fpShowStep('fp-step-password');
                document.getElementById('fp_password')?.focus();
            } catch (e) {
                fpSetMsg('Could not verify the code.', 'err');
            } finally {
                fpBusy('fpVerifyBtn', false);
            }
        }

        function refreshFpPasswordRequirements() {
            var pw = document.getElementById('fp_password')?.value || '';
            var cf = document.getElementById('fp_confirm')?.value || '';
            var state = passwordStrengthState(pw);
            state.match = pw.length > 0 && pw === cf;
            document.querySelectorAll('#fpPwReqList [data-rule]').forEach(function (li) {
                li.classList.toggle('met', !!state[li.getAttribute('data-rule')]);
            });
        }

        async function fpChangePassword() {
            var password = document.getElementById('fp_password').value;
            var confirmPassword = document.getElementById('fp_confirm').value;
            if (!isStrongPassword(password)) {
                fpSetMsg('Password must be at least 8 characters and include 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special character.', 'err');
                return;
            }
            if (password !== confirmPassword) {
                fpSetMsg('Passwords do not match.', 'err');
                return;
            }
            fpBusy('fpResetBtn', true);
            fpSetMsg('');
            try {
                var r = await fpPost('auth/forgot-reset', { password: password, confirm_password: confirmPassword });
                if (!r.success) {
                    fpSetMsg(r.message || 'Could not change the password.', 'err');
                    return;
                }
                document.getElementById('fp-done-text').textContent = r.message || 'Your password has been changed.';
                fpSetMsg('');
                fpShowStep('fp-step-done');
            } catch (e) {
                fpSetMsg('Could not change the password.', 'err');
            } finally {
                fpBusy('fpResetBtn', false);
            }
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentElement.querySelector('.toggle-password i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function openOfficePicker() {
            const btn = document.getElementById('reg_office_btn');
            if (btn.disabled) return;
            var el = document.getElementById('officePickerOverlay');
            el.removeAttribute('hidden');
            el.classList.add('open');
            el.setAttribute('aria-hidden', 'false');
            const q = document.getElementById('officePickerSearch');
            if (q) { q.value = ''; filterOfficeList(''); q.focus(); }
            refreshAuthModalScrollLock();
        }

        function closeOfficePicker() {
            var el = document.getElementById('officePickerOverlay');
            el.classList.remove('open');
            el.setAttribute('hidden', '');
            el.setAttribute('aria-hidden', 'true');
            refreshAuthModalScrollLock();
        }

        function openDeptLimitModal() {
            var el = document.getElementById('deptLimitOverlay');
            if (!el) return;
            el.removeAttribute('hidden');
            el.classList.add('open');
            el.setAttribute('aria-hidden', 'false');
            refreshAuthModalScrollLock();
        }

        function closeDeptLimitModal() {
            var el = document.getElementById('deptLimitOverlay');
            if (!el) return;
            el.classList.remove('open');
            el.setAttribute('hidden', '');
            el.setAttribute('aria-hidden', 'true');
            refreshAuthModalScrollLock();
        }

        document.querySelectorAll('.office-picker-item').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.getAttribute('data-at-limit') === '1') {
                    openDeptLimitModal();
                    return;
                }
                var id = btn.getAttribute('data-id');
                var label = btn.getAttribute('data-label') || '';
                document.getElementById('reg_office_id').value = id;
                var od = document.getElementById('reg_office_display');
                od.textContent = label;
                od.classList.remove('picker-value-placeholder');
                document.getElementById('reg_office_btn').classList.add('picked-valid');
                closeOfficePicker();
            });
        });

        function filterOfficeList(q) {
            var needle = (q || '').toLowerCase().trim();
            document.querySelectorAll('.office-picker-item').forEach(function (el) {
                var hay = el.getAttribute('data-search') || '';
                el.style.display = !needle || hay.indexOf(needle) !== -1 ? '' : 'none';
            });
        }

        document.getElementById('officePickerSearch')?.addEventListener('input', function () {
            filterOfficeList(this.value);
        });

        document.getElementById('officePickerOverlay')?.addEventListener('click', function (e) {
            if (e.target === this) closeOfficePicker();
        });
        document.getElementById('deptLimitOverlay')?.addEventListener('click', function (e) {
            if (e.target === this) closeDeptLimitModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDeptLimitModal();
                closeOfficePicker();
            }
        });

        function passwordStrengthState(pw) {
            return {
                len: pw.length >= 8,
                upper: /[A-Z]/.test(pw),
                lower: /[a-z]/.test(pw),
                num: /[0-9]/.test(pw),
                special: /[^A-Za-z0-9]/.test(pw)
            };
        }

        function isStrongPassword(pw) {
            var s = passwordStrengthState(pw);
            return s.len && s.upper && s.lower && s.num && s.special;
        }

        function refreshPasswordRequirements() {
            var pw = document.getElementById('reg_password')?.value || '';
            var state = passwordStrengthState(pw);
            document.querySelectorAll('#pwReqList [data-rule]').forEach(function (li) {
                var ok = !!state[li.getAttribute('data-rule')];
                li.classList.toggle('met', ok);
            });
        }

        document.getElementById('reg_password')?.addEventListener('input', refreshPasswordRequirements);
        document.getElementById('fp_password')?.addEventListener('input', refreshFpPasswordRequirements);
        document.getElementById('fp_confirm')?.addEventListener('input', refreshFpPasswordRequirements);
        document.addEventListener('DOMContentLoaded', refreshPasswordRequirements);

        document.getElementById('loginForm').addEventListener('submit', function () {
            document.getElementById('loginBtn').classList.add('loading');
            document.getElementById('loginBtn').disabled = true;
        });

        document.getElementById('registerForm').addEventListener('submit', function (e) {
            e.preventDefault();
            if (document.getElementById('reg-step-code') && !document.getElementById('reg-step-code').hidden) {
                regVerifyCode();
                return false;
            }
            if (document.getElementById('reg-step-done') && !document.getElementById('reg-step-done').hidden) {
                switchTab('login');
                return false;
            }
            var password = document.getElementById('reg_password').value;
            var confirmPassword = document.getElementById('reg_confirm_password').value;
            var nameEl = document.getElementById('reg_name');
            var emailEl = document.getElementById('reg_email');
            var userEl = document.getElementById('reg_username');

            function hasLetter(str) {
                try {
                    return str && /\p{L}/u.test(str);
                } catch (err) {
                    return /[a-zA-Z]/.test(str);
                }
            }

            var rawName = (nameEl.value || '').trim();
            if (!hasLetter(rawName)) {
                regSetMsg('Full name must contain at least one letter.', 'err');
                nameEl.focus();
                return false;
            }
            var compactName = rawName.replace(/\s+/g, '').toLowerCase();
            if (compactName.length >= 6 && (/^(asd|asdf|qwe|qwer|zxc|xxx|aaa|test|qwerty|asdfgh)+$/i.test(compactName) || /(.)\1{4,}/.test(compactName))) {
                regSetMsg('Full name must be your real name — random letters cannot be submitted.', 'err');
                nameEl.focus();
                return false;
            }
            if (!/^[A-Za-zÑñÁÉÍÓÚáéíóúüÜ][A-Za-zÑñÁÉÍÓÚáéíóúüÜ .,'\-]{1,119}$/.test(rawName)) {
                regSetMsg('Full name must be a proper name (letters only).', 'err');
                nameEl.focus();
                return false;
            }

            var email = (emailEl.value || '').trim();
            var localPart = email.split('@')[0] || '';
            if (email.indexOf('@') === -1 || !hasLetter(localPart)) {
                regSetMsg('Enter an email whose part before @ includes letters — not numbers only.', 'err');
                emailEl.focus();
                return false;
            }

            var user = (userEl.value || '').trim();
            if (!/^[a-zA-Z][a-zA-Z0-9_]{2,63}$/.test(user)) {
                regSetMsg('Username must start with a letter and use only letters, numbers, and underscores (3–64 characters).', 'err');
                userEl.focus();
                return false;
            }

            if (!isStrongPassword(password)) {
                regSetMsg('Password must be at least 8 characters and include 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special character.', 'err');
                document.getElementById('reg_password').focus();
                return false;
            }

            if (password !== confirmPassword) {
                regSetMsg('Passwords do not match.', 'err');
                return false;
            }
            var officeInp = document.getElementById('reg_office_id');
            var officeBtn = document.getElementById('reg_office_btn');
            if (officeBtn && !officeBtn.disabled && (!officeInp.value || officeInp.value === '0')) {
                regSetMsg('Please choose your department / office.', 'err');
                openOfficePicker();
                return false;
            }
            regSendCode();
        });

        document.addEventListener('DOMContentLoaded', function () {
            var pending = <?php echo json_encode($regPendingEmail, JSON_UNESCAPED_UNICODE); ?>;
            var left = <?php echo (int) $regPendingLeft; ?>;
            if (pending) {
                switchTab('register');
                var emailEl = document.getElementById('reg_email');
                if (emailEl && !emailEl.value) emailEl.value = pending;
                var shown = document.getElementById('reg-code-email');
                if (shown) shown.textContent = pending;
                regShowStep('code');
                if (left > 0) {
                    regStartTimer(left);
                } else {
                    var t = document.getElementById('reg-timer');
                    if (t) t.textContent = 'This code has expired. Request a new code.';
                }
            }
        });

        document.querySelectorAll('.form-input').forEach(function (input) {
            input.addEventListener('focus', function () {
                this.closest('.input-shell')?.classList.add('focused');
            });
            input.addEventListener('blur', function () {
                this.closest('.input-shell')?.classList.remove('focused');
            });
        });

        function syncRegOfficeRequired() {
            var star = document.getElementById('reg_office_star');
            var starWrap = document.getElementById('reg_office_star_wrap');
            var officeBtn = document.getElementById('reg_office_btn');
            var group = document.getElementById('reg_office_group');
            if (starWrap) starWrap.style.visibility = 'visible';
            if (star) star.style.visibility = 'visible';
            officeBtn?.classList.add('picker-trigger-required');
            group?.classList.add('office-required');
        }

        document.addEventListener('DOMContentLoaded', syncRegOfficeRequired);
    </script>
</body>
</html>
