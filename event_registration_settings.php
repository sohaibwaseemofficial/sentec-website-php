<?php
function event_registrations_open($conn) {
    $res = $conn->query("SELECT is_open FROM event_registration_settings WHERE id = 1");
    if ($res && $row = $res->fetch_assoc()) {
        return (bool)$row['is_open'];
    }
    return true; // Default to open
}

function event_registrations_limit($conn) {
    $res = $conn->query("SELECT registration_limit FROM event_registration_settings WHERE id = 1");
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['registration_limit'];
    }
    return 100;
}

function event_registrations_visible($conn) {
    $res = $conn->query("SELECT is_visible FROM event_registration_settings WHERE id = 1");
    return ($res && $row = $res->fetch_assoc()) ? (bool)$row['is_visible'] : true;
}

function event_inst_visibility($conn) {
    $res = $conn->query("SELECT show_ned, show_non_ned, show_college FROM event_registration_settings WHERE id = 1");
    if ($res && $row = $res->fetch_assoc()) {
        return [
            'ned' => (bool)$row['show_ned'],
            'non_ned' => (bool)$row['show_non_ned'],
            'college' => (bool)$row['show_college']
        ];
    }
    return ['ned' => true, 'non_ned' => true, 'college' => true];
}

?>