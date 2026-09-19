<?php
session_start();
include '../db_connection.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$response = ['success' => false, 'message' => 'No action taken.'];

$conn->query("INSERT IGNORE INTO election_portal_settings (id, is_open, is_visible, show_results) VALUES (1, 0, 1, 0)");

if (isset($_POST['open'])) {
    $val = (int) $_POST['open'];
    if ($conn->query("UPDATE election_portal_settings SET is_open = $val WHERE id = 1")) {
        $response = ['success' => true, 'message' => $val ? 'Election portal opened for voting.' : 'Election portal closed for voting.'];
    } else {
        $response = ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
} elseif (isset($_POST['visible'])) {
    $val = (int) $_POST['visible'];
    if ($conn->query("UPDATE election_portal_settings SET is_visible = $val WHERE id = 1")) {
        $response = ['success' => true, 'message' => $val ? 'Election tile is now visible on the dashboard.' : 'Election tile is now hidden from the dashboard.'];
    } else {
        $response = ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
} elseif (isset($_POST['results'])) {
    $val = (int) $_POST['results'];
    if ($conn->query("UPDATE election_portal_settings SET show_results = $val WHERE id = 1")) {
        $response = ['success' => true, 'message' => $val ? 'Public results enabled.' : 'Public results hidden.'];
    } else {
        $response = ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
}

echo json_encode($response);
exit;
?>