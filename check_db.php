<?php
require 'db_connection.php';
$res = $conn->query('SELECT id, user_id, team_name, module_selection FROM event_registrations ORDER BY created_at DESC LIMIT 10');
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}
