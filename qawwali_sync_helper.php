<?php
/**
 * Qawwali Sync Database Helper
 * Handles querying existing entries and inserting new ones with attendee sync.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/social_attendees_helper.php';

$action = $argv[1] ?? '';

if ($action === 'get_existing') {
    $existing = [
        'emails' => [],
        'proofs' => []
    ];
    
    $res = $conn->query("SELECT LOWER(TRIM(email)) AS email, payment_proof FROM social_registrations");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if (!empty($r['email'])) {
                $existing['emails'][] = $r['email'];
            }
            if (!empty($r['payment_proof'])) {
                $existing['proofs'][] = $r['payment_proof'];
            }
        }
    }
    echo json_encode($existing);
    exit(0);
}

if ($action === 'insert_batch') {
    $inputFile = $argv[2] ?? '';
    if (!file_exists($inputFile)) {
        echo json_encode(['error' => 'Input file not found']);
        exit(1);
    }
    
    $records = json_decode(file_get_contents($inputFile), true);
    if (!is_array($records)) {
        echo json_encode(['error' => 'Invalid JSON']);
        exit(1);
    }
    
    $userStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $insertSql = "INSERT INTO social_registrations 
        (user_id, full_name, email, phone, cnic, face_image, id_card_image, payment_proof, payment_status, 
         registration_type, ambassador_code, total_amount,
         participant2_name, participant2_email, participant2_phone, participant2_cnic, participant2_face, participant2_card,
         participant3_name, participant3_email, participant3_phone, participant3_cnic, participant3_face, participant3_card,
         event_label) 
        VALUES (?,?,?,?,?,?,?,?, 'submitted', ?,?,?, ?,?,?,?,?,?, ?,?,?,?,?,?, ?)";
    $insertStmt = $conn->prepare($insertSql);
    
    $results = [];
    foreach ($records as $r) {
        $name = trim($r['name'] ?? '');
        $email = strtolower(trim($r['email'] ?? ''));
        $phone = trim($r['phone'] ?? '');
        $roll = trim($r['roll'] ?? '');
        $cnic = !empty($roll) ? $roll : 'N/A';
        $face = !empty($r['face_path']) ? $r['face_path'] : null;
        $card = !empty($r['card_path']) ? $r['card_path'] : null;
        $payProof = $r['saved_path'] ?? '';
        $reg_type = $r['reg_type'] ?? 'standard';
        $amb_code = 'GOOGLE_FORM';
        $amount = (int)($r['amount'] ?? 500);
        
        $user_id = null;
        $userStmt->bind_param('s', $email);
        $userStmt->execute();
        $uRes = $userStmt->get_result()->fetch_assoc();
        if ($uRes) {
            $user_id = (int)$uRes['id'];
        }
        
        $p2_name = null; $p2_email = null; $p2_phone = null; $p2_cnic = null; $p2_face = null; $p2_card = null;
        $p3_name = null; $p3_email = null; $p3_phone = null; $p3_cnic = null; $p3_face = null; $p3_card = null;
        $activeEventLabel = 'RUH-E-RAQS';
        
        $types = 'isssssssssisssssssssssss';
        $insertStmt->bind_param($types,
            $user_id,
            $name,
            $email,
            $phone,
            $cnic,
            $face,
            $card,
            $payProof,
            $reg_type,
            $amb_code,
            $amount,
            $p2_name, $p2_email, $p2_phone, $p2_cnic, $p2_face, $p2_card,
            $p3_name, $p3_email, $p3_phone, $p3_cnic, $p3_face, $p3_card,
            $activeEventLabel
        );
        
        if ($insertStmt->execute()) {
            $newId = $conn->insert_id;
            $memberPayload = [
                [
                    'label' => 'Person 1',
                    'name'  => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'cnic'  => $cnic,
                    'face'  => $face,
                    'card'  => $card
                ]
            ];
            social_attendees_sync($conn, $newId, $memberPayload, $payProof, 'submitted');
            $results[] = ['success' => true, 'id' => $newId, 'name' => $name, 'email' => $email];
        } else {
            $results[] = ['success' => false, 'error' => $insertStmt->error, 'name' => $name, 'email' => $email];
        }
    }
    
    echo json_encode($results);
    exit(0);
}

echo json_encode(['error' => 'Unknown action']);
