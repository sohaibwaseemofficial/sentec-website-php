<?php
// Prevent accidental output
ob_start();
session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Unknown Error'];

try {
    if (!isset($_SESSION['admin'])) throw new Exception("Unauthorized");
    
    include '../db_connection.php';
    require_once __DIR__ . '/../social_attendees_helper.php';

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception("Invalid ID");

    // 1. Get image paths first so we can delete the files
    $stmt = $conn->prepare("SELECT face_image, id_card_image, payment_proof FROM social_registrations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        // Delete files from server to save space
        if (!empty($row['face_image']) && file_exists("../" . $row['face_image'])) unlink("../" . $row['face_image']);
        if (!empty($row['id_card_image']) && file_exists("../" . $row['id_card_image'])) unlink("../" . $row['id_card_image']);
        if (!empty($row['payment_proof']) && file_exists("../" . $row['payment_proof'])) unlink("../" . $row['payment_proof']);
    }

    if (social_attendees_table_exists($conn)) {
        $att = $conn->prepare("SELECT face_image, id_card_image FROM social_attendees WHERE registration_id = ?");
        $att->bind_param("i", $id);
        $att->execute();
        $attRes = $att->get_result();
        $paths = [];
        while ($attRow = $attRes->fetch_assoc()) {
            if (!empty($attRow['face_image'])) $paths[] = $attRow['face_image'];
            if (!empty($attRow['id_card_image'])) $paths[] = $attRow['id_card_image'];
        }
        $att->close();
        foreach (array_unique($paths) as $path) {
            $full = "../" . ltrim($path, '/');
            if (file_exists($full)) {
                @unlink($full);
            }
        }
        $delAtt = $conn->prepare("DELETE FROM social_attendees WHERE registration_id = ?");
        $delAtt->bind_param("i", $id);
        $delAtt->execute();
        $delAtt->close();
    }

    // 2. Delete from Database
    $del = $conn->prepare("DELETE FROM social_registrations WHERE id = ?");
    $del->bind_param("i", $id);
    
    if ($del->execute()) {
        $response['success'] = true;
        $response['message'] = "Deleted Successfully";
    } else {
        throw new Exception("Database Error: " . $conn->error);
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
?>
