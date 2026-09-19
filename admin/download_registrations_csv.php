<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

include 'db_connection.php';

// Fetch all registrations
$sql = "SELECT * FROM event_registrations ORDER BY created_at DESC";
$result = $conn->query($sql);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=event_registrations_' . date('Y-m-d_His') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Headers
$headers = [
    'ID',
    'Institution Type',
    'Team Name',
    'Module Selection',
    'Brand Ambassador Code',
    'Fees Screenshot URL',
    
    'Participant 1 - Name',
    'Participant 1 - Contact',
    'Participant 1 - Email',
    'Participant 1 - CNIC',
    'Participant 1 - Roll Number',
    'Participant 1 - Face Image URL',
    'Participant 1 - ID Card URL',
    
    'Participant 2 - Name',
    'Participant 2 - Contact',
    'Participant 2 - Email',
    'Participant 2 - CNIC',
    'Participant 2 - Roll Number',
    'Participant 2 - Face Image URL',
    'Participant 2 - ID Card URL',
    
    'Participant 3 - Name',
    'Participant 3 - Contact',
    'Participant 3 - Email',
    'Participant 3 - CNIC',
    'Participant 3 - Roll Number',
    'Participant 3 - Face Image URL',
    'Participant 3 - ID Card URL',
    
    'Participant 4 - Name',
    'Participant 4 - Contact',
    'Participant 4 - Email',
    'Participant 4 - CNIC',
    'Participant 4 - Roll Number',
    'Participant 4 - Face Image URL',
    'Participant 4 - ID Card URL',
    
    'Status',
    'Submitted Date',
    'Last Updated'
];

fputcsv($output, $headers);

// Add data rows
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $csvRow = [
            $row['id'],
            $row['institution_type'],
            $row['team_name'],
            $row['module_selection'],
            $row['brand_ambassador_code'] ?? '',
            'https://sentec.live/' . $row['fees_screenshot'],
            
            $row['participant1_name'],
            $row['participant1_contact'],
            $row['participant1_email'],
            $row['participant1_cnic'],
            $row['participant1_roll_number'],
            'https://sentec.live/' . $row['participant1_face_image'],
            'https://sentec.live/' . $row['participant1_id_card'],
            
            $row['participant2_name'] ?? '',
            $row['participant2_contact'] ?? '',
            $row['participant2_email'] ?? '',
            $row['participant2_cnic'] ?? '',
            $row['participant2_roll_number'] ?? '',
            $row['participant2_face_image'] ? 'https://sentec.live/' . $row['participant2_face_image'] : '',
            $row['participant2_id_card'] ? 'https://sentec.live/' . $row['participant2_id_card'] : '',
            
            $row['participant3_name'] ?? '',
            $row['participant3_contact'] ?? '',
            $row['participant3_email'] ?? '',
            $row['participant3_cnic'] ?? '',
            $row['participant3_roll_number'] ?? '',
            $row['participant3_face_image'] ? 'https://sentec.live/' . $row['participant3_face_image'] : '',
            $row['participant3_id_card'] ? 'https://sentec.live/' . $row['participant3_id_card'] : '',
            
            $row['participant4_name'] ?? '',
            $row['participant4_contact'] ?? '',
            $row['participant4_email'] ?? '',
            $row['participant4_cnic'] ?? '',
            $row['participant4_roll_number'] ?? '',
            $row['participant4_face_image'] ? 'https://sentec.live/' . $row['participant4_face_image'] : '',
            $row['participant4_id_card'] ? 'https://sentec.live/' . $row['participant4_id_card'] : '',
            
            ucfirst($row['status']),
            date('F j, Y, g:i A', strtotime($row['created_at'])),
            date('F j, Y, g:i A', strtotime($row['updated_at']))
        ];
        
        fputcsv($output, $csvRow);
    }
}

fclose($output);
$conn->close();
exit();
?>
