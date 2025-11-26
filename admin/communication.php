<?php
session_start();
require_once '../config/database.php';
require_once '../config/upload.php';
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_message':
                $attachment_path = null;
                $attachment_name = null;
                $attachment_size = null;
                
                // Handle file upload if present
                if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                    $uploader = new FileUpload();
                    $uploadResult = $uploader->uploadFile($_FILES['attachment'], 'messages');
                    
                    if ($uploadResult['success']) {
                        $attachment_path = $uploadResult['path'];
                        $attachment_name = $uploadResult['original_name'];
                        $attachment_size = $uploadResult['size'];
                    }
                }
                
                $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, parent_message_id, attachment_path, attachment_name, attachment_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $parent_message_id = !empty($_POST['parent_message_id']) ? $_POST['parent_message_id'] : null;
                $stmt->execute([
                    $_SESSION['user_id'],
                    $_POST['receiver_id'],
                    $_POST['subject'],
                    $_POST['message'],
                    $parent_message_id,
                    $attachment_path,
                    $attachment_name,
                    $attachment_size
                ]);
                
                // Create notification for the parent
                $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')");
                $notification_title = "New Message: " . $_POST['subject'];
                $notification_message = "You have received a new message from the admin. Click to view details.";
                $notification_stmt->execute([$_POST['receiver_id'], $notification_title, $notification_message]);
                
                header('Location: communication.php?success=message_sent');
                exit();
                break;
                
            case 'mark_read':
                $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
                $stmt->execute([$_POST['message_id']]);
                header('Location: communication.php?success=message_read');
                exit();
                break;
                
            case 'delete_message':
                $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
                $stmt->execute([$_POST['message_id']]);
                header('Location: communication.php?success=message_deleted');
                exit();
                break;
        }
    }
}

