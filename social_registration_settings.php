<?php
require_once __DIR__ . '/env_loader.php';

function social_registrations_status_file(): string {
    return __DIR__ . '/social_registration_status.json';
}

function social_registrations_read_state(): array {
    $state = [
        'open' => true,
        'limit' => null,
    ];

    $file = social_registrations_status_file();
    if (file_exists($file)) {
        $json = json_decode(file_get_contents($file), true);
        if (is_array($json)) {
            if (array_key_exists('open', $json)) {
                $state['open'] = (bool) $json['open'];
            }
            if (array_key_exists('limit', $json) && $json['limit'] !== null && $json['limit'] !== '') {
                $state['limit'] = (int) $json['limit'];
            }
        }
    }

    return $state;
}

function social_registrations_write_state(array $state): bool {
    $payload = [
        'open' => (bool) ($state['open'] ?? true),
        'limit' => $state['limit'] ?? null,
        'updated_at' => date('c')
    ];
    return (bool) file_put_contents(social_registrations_status_file(), json_encode($payload, JSON_PRETTY_PRINT));
}

function social_registrations_limit(): ?int {
    $envLimit = env('SOCIAL_REGISTRATIONS_LIMIT', null);
    if ($envLimit !== null && $envLimit !== '') {
        return is_numeric($envLimit) ? (int) $envLimit : null;
    }
    $state = social_registrations_read_state();
    return $state['limit'] ?? null;
}

function social_registrations_set_limit(?int $limit): bool {
    $state = social_registrations_read_state();
    $state['limit'] = $limit;
    return social_registrations_write_state($state);
}

function social_registrations_count(mysqli $conn): int {
    $check = $conn->query("SHOW TABLES LIKE 'social_registrations'");
    if (!$check || $check->num_rows === 0) {
        return 0;
    }
    $result = $conn->query("SELECT COUNT(*) AS total FROM social_registrations");
    if ($result && ($row = $result->fetch_assoc())) {
        return (int) $row['total'];
    }
    return 0;
}

function social_registrations_open(mysqli $conn = null): bool {
    $envFlag = env('SOCIAL_REGISTRATIONS_OPEN', null);
    if ($envFlag !== null) {
        $normalized = strtolower((string) $envFlag);
        if (in_array($normalized, ['0', 'false', 'off', 'closed'], true)) {
            return false;
        }
        return true;
    }

    $state = social_registrations_read_state();
    if (!$state['open']) {
        return false;
    }

    $limit = social_registrations_limit();
    if ($limit !== null && $limit > 0 && $conn instanceof mysqli) {
        $count = social_registrations_count($conn);
        if ($count >= $limit) {
            return false;
        }
    }

    return true;
}

function social_registrations_set_open(bool $open): bool {
    $state = social_registrations_read_state();
    $state['open'] = $open;
    return social_registrations_write_state($state);
}

function social_registrations_visible($conn = null): bool {
    if ($conn) {
        $res = $conn->query("SELECT is_visible FROM social_registration_settings WHERE id = 1");
        if ($res && $row = $res->fetch_assoc()) {
            return (bool)$row['is_visible'];
        }
    }
    return true;
}

?>
