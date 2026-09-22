<?php
// Prevent accidental output buffer leaks
ob_start();
session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');

// 1. SECURITY CHECK
if (!isset($_SESSION['admin']) && !isset($_SESSION['admin_logged_in'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/admin_logger.php';

$response = ['success' => false, 'message' => ''];

try {
    // 2. VALIDATE ID
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        throw new Exception('Registration ID is required');
    }
    
    $id = intval($_POST['id']);
    if ($id <= 0) {
        throw new Exception('Invalid Registration ID');
    }
    
    // 3. FETCH REGISTRATION DATA
    // We fetch everything to ensure we have paths for all participant images
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
            $clean = ltrim(str_replace(['\\', '//'], '/', $filepath), '/');
            if (strpos($clean, '../') === 0) {
                $clean = substr($clean, 3);
            }
            $fullPath = __DIR__ . '/../' . $clean;
            if (file_exists($fullPath) && is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }
    
    // 4. CLEAN UP SERVER FILES
    // Delete team fee proof (both legacy fees_screenshot and payment_proof)
    deleteRegistrationFile($registration['fees_screenshot'] ?? '');
    deleteRegistrationFile($registration['payment_proof'] ?? '');
    
    // Loop through all 6 participants to delete face images and ID cards
    for ($i = 1; $i <= 6; $i++) {
        $faceField = "participant{$i}_face_image";
        $cardField = "participant{$i}_id_card";

        deleteRegistrationFile($registration[$faceField] ?? '');
        deleteRegistrationFile($registration[$cardField] ?? '');
    }

    // 4.5 CLEAN UP CHILD RECORDS (event_attendees)
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
    
    $delStmt->close();
    
    // Log this action BEFORE closing the database connection
    log_admin_action('DELETE_REGISTRATION', "Deleted registration ID $id (Team: " . ($registration['team_name'] ?? 'Unknown') . ")", $conn);
    
    $conn->close();

    $response['success'] = true;
    $response['message'] = 'Registration and associated files deleted successfully';
    
} catch (Throwable $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
exit();