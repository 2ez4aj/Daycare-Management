// Register page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const submitButton = registerForm.querySelector('button[type="submit"]');
    
    // Handle form submission
    registerForm.addEventListener('submit', function(e) {
        const formData = new FormData(registerForm);
        const password = formData.get('password');
        const confirmPassword = formData.get('confirm_password');
        
        // Validate passwords match
        if (password !== confirmPassword) {
            e.preventDefault();
            showError('Passwords do not match');
            return;
        }
        
        // Validate password length
        if (password.length < 6) {
            e.preventDefault();
            showError('Password must be at least 6 characters long');
            return;
        }
        
        // Validate required fields
        const requiredFields = ['first_name', 'last_name', 'username', 'email', 'password'];
        for (let field of requiredFields) {
            if (!formData.get(field).trim()) {
                e.preventDefault();
                showError('Please fill in all required fields');
                return;
            }
        }
        
        // Validate email format
        const email = formData.get('email');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            showError('Please enter a valid email address');
            return;
        }
        
        // Show loading state
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
        submitButton.disabled = true;
        
        // Reset button after a delay if form submission fails
        setTimeout(() => {
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }, 5000);
    });
    
    // Handle URL parameters for messages
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    
    if (error) {
        let errorMessage = 'An error occurred';
        switch (error) {
            case 'empty_fields':
                errorMessage = 'Please fill in all required fields';
                break;
            case 'password_mismatch':
                errorMessage = 'Passwords do not match';
                break;
            case 'password_too_short':
                errorMessage = 'Password must be at least 6 characters long';
                break;
            case 'user_exists':
                errorMessage = 'Username or email already exists';
                break;
            case 'registration_failed':
                errorMessage = 'Registration failed. Please try again.';
                break;
            case 'system_error':
                errorMessage = 'System error. Please try again later.';
                break;
        }
        showError(errorMessage);
    }
    
    // Real-time password confirmation validation
    const passwordField = registerForm.querySelector('input[name="password"]');
    const confirmPasswordField = registerForm.querySelector('input[name="confirm_password"]');
    
    confirmPasswordField.addEventListener('input', function() {
        if (this.value && passwordField.value !== this.value) {
            this.setCustomValidity('Passwords do not match');
        } else {
            this.setCustomValidity('');
        }
    });
    
    passwordField.addEventListener('input', function() {
        if (confirmPasswordField.value && this.value !== confirmPasswordField.value) {
            confirmPasswordField.setCustomValidity('Passwords do not match');
        } else {
            confirmPasswordField.setCustomValidity('');
        }
    });
});

function showError(message) {
    showNotification(message, 'error');
}

function showNotification(message, type) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 400px;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 14px;
        animation: slideIn 0.3s ease;
        ${getNotificationStyles(type)}
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

function getNotificationIcon(type) {
    switch (type) {
        case 'success': return 'fa-check-circle';
        case 'error': return 'fa-exclamation-circle';
        case 'info': return 'fa-info-circle';
        default: return 'fa-info-circle';
    }
}

function getNotificationStyles(type) {
    switch (type) {
        case 'success':
            return 'background: #d4edda; color: #155724; border-left: 4px solid #28a745;';
        case 'error':
            return 'background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545;';
        case 'info':
            return 'background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8;';
        default:
            return 'background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8;';
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .notification-content {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
    }
    
    .notification-close {
        background: none;
        border: none;
        cursor: pointer;
        opacity: 0.7;
        padding: 5px;
        margin-left: 10px;
    }
    
    .notification-close:hover {
        opacity: 1;
    }
`;
document.head.appendChild(style);
