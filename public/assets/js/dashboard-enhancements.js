/**
 * Dashboard UI Enhancements
 * LGU Property Inventory Management System
 * 
 * Adds modern UI interactions without breaking existing functionality
 */

(function() {
    'use strict';
    
    // ========================================
    // SIDEBAR TOGGLE FUNCTIONALITY
    // ========================================
    
    function initSidebarToggle() {
        const sidebar = document.querySelector('.sb');
        const main = document.querySelector('.main');
        
        if (!sidebar) return;
        
        // Create toggle button
        const toggleBtn = document.createElement('div');
        toggleBtn.className = 'sb-toggle';
        toggleBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        toggleBtn.title = 'Toggle Sidebar';
        sidebar.appendChild(toggleBtn);
        
        // Load saved state
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            if (main) main.style.marginLeft = '70px';
        }
        
        // Toggle functionality
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const collapsed = sidebar.classList.contains('collapsed');
            
            // Adjust main content margin
            if (main) {
                main.style.marginLeft = collapsed ? '70px' : '260px';
            }
            
            // Save state
            localStorage.setItem('sidebarCollapsed', collapsed);
            
            // Trigger resize event for charts/tables
            window.dispatchEvent(new Event('resize'));
        });
    }
    
    // ========================================
    // USER PROFILE DROPDOWN
    // ========================================
    
    function initUserProfile() {
        // Get user info from session (if available in DOM)
        const userNameElement = document.querySelector('[data-user-name]');
        const userRoleElement = document.querySelector('[data-user-role]');
        
        if (!userNameElement) return;
        
        const userName = userNameElement.getAttribute('data-user-name') || 'User';
        const userRole = userRoleElement ? userRoleElement.getAttribute('data-user-role') : 'Staff';
        
        // Get initials for profile circle
        const initials = userName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        
        // Create user profile section in topbar
        const topbarRight = document.querySelector('.tb-right');
        if (!topbarRight) return;
        
        // Check if profile already exists
        if (document.querySelector('.user-profile')) return;
        
        const userProfile = document.createElement('div');
        userProfile.className = 'user-profile';
        userProfile.innerHTML = `
            <div class="profile-circle" title="User Profile">
                ${initials}
                <!-- TODO: Replace with user image -->
            </div>
            <div class="user-info-text">
                <div class="user-name">${userName}</div>
                <div class="user-role">${userRole}</div>
            </div>
        `;
        
        // Insert before logout button or at the end
        const logoutBtn = topbarRight.querySelector('.btn-logout');
        if (logoutBtn) {
            topbarRight.insertBefore(userProfile, logoutBtn);
        } else {
            topbarRight.appendChild(userProfile);
        }
    }
    
    // ========================================
    // LOGOUT CONFIRMATION
    // ========================================
    
    function initLogoutConfirmation() {
        const logoutButtons = document.querySelectorAll('.btn-logout, [href*="logout"]');
        
        logoutButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                const confirmed = confirm('Are you sure you want to logout?');
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                if (this.classList.contains('btn-logout')) {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging out...';
                    this.disabled = true;
                }
            });
        });
    }
    
    // ========================================
    // SMOOTH SCROLL TO TOP
    // ========================================
    
    function initScrollToTop() {
        const content = document.querySelector('.content');
        if (!content) return;
        
        // Create scroll to top button
        const scrollBtn = document.createElement('button');
        scrollBtn.className = 'scroll-to-top';
        scrollBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        scrollBtn.title = 'Scroll to Top';
        scrollBtn.style.cssText = `
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #B22222 0%, #8B0000 100%);
            color: #FFFFFF;
            border: none;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 998;
            box-shadow: 0 4px 12px rgba(178, 34, 34, 0.3);
        `;
        
        document.body.appendChild(scrollBtn);
        
        // Show/hide based on scroll
        content.addEventListener('scroll', function() {
            if (content.scrollTop > 300) {
                scrollBtn.style.opacity = '1';
                scrollBtn.style.visibility = 'visible';
            } else {
                scrollBtn.style.opacity = '0';
                scrollBtn.style.visibility = 'hidden';
            }
        });
        
        // Scroll to top on click
        scrollBtn.addEventListener('click', function() {
            content.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Hover effect
        scrollBtn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px) scale(1.05)';
            this.style.boxShadow = '0 6px 20px rgba(178, 34, 34, 0.4)';
        });
        
        scrollBtn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
            this.style.boxShadow = '0 4px 12px rgba(178, 34, 34, 0.3)';
        });
    }
    
    // ========================================
    // ENHANCED BUTTON INTERACTIONS
    // ========================================
    
    function initButtonEnhancements() {
        // Add ripple effect to all buttons
        document.querySelectorAll('.btn, .stat, .sb-item').forEach(element => {
            element.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                ripple.className = 'ripple';
                
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.5);
                    left: ${x}px;
                    top: ${y}px;
                    pointer-events: none;
                    animation: ripple-animation 0.6s ease-out;
                `;
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });
        
        // Add ripple animation
        if (!document.getElementById('ripple-styles')) {
            const style = document.createElement('style');
            style.id = 'ripple-styles';
            style.textContent = `
                @keyframes ripple-animation {
                    from {
                        transform: scale(0);
                        opacity: 1;
                    }
                    to {
                        transform: scale(2);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    // ========================================
    // LOADING INDICATORS
    // ========================================
    
    function showLoadingIndicator(message = 'Loading...') {
        // Remove existing loader
        const existingLoader = document.getElementById('global-loader');
        if (existingLoader) existingLoader.remove();
        
        const loader = document.createElement('div');
        loader.id = 'global-loader';
        loader.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            backdrop-filter: blur(4px);
        `;
        
        loader.innerHTML = `
            <div style="
                background: #FFFFFF;
                padding: 30px 40px;
                border-radius: 16px;
                text-align: center;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            ">
                <div class="spinner" style="
                    width: 40px;
                    height: 40px;
                    border: 4px solid #F0F0F0;
                    border-top-color: #B22222;
                    border-radius: 50%;
                    animation: spin 0.8s linear infinite;
                    margin: 0 auto 15px;
                "></div>
                <div style="
                    font-size: 14px;
                    color: #666;
                    font-weight: 600;
                ">${message}</div>
            </div>
        `;
        
        document.body.appendChild(loader);
        
        return loader;
    }
    
    function hideLoadingIndicator() {
        const loader = document.getElementById('global-loader');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => loader.remove(), 300);
        }
    }
    
    // Make globally available
    window.showLoadingIndicator = showLoadingIndicator;
    window.hideLoadingIndicator = hideLoadingIndicator;
    
    // ========================================
    // FORM VALIDATION ENHANCEMENTS
    // ========================================
    
    function initFormEnhancements() {
        // Add visual feedback to form inputs
        document.querySelectorAll('input, select, textarea').forEach(input => {
            // Focus effect
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
            
            // Validation feedback
            if (input.hasAttribute('required')) {
                input.addEventListener('invalid', function(e) {
                    e.preventDefault();
                    this.classList.add('is-invalid');
                    
                    // Show error message
                    let errorMsg = this.parentElement.querySelector('.error-message');
                    if (!errorMsg) {
                        errorMsg = document.createElement('div');
                        errorMsg.className = 'error-message';
                        errorMsg.style.cssText = `
                            color: #DC3545;
                            font-size: 12px;
                            margin-top: 4px;
                            animation: slideDown 0.3s ease-out;
                        `;
                        this.parentElement.appendChild(errorMsg);
                    }
                    errorMsg.textContent = this.validationMessage || 'This field is required';
                });
                
                input.addEventListener('input', function() {
                    if (this.validity.valid) {
                        this.classList.remove('is-invalid');
                        const errorMsg = this.parentElement.querySelector('.error-message');
                        if (errorMsg) errorMsg.remove();
                    }
                });
            }
        });
    }
    
    // ========================================
    // CONFIRMATION DIALOGS
    // ========================================
    
    function initConfirmationDialogs() {
        // Add confirmation to delete/critical actions
        document.querySelectorAll('[data-confirm]').forEach(element => {
            element.addEventListener('click', function(e) {
                const message = this.getAttribute('data-confirm') || 'Are you sure?';
                if (!confirm(message)) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
        });
    }
    
    // ========================================
    // INITIALIZE ALL ENHANCEMENTS
    // ========================================
    
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }
        
        // Initialize all enhancements
        initSidebarToggle();
        initUserProfile();
        initLogoutConfirmation();
        initScrollToTop();
        initButtonEnhancements();
        initFormEnhancements();
        initConfirmationDialogs();
        
        console.log('✨ Dashboard enhancements loaded successfully');
    }
    
    // Start initialization
    init();
    
})();
