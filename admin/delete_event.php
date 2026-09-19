<?php
session_start();
if (!isset($_SESSION['admin'])) { exit("Unauthorized"); }

include '../db_connection.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $event_id = intval($_GET['id']);
    
    // 1. Fetch image path to delete the physical file too
    $res = $conn->query("SELECT image_url FROM events WHERE id = $event_id");
    if($row = $res->fetch_assoc()) {
        $filePath = __DIR__ . '/../' . $row['image_url'];
        if(file_exists($filePath)) { unlink($filePath); }
    }

    // 2. Delete from database
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    
    if ($stmt->execute()) {
        // Redirect back with success message
        header("Location: add_event.php?msg=deleted");
    } else {
        echo "Error deleting record: " . $conn->error;
    }
} else {
    header("Location: add_event.php");
}
exit();
?>