<?php
session_start();
if (!isset($_SESSION['admin'])) {
    die("Unauthorized");
}

include '../db_connection.php';

$id = intval($_GET['id'] ?? 0);
$bulk = isset($_GET['bulk']) && $_GET['bulk'] == 1;

if ($bulk) {
    // Bulk fix - update all empty payment_status to 'submitted'
    $sql = "UPDATE event_registrations 
            SET payment_status = 'submitted' 
            WHERE payment_proof IS NOT NULL 
            AND payment_proof != '' 
            AND payment_proof != 'Not Collected'
            AND (payment_status IS NULL OR payment_status = '' OR payment_status = 'pending')";
    
    if ($conn->query($sql)) {
        $affected = $conn->affected_rows;
        echo "<div style='background:#00FF94; color:#000; padding:20px; font-family:sans-serif; text-align:center;'>
            <h2>✅ Bulk Fix Complete!</h2>
            <p>$affected payment status(es) updated to 'submitted'.</p>
            <a href='check_payments'>Back to List</a>
        </div>";
    } else {
        echo "Error: " . $conn->error;
    }
} elseif ($id > 0) {
    // Single fix
    $stmt = $conn->prepare("UPDATE event_registrations SET payment_status = 'submitted' WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo "<div style='background:#00FF94; color:#000; padding:20px; font-family:sans-serif; text-align:center;'>
            <h2>✅ Payment Status Fixed!</h2>
            <p>Registration #$id updated to 'submitted'.</p>
            <a href='check_payments'>Back to List</a>
        </div>";
    } else {
        echo "Error: " . $conn->error;
    }
    $stmt->close();
} else {
    echo "Invalid request.";
}

$conn->close();
?>