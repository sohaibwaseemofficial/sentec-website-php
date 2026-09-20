<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

include 'header.php'; // Using your standard dashboard header
include '../db_connection.php';

$message = "";

// Fetch the event to edit
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $event_id = intval($_GET['id']);
    $query = "SELECT * FROM events WHERE id = ?";
    $stmt = $conn->prepare($query);

    if ($stmt) {
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();

        if (!$event) {
            echo "<div class='alert alert-danger'>Event not found.</div>";
            exit();
        }
    }
} else {
    header("Location: add_event.php");
    exit();
}

// Update event logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_title = trim($_POST['event_title']);
    $event_description = trim($_POST['event_description']);
    $event_date = trim($_POST['event_date']);
    $event_link = trim($_POST['event_link']);
    $category = trim($_POST['category']);

    $update_query = "UPDATE events SET title = ?, description = ?, event_date = ?, event_link = ?, category = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);

    if ($update_stmt) {
        $update_stmt->bind_param('sssssi', $event_title, $event_description, $event_date, $event_link, $category, $event_id);
        if ($update_stmt->execute()) {
            if (function_exists('invalidate_cache')) invalidate_cache('public_events_data');
            $message = "<div class='alert alert-success'>Event Updated Successfully! 🚀</div>";
            // Refresh event data for the form
            $event['title'] = $event_title;
            $event['description'] = $event_description;
            $event['event_date'] = $event_date;
            $event['event_link'] = $event_link;
            $event['category'] = $category;
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $update_stmt->error . "</div>";
        }
    }
}
?>

<style>
    /* Matches your Manage Social and Manage Registrations theme */
    .form-control, .form-select {
        background: rgba(5, 12, 28, 0.9) !important;
        border: 1px solid rgba(0, 255, 148, 0.15) !important;
        color: #e5f8ff !important;
        border-radius: 12px;
    }
    .form-control:focus, .form-select:focus {
        border-color: rgba(0, 255, 148, 0.5) !important;
        box-shadow: 0 0 20px rgba(0, 255, 148, 0.2);
    }
    .current-image-preview {
        width: 100%;
        max-height: 200px;
        object-fit: cover;
        border-radius: 12px;
        border: 1px solid rgba(0, 255, 148, 0.3);
        margin-bottom: 15px;
    }
</style>

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-edit me-2"></i> Edit Event</h2>
        <p class="text-muted">Modify details for: <strong><?php echo htmlspecialchars($event['title']); ?></strong></p>
    </div>
    <a href="add_event.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back to List
    </a>
</div>

<?php echo $message; ?>

<div class="glass-panel" style="max-width: 900px; margin: 0 auto;">
    <form method="POST">
        <div class="row">
            <div class="col-md-8">
                <div class="mb-4">
                    <label class="form-label text-white small fw-bold uppercase">Event Title</label>
                    <input type="text" class="form-control" name="event_title" 
                           value="<?php echo htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                
                <div class="mb-4">
                    <label class="form-label text-white small fw-bold uppercase">Category</label>
                    <select name="category" class="form-select">
                        <option value="Workshop" <?php if($event['category'] == 'Workshop') echo 'selected'; ?>>Workshop</option>
                        <option value="Competition" <?php if($event['category'] == 'Competition') echo 'selected'; ?>>Competition</option>
                        <option value="Seminar" <?php if($event['category'] == 'Seminar') echo 'selected'; ?>>Seminar</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label text-white small fw-bold uppercase">Description</label>
                    <textarea class="form-control" name="event_description" rows="6" required><?php echo htmlspecialchars($event['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>

            <div class="col-md-4 border-start border-secondary ps-4">
                <label class="form-label text-white small fw-bold uppercase">Current Cover</label>
                <img src="../<?php echo $event['image_url']; ?>" class="current-image-preview">
                
                <div class="mb-4">
                    <label class="form-label text-white small fw-bold uppercase">Event Date</label>
                    <input type="datetime-local" class="form-control" name="event_date" 
                           value="<?php echo date("Y-m-d\TH:i", strtotime($event['event_date'])); ?>" required>
                </div>
                
                <div class="mb-4">
                    <label class="form-label text-white small fw-bold uppercase">External Link (Optional)</label>
                    <input type="url" class="form-control" name="event_link" 
                           value="<?php echo htmlspecialchars($event['event_link'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           placeholder="https://...">
                </div>

                <div class="d-grid gap-2 mt-5">
                    <button type="submit" class="btn-neon">
                        <i class="fas fa-save me-2"></i> Update Event
                    </button>
                    <a href="add_event.php" class="btn btn-outline-secondary btn-sm">Cancel Changes</a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>