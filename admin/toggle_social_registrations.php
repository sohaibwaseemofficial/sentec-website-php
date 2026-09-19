<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_SESSION['admin'])) {
        throw new Exception('Unauthorized');
    }

    require_once __DIR__ . '/../social_registration_settings.php';
    require_once __DIR__ . '/../db_connection.php'; // Need DB for visibility

    $messages = [];

    if (array_key_exists('open', $_POST)) {
        $normalized = strtolower(trim((string) $_POST['open']));
        $open = !in_array($normalized, ['0', 'false', 'no', 'closed'], true);

        if (!social_registrations_set_open($open)) {
            throw new Exception('Failed to update open/close status');
        }
        $messages[] = $open ? 'Social registrations are OPEN.' : 'Social registrations are CLOSED.';
    }

    if (array_key_exists('limit', $_POST)) {
        $limitRaw = trim((string) $_POST['limit']);
        $limit = ($limitRaw === '' || $limitRaw === '0') ? null : (int) $limitRaw;
        if (!social_registrations_set_limit($limit)) {
            throw new Exception('Failed to update limit');
        }
        $messages[] = $limit ? ("Limit set to " . $limit) : 'Limit cleared';
    }

    // NEW VISIBILITY LOGIC
    if (array_key_exists('visible', $_POST)) {
        $val = (int)$_POST['visible'];
        if ($conn->query("UPDATE social_registration_settings SET is_visible = $val WHERE id = 1")) {
            $messages[] = $val ? 'Box is now VISIBLE on dashboard.' : 'Box is now VANISHED from dashboard.';
        } else {
            throw new Exception('Failed to update visibility in database.');
        }
    }

    if (empty($messages)) {
        throw new Exception('No changes submitted');
    }

    $response['success'] = true;
    $response['message'] = implode(' | ', $messages);
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>