// Get messages data
try {
    $conn = getDBConnection();
    
    // Get all messages involving admin
    $stmt = $conn->prepare("
        SELECT m.*, 
               sender.first_name as sender_first_name, sender.last_name as sender_last_name, sender.user_type as sender_type,
               receiver.first_name as receiver_first_name, receiver.last_name as receiver_last_name, receiver.user_type as receiver_type
        FROM messages m 
        JOIN users sender ON m.sender_id = sender.id 
        JOIN users receiver ON m.receiver_id = receiver.id 
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all parents for compose dropdown
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE user_type = 'parent' AND status = 'active' ORDER BY first_name, last_name");
    $stmt->execute();
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread messages count
    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
    
} catch (Exception $e) {
    $messages = [];
    $parents = [];
    $unread_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <link href="../assets/css/mobile_nav.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header d-flex justify-content-between align-items-center">
                <h1>Communication Center</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
                    <i class="fas fa-plus"></i> Compose Message
                </button>
            </div>
            
            <!-- Message Stats -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card stat-card-blue">
                        <div class="stat-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($messages); ?></h3>
                            <p>Total Messages</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card stat-card-orange">
                        <div class="stat-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $unread_count; ?></h3>
                            <p>Unread Messages</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card stat-card-green">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($parents); ?></h3>
                            <p>Active Parents</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card stat-card-red">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo date('H:i'); ?></h3>
                            <p>Current Time</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Messages List -->
            <div class="card">
                <div class="card-header">
                    <h5>Messages</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($messages)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-comments fa-3x mb-3 opacity-25"></i>
                            <p>No messages found</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
                                Send your first message
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="messages-list">
                            <?php foreach ($messages as $message): ?>
                                <div class="message-item <?php echo !$message['is_read'] && $message['receiver_id'] == $_SESSION['user_id'] ? 'unread' : ''; ?>" 
                                     onclick="viewMessage(<?php echo htmlspecialchars(json_encode($message)); ?>)">
                                    <div class="message-avatar">
                                        <i class="fas <?php echo $message['sender_type'] == 'admin' ? 'fa-user-shield' : 'fa-user'; ?>"></i>
                                    </div>
                                    <div class="message-content">
                                        <div class="message-header">
                                            <div class="message-sender">
                                                <?php if ($message['sender_id'] == $_SESSION['user_id']): ?>
                                                    <strong>To: <?php echo htmlspecialchars($message['receiver_first_name'] . ' ' . $message['receiver_last_name']); ?></strong>
                                                    <span class="message-direction sent">Sent</span>
                                                <?php else: ?>
                                                    <strong>From: <?php echo htmlspecialchars($message['sender_first_name'] . ' ' . $message['sender_last_name']); ?></strong>
                                                    <span class="message-direction received">Received</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="message-time">
                                                <?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="message-subject">
                                            <?php echo htmlspecialchars($message['subject']); ?>
                                            <?php if (!$message['is_read'] && $message['receiver_id'] == $_SESSION['user_id']): ?>
                                                <span class="badge bg-primary ms-2">New</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-preview">
                                            <?php echo substr(htmlspecialchars($message['message']), 0, 100) . '...'; ?>
                                        </div>
                                    </div>
                                    <div class="message-actions">
                                        <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); replyToMessage(<?php echo htmlspecialchars(json_encode($message)); ?>)">
                                            <i class="fas fa-reply"></i>
                                        </button>
                                        <?php if (!$message['is_read'] && $message['receiver_id'] == $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-outline-success" onclick="event.stopPropagation(); markAsRead(<?php echo $message['id']; ?>)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); deleteMessage(<?php echo $message['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Compose Message Modal -->
    <div class="modal fade" id="composeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Compose Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="send_message">
                        <input type="hidden" name="parent_message_id" id="parent_message_id">
                        
                        <div class="mb-3">
                            <label class="form-label">To (Parent)</label>
                            <select class="form-control" name="receiver_id" id="receiver_id" required>
                                <option value="">Select Parent</option>
                                <?php foreach ($parents as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>">
                                        <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name'] . ' (' . $parent['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" id="message_subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" id="message_content" rows="6" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Message Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalTitle">Message Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewModalContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="replyButton">
                        <i class="fas fa-reply"></i> Reply
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        let currentMessage = null;
        
        function viewMessage(message) {
            currentMessage = message;
            document.getElementById('viewModalTitle').textContent = message.subject;
            
            const isReceived = message.receiver_id == <?php echo $_SESSION['user_id']; ?>;
            const senderName = isReceived ? 
                message.sender_first_name + ' ' + message.sender_last_name :
                message.receiver_first_name + ' ' + message.receiver_last_name;
            
            document.getElementById('viewModalContent').innerHTML = `
                <div class="message-details">
                    <div class="message-meta mb-3">
                        <strong>${isReceived ? 'From' : 'To'}:</strong> ${senderName}<br>
                        <strong>Date:</strong> ${new Date(message.created_at).toLocaleString()}<br>
                        <strong>Subject:</strong> ${message.subject}
                    </div>
                    <div class="message-body">
                        <p class="message-body-content">${message.message}</p>
                    </div>
                </div>
            `;
            
            document.getElementById('replyButton').onclick = () => replyToMessage(message);
            
            new bootstrap.Modal(document.getElementById('viewModal')).show();
            
            // Mark as read if it's an unread received message
            if (!message.is_read && isReceived) {
                markAsRead(message.id);
            }
        }
        
        function replyToMessage(message) {
            const isReceived = message.receiver_id == <?php echo $_SESSION['user_id']; ?>;
            const replyToId = isReceived ? message.sender_id : message.receiver_id;
            
            document.getElementById('receiver_id').value = replyToId;
            document.getElementById('message_subject').value = 'Re: ' + message.subject;
            document.getElementById('parent_message_id').value = message.id;
            document.getElementById('message_content').value = '\n\n--- Original Message ---\n' + message.message;
            
            // Close view modal and open compose modal
            bootstrap.Modal.getInstance(document.getElementById('viewModal'))?.hide();
            new bootstrap.Modal(document.getElementById('composeModal')).show();
        }
        
        function markAsRead(messageId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="message_id" value="${messageId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function deleteMessage(messageId) {
            if (confirm('Are you sure you want to delete this message?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_message">
                    <input type="hidden" name="message_id" value="${messageId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Handle success messages
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success) {
            let message = '';
            switch (success) {
                case 'message_sent':
                    message = 'Message sent successfully!';
                    break;
                case 'message_read':
                    message = 'Message marked as read!';
                    break;
                case 'message_deleted':
                    message = 'Message deleted successfully!';
                    break;
            }
            if (message) {
                showNotification(message, 'success');
            }
        }
    </script>
    
    <style>
        .messages-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .message-item {
            display: flex;
            align-items: flex-start;
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .message-item:hover {
            background-color: #f8f9fa;
        }
        
        .message-item.unread {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .message-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #FF6B9D, #FF8E9B);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .message-content {
            flex: 1;
            min-width: 0;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .message-sender {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message-direction {
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .message-direction.sent {
            background: #d4edda;
            color: #155724;
        }
        
        .message-direction.received {
            background: #cce5ff;
            color: #004085;
        }
        
        .message-time {
            font-size: 12px;
            color: #666;
        }
        
        .message-subject {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .message-preview {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .message-actions {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-left: 15px;
        }
        
        .message-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .message-meta {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 15px;
            color: #666;
        }
        
        .message-body {
            padding-top: 15px;
        }
        
        @media (max-width: 768px) {
            .message-item {
                flex-direction: column;
                gap: 10px;
            }
            
            .message-actions {
                flex-direction: row;
                margin-left: 0;
            }
        }
    </style>
    <script src="../assets/js/mobile_nav.js"></script>
    <?php include 'mobile_nav.php'; ?>
</body>
</html>
