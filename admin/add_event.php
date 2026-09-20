<?php
include 'header.php';
include '../db_connection.php';
require_once __DIR__ . '/../image_utils.php';
$message = ""; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $event_date = $_POST['event_date'];
    $event_link = trim($_POST['event_link']);
    $status = 'upcoming'; 

    if (!isset($_FILES['image'])) {
        $message = "<div class='alert alert-danger'>Cover image is required.</div>";
    } else {
        $uploadDir = __DIR__ . '/../images/upload/event/';
        $uploadResult = save_image_as_webp($_FILES['image'], $uploadDir, 'images/upload/event/');

        if (!$uploadResult['success']) {
            $message = "<div class='alert alert-danger'>" . htmlspecialchars($uploadResult['error']) . "</div>";
        } else {
            $db_path = $uploadResult['path'];
            $stmt = $conn->prepare("INSERT INTO events (title, description, category, event_date, image_url, event_link, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $title, $description, $category, $event_date, $db_path, $event_link, $status);
            
            if ($stmt->execute()) {
                if (function_exists('invalidate_cache')) invalidate_cache('public_events_data');
                $message = "<div class='alert alert-success'>Event Published Successfully! 🚀</div>";
            } else {
                $message = "<div class='alert alert-danger'>DB Error: " . $stmt->error . "</div>";
            }
        }
    }
}
?>

<style>
    .event-thumb { width: 80px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid #444; }
    .btn-icon-sm { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; transition: 0.3s; }
    .btn-edit { background: rgba(0, 195, 255, 0.1); color: #4cd9ff; border: 1px solid #4cd9ff; }
    .btn-delete { background: rgba(255, 68, 68, 0.1); color: #ff4444; border: 1px solid #ff4444; }
    .btn-edit:hover { background: #4cd9ff; color: #000; }
    .btn-delete:hover { background: #ff4444; color: #fff; }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="fas fa-calendar-plus me-2"></i> Event Management</h2>
        <p class="text-muted">Create and manage upcoming events for the website.</p>
    </div>
</div>

<?php echo $message; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="glass-panel">
            <h5 class="text-white mb-4">Add New Event</h5>
            <form action="add_event.php" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="text-white small mb-2">Event Title</label>
                    <input type="text" name="title" class="form-control bg-dark text-white border-secondary" placeholder="Robotics Workshop" required>
                </div>
                <div class="mb-3">
                    <label class="text-white small mb-2">Category</label>
                    <select name="category" class="form-select bg-dark text-white border-secondary">
                        <option value="Workshop">Workshop</option>
                        <option value="Competition">Competition</option>
                        <option value="Seminar">Seminar</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="text-white small mb-2">Description</label>
                    <textarea name="description" class="form-control bg-dark text-white border-secondary" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="text-white small mb-2">Event Date</label>
                    <input type="date" name="event_date" class="form-control bg-dark text-white border-secondary" required>
                </div>
                <div class="mb-3">
                    <label class="text-white small mb-2">Cover Image</label>
                    <input type="file" name="image" class="form-control bg-dark text-white border-secondary" accept=".jpg,.png,.webp" required>
                </div>
                <div class="mb-3">
                    <label class="text-white small mb-2">Event Link (Optional)</label>
                    <input type="url" name="event_link" class="form-control bg-dark text-white border-secondary" placeholder="https://...">
                </div>
                <button type="submit" class="btn-neon w-100 mt-2">Publish Event</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="glass-panel">
            <h5 class="text-white mb-4">Existing Events</h5>
            <div class="table-responsive">
                <table class="table table-hover text-white align-middle">
                    <thead>
                        <tr style="border-bottom: 2px solid #00FF94;">
                            <th>Image</th>
                            <th>Title</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $events = $conn->query("SELECT * FROM events ORDER BY event_date DESC");
                        while($ev = $events->fetch_assoc()):
                        ?>
                        <tr>
                            <td><img src="../<?php echo $ev['image_url']; ?>" class="event-thumb"></td>
                            <td>
                                <strong><?php echo htmlspecialchars($ev['title']); ?></strong><br>
                                <span class="badge bg-secondary" style="font-size:0.65rem;"><?php echo $ev['category']; ?></span>
                            </td>
                            <td class="small"><?php echo date('M d, Y', strtotime($ev['event_date'])); ?></td>
                            <td class="text-end">
                                <a href="edit_event.php?id=<?php echo $ev['id']; ?>" class="btn-icon-sm btn-edit me-2"><i class="fas fa-edit"></i></a>
                                <a href="javascript:void(0)" onclick="deleteEvent(<?php echo $ev['id']; ?>)" class="btn-icon-sm btn-delete"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function deleteEvent(id) {
    if(confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
        window.location.href = 'delete_event.php?id=' + id;
    }
}
</script>

<?php include 'footer.php'; ?>