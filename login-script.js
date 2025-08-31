// DOM Elements
const loginForm = document.getElementById('loginForm');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const passwordToggle = document.getElementById('passwordToggle');
const rememberCheckbox = document.getElementById('remember');
const loginBtn = document.querySelector('.login-btn');
const btnText = document.querySelector('.btn-text');
const loadingSpinner = document.querySelector('.loading-spinner');

// Demo credentials
const DEMO_CREDENTIALS = {
    email: 'demo@company.com',
    password: 'demo123'
};

// Password visibility toggle
function togglePasswordVisibility() {
    const type = passwordInput.type === 'password' ? 'text' : 'password';
    passwordInput.type = type;
    
    const icon = passwordToggle.querySelector('i');
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}

// Form validation
function validateForm() {
    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();
    
    // Reset previous error states
    clearErrors();
    
    let isValid = true;
    
    // Email validation
    if (!email) {
        showFieldError(emailInput, 'Email is required');
        isValid = false;
    } else if (!isValidEmail(email)) {
        showFieldError(emailInput, 'Please enter a valid email address');
        isValid = false;
    }
    
    // Password validation
    if (!password) {
        showFieldError(passwordInput, 'Password is required');
        isValid = false;
    } else if (password.length < 6) {
        showFieldError(passwordInput, 'Password must be at least 6 characters');
        isValid = false;
    }
    
    return isValid;
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function showFieldError(input, message) {
    const inputWrapper = input.closest('.input-wrapper');
    inputWrapper.classList.add('error');
    
    // Remove existing error message
    const existingError = inputWrapper.parentNode.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // Add error message
    const errorElement = document.createElement('div');
    errorElement.className = 'error-message';
    errorElement.textContent = message;
    inputWrapper.parentNode.appendChild(errorElement);
    
    // Add error styles
    input.style.borderColor = '#ef4444';
    input.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
}

function clearErrors() {
    // Remove error classes and styles
    document.querySelectorAll('.input-wrapper').forEach(wrapper => {
        wrapper.classList.remove('error');
        const input = wrapper.querySelector('input');
        input.style.borderColor = '';
        input.style.boxShadow = '';
    });
    
    // Remove error messages
    document.querySelectorAll('.error-message').forEach(error => {
        error.remove();
    });
}

// Login simulation
async function simulateLogin(email, password) {
    // Show loading state
    loginBtn.classList.add('loading');
    loginBtn.disabled = true;
    
    // Simulate API call delay
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Check credentials
    if (email === DEMO_CREDENTIALS.email && password === DEMO_CREDENTIALS.password) {
        // Show success message
        showToast('Login successful! Redirecting...', 'success');
        
        // Redirect to dashboard after short delay
        setTimeout(() => {
            window.location.href = 'index.php';
        }, 1000);
        
        return true;
    } else {
        // Failed login
        showToast('Invalid email or password. Please try again.', 'error');
        
        // Highlight form with error state
        loginForm.classList.add('shake');
        setTimeout(() => {
            loginForm.classList.remove('shake');
        }, 500);
        
        return false;
    }
}

// Social login handlers
function handleMicrosoftLogin() {
    showToast('Microsoft login would redirect to Microsoft OAuth', 'info');
    // In PHP version, this is handled by the social login buttons in HTML
}

function handleGoogleLogin() {
    showToast('Google login would redirect to Google OAuth', 'info');
    // In PHP version, this is handled by the social login buttons in HTML
}

// Toast notification system
function showToast(message, type = 'info') {
    // Remove existing toast
    const existingToast = document.querySelector('.toast');
    if (existingToast) {
        existingToast.remove();
    }
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = type === 'success' ? 'fa-check-circle' : 
                type === 'error' ? 'fa-exclamation-circle' : 
                'fa-info-circle';
    
    toast.innerHTML = `
        <i class="fas ${icon}"></i>
        <span>${message}</span>
    `;
    
    // Toast styles
    const bgColor = type === 'success' ? '#10b981' : 
                   type === 'error' ? '#ef4444' : 
                   '#6366f1';
    
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${bgColor};
        color: white;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 8px;
        z-index: 9999;
        font-weight: 500;
        max-width: 400px;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Remove after delay
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 300);
    }, 4000);
}

