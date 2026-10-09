<?php
require_once __DIR__ . '/env_loader.php';

function social_registrations_status_file(): string {
    return __DIR__ . '/social_registration_status.json';
}

function social_registrations_read_state(): array {
    $state = [
        'open' => true,
        'limit' => null,
        'visible' => false,
        'enable_individual' => true,
        'enable_participant' => false,
        'enable_group' => false,
        'individual_price' => 500,
        'individual_original_price' => 700,
        'early_bird_active' => true,
        'participant_price' => 0,
        'participant_original_price' => 0,
        'participant_discount_active' => false,
        'group_price' => 1200,
        'group_original_price' => 1500,
        'group_discount_active' => false
    ];

    $file = social_registrations_status_file();
    if (file_exists($file)) {
        $json = json_decode(file_get_contents($file), true);
        if (is_array($json)) {
            foreach ($state as $k => $def) {
                if (array_key_exists($k, $json)) {
                    $state[$k] = $json[$k];
                }
            }
        }
    }

    return $state;
}

function social_registrations_write_state(array $state): bool {
    $payload = [
        'open' => (bool) ($state['open'] ?? true),
        'limit' => $state['limit'] ?? null,
        'visible' => (bool) ($state['visible'] ?? false),
        'enable_individual' => (bool) ($state['enable_individual'] ?? true),
        'enable_participant' => (bool) ($state['enable_participant'] ?? false),
        'enable_group' => (bool) ($state['enable_group'] ?? false),
        'individual_price' => (int) ($state['individual_price'] ?? 500),
        'individual_original_price' => (int) ($state['individual_original_price'] ?? 700),
        'early_bird_active' => (bool) ($state['early_bird_active'] ?? true),
        'participant_price' => (int) ($state['participant_price'] ?? 0),
        'participant_original_price' => (int) ($state['participant_original_price'] ?? 0),
        'participant_discount_active' => (bool) ($state['participant_discount_active'] ?? false),
        'group_price' => (int) ($state['group_price'] ?? 1200),
        'group_original_price' => (int) ($state['group_original_price'] ?? 1500),
        'group_discount_active' => (bool) ($state['group_discount_active'] ?? false),
        'updated_at' => date('c')
    ];
    return (bool) file_put_contents(social_registrations_status_file(), json_encode($payload, JSON_PRETTY_PRINT));
}

function social_registrations_get_settings(mysqli $conn = null): array {
    $state = social_registrations_read_state();
    if ($conn instanceof mysqli) {
        $res = $conn->query("SELECT * FROM social_registration_settings WHERE id = 1");
        if ($res && $row = $res->fetch_assoc()) {
            return [
                'open' => (bool)$row['is_open'],
                'limit' => (!empty($row['registration_limit']) && (int)$row['registration_limit'] > 0) ? (int)$row['registration_limit'] : null,
                'visible' => (bool)($row['is_visible'] ?? 0),
                'enable_individual' => isset($row['enable_individual']) ? (bool)$row['enable_individual'] : true,
                'enable_participant' => isset($row['enable_participant']) ? (bool)$row['enable_participant'] : false,
                'enable_group' => isset($row['enable_group']) ? (bool)$row['enable_group'] : false,
                'individual_price' => isset($row['individual_price']) ? (int)$row['individual_price'] : 500,
                'individual_original_price' => isset($row['individual_original_price']) ? (int)$row['individual_original_price'] : 700,
                'early_bird_active' => isset($row['early_bird_active']) ? (bool)$row['early_bird_active'] : true,
                'participant_price' => isset($row['participant_price']) ? (int)$row['participant_price'] : 0,
                'participant_original_price' => isset($row['participant_original_price']) ? (int)$row['participant_original_price'] : 0,
                'participant_discount_active' => isset($row['participant_discount_active']) ? (bool)$row['participant_discount_active'] : false,
                'group_price' => isset($row['group_price']) ? (int)$row['group_price'] : 1200,
                'group_original_price' => isset($row['group_original_price']) ? (int)$row['group_original_price'] : 1500,
                'group_discount_active' => isset($row['group_discount_active']) ? (bool)$row['group_discount_active'] : false,
            ];
        }
    }
    return $state;
}

function social_tier_effective_price(string $tier, array $settings): int {
    if ($tier === 'standard') {
        $ebActive = !empty($settings['early_bird_active']);
        $orig = (int)($settings['individual_original_price'] ?? 700);
        $disc = (int)($settings['individual_price'] ?? 500);
        return ($ebActive && $orig > $disc) ? $disc : $orig;
    }
    if ($tier === 'participant') {
        $discActive = !empty($settings['participant_discount_active']);
        $orig = (int)($settings['participant_original_price'] ?? 0);
        $disc = (int)($settings['participant_price'] ?? 0);
        if ($discActive && $orig > $disc) {
            return $disc;
        }
        return ($orig > 0) ? $orig : $disc;
    }
    if ($tier === 'group') {
        $discActive = !empty($settings['group_discount_active']);
        $orig = (int)($settings['group_original_price'] ?? 1500);
        $disc = (int)($settings['group_price'] ?? 1200);
        return ($discActive && $orig > $disc) ? $disc : $orig;
    }
    return 0;
}

