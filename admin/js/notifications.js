document.addEventListener('DOMContentLoaded', function() {
    const notificationBell = document.getElementById('notificationBell');
    if (!notificationBell) return;

    const notificationDropdown = notificationBell.nextElementSibling;
    const notificationList = notificationDropdown.querySelector('.notification-list');
    let isDropdownOpen = false;

    // Toggle dropdown
    notificationBell.addEventListener('click', function(e) {
        e.preventDefault();
        isDropdownOpen = !isDropdownOpen;
        
        if (isDropdownOpen) {
            // Show loading state
            notificationList.innerHTML = `
                <div class="text-center p-3 text-muted">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span class="ms-2">Loading notifications...</span>
                </div>`;
            
            // Load notifications
            loadNotifications();
            
            // Mark as read when opened
            markAllAsRead();
        }
        
        // Toggle dropdown
        notificationDropdown.classList.toggle('show');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
            notificationDropdown.classList.remove('show');
            isDropdownOpen = false;
        }
    });

    // Mark all as read
    const markAllReadBtn = notificationDropdown.querySelector('.mark-all-read');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            markAllAsRead();
        });
    }

    // View all notifications
    const viewAllBtn = notificationDropdown.querySelector('.view-all');
    if (viewAllBtn) {
        viewAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'notifications.php';
        });
    }

    // Load notifications function
    async function loadNotifications() {
        try {
            const response = await fetch('get_notifications.php?limit=5');
            const data = await response.json();
            
            if (data.success && data.notifications.length > 0) {
                let html = '';
                data.notifications.forEach(notification => {
                    const timeAgo = getTimeAgo(notification.created_at);
                    const unreadClass = notification.is_read ? '' : 'unread';
                    
                    html += `
                        <div class="notification-item ${unreadClass}" data-id="${notification.id}">
                            <div class="notification-title">
                                <span>${escapeHtml(notification.title)}</span>
                                <small class="notification-time">${timeAgo}</small>
                            </div>
                            <div class="notification-message">${escapeHtml(notification.message)}</div>
                        </div>`;
                });
                
                notificationList.innerHTML = html;
                
                // Add click handler for each notification
                document.querySelectorAll('.notification-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const notificationId = this.getAttribute('data-id');
                        markAsRead(notificationId);
                        // You can add navigation to the notification's target page here
                    });
                });
                
            } else {
                notificationList.innerHTML = `
                    <div class="text-center p-3 text-muted">
                        <i class="fas fa-bell-slash fa-2x mb-2"></i>
                        <p class="mb-0">No notifications yet</p>
                    </div>`;
            }
            
        } catch (error) {
            console.error('Error loading notifications:', error);
            notificationList.innerHTML = `
                <div class="text-center p-3 text-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <p class="mb-0">Failed to load notifications</p>
                </div>`;
        }
    }

    // Mark a single notification as read
    async function markAsRead(notificationId) {
        try {
            await fetch('update_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: notificationId,
                    is_read: 1
                })
            });
            
            // Update UI
            const notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (notificationItem) {
                notificationItem.classList.remove('unread');
                updateUnreadCount();
            }
            
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }
    
    // Mark all notifications as read
    async function markAllAsRead() {
        try {
            await fetch('update_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    mark_all_read: true,
                    user_id: '<?php echo $_SESSION['user_id'] ?? 0; ?>'
                })
            });
            
            // Update UI
            document.querySelectorAll('.notification-item').forEach(item => {
                item.classList.remove('unread');
            });
            
            updateUnreadCount();
            
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
        }
    }
    
    // Update unread count in the badge
    async function updateUnreadCount() {
        try {
            const response = await fetch('get_notification_count.php');
            const data = await response.json();
            
            const badge = notificationBell.querySelector('.badge');
            if (data.count > 0) {
                if (!badge) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger pulse';
                    newBadge.style.fontSize = '0.6rem';
                    newBadge.textContent = data.count > 9 ? '9+' : data.count;
                    notificationBell.appendChild(newBadge);
                } else {
                    badge.textContent = data.count > 9 ? '9+' : data.count;
                    badge.classList.add('pulse');
                    setTimeout(() => badge.classList.remove('pulse'), 1000);
                }
                
                // Add ring animation to bell
                const bellIcon = notificationBell.querySelector('i');
                if (bellIcon) {
                    bellIcon.classList.add('ringing');
                    setTimeout(() => bellIcon.classList.remove('ringing'), 2000);
                }
            } else if (badge) {
                badge.remove();
            }
            
        } catch (error) {
            console.error('Error updating unread count:', error);
        }
    }
    
    // Helper function to escape HTML
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    // Helper function to format time ago
    function getTimeAgo(timestamp) {
        const seconds = Math.floor((new Date() - new Date(timestamp)) / 1000);
        
        const intervals = {
            year: 31536000,
            month: 2592000,
            week: 604800,
            day: 86400,
            hour: 3600,
            minute: 60,
            second: 1
        };
        
        for (const [unit, secondsInUnit] of Object.entries(intervals)) {
            const interval = Math.floor(seconds / secondsInUnit);
            if (interval >= 1) {
                return interval === 1 ? `1 ${unit} ago` : `${interval} ${unit}s ago`;
            }
        }
        
        return 'just now';
    }
    
    // Check for new notifications periodically (every 2 minutes)
    setInterval(updateUnreadCount, 120000);
    
    // Initial load
    updateUnreadCount();
});
