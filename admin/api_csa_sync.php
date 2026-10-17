<?php
/**
 * CSA Audit API - Database Sync & Cloudinary WebP Upload Handler
 * Part of SENTEC NED University Administration Portal
 */

// Enable session & security check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Allow admin session or local script access
if (!isset($_SESSION['admin']) && !isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized admin session. Please log in to admin portal.']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../image_utils.php';

// Synchronize admin role from database if logged in
if (isset($_SESSION['admin_id']) && isset($conn) && $conn instanceof mysqli) {
    $uid = intval($_SESSION['admin_id']);
    $roleQ = $conn->query("SELECT role FROM admin_users WHERE id = $uid LIMIT 1");
    if ($roleQ && $rRow = $roleQ->fetch_assoc()) {
        $_SESSION['admin_role'] = $rRow['role'];
    }
}

// Strict Super Super Admin role check
if (($_SESSION['admin_role'] ?? '') !== 'super_super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: CSA API is restricted to Super Super Admin clearance level.']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'fetch_all_registrations':
        handleFetchAll($conn);
        break;

    case 'upload_receipt':
        handleUploadReceipt();
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action parameter']);
        exit();
}

/**
 * Fetch all payment proofs from both event_registrations and social_registrations
 */
function handleFetchAll($conn)
{
    $records = [];
    $seenUrls = [];

    // 1. Fetch Event Registrations
    $eventSql = "SELECT id, team_name, module_selection, 
                        participant1_name, participant1_roll_number, participant1_contact, participant1_email,
                        participant2_name, participant3_name, participant4_name, participant5_name, participant6_name,
                        payment_proof, fees_screenshot, payment_status, created_at
                 FROM event_registrations 
                 WHERE (payment_proof IS NOT NULL AND payment_proof != '' AND payment_proof != 'Not Collected')
                    OR (fees_screenshot IS NOT NULL AND fees_screenshot != '' AND fees_screenshot != 'Not Collected')
                 ORDER BY id ASC";

    $res = $conn->query($eventSql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $proof = trim($row['payment_proof'] ?: $row['fees_screenshot']);
            if (empty($proof) || $proof === 'Not Collected') continue;

            $fullUrl = resolve_image_url($proof, '../');

            // Collect team members
            $members = array_filter([
                $row['participant1_name'] ? "{$row['participant1_name']} (" . ($row['participant1_roll_number'] ?: 'Lead') . ")" : '',
                $row['participant2_name'],
                $row['participant3_name'],
                $row['participant4_name'],
                $row['participant5_name'],
                $row['participant6_name']
            ]);

            $studentLabel = "Team: " . ($row['team_name'] ?: 'Event Team') . " [" . ($row['module_selection'] ?: 'Event') . "]";
            if (!empty($members)) {
                $studentLabel .= " — " . implode(', ', $members);
            }

            $records[] = [
                'id' => 'EVENT_' . $row['id'],
                'sourceTable' => 'event_registrations',
                'sourceId' => (int)$row['id'],
                'category' => 'Event Registration',
                'module' => $row['module_selection'] ?: 'SENTEC Event',
                'student' => $studentLabel,
                'phone' => $row['participant1_contact'] ?: '',
                'email' => $row['participant1_email'] ?: '',
                'fileName' => basename(parse_url($proof, PHP_URL_PATH) ?: "event_{$row['id']}.webp"),
                'imageUrl' => $fullUrl,
                'dbPaymentStatus' => $row['payment_status'] ?: 'submitted',
                'createdAt' => $row['created_at'] ?: '',
                'status' => 'Pending'
            ];
        }
    }

    // 2. Fetch Social Registrations
    $socialSql = "SELECT sr.id, sr.full_name, sr.email, sr.phone, sr.cnic, 
                         sr.total_amount, sr.payment_proof, sr.payment_status, 
                         sr.registration_type, sr.event_label,
                         sr.participant2_name, sr.participant3_name, sr.created_at
                  FROM social_registrations sr
                  WHERE sr.payment_proof IS NOT NULL AND sr.payment_proof != ''
                  ORDER BY sr.id ASC";

    $res2 = $conn->query($socialSql);
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            $proof = trim($row['payment_proof']);
            if (empty($proof)) continue;

            $fullUrl = resolve_image_url($proof, '../');

            // Format student label with roll / cnic and any covered friends
            $parts = [];
            $mainPerson = $row['full_name'];
            if (!empty($row['cnic'])) $mainPerson .= " ({$row['cnic']})";
            $parts[] = $mainPerson;

            if (!empty($row['participant2_name'])) $parts[] = $row['participant2_name'];
            if (!empty($row['participant3_name'])) $parts[] = $row['participant3_name'];

            $label = implode(' | ', $parts) . " [" . ($row['event_label'] ?: 'Social Pass') . "]";

            $records[] = [
                'id' => 'SOC_' . $row['id'],
                'sourceTable' => 'social_registrations',
                'sourceId' => (int)$row['id'],
                'category' => 'Social Pass',
                'module' => $row['event_label'] ?: 'Qawwali / Social',
                'student' => $label,
                'phone' => $row['phone'] ?: '',
                'email' => $row['email'] ?: '',
                'amount' => (int)($row['total_amount'] ?: 500),
                'fileName' => basename(parse_url($proof, PHP_URL_PATH) ?: "social_{$row['id']}.webp"),
                'imageUrl' => $fullUrl,
                'dbPaymentStatus' => $row['payment_status'] ?: 'submitted',
                'createdAt' => $row['created_at'] ?: '',
                'status' => 'Pending'
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'count' => count($records),
        'records' => $records
    ]);
    exit();
}

/**
 * Handle new receipt file upload: converts to WebP and uploads to Cloudinary
 */
function handleUploadReceipt()
{
    if (empty($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => 'No file received']);
        exit();
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'File upload error code: ' . $file['error']]);
        exit();
    }

    $uploadDir = __DIR__ . '/../images/uploads/payments/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    // Save as WebP and automatically push to Cloudinary
    $saveResult = save_image_as_webp($file, $uploadDir, 'sentec_uploads/payments', 82);

    if (!$saveResult['success']) {
        echo json_encode(['success' => false, 'error' => $saveResult['error'] ?: 'Failed to process and upload image']);
        exit();
    }

    $finalUrl = resolve_image_url($saveResult['path'], '../');

    echo json_encode([
        'success' => true,
        'url' => $finalUrl,
        'path' => $saveResult['path'],
        'fileName' => $file['name'],
        'isCloudinary' => (strpos($finalUrl, 'res.cloudinary.com') !== false)
    ]);
    exit();
}