// Auto-fill demo credentials
function fillDemoCredentials() {
    emailInput.value = DEMO_CREDENTIALS.email;
    passwordInput.value = DEMO_CREDENTIALS.password;
    
    // Add subtle animation to show the fields were filled
    [emailInput, passwordInput].forEach(input => {
        input.style.transform = 'scale(1.02)';
        input.style.borderColor = '#6366f1';
        setTimeout(() => {
            input.style.transform = '';
            input.style.borderColor = '';
        }, 300);
    });
    
    showToast('Demo credentials filled', 'info');
}

// Input animations
function addInputAnimations() {
    const inputs = document.querySelectorAll('input');
    
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.closest('.input-wrapper').classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.closest('.input-wrapper').classList.remove('focused');
        });
        
        input.addEventListener('input', function() {
            if (this.value.length > 0) {
                this.closest('.input-wrapper').classList.add('has-value');
            } else {
                this.closest('.input-wrapper').classList.remove('has-value');
            }
        });
    });
}

// Keyboard shortcuts
function handleKeyboardShortcuts(event) {
    // Ctrl + D to fill demo credentials
    if (event.ctrlKey && event.key === 'd') {
        event.preventDefault();
        fillDemoCredentials();
    }
    
    // Enter key on social buttons
    if (event.key === 'Enter' && event.target.classList.contains('social-btn')) {
        event.target.click();
    }
}

// Check if already logged in - handled server-side in PHP version
function checkExistingLogin() {
    // Authentication state is managed server-side
    // Pre-filled email is handled by PHP from cookies
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Check existing login
    checkExistingLogin();
    
    // Password toggle
    passwordToggle?.addEventListener('click', togglePasswordVisibility);
    
    // Form submission
    loginForm?.addEventListener('submit', function(event) {
        // Only prevent default if validation fails
        if (!validateForm()) {
            event.preventDefault();
            return;
        }
        
        // Show loading state while form submits
        loginBtn.classList.add('loading');
        loginBtn.disabled = true;
        
        // Let the form submit normally to PHP - don't prevent default
        // The PHP backend will handle authentication and redirect
    });
    
    // Social login buttons
    document.querySelector('.microsoft-btn')?.addEventListener('click', handleMicrosoftLogin);
    document.querySelector('.google-btn')?.addEventListener('click', handleGoogleLogin);
    
    // Demo notice click to auto-fill
    document.querySelector('.demo-notice')?.addEventListener('click', fillDemoCredentials);
    
    // Input animations
    addInputAnimations();
    
    // Keyboard shortcuts
    document.addEventListener('keydown', handleKeyboardShortcuts);
    
    // Clear errors on input
    [emailInput, passwordInput].forEach(input => {
        input?.addEventListener('input', clearErrors);
    });
    
    // Show welcome message after page load
    setTimeout(() => {
        showToast('Click the demo notice below to auto-fill credentials', 'info');
    }, 1500);
});

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    .input-wrapper.focused {
        transform: scale(1.01);
    }
    
    .input-wrapper.has-value i {
        color: #6366f1;
    }
    
    .error-message {
        color: #ef4444;
        font-size: 12px;
        margin-top: 4px;
        font-weight: 500;
    }
    
    .login-form.shake {
        animation: shake 0.5s ease-in-out;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    
    .demo-notice {
        cursor: pointer;
        transition: transform 0.2s ease;
    }
    
    .demo-notice:hover {
        transform: translateX(-50%) scale(1.02);
    }
    
    @media (max-width: 768px) {
        .demo-notice:hover {
            transform: scale(1.02);
        }
    }
`;
document.head.appendChild(style);