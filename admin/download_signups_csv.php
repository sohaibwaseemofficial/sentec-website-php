<?php
session_start();

// 1. SECURITY CHECK
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

// 2. CONNECT TO DATABASE
// Use the correct path to db_connection.php
if (file_exists('../db_connection.php')) {
    include '../db_connection.php';
} else {
    die("Error: Database connection file not found.");
}

// 3. CLEAR BUFFER (Crucial for CSVs to work)
if (ob_get_level()) ob_end_clean();

// 4. SET HEADERS
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=portal_signups_' . date('Y-m-d_His') . '.csv');

// 5. OPEN OUTPUT STREAM
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// 6. WRITE COLUMN HEADERS
fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Institution', 'Verified', 'Joined Date']);

// 7. FETCH & WRITE DATA
$sql = "SELECT * FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $isVerified = ($row['is_verified'] == 1) ? 'Yes' : 'Pending';
        $date = date('Y-m-d H:i', strtotime($row['created_at']));
        
        fputcsv($output, [
            $row['id'],
            $row['full_name'],
            $row['email'],
            $row['phone'] ?? '',     // Use null coalescing in case column missing
            $row['institution'] ?? '', // Use null coalescing
            $isVerified,
            $date
        ]);
    }
} else {
    fputcsv($output, ['No signups found']);
}

fclose($output);
$conn->close();
exit();
?>
