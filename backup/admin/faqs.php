<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    // Validate user session before any database operations
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: ../auth/logout.php');
        exit();
    }
    
    // Verify user exists in database
    $userCheck = $conn->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'admin' AND status = 'active'");
    $userCheck->execute([$_SESSION['user_id']]);
    if (!$userCheck->fetch()) {
        header('Location: ../auth/logout.php');
        exit();
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_faq':
                $stmt = $conn->prepare("INSERT INTO faqs (question, answer, category, status, created_by, created_at) VALUES (?, ?, ?, 'active', ?, NOW())");
                $stmt->execute([
                    $_POST['question'],
                    $_POST['answer'],
                    $_POST['category'],
                    $_SESSION['user_id']
                ]);
                header('Location: faqs.php?success=faq_added');
                exit();
                break;
                
            case 'edit_faq':
                $stmt = $conn->prepare("UPDATE faqs SET question = ?, answer = ?, category = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['question'],
                    $_POST['answer'],
                    $_POST['category'],
                    $_POST['faq_id']
                ]);
                header('Location: faqs.php?success=faq_updated');
                exit();
                break;
                
            case 'toggle_status':
                $stmt = $conn->prepare("UPDATE faqs SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?");
                $stmt->execute([$_POST['faq_id']]);
                header('Location: faqs.php?success=status_updated');
                exit();
                break;
                
            case 'delete_faq':
                $stmt = $conn->prepare("DELETE FROM faqs WHERE id = ?");
                $stmt->execute([$_POST['faq_id']]);
                header('Location: faqs.php?success=faq_deleted');
                exit();
                break;
        }
    }
}

// Get FAQs data
try {
    $conn = getDBConnection();
    
    // Get all FAQs
    $stmt = $conn->prepare("SELECT * FROM faqs ORDER BY category, created_at DESC");
    $stmt->execute();
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get FAQ categories
    $categories = ['General', 'Enrollment', 'Policies', 'Health & Safety', 'Activities', 'Fees & Payment'];
    
} catch (Exception $e) {
    $faqs = [];
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ Management - Gumamela Daycare Center</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-question-circle me-2"></i>FAQ Management</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">FAQs</li>
                    </ol>
                </nav>
            </div>
            
            <!-- Success Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php
                    switch ($_GET['success']) {
                        case 'faq_added': echo 'FAQ added successfully!'; break;
                        case 'faq_updated': echo 'FAQ updated successfully!'; break;
                        case 'status_updated': echo 'FAQ status updated successfully!'; break;
                        case 'faq_deleted': echo 'FAQ deleted successfully!'; break;
                    }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Add FAQ Button -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Frequently Asked Questions</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFaqModal">
                    <i class="fas fa-plus"></i> Add New FAQ
                </button>
            </div>
            
            <!-- FAQs List -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($faqs)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No FAQs Found</h5>
                            <p class="text-muted">Start by adding your first FAQ to help parents find answers quickly.</p>
                        </div>
                    <?php else: ?>
                        <div class="accordion" id="faqAccordion">
                            <?php 
                            $currentCategory = '';
                            $index = 0;
                            foreach ($faqs as $faq): 
                                if ($currentCategory !== $faq['category']):
                                    if ($currentCategory !== '') echo '</div></div>';
                                    $currentCategory = $faq['category'];
                            ?>
                                <div class="category-section mb-4">
                                    <h5 class="category-header">
                                        <i class="fas fa-folder-open me-2"></i><?php echo htmlspecialchars($currentCategory); ?>
                                    </h5>
                                    <div class="category-faqs">
                            <?php endif; ?>
                            
                            <div class="faq-item mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="faq-question mb-2">
                                            <i class="fas fa-question-circle text-primary me-2"></i>
                                            <?php echo htmlspecialchars($faq['question']); ?>
                                            <?php if ($faq['status'] === 'inactive'): ?>
                                                <span class="badge bg-secondary ms-2">Inactive</span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="faq-answer text-muted mb-2"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                                        <small class="text-muted">
                                            Created: <?php echo date('M d, Y', strtotime($faq['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="faq-actions ms-3">
                                        <button class="btn btn-sm btn-outline-primary" onclick="editFaq(<?php echo htmlspecialchars(json_encode($faq)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-<?php echo $faq['status'] === 'active' ? 'warning' : 'success'; ?>" onclick="toggleStatus(<?php echo $faq['id']; ?>)">
                                            <i class="fas fa-<?php echo $faq['status'] === 'active' ? 'eye-slash' : 'eye'; ?>"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteFaq(<?php echo $faq['id']; ?>, '<?php echo htmlspecialchars($faq['question']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <?php 
                            $index++;
                            endforeach; 
                            if ($currentCategory !== '') echo '</div></div>';
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add FAQ Modal -->
    <div class="modal fade" id="addFaqModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New FAQ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_faq">
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Question</label>
                            <input type="text" class="form-control" name="question" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Answer</label>
                            <textarea class="form-control" name="answer" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add FAQ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit FAQ Modal -->
    <div class="modal fade" id="editFaqModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit FAQ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_faq">
                        <input type="hidden" name="faq_id" id="edit_faq_id">
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" id="edit_category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Question</label>
                            <input type="text" class="form-control" name="question" id="edit_question" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Answer</label>
                            <textarea class="form-control" name="answer" id="edit_answer" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update FAQ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        function editFaq(faq) {
            document.getElementById('edit_faq_id').value = faq.id;
            document.getElementById('edit_category').value = faq.category;
            document.getElementById('edit_question').value = faq.question;
            document.getElementById('edit_answer').value = faq.answer;
            
            new bootstrap.Modal(document.getElementById('editFaqModal')).show();
        }
        
        function toggleStatus(faqId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="faq_id" value="${faqId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function deleteFaq(faqId, question) {
            if (confirm(`Are you sure you want to delete this FAQ: "${question}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_faq">
                    <input type="hidden" name="faq_id" value="${faqId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
