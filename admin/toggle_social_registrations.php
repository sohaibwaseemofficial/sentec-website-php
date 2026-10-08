<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_SESSION['admin']) && !isset($_SESSION['admin_logged_in'])) {
        throw new Exception('Unauthorized');
    }

    require_once __DIR__ . '/../social_registration_settings.php';
    require_once __DIR__ . '/../db_connection.php';

    $messages = [];

    if (array_key_exists('open', $_POST)) {
        $normalized = strtolower(trim((string) $_POST['open']));
        $open = !in_array($normalized, ['0', 'false', 'no', 'closed'], true);

        if (!social_registrations_set_open($open, $conn)) {
            throw new Exception('Failed to update open/close status');
        }
        $messages[] = $open ? 'Social registrations are OPEN.' : 'Social registrations are CLOSED.';
    }

    if (array_key_exists('limit', $_POST)) {
        $limitRaw = trim((string) $_POST['limit']);
        $limit = ($limitRaw === '' || $limitRaw === '0') ? null : (int) $limitRaw;
        if (!social_registrations_set_limit($limit, $conn)) {
            throw new Exception('Failed to update limit');
        }
        $messages[] = $limit ? ("Limit set to " . $limit) : 'Limit cleared';
    }

    if (array_key_exists('visible', $_POST)) {
        $val = (int)$_POST['visible'];
        if ($conn->query("UPDATE social_registration_settings SET is_visible = $val WHERE id = 1")) {
            $messages[] = $val ? 'Box is now VISIBLE on dashboard.' : 'Box is now VANISHED from dashboard.';
        } else {
            throw new Exception('Failed to update visibility in database.');
        }
    }

    // Pass Tiers & Pricing update (bulk or single toggle)
    $tierUpdates = [];

    if (isset($_POST['enable_individual'])) {
        $tierUpdates['enable_individual'] = (int)$_POST['enable_individual'] ? 1 : 0;
        $messages[] = $tierUpdates['enable_individual'] ? 'Individual Pass ENABLED.' : 'Individual Pass DISABLED.';
    }

    if (isset($_POST['enable_participant'])) {
        $tierUpdates['enable_participant'] = (int)$_POST['enable_participant'] ? 1 : 0;
        $messages[] = $tierUpdates['enable_participant'] ? 'Participant Pass ENABLED.' : 'Participant Pass DISABLED.';
    }

    if (isset($_POST['enable_group'])) {
        $tierUpdates['enable_group'] = (int)$_POST['enable_group'] ? 1 : 0;
        $messages[] = $tierUpdates['enable_group'] ? 'Group Pass ENABLED.' : 'Group Pass DISABLED.';
    }

    if (isset($_POST['early_bird_active'])) {
        $tierUpdates['early_bird_active'] = (int)$_POST['early_bird_active'] ? 1 : 0;
        $messages[] = $tierUpdates['early_bird_active'] ? 'Individual Early Bird ACTIVATED.' : 'Individual Early Bird DEACTIVATED.';
    }

    if (isset($_POST['participant_discount_active'])) {
        $tierUpdates['participant_discount_active'] = (int)$_POST['participant_discount_active'] ? 1 : 0;
        $messages[] = $tierUpdates['participant_discount_active'] ? 'Participant Discount ACTIVATED.' : 'Participant Discount DEACTIVATED.';
    }

    if (isset($_POST['group_discount_active'])) {
        $tierUpdates['group_discount_active'] = (int)$_POST['group_discount_active'] ? 1 : 0;
        $messages[] = $tierUpdates['group_discount_active'] ? 'Group Discount ACTIVATED.' : 'Group Discount DEACTIVATED.';
    }

    if (isset($_POST['individual_price']) && is_numeric($_POST['individual_price'])) {
        $tierUpdates['individual_price'] = max(0, (int)$_POST['individual_price']);
    }

    if (isset($_POST['individual_original_price']) && is_numeric($_POST['individual_original_price'])) {
        $tierUpdates['individual_original_price'] = max(0, (int)$_POST['individual_original_price']);
    }

    if (isset($_POST['participant_price']) && is_numeric($_POST['participant_price'])) {
        $tierUpdates['participant_price'] = max(0, (int)$_POST['participant_price']);
    }

    if (isset($_POST['participant_original_price']) && is_numeric($_POST['participant_original_price'])) {
        $tierUpdates['participant_original_price'] = max(0, (int)$_POST['participant_original_price']);
    }

    if (isset($_POST['group_price']) && is_numeric($_POST['group_price'])) {
        $tierUpdates['group_price'] = max(0, (int)$_POST['group_price']);
    }

    if (isset($_POST['group_original_price']) && is_numeric($_POST['group_original_price'])) {
        $tierUpdates['group_original_price'] = max(0, (int)$_POST['group_original_price']);
    }

    if (!empty($tierUpdates)) {
        social_registrations_update_settings($tierUpdates, $conn);
        if (isset($_POST['save_all_tiers'])) {
            $messages = ['Pass tier and pricing settings saved successfully!'];
        }
    }

    if (empty($messages)) {
        throw new Exception('No changes submitted');
    }

    $response['success'] = true;
    $response['message'] = implode(' | ', $messages);
    $response['settings'] = social_registrations_get_settings($conn);
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>