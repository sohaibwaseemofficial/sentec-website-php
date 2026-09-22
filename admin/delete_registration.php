<?php
session_start();

// 1. SECURITY CHECK
if (!isset($_SESSION['admin']) && !isset($_SESSION['admin_logged_in'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');
include '../db_connection.php';

$response = ['success' => false, 'message' => ''];

try {
    // 2. VALIDATE ID
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        throw new Exception('Registration ID is required');
    }
    
    $id = intval($_POST['id']);
    
    // 3. FETCH REGISTRATION DATA
    // We fetch everything to ensure we have paths for all 6 participant images
    $stmt = $conn->prepare("SELECT * FROM event_registrations WHERE id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Registration record not found');
    }
    
    $registration = $result->fetch_assoc();
    $stmt->close();
    
    /**
     * Helper function to delete file safely
     * Handles the '../' prefix to reach the root upload directory
     */
    function deleteRegistrationFile($filepath) {
        if (!empty($filepath)) {
            $fullPath = '../' . $filepath;
            if (file_exists($fullPath) && is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }
    
    // 4. CLEAN UP SERVER FILES
    // Delete team fee proof
    deleteRegistrationFile($registration['fees_screenshot']);
    
    // Loop through all 6 participants to delete face images and ID cards
    for ($i = 1; $i <= 6; $i++) {
        $nameField = "participant{$i}_name";
        $faceField = "participant{$i}_face_image";
        $cardField = "participant{$i}_id_card";

        // Only attempt deletion if the participant columns exist in the database row
        if (isset($registration[$nameField])) {
            deleteRegistrationFile($registration[$faceField] ?? '');
            deleteRegistrationFile($registration[$cardField] ?? '');
        }
    }

    // 4.5 CLEAN UP CHILD RECORDS
    // Delete from event_attendees first to prevent Foreign Key constraint errors
    $delAttendeesStmt = $conn->prepare("DELETE FROM event_attendees WHERE registration_id = ?");
    if ($delAttendeesStmt) {
        $delAttendeesStmt->bind_param("i", $id);
        $delAttendeesStmt->execute();
        $delAttendeesStmt->close();
    }
    
    // 5. DELETE DATABASE RECORD
    $delStmt = $conn->prepare("DELETE FROM event_registrations WHERE id = ?");
    if (!$delStmt) {
        throw new Exception('Database delete prepare error: ' . $conn->error);
    }
    
    $delStmt->bind_param("i", $id);
    
    if (!$delStmt->execute()) {
        throw new Exception('Failed to execute delete: ' . $delStmt->error);
    }
    
    if (isset($_SESSION['admin_id'])) {
        $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        if ($logStmt) {
            $action = 'DELETE_REGISTRATION';
            $details = "Deleted registration ID $id";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $logStmt->bind_param('isss', $_SESSION['admin_id'], $action, $details, $ip);
            $logStmt->execute();
            $logStmt->close();
        }
    }

    $delStmt->close();
    $conn->close();

    $response['success'] = true;
    $response['message'] = 'Registration and associated files deleted successfully';

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Return clean JSON response to your AJAX handler
echo json_encode($response);
exit();
?>