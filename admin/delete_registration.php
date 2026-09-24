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

/**
 * Helper function to delete file safely
 * Handles '../' prefix to reach root upload directory
 */
function deleteRegistrationFile($filepath) {
    if (!empty($filepath) && $filepath !== 'Not Collected') {
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

/**
 * Clean up all physical files associated with a registration row
 */
function deleteRegistrationRowFiles($row) {
    if (!is_array($row)) return;
    deleteRegistrationFile($row['fees_screenshot'] ?? '');
    deleteRegistrationFile($row['payment_proof'] ?? '');

    for ($i = 1; $i <= 6; $i++) {
        deleteRegistrationFile($row["participant{$i}_face_image"] ?? '');
        deleteRegistrationFile($row["participant{$i}_id_card"] ?? '');
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $isDeleteAll = !empty($_POST['delete_all']) || (isset($_POST['action']) && $_POST['action'] === 'delete_all');
    $rawIds = $_POST['ids'] ?? [];
    $singleId = intval($_POST['id'] ?? 0);

    // ============================================================
    // CASE A: DELETE ALL REGISTRATIONS
    // ============================================================
    if ($isDeleteAll) {
        // 1. Fetch all rows to delete associated media files
        $result = $conn->query("SELECT * FROM event_registrations");
        $count = 0;
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                deleteRegistrationRowFiles($row);
                $count++;
            }
        }

        // 2. Delete all child records in event_attendees
        @$conn->query("DELETE FROM event_attendees");

        // 3. Delete all event_registrations
        if (!$conn->query("DELETE FROM event_registrations")) {
            throw new Exception("Failed to clear registrations table: " . $conn->error);
        }

        // Reset auto increment for clean slate
        @$conn->query("ALTER TABLE event_registrations AUTO_INCREMENT = 1");
        @$conn->query("ALTER TABLE event_attendees AUTO_INCREMENT = 1");

        log_admin_action('DELETE_ALL_REGISTRATIONS', "Permanently deleted ALL {$count} event registration(s)", $conn);

        $response['success'] = true;
        $response['message'] = "All {$count} event registration(s) and uploaded files have been permanently deleted.";

    // ============================================================
    // CASE B: BULK DELETE SELECTED IDS
    // ============================================================
    } elseif (is_array($rawIds) && !empty($rawIds)) {
        $validIds = [];
        foreach ($rawIds as $v) {
            $vid = intval($v);
            if ($vid > 0) $validIds[] = $vid;
        }
        $validIds = array_values(array_unique($validIds));

        if (empty($validIds)) {
            throw new Exception("No valid registration IDs provided for deletion.");
        }

        $idList = implode(',', $validIds);

        // Fetch to clean up physical files
        $result = $conn->query("SELECT * FROM event_registrations WHERE id IN ($idList)");
        $deletedCount = 0;
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                deleteRegistrationRowFiles($row);
                $deletedCount++;
            }
        }

        // Delete child attendees
        @$conn->query("DELETE FROM event_attendees WHERE registration_id IN ($idList)");

        // Delete registrations
        if (!$conn->query("DELETE FROM event_registrations WHERE id IN ($idList)")) {
            throw new Exception("Failed to delete selected registrations: " . $conn->error);
        }

        log_admin_action('BULK_DELETE_REGISTRATIONS', "Deleted {$deletedCount} registration(s): [$idList]", $conn);

        $response['success'] = true;
        $response['message'] = "Successfully deleted {$deletedCount} registration(s) and associated files.";

    // ============================================================
    // CASE C: SINGLE REGISTRATION DELETE
    // ============================================================
    } elseif ($singleId > 0) {
        $stmt = $conn->prepare("SELECT * FROM event_registrations WHERE id = ? LIMIT 1");
        if (!$stmt) throw new Exception('Database error: ' . $conn->error);
        $stmt->bind_param("i", $singleId);
        $stmt->execute();
        $registration = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registration) {
            throw new Exception('Registration record not found.');
        }

        // Delete files
        deleteRegistrationRowFiles($registration);

        // Delete attendees
        $delAtt = $conn->prepare("DELETE FROM event_attendees WHERE registration_id = ?");
        if ($delAtt) {
            $delAtt->bind_param("i", $singleId);
            $delAtt->execute();
            $delAtt->close();
        }

        // Delete registration record
        $delStmt = $conn->prepare("DELETE FROM event_registrations WHERE id = ?");
        if (!$delStmt) throw new Exception('Database delete error: ' . $conn->error);
        $delStmt->bind_param("i", $singleId);
        if (!$delStmt->execute()) {
            throw new Exception('Failed to execute delete: ' . $delStmt->error);
        }
        $delStmt->close();

        log_admin_action('DELETE_REGISTRATION', "Deleted registration ID $singleId (Team: " . ($registration['team_name'] ?? 'Unknown') . ")", $conn);

        $response['success'] = true;
        $response['message'] = 'Registration and associated files deleted successfully.';

    } else {
        throw new Exception('No registration ID specified.');
    }

} catch (Throwable $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
exit();