function social_registrations_update_settings(array $fields, mysqli $conn = null): bool {
    $current = social_registrations_get_settings($conn);
    $merged = array_merge($current, $fields);

    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare("UPDATE social_registration_settings SET 
            is_open = ?,
            registration_limit = ?,
            is_visible = ?,
            enable_individual = ?,
            enable_participant = ?,
            enable_group = ?,
            individual_price = ?,
            individual_original_price = ?,
            early_bird_active = ?,
            participant_price = ?,
            participant_original_price = ?,
            participant_discount_active = ?,
            group_price = ?,
            group_original_price = ?,
            group_discount_active = ?
            WHERE id = 1");
        if ($stmt) {
            $isOpen = $merged['open'] ? 1 : 0;
            $limitVal = $merged['limit'] !== null ? (int)$merged['limit'] : 0;
            $isVisible = $merged['visible'] ? 1 : 0;
            $enInd = $merged['enable_individual'] ? 1 : 0;
            $enPart = $merged['enable_participant'] ? 1 : 0;
            $enGrp = $merged['enable_group'] ? 1 : 0;
            $indPrice = (int)$merged['individual_price'];
            $indOrig = (int)$merged['individual_original_price'];
            $ebActive = $merged['early_bird_active'] ? 1 : 0;
            $partPrice = (int)$merged['participant_price'];
            $partOrig = (int)$merged['participant_original_price'];
            $partDiscActive = $merged['participant_discount_active'] ? 1 : 0;
            $grpPrice = (int)$merged['group_price'];
            $grpOrig = (int)$merged['group_original_price'];
            $grpDiscActive = $merged['group_discount_active'] ? 1 : 0;

            $stmt->bind_param("iiiiiiiiiiiiiii", 
                $isOpen, $limitVal, $isVisible,
                $enInd, $enPart, $enGrp,
                $indPrice, $indOrig, $ebActive,
                $partPrice, $partOrig, $partDiscActive,
                $grpPrice, $grpOrig, $grpDiscActive
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    return social_registrations_write_state($merged);
}

function social_registrations_limit(mysqli $conn = null): ?int {
    $envLimit = env('SOCIAL_REGISTRATIONS_LIMIT', null);
    if ($envLimit !== null && $envLimit !== '') {
        return is_numeric($envLimit) ? (int) $envLimit : null;
    }
    if ($conn instanceof mysqli) {
        $res = $conn->query("SELECT registration_limit FROM social_registration_settings WHERE id = 1");
        if ($res && $row = $res->fetch_assoc()) {
            $dbLimit = (int)$row['registration_limit'];
            if ($dbLimit > 0) return $dbLimit;
        }
    }
    $state = social_registrations_read_state();
    return $state['limit'] ?? null;
}

function social_registrations_set_limit(?int $limit, mysqli $conn = null): bool {
    $state = social_registrations_read_state();
    $state['limit'] = $limit;
    if ($conn instanceof mysqli) {
        $dbLimit = $limit !== null ? (int)$limit : 0;
        $conn->query("UPDATE social_registration_settings SET registration_limit = $dbLimit WHERE id = 1");
    }
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

    if ($conn instanceof mysqli) {
        $res = $conn->query("SELECT is_open, registration_limit FROM social_registration_settings WHERE id = 1");
        if ($res && $row = $res->fetch_assoc()) {
            if (!(bool)$row['is_open']) {
                return false;
            }
            $limit = (int)$row['registration_limit'];
            if ($limit > 0) {
                $count = social_registrations_count($conn);
                if ($count >= $limit) {
                    return false;
                }
            }
            return true;
        }
    }

    $state = social_registrations_read_state();
    if (!$state['open']) {
        return false;
    }

    $limit = social_registrations_limit($conn);
    if ($limit !== null && $limit > 0 && $conn instanceof mysqli) {
        $count = social_registrations_count($conn);
        if ($count >= $limit) {
            return false;
        }
    }

    return true;
}

function social_registrations_set_open(bool $open, mysqli $conn = null): bool {
    $state = social_registrations_read_state();
    $state['open'] = $open;
    if ($conn instanceof mysqli) {
        $dbOpen = $open ? 1 : 0;
        $conn->query("UPDATE social_registration_settings SET is_open = $dbOpen WHERE id = 1");
    }
    return social_registrations_write_state($state);
}

function social_registrations_visible($conn = null): bool {
    if ($conn) {
        $res = $conn->query("SELECT is_visible FROM social_registration_settings WHERE id = 1");
        if ($res && $row = $res->fetch_assoc()) {
            return (bool)$row['is_visible'];
        }
    }
    $state = social_registrations_read_state();
    return (bool)($state['visible'] ?? false);
}
?>
