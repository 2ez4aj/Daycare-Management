<?php
session_start();
require_once '../config/database.php';
require_once '../config/upload.php';

// Check if user is logged in and is parent
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'parent') {
    header('Location: ../index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_message':
                // Get admin user ID (assuming there's at least one admin)
                $stmt = $conn->prepare("SELECT id FROM users WHERE user_type = 'admin' AND status = 'active' LIMIT 1");
                $stmt->execute();
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($admin) {
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
                        $admin['id'],
                        $_POST['subject'],
                        $_POST['message'],
                        $parent_message_id,
                        $attachment_path,
                        $attachment_name,
                        $attachment_size
                    ]);
                    header('Location: messages.php?success=message_sent');
                    exit();
                }
                break;
                
            case 'mark_read':
                $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
                $stmt->execute([$_POST['message_id'], $_SESSION['user_id']]);
                header('Location: messages.php?success=message_read');
                exit();
                break;
                
            case 'delete_message':
                $stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
                $stmt->execute([$_POST['message_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
                header('Location: messages.php?success=message_deleted');
                exit();
                break;
        }
    }
}

// Get messages data
try {
    $conn = getDBConnection();
    
    // Get all messages involving this parent
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
    
    // Initialize file uploader for displaying file info
    $uploader = new FileUpload();
    
    // Get unread messages count
    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_messages = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
    
    // Get unread notifications count for sidebar badge
    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
    
    // Get parent's children for context
    $stmt = $conn->prepare("SELECT first_name, last_name FROM students WHERE parent_id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $messages = [];
    $unread_messages = 0;
    $unread_notifications = 0;
    $children = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/parent.css" rel="stylesheet">
</head>
<body>
    <div class="parent-container">
        <?php include 'parent_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header d-flex justify-content-between align-items-center">
                <div>
                    <h1>Messages</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Messages</li>
                        </ol>
                    </nav>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
                    <i class="fas fa-plus"></i> New Message
                </button>
            </div>
            
            <!-- Message Stats -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="message-stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?php echo count($messages); ?></h4>
                            <p>Total Messages</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="message-stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?php echo $unread_count; ?></h4>
                            <p>Unread Messages</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="message-stat-card">
                        <div class="stat-icon bg-info">
                            <i class="fas fa-child"></i>
                        </div>
                        <div class="stat-info">
                            <h4><?php echo count($children); ?></h4>
                            <p>My Children</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Message Templates -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-lightning-bolt me-2"></i>Quick Message Templates</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-2">
                            <button class="btn btn-outline-primary btn-sm w-100" onclick="useTemplate('General Inquiry', 'I have a general question about...')">
                                <i class="fas fa-question-circle"></i> General Inquiry
                            </button>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <button class="btn btn-outline-success btn-sm w-100" onclick="useTemplate('Attendance Issue', 'I need to report an attendance matter regarding...')">
                                <i class="fas fa-calendar-times"></i> Attendance Issue
                            </button>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <button class="btn btn-outline-info btn-sm w-100" onclick="useTemplate('Schedule Change', 'I need to inform you about a schedule change...')">
                                <i class="fas fa-clock"></i> Schedule Change
                            </button>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <button class="btn btn-outline-warning btn-sm w-100" onclick="useTemplate('Health Update', 'I need to update you about my child\\'s health...')">
                                <i class="fas fa-heartbeat"></i> Health Update
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Messages List -->
            <div class="card">
                <div class="card-header">
                    <h5>Message History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($messages)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-comments fa-3x mb-3 opacity-25"></i>
                            <h5>No messages yet</h5>
                            <p>Start a conversation with the daycare administration</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
                                <i class="fas fa-plus"></i> Send your first message
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
                                                    <strong>To: Daycare Administration</strong>
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
                                            <?php if ($message['attachment_name']): ?>
                                                <div class="attachment-indicator mt-1">
                                                    <i class="fas fa-paperclip text-muted"></i>
                                                    <small class="text-muted"><?php echo htmlspecialchars($message['attachment_name']); ?></small>
                                                </div>
                                            <?php endif; ?>
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
                    <h5 class="modal-title">Send Message to Administration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="send_message">
                        <input type="hidden" name="parent_message_id" id="parent_message_id">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> Your message will be sent to the daycare administration team. They typically respond within 24 hours during business days.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" id="message_subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" id="message_content" rows="6" required placeholder="Please describe your inquiry, concern, or message in detail..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Attachment (Optional)</label>
                            <input type="file" class="form-control" name="attachment" id="attachment" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i>
                                Supported files: Images (JPG, PNG, GIF), PDF, Word documents. Max size: 5MB
                            </div>
                            <div id="file-preview" class="mt-2"></div>
                        </div>
                        
                        <?php if (!empty($children)): ?>
                        <div class="mb-3">
                            <label class="form-label">Related to child (optional)</label>
                            <select class="form-control" id="child_reference">
                                <option value="">Select child (optional)</option>
                                <?php foreach ($children as $child): ?>
                                    <option value="<?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>">
                                        <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Select if this message is about a specific child</small>
                        </div>
                        <?php endif; ?>
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
    <script src="../assets/js/parent.js"></script>
    <script>
        let currentMessage = null;
        
        function useTemplate(subject, message) {
            document.getElementById('message_subject').value = subject;
            document.getElementById('message_content').value = message;
            new bootstrap.Modal(document.getElementById('composeModal')).show();
        }
        
        function viewMessage(message) {
            currentMessage = message;
            document.getElementById('viewModalTitle').textContent = message.subject;
            
            const isReceived = message.receiver_id == <?php echo $_SESSION['user_id']; ?>;
            const senderName = isReceived ? 
                message.sender_first_name + ' ' + message.sender_last_name + ' (Administration)' :
                'You';
            
            let attachmentHtml = '';
            if (message.attachment_name) {
                attachmentHtml = `
                    <div class="message-attachment mt-3">
                        <div class="attachment-item">
                            <i class="fas fa-paperclip text-primary"></i>
                            <a href="${message.attachment_path}" target="_blank" class="text-decoration-none">
                                ${message.attachment_name}
                            </a>
                            <small class="text-muted ms-2">(${formatFileSize(message.attachment_size)})</small>
                        </div>
                    </div>
                `;
            }
            
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
                    ${attachmentHtml}
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
        
        // Add child reference to message when selected
        document.getElementById('child_reference')?.addEventListener('change', function() {
            if (this.value) {
                const currentMessage = document.getElementById('message_content').value;
                const childRef = `\n\nRegarding: ${this.value}`;
                if (!currentMessage.includes('Regarding:')) {
                    document.getElementById('message_content').value = currentMessage + childRef;
                }
            }
        });
        
        // File upload preview
        document.getElementById('attachment').addEventListener('change', function() {
            const filePreview = document.getElementById('file-preview');
            const file = this.files[0];
            
            if (file) {
                const fileSize = formatFileSize(file.size);
                const fileName = file.name;
                const fileType = file.type;
                
                let iconClass = 'fa-file';
                if (fileType.startsWith('image/')) {
                    iconClass = 'fa-image';
                } else if (fileType === 'application/pdf') {
                    iconClass = 'fa-file-pdf';
                } else if (fileType.includes('word')) {
                    iconClass = 'fa-file-word';
                }
                
                filePreview.innerHTML = `
                    <div class="file-preview-item">
                        <i class="fas ${iconClass} text-primary me-2"></i>
                        <span class="file-name">${fileName}</span>
                        <small class="text-muted ms-2">(${fileSize})</small>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="clearFileInput()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            } else {
                filePreview.innerHTML = '';
            }
        });
        
        function clearFileInput() {
            document.getElementById('attachment').value = '';
            document.getElementById('file-preview').innerHTML = '';
        }
        
        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else {
                return bytes + ' bytes';
            }
        }
        
        // Handle success messages
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success) {
            let message = '';
            switch (success) {
                case 'message_sent':
                    message = 'Message sent successfully! The administration will respond soon.';
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
</body>
</html>
