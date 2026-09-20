<?php
session_start();

// 1. Security Check
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

// 2. Connect to Database (Go up one folder)
include '../db_connection.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // Clean the ID

    // 3. OPTIONAL: Delete the actual image file to save space
    $imgQuery = "SELECT image FROM team_members WHERE id = ?";
    if ($stmt = $conn->prepare($imgQuery)) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            // The image path in DB is like "images/uploads/team/..."
            // We need to add "../" to find it from the admin folder
            $filePath = "../" . $row['image'];
            if (file_exists($filePath)) {
                unlink($filePath); // Delete the file
            }
        }
        $stmt->close();
    }

    // 4. Delete the Database Record
    $deleteQuery = "DELETE FROM team_members WHERE id = ?";
    if ($stmt = $conn->prepare($deleteQuery)) {
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if (function_exists('invalidate_cache')) {
                invalidate_cache('team_members');
            }
            // Success - Go back
            header("Location: manage_team.php?msg=deleted");
            exit();
        } else {
            echo "Error deleting record: " . $conn->error;
        }
        $stmt->close();
    }
} else {
    header("Location: manage_team.php");
    exit();
}

$conn->close();
?>
