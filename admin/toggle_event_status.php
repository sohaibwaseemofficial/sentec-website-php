<?php
session_start();
include '../db_connection.php';
header('Content-Type: application/json');

// Basic security check
if (!isset($_SESSION['admin']) && !isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$response = ['success' => false, 'message' => 'No action taken.'];

if (isset($_POST['open'])) {
    $val = (int)$_POST['open'];
    if ($conn->query("UPDATE event_registration_settings SET is_open = $val WHERE id = 1")) {
        $response = ['success' => true, 'message' => $val ? 'Event Registrations OPENED.' : 'Event Registrations CLOSED.'];
    }
} 
elseif (isset($_POST['visible'])) {
    $val = (int)$_POST['visible'];
    if ($conn->query("UPDATE event_registration_settings SET is_visible = $val WHERE id = 1")) {
        $response = ['success' => true, 'message' => $val ? 'Competitions box is now VISIBLE.' : 'Competitions box is now VANISHED.'];
    } else {
        $response = ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
}
elseif (isset($_POST['limit'])) {
    $val = (int)$_POST['limit'];
    if ($conn->query("UPDATE event_registration_settings SET registration_limit = $val WHERE id = 1")) {
        $response = ['success' => true, 'message' => 'Registration Limit Updated.'];
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
        if ($conn->query("UPDATE event_registration_settings SET $col = $val WHERE id = 1")) {
            $response = ['success' => true, 'message' => "Institution form visibility updated."];
        } else {
            $response = ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
    }
}

echo json_encode($response);
exit;
?>