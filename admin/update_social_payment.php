<?php
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

    $stmt = $conn->prepare("UPDATE social_registrations SET payment_status = 'confirmed' WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if (social_attendees_table_exists($conn)) {
            $sync = $conn->prepare("UPDATE social_attendees SET payment_status = 'confirmed' WHERE registration_id = ?");
            $sync->bind_param("i", $id);
            $sync->execute();
            $sync->close();
        }
        $response['success'] = true;
        $response['message'] = "Payment Confirmed";
    } else {
        throw new Exception("DB Error: " . $conn->error);
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
?>
