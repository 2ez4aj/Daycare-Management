<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is parent
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'parent') {
    header('Location: ../index.php');
    exit();
}

// Get unread messages count for sidebar
$unread_messages = 0;
$unread_notifications = 0;
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_messages = $stmt->fetchColumn();
    
    // Get unread notifications count for sidebar badge
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetchColumn();
} catch (Exception $e) {
    // Ignore error for sidebar count
}

// Check if enrollment is enabled
$enrollment_enabled = false;
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'enrollment_enabled'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $enrollment_enabled = $result && $result['setting_value'] == '1';
} catch (Exception $e) {
    // Ignore error
}

// Get FAQs
$faqs = [];
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM faqs WHERE status = 'active' ORDER BY id ASC");
    $stmt->execute();
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to load FAQs.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frequently Asked Questions - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/parent.css" rel="stylesheet">
</head>
<body>
    <div class="parent-container">
        <?php include 'parent_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1>Frequently Asked Questions</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">FAQ</li>
                    </ol>
                </nav>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- FAQ Content -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-question-circle me-2"></i>Common Questions & Answers</h5>
                    <small class="text-muted">Find answers to frequently asked questions about our daycare services.</small>
                </div>
                <div class="card-body">
                    <?php if (empty($faqs)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No FAQs Available</h5>
                            <p class="text-muted">Frequently asked questions will be displayed here when available.</p>
                            <div class="mt-4">
                                <a href="messages.php" class="btn btn-primary">
                                    <i class="fas fa-envelope"></i> Contact Us
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="accordion" id="faqAccordion">
                            <?php foreach ($faqs as $index => $faq): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                        <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                                            <i class="fas fa-question-circle me-2 text-primary"></i>
                                            <?php echo htmlspecialchars($faq['question']); ?>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            <div class="d-flex">
                                                <i class="fas fa-reply text-success me-2 mt-1"></i>
                                                <div><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6><i class="fas fa-info-circle text-primary me-2"></i>Still have questions?</h6>
                            <p class="mb-2">If you can't find the answer you're looking for, feel free to contact us directly.</p>
                            <a href="messages.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-envelope"></i> Send Message
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/parent.js"></script>
    
</body>
</html>
