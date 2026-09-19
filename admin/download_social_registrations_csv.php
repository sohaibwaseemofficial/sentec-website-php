<?php
session_start();

// Require admin session
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

include '../db_connection.php';
require_once __DIR__ . '/../social_attendees_helper.php';

// Clear any buffered output for clean CSV download
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=social_registrations_' . date('Y-m-d_His') . '.csv');

$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

$baseUrl = 'https://sentec.live/';
$typeLabels = [
    'standard' => 'Individual',
    'participant' => 'Event Participant',
    'group' => 'Group (3 People)'
];

$assetUrl = function ($path) use ($baseUrl) {
    if (empty($path)) {
        return '';
    }
    return $baseUrl . ltrim($path, '/');
};

$hasAttendeesTable = social_attendees_table_exists($conn);

if ($hasAttendeesTable) {
    $headers = [
        'Registration ID', 'Form Type', 'Ambassador Code', 'Total Amount',
        'Payment Status', 'Registration Status', 'Attendance Status', 'Created At',
        'Registrant Name', 'Registrant Email', 'Registrant Phone', 'Registrant CNIC',
        'Attendee Label', 'Attendee Name', 'Attendee Email', 'Attendee Phone', 'Attendee CNIC',
        'Attendee Status', 'Attendee Attendance', 'Face Image URL', 'ID Card URL', 'Payment Proof URL'
    ];
    fputcsv($output, $headers);

    $sql = "SELECT sr.id, sr.registration_type, sr.ambassador_code, sr.total_amount, sr.payment_status, sr.status, sr.attendance_status, sr.created_at,
                   sr.full_name, sr.email, sr.phone, sr.cnic, sr.payment_proof,
                   sa.label, sa.full_name AS attendee_name, sa.email AS attendee_email, sa.phone AS attendee_phone,
                   sa.cnic AS attendee_cnic, sa.status AS attendee_status, sa.attendance_status AS attendee_attendance,
                   sa.face_image, sa.id_card_image
            FROM social_registrations sr
            JOIN social_attendees sa ON sa.registration_id = sr.id
            ORDER BY sr.created_at DESC, sa.person_index ASC";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $typeCode = $row['registration_type'] ?? '';
            $typeLabel = $typeLabels[$typeCode] ?? ($typeCode ? ucfirst($typeCode) : '');

            fputcsv($output, [
                $row['id'],
                $typeLabel,
                $row['ambassador_code'] ?? '',
                $row['total_amount'] ?? '',
                $row['payment_status'] ?? '',
                $row['status'] ?? '',
                $row['attendance_status'] ?? '',
                $row['created_at'] ?? '',
                $row['full_name'] ?? '',
                $row['email'] ?? '',
                $row['phone'] ?? '',
                $row['cnic'] ?? '',
                $row['label'] ?? '',
                $row['attendee_name'] ?? '',
                $row['attendee_email'] ?? '',
                $row['attendee_phone'] ?? '',
                $row['attendee_cnic'] ?? '',
                $row['attendee_status'] ?? '',
                $row['attendee_attendance'] ?? '',
                $assetUrl($row['face_image'] ?? ''),
                $assetUrl($row['id_card_image'] ?? ''),
                $assetUrl($row['payment_proof'] ?? '')
            ]);
        }
    } else {
        fputcsv($output, ['No social registrations found']);
    }
} else {
    $headers = [
        'Registration ID', 'Form Type', 'Ambassador Code', 'Total Amount',
        'Payment Status', 'Registration Status', 'Attendance Status', 'Created At',
        'Primary Name', 'Primary Email', 'Primary Phone', 'Primary CNIC', 'Primary Face URL', 'Primary ID URL',
        'Participant 2 Name', 'Participant 2 Email', 'Participant 2 Phone', 'Participant 2 CNIC', 'Participant 2 Face URL', 'Participant 2 ID URL',
        'Participant 3 Name', 'Participant 3 Email', 'Participant 3 Phone', 'Participant 3 CNIC', 'Participant 3 Face URL', 'Participant 3 ID URL',
        'Payment Proof URL'
    ];
    fputcsv($output, $headers);

    $sql = "SELECT * FROM social_registrations ORDER BY created_at DESC";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $typeCode = $row['registration_type'] ?? '';
            $typeLabel = $typeLabels[$typeCode] ?? ($typeCode ? ucfirst($typeCode) : '');

            fputcsv($output, [
                $row['id'],
                $typeLabel,
                $row['ambassador_code'] ?? '',
                $row['total_amount'] ?? '',
                $row['payment_status'] ?? '',
                $row['status'] ?? '',
                $row['attendance_status'] ?? '',
                $row['created_at'] ?? '',
                $row['full_name'] ?? '',
                $row['email'] ?? '',
                $row['phone'] ?? '',
                $row['cnic'] ?? '',
                $assetUrl($row['face_image'] ?? ''),
                $assetUrl($row['id_card_image'] ?? ''),
                $row['participant2_name'] ?? '',
                $row['participant2_email'] ?? '',
                $row['participant2_phone'] ?? '',
                $row['participant2_cnic'] ?? '',
                $assetUrl($row['participant2_face'] ?? ''),
                $assetUrl($row['participant2_card'] ?? ''),
                $row['participant3_name'] ?? '',
                $row['participant3_email'] ?? '',
                $row['participant3_phone'] ?? '',
                $row['participant3_cnic'] ?? '',
                $assetUrl($row['participant3_face'] ?? ''),
                $assetUrl($row['participant3_card'] ?? ''),
                $assetUrl($row['payment_proof'] ?? '')
            ]);
        }
    } else {
        fputcsv($output, ['No social registrations found']);
    }
}

fclose($output);
$conn->close();
exit();
?>
