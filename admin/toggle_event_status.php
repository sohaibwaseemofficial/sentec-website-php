<?php
ob_start();
session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['admin']) && !isset($_SESSION['admin_logged_in'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/admin_logger.php';

$response = ['success' => false, 'message' => 'No action taken.'];

try {
    if (isset($_POST['open'])) {
        $val = (int)$_POST['open'];
        $stmt = $conn->prepare("UPDATE event_registration_settings SET is_open = ? WHERE id = 1");
        if ($stmt) {
            $stmt->bind_param("i", $val);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => $val ? 'Event Registrations OPENED.' : 'Event Registrations CLOSED.'];
                log_admin_action('TOGGLE_EVENT_REGISTRATIONS', $response['message'], $conn);
            }
            $stmt->close();
        }
    } 
    elseif (isset($_POST['visible'])) {
        $val = (int)$_POST['visible'];
        $stmt = $conn->prepare("UPDATE event_registration_settings SET is_visible = ? WHERE id = 1");
        if ($stmt) {
            $stmt->bind_param("i", $val);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => $val ? 'Competitions box is now VISIBLE on dashboard.' : 'Competitions box is now VANISHED from dashboard.'];
                log_admin_action('TOGGLE_EVENT_VISIBILITY', $response['message'], $conn);
            } else {
                $response = ['success' => false, 'message' => 'Database error: ' . $stmt->error];
            }
            $stmt->close();
        }
    }
    elseif (isset($_POST['limit'])) {
        $val = (int)$_POST['limit'];
        $stmt = $conn->prepare("UPDATE event_registration_settings SET registration_limit = ? WHERE id = 1");
        if ($stmt) {
            $stmt->bind_param("i", $val);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Registration Limit Updated to ' . $val . '.'];
                log_admin_action('UPDATE_REGISTRATION_LIMIT', "Set limit to $val", $conn);
            }
            $stmt->close();
        }
    }
    elseif (isset($_POST['toggle_inst']) && isset($_POST['val'])) {
        $type = $_POST['toggle_inst'];
        $val = (int)$_POST['val'];
        $col = '';
        
        if ($type === 'ned') $col = 'show_ned';
        elseif ($type === 'non_ned') $col = 'show_non_ned';
        elseif ($type === 'college') $col = 'show_college';

        if ($col !== '') {
            $sql = "UPDATE event_registration_settings SET `{$col}` = ? WHERE id = 1";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $val);
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => "Institution form visibility updated."];
                    log_admin_action('TOGGLE_INST_VISIBILITY', "Set $col to $val", $conn);
                } else {
                    $response = ['success' => false, 'message' => 'Database error: ' . $stmt->error];
                }
                $stmt->close();
            }
        }
    }
} catch (Throwable $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

ob_end_clean();
echo json_encode($response);
exit;