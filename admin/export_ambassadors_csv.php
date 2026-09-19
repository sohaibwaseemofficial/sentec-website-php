<?php
session_start();

// 1. SECURITY CHECK
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

// 2. CONNECT TO DATABASE
// Ensure we are pointing to the correct file location
if (file_exists('../db_connection.php')) {
    include '../db_connection.php';
} else {
    die("Error: Database connection file not found.");
}

// 3. PREPARE CSV
// Clear any previous output to prevent corruption
if (ob_get_level()) ob_end_clean();

$requestedType = strtolower($_GET['type'] ?? '');
$typeFilter = in_array($requestedType, ['volunteer','brand'], true) ? $requestedType : '';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=ambassadors_report_' . date('Y-m-d_His') . '.csv');

$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Detect manual credit and custom perk threshold support for dynamic reporting
$hasManualCredit = false; $hasPerkOverride = false;
$defaultTypeThresholds = ['volunteer' => 2, 'brand' => 5];
$typeSettings = [];
try {
    $col = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'manual_registration_credit'");
    $hasManualCredit = $col && $col->num_rows > 0;
    $col2 = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'perk_threshold_override'");
    $hasPerkOverride = $col2 && $col2->num_rows > 0;
} catch (Exception $e) {}

try {
    $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $defaultSeeds = [
        'ambassador_perk_threshold' => '2',
        'volunteer_perk_threshold' => (string)$defaultTypeThresholds['volunteer'],
        'brand_perk_threshold' => (string)$defaultTypeThresholds['brand']
    ];
    foreach ($defaultSeeds as $key => $value) {
        $escapedKey = $conn->real_escape_string($key);
        $escapedVal = $conn->real_escape_string($value);
        $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('$escapedKey','$escapedVal')");
    }
    $settingsRes = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    if ($settingsRes) {
        while ($row = $settingsRes->fetch_assoc()) {
            $typeSettings[$row['setting_key']] = (int)$row['setting_value'];
        }
    }
} catch (Exception $e) {}

// CSV Headers
fputcsv($output, [
    'ID',
    'Name',
    'Email',
    'Phone',
    'Institution',
    'Ambassador Code',
    'Status',
    'Ambassador Type',
    'Total Teams Referred',
    'Pending Teams',
    'Approved Teams',
    'Manual Registration Credit',
    'Perk Threshold Override',
    'Effective Perk Threshold',
    'Approved Teams (With Manual)',
    'Total Teams (With Manual)',
    'Rejected Teams',
    'Payments - No Proof',
    'Payments - Awaiting Verification',
    'Payments - Confirmed',
    'Perk Status',
    'Joined Date'
]);

// 4. FETCH DATA
// We use a simple query first to ensure it works
$query = "SELECT * FROM brand_ambassadors";
if ($typeFilter) {
    $query .= " WHERE ambassador_type = '" . $conn->real_escape_string($typeFilter) . "'";
}
$query .= " ORDER BY id DESC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $code = $conn->real_escape_string($row['code']);
        
        // Calculate Stats for this Ambassador
        $stats = ['total'=>0, 'pending'=>0, 'approved'=>0, 'rejected'=>0];
        
        // Check if event_registrations table exists before querying it
        $checkTable = $conn->query("SHOW TABLES LIKE 'event_registrations'");
        $hasRegistrations = $checkTable && $checkTable->num_rows > 0;
        if ($hasRegistrations) {
            $statQuery = "SELECT status, COUNT(*) as count FROM event_registrations WHERE brand_ambassador_code = '$code' GROUP BY status";
            $statResult = $conn->query($statQuery);
            
            if ($statResult) {
                while ($statRow = $statResult->fetch_assoc()) {
                    $stats[$statRow['status']] = $statRow['count'];
                    $stats['total'] += $statRow['count'];
                }
            }
        }

        // Payment breakdown
        $paymentStats = ['not_submitted'=>0, 'submitted'=>0, 'confirmed'=>0];
        if ($hasRegistrations) {
            $paymentQuery = "SELECT 
                    SUM(CASE WHEN payment_status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
                    SUM(CASE WHEN payment_status = 'submitted' THEN 1 ELSE 0 END) AS submitted,
                    SUM(CASE WHEN payment_status IS NULL OR payment_status = '' OR payment_status = 'pending' THEN 1 ELSE 0 END) AS not_submitted
                FROM event_registrations WHERE brand_ambassador_code = '$code'";
            $paymentResult = $conn->query($paymentQuery);
            if ($paymentResult) {
                $paymentRow = $paymentResult->fetch_assoc();
                $paymentStats['confirmed'] = (int)($paymentRow['confirmed'] ?? 0);
                $paymentStats['submitted'] = (int)($paymentRow['submitted'] ?? 0);
                $paymentStats['not_submitted'] = (int)($paymentRow['not_submitted'] ?? 0);
            }
        }

        $manualCredit = $hasManualCredit ? (int)($row['manual_registration_credit'] ?? 0) : 0;
        $perkOverride = $hasPerkOverride ? (int)($row['perk_threshold_override'] ?? 0) : 0;
        $ambType = strtolower($row['ambassador_type'] ?? 'brand');
        if (!in_array($ambType, ['volunteer','brand'], true)) { $ambType = 'brand'; }
        $defaultThreshold = $defaultTypeThresholds[$ambType];
        foreach ([$ambType . '_perk_threshold', 'ambassador_perk_threshold_' . $ambType, 'ambassador_perk_threshold'] as $settingKey) {
            if (isset($typeSettings[$settingKey]) && $typeSettings[$settingKey] > 0) {
                $defaultThreshold = $typeSettings[$settingKey];
                break;
            }
        }
        $effectivePerkThreshold = $perkOverride > 0 ? $perkOverride : $defaultThreshold;
        $approvedWithManual = $stats['approved'] + $manualCredit;
        $totalWithManual = $stats['total'] + $manualCredit;

        // Determine Perk Status
        $perkStatus = "N/A";
        if (isset($row['perk_granted']) && $row['perk_granted'] == 1) {
            $perkStatus = "Granted";
        } elseif (isset($row['perk_requested']) && $row['perk_requested'] == 1) {
            $perkStatus = "Requested";
        }

        // Write Row
        fputcsv($output, [
            $row['id'],
            $row['name'],
            $row['email'],
            $row['phone'],
            $row['institution'],
            $row['code'],
            ucfirst($row['status']),
            ucfirst($ambType),
            $stats['total'],
            $stats['pending'],
            $stats['approved'],
            $manualCredit,
            $perkOverride,
            $effectivePerkThreshold,
            $approvedWithManual,
            $totalWithManual,
            $stats['rejected'],
            $paymentStats['not_submitted'],
            $paymentStats['submitted'],
            $paymentStats['confirmed'],
            $perkStatus,
            date('Y-m-d H:i', strtotime($row['created_at']))
        ]);
    }
} else {
    // If no data, write a message in the CSV so you know why
    if (!$result) {
        fputcsv($output, ["Error: Query failed - " . $conn->error]);
    } else {
        fputcsv($output, ["No ambassadors found in database."]);
    }
}

fclose($output);
$conn->close();
exit();
?>