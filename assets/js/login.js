// Login page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const loginCards = document.querySelectorAll('.login-card');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all buttons and cards
            tabButtons.forEach(btn => btn.classList.remove('active'));
            loginCards.forEach(card => card.classList.remove('active'));
            
            // Add active class to clicked button and corresponding card
            this.classList.add('active');
            document.getElementById(`${targetTab}-login`).classList.add('active');
        });
    });
    
    // Handle both login forms
    const userLoginForm = document.getElementById('userLoginForm');
    const adminLoginForm = document.getElementById('adminLoginForm');
    
    // User login form handler
    if (userLoginForm) {
        userLoginForm.addEventListener('submit', function(e) {
            handleFormSubmit(e, this, 'Logging in...');
        });
    }
    
    // Admin login form handler
    if (adminLoginForm) {
        adminLoginForm.addEventListener('submit', function(e) {
            handleFormSubmit(e, this, 'Logging in as admin...');
        });
    }
    
    // Form submission handler
    function handleFormSubmit(e, form, loadingText) {
        const username = form.querySelector('input[name="username"]').value.trim();
        const password = form.querySelector('input[name="password"]').value;
        
        // Basic validation
        if (!username || !password) {
            e.preventDefault();
            showError('Please fill in all fields');
            return;
        }
        
        // Show loading state
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${loadingText}`;
        submitButton.disabled = true;
        
        // Reset button after a delay if form submission fails
        setTimeout(() => {
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }, 5000);
    }
    
    // Handle URL parameters for messages
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    const success = urlParams.get('success');
    const message = urlParams.get('message');
    
    if (error) {
        let errorMessage = 'An error occurred';
        switch (error) {
            case 'empty_fields':
                errorMessage = 'Please fill in all fields';
                break;
            case 'invalid_credentials':
                errorMessage = 'Invalid username or password';
                break;
            case 'account_inactive':
                errorMessage = 'Your account is not active. Please contact the administrator.';
                break;
            case 'system_error':
                errorMessage = 'System error. Please try again later.';
                break;
            case 'database_connection':
                errorMessage = 'Database connection failed. Please ensure MySQL is running and the database exists.';
                break;
        }
        showError(errorMessage);
    }
    
    if (success) {
        let successMessage = 'Success!';
        switch (success) {
            case 'registration_complete':
                successMessage = 'Registration successful! Please wait for admin approval.';
                break;
        }
        showSuccess(successMessage);
    }
    
    if (message) {
        switch (message) {
            case 'logged_out':
                showInfo('You have been logged out successfully.');
                break;
        }
    }
});

function showError(message) {
    showNotification(message, 'error');
}

function showSuccess(message) {
    showNotification(message, 'success');
}

function showInfo(message) {
    showNotification(message, 'info');
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
