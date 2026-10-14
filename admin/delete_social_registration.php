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

    require_once __DIR__ . '/admin_logger.php';

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception("Invalid ID");

    // 1. Fetch full registration record to archive before deletion
    $stmt = $conn->prepare("SELECT * FROM social_registrations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $adminId = $_SESSION['admin_id'] ?? null;
        $adminUser = $_SESSION['admin'] ?? ($_SESSION['admin_user'] ?? 'Admin');

        // Archive into archived_social_registrations table
        $archStmt = $conn->prepare("INSERT INTO archived_social_registrations 
            (original_id, admin_id, full_name, email, phone, cnic, face_image, id_card_image, payment_proof, 
             registration_type, ambassador_code, total_amount, participant2_name, participant2_email, 
             participant3_name, participant3_email, deleted_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($archStmt) {
            $archStmt->bind_param("iisssssssssisssss",
                $id,
                $adminId,
                $row['full_name'],
                $row['email'],
                $row['phone'],
                $row['cnic'],
                $row['face_image'],
                $row['id_card_image'],
                $row['payment_proof'],
                $row['registration_type'],
                $row['ambassador_code'],
                $row['total_amount'],
                $row['participant2_name'],
                $row['participant2_email'],
                $row['participant3_name'],
                $row['participant3_email'],
                $adminUser
            );
            $archStmt->execute();
            $archStmt->close();
        }

        // Log admin action
        log_admin_action('DELETE_SOCIAL_REGISTRATION', "Deleted registration #{$id} ({$row['full_name']} - {$row['email']})", $conn);
    }

    if (social_attendees_table_exists($conn)) {
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
        $response['message'] = "Deleted and archived successfully";
    } else {
        throw new Exception("Database Error: " . $conn->error);
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
?>
