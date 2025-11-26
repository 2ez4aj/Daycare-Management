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
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_event':
                $schedule_id = !empty($_POST['schedule_id']) ? $_POST['schedule_id'] : null;
                $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, event_time, location, schedule_id, category, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['event_date'],
                    $_POST['event_time'],
                    $_POST['location'],
                    $schedule_id,
                    $_POST['category']
                ]);
                header('Location: events.php?success=event_added');
                exit();
                break;
                
            case 'edit_event':
                $schedule_id = !empty($_POST['schedule_id']) ? $_POST['schedule_id'] : null;
                $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, event_date = ?, event_time = ?, location = ?, schedule_id = ?, category = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['event_date'],
                    $_POST['event_time'],
                    $_POST['location'],
                    $schedule_id,
                    $_POST['category'],
                    $_POST['event_id']
                ]);
                header('Location: events.php?success=event_updated');
                exit();
                break;
                
            case 'toggle_status':
                $stmt = $conn->prepare("UPDATE events SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?");
                $stmt->execute([$_POST['event_id']]);
                header('Location: events.php?success=status_updated');
                exit();
                break;
                
            case 'delete_event':
                $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
                $stmt->execute([$_POST['event_id']]);
                header('Location: events.php?success=event_deleted');
                exit();
                break;
        }
    }
}

// Get events data
try {
    $conn = getDBConnection();
    
    // Get all events
    $stmt = $conn->prepare("SELECT * FROM events ORDER BY event_date DESC, event_time DESC");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get event categories
    $categories = ['Educational', 'Social', 'Health & Safety', 'Holiday', 'Parent Meeting', 'Field Trip', 'Performance', 'Other'];
    
    // Get schedules for dropdown
    $stmt = $conn->prepare("SELECT id, schedule_name, start_time, end_time FROM schedules WHERE is_active = 1 ORDER BY start_time");
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $events = [];
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Management - Gumamela Daycare Center</title>
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
                <h1><i class="fas fa-calendar-star me-2"></i>Events Management</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Events</li>
                    </ol>
                </nav>
            </div>
            
            <!-- Success Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php
                    switch ($_GET['success']) {
                        case 'event_added': echo 'Event added successfully!'; break;
                        case 'event_updated': echo 'Event updated successfully!'; break;
                        case 'status_updated': echo 'Event status updated successfully!'; break;
                        case 'event_deleted': echo 'Event deleted successfully!'; break;
                    }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Add Event Button -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Upcoming Events</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                    <i class="fas fa-plus"></i> Add New Event
                </button>
            </div>
            
            <!-- Events List -->
            <div class="row">
                <?php if (empty($events)): ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-calendar-star fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Events Found</h5>
                                <p class="text-muted">Start by adding your first event to keep parents informed about upcoming activities.</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card event-card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?php echo $event['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($event['status']); ?>
                                    </span>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($event['category']); ?></span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                    <p class="card-text text-muted"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                                    
                                    <div class="event-details mt-3">
                                        <div class="detail-item mb-2">
                                            <i class="fas fa-calendar text-primary me-2"></i>
                                            <strong>Date:</strong> <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                        </div>
                                        <div class="detail-item mb-2">
                                            <i class="fas fa-clock text-primary me-2"></i>
                                            <strong>Time:</strong> <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                        </div>
                                        <?php if (!empty($event['location'])): ?>
                                            <div class="detail-item mb-2">
                                                <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                                <strong>Location:</strong> <?php echo htmlspecialchars($event['location']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="btn-group w-100" role="group">
                                        <button class="btn btn-outline-primary btn-sm" onclick="editEvent(<?php echo htmlspecialchars(json_encode($event)); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-outline-<?php echo $event['status'] === 'active' ? 'warning' : 'success'; ?> btn-sm" onclick="toggleStatus(<?php echo $event['id']; ?>)">
                                            <i class="fas fa-<?php echo $event['status'] === 'active' ? 'eye-slash' : 'eye'; ?>"></i>
                                            <?php echo $event['status'] === 'active' ? 'Hide' : 'Show'; ?>
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm" onclick="deleteEvent(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['title']); ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        Created: <?php echo date('M d, Y', strtotime($event['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Event Modal -->
    <div class="modal fade" id="addEventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_event">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Event Title</label>
                                    <input type="text" class="form-control" name="title" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select class="form-select" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Event Date</label>
                                    <input type="date" class="form-control" name="event_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Event Time</label>
                                    <input type="time" class="form-control" name="event_time" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" placeholder="e.g., Main Classroom, Playground, etc.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Schedule/Section</label>
                            <select class="form-control" name="schedule_id">
                                <option value="">All Schedules</option>
                                <?php foreach ($schedules as $schedule): ?>
                                    <option value="<?php echo $schedule['id']; ?>">
                                        <?php echo htmlspecialchars($schedule['schedule_name']); ?> (<?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Event Modal -->
    <div class="modal fade" id="editEventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_event">
                        <input type="hidden" name="event_id" id="edit_event_id">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Event Title</label>
                                    <input type="text" class="form-control" name="title" id="edit_title" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select class="form-select" name="category" id="edit_category" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="4" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Event Date</label>
                                    <input type="date" class="form-control" name="event_date" id="edit_event_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Event Time</label>
                                    <input type="time" class="form-control" name="event_time" id="edit_event_time" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" id="edit_location" placeholder="e.g., Main Classroom, Playground, etc.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Schedule/Section</label>
                            <select class="form-control" name="schedule_id" id="edit_schedule_id">
                                <option value="">All Schedules</option>
                                <?php foreach ($schedules as $schedule): ?>
                                    <option value="<?php echo $schedule['id']; ?>">
                                        <?php echo htmlspecialchars($schedule['schedule_name']); ?> (<?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        function editEvent(event) {
            document.getElementById('edit_event_id').value = event.id;
            document.getElementById('edit_title').value = event.title;
            document.getElementById('edit_category').value = event.category;
            document.getElementById('edit_description').value = event.description;
            document.getElementById('edit_event_date').value = event.event_date;
            document.getElementById('edit_event_time').value = event.event_time;
            document.getElementById('edit_location').value = event.location || '';
            document.getElementById('edit_schedule_id').value = event.schedule_id || '';
            
            new bootstrap.Modal(document.getElementById('editEventModal')).show();
        }
        
        function toggleStatus(eventId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="event_id" value="${eventId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function deleteEvent(eventId, title) {
            if (confirm(`Are you sure you want to delete the event: "${title}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_event">
                    <input type="hidden" name="event_id" value="${eventId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Set minimum date to today for new events
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="event_date"]').setAttribute('min', today);
        });
    </script>
</body>
</html>
