<?php
function election_portal_settings_table_exists(mysqli $conn): bool {
    $result = $conn->query("SHOW TABLES LIKE 'election_portal_settings'");
    return $result && $result->num_rows > 0;
}

function election_portal_setting_value(mysqli $conn, string $column, $default) {
    if (!election_portal_settings_table_exists($conn)) {
        return $default;
    }

    $stmt = $conn->prepare("SELECT $column FROM election_portal_settings WHERE id = 1 LIMIT 1");
    if (!$stmt) {
        return $default;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row && array_key_exists($column, $row) ? $row[$column] : $default;
}

function election_portal_visible(mysqli $conn): bool {
    return (bool) election_portal_setting_value($conn, 'is_visible', 1);
}

function election_portal_open(mysqli $conn): bool {
    return (bool) election_portal_setting_value($conn, 'is_open', 0);
}

function election_portal_results_visible(mysqli $conn): bool {
    return (bool) election_portal_setting_value($conn, 'show_results', 0);
}

function election_candidates_have_active_flag(mysqli $conn): bool {
    $result = $conn->query("SHOW COLUMNS FROM election_candidates LIKE 'is_active'");
    return $result && $result->num_rows > 0;
}

function election_ensure_candidate_active_flag(mysqli $conn): bool {
    if (election_candidates_have_active_flag($conn)) {
        return true;
    }

    return (bool) @$conn->query("ALTER TABLE election_candidates ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER bio");
}

?>