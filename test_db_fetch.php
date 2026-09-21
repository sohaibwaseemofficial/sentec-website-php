<?php
include 'db_connection.php';
$query = "SELECT id, user_id, team_name, module_selection, created_at FROM event_registrations ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($query);
while($row = $result->fetch_assoc()) {
    print_r($row);
}
