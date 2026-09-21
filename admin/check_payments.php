<?php
session_start();
if (!isset($_SESSION['admin'])) {
    die("Unauthorized - Please login to admin panel first");
}

include '../db_connection.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Payment Status Check</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { background: #0a0a0a; color: #fff; padding: 30px; font-family: 'Outfit', sans-serif; }
        table { background: #111; border-radius: 12px; overflow: hidden; }
        th { background: #00FF94; color: #000; padding: 12px; }
        td { padding: 10px; border-bottom: 1px solid #333; }
        .btn-fix { background: #00FF94; color: #000; padding: 5px 15px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .btn-fix:hover { background: #00cc7a; }
        .status-submitted { background: #ffbb33; color: #000; padding: 3px 10px; border-radius: 4px; font-weight: bold; }
        .status-confirmed { background: #00FF94; color: #000; padding: 3px 10px; border-radius: 4px; font-weight: bold; }
        .status-null { background: #ff4444; color: #fff; padding: 3px 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class='container'>
        <h2>🔍 Payment Status Check</h2>
        <p class='text-muted'>Registrations with payment proof uploaded</p>";

// Get all registrations with payment proof
$sql = "SELECT id, team_name, payment_status, payment_proof 
        FROM event_registrations 
        WHERE payment_proof IS NOT NULL 
        AND payment_proof != '' 
        AND payment_proof != 'Not Collected'
        ORDER BY id DESC 
        LIMIT 30";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table class='table table-dark'>
        <thead>
            <tr>
                <th>ID</th>
                <th>Team Name</th>
                <th>Payment Status</th>
                <th>Proof</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>";
    
    while ($row = $result->fetch_assoc()) {
        $status = $row['payment_status'] ?? '';
        $statusClass = '';
        $statusText = $status ?: 'NULL/EMPTY';
        
        if ($status == 'submitted') {
            $statusClass = 'status-submitted';
        } elseif ($status == 'confirmed') {
            $statusClass = 'status-confirmed';
        } elseif (empty($status)) {
            $statusClass = 'status-null';
        }
        
        echo "<tr>
            <td>#" . $row['id'] . "</td>
            <td>" . htmlspecialchars($row['team_name']) . "</td>
            <td><span class='$statusClass'>" . $statusText . "</span></td>
            <td>";
        
        if (!empty($row['payment_proof'])) {
            echo "<a href='../" . htmlspecialchars($row['payment_proof']) . "' target='_blank'>View</a>";
        } else {
            echo "—";
        }
        
        echo "</td>
            <td>";
        
        // Show fix button if status is empty or not 'submitted'/'confirmed'
        if (empty($status) || ($status != 'submitted' && $status != 'confirmed')) {
            echo "<a href='fix_payment.php?id=" . $row['id'] . "' class='btn-fix'>Fix Status</a>";
        } else {
            echo "✓ OK";
        }
        
        echo "</td>
        </tr>";
    }
    
    echo "</tbody></table>";
} else {
    echo "<div class='alert alert-info'>No registrations with payment proof found.</div>";
}

// Show bulk fix option
echo "<hr style='border-color:#333; margin:30px 0;'>
    <h3>🔧 Bulk Fix</h3>
    <p>Click below to fix ALL payment statuses at once (sets empty status to 'submitted')</p>
    <a href='fix_payment?bulk=1' class='btn btn-warning' onclick='return confirm(\"Fix ALL payment statuses?\")'>Bulk Fix All</a>
    
    <hr style='border-color:#333; margin:30px 0;'>
    <p><a href='manage_registrations' class='btn btn-outline-light'>← Back to Registrations</a></p>
    </div>
</body>
</html>";

$conn->close();
?>