<?php
session_start();

// 1. SECURITY CHECK
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

// 2. CONNECT TO DATABASE
// Ensure we are pointing to the correct file location
require_once __DIR__ . '/../db_connection.php';

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
    'Event Teams Total',
    'Event Teams Approved',
    'Event Teams Pending',
    'Social Passes Total',
    'Social Passes Confirmed',
    'Total Impact (Event + Social + Manual)',
    'Manual Registration Credit',
    'Perk Threshold Override',
    'Effective Perk Threshold',
    'Perk Status',
    'Payments - Confirmed',
    'Payments - Pending',
    'Payments - No Proof',
    'Joined Date'
]);

// 4. PRE-FETCH AGGREGATE STATS (High Performance)
$eventStats = [];
$resE = $conn->query("SELECT brand_ambassador_code, status, COUNT(*) as c FROM event_registrations WHERE brand_ambassador_code IS NOT NULL AND brand_ambassador_code != '' GROUP BY brand_ambassador_code, status");
if ($resE) {
    while ($r = $resE->fetch_assoc()) {
        $cd = $r['brand_ambassador_code'];
        if (!isset($eventStats[$cd])) $eventStats[$cd] = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        $st = strtolower($r['status'] ?? '');
        $cnt = (int)$r['c'];
        $eventStats[$cd]['total'] += $cnt;
        if (isset($eventStats[$cd][$st])) $eventStats[$cd][$st] += $cnt;
        else $eventStats[$cd][$st] = $cnt;
    }
}

$socialStats = [];
$hasSocialTable = false;
try {
    $resS = $conn->query("SHOW TABLES LIKE 'social_registrations'");
    $hasSocialTable = $resS && $resS->num_rows > 0;
    if ($hasSocialTable) {
        $sq = $conn->query("SELECT ambassador_code, status, COUNT(*) as c FROM social_registrations WHERE ambassador_code IS NOT NULL AND ambassador_code != '' GROUP BY ambassador_code, status");
        if ($sq) {
            while ($r = $sq->fetch_assoc()) {
                $cd = $r['ambassador_code'];
                if (!isset($socialStats[$cd])) $socialStats[$cd] = ['total' => 0, 'approved' => 0, 'pending' => 0];
                $st = strtolower($r['status'] ?? '');
                $cnt = (int)$r['c'];
                $socialStats[$cd]['total'] += $cnt;
                if ($st === 'approved' || $st === 'confirmed') $socialStats[$cd]['approved'] += $cnt;
                else $socialStats[$cd]['pending'] += $cnt;
            }
        }
    }
} catch(Exception $e) {}

$paymentStats = [];
$resP = $conn->query("SELECT brand_ambassador_code,
    SUM(CASE WHEN payment_status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
    SUM(CASE WHEN payment_status = 'submitted' THEN 1 ELSE 0 END) AS submitted,
    SUM(CASE WHEN payment_status IS NULL OR payment_status = '' OR payment_status = 'pending' THEN 1 ELSE 0 END) AS not_submitted
    FROM event_registrations WHERE brand_ambassador_code IS NOT NULL AND brand_ambassador_code != '' GROUP BY brand_ambassador_code");
if ($resP) {
    while ($r = $resP->fetch_assoc()) {
        $cd = $r['brand_ambassador_code'];
        $paymentStats[$cd] = [
            'confirmed' => (int)($r['confirmed'] ?? 0),
            'submitted' => (int)($r['submitted'] ?? 0),
            'not_submitted' => (int)($r['not_submitted'] ?? 0)
        ];
    }
}

// 5. FETCH DATA
$query = "SELECT * FROM brand_ambassadors";
if ($typeFilter) {
    $query .= " WHERE ambassador_type = '" . $conn->real_escape_string($typeFilter) . "'";
}
$query .= " ORDER BY id DESC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $code = $row['code'];
        $ambType = strtolower($row['ambassador_type'] ?? 'brand');
        if (!in_array($ambType, ['volunteer','brand'], true)) { $ambType = 'brand'; }

        $e = $eventStats[$code] ?? ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        $s = $socialStats[$code] ?? ['total' => 0, 'approved' => 0, 'pending' => 0];
        $p = $paymentStats[$code] ?? ['confirmed' => 0, 'submitted' => 0, 'not_submitted' => 0];

        $manualCredit = $hasManualCredit ? max(0, (int)($row['manual_registration_credit'] ?? 0)) : 0;
        $perkOverride = $hasPerkOverride ? max(0, (int)($row['perk_threshold_override'] ?? 0)) : 0;

        $defaultThreshold = $defaultTypeThresholds[$ambType] ?? 5;
        foreach ([$ambType . '_perk_threshold', 'ambassador_perk_threshold_' . $ambType, 'ambassador_perk_threshold'] as $settingKey) {
            if (isset($typeSettings[$settingKey]) && $typeSettings[$settingKey] > 0) {
                $defaultThreshold = $typeSettings[$settingKey];
                break;
            }
        }
        $effectivePerkThreshold = $perkOverride > 0 ? $perkOverride : $defaultThreshold;
        $approvedWithManual = $e['approved'] + $s['approved'] + $manualCredit;
        $totalWithManual = $e['total'] + $s['total'] + $manualCredit;

        // Determine Perk Status
        $perkStatus = "In Progress";
        if (isset($row['perk_granted']) && $row['perk_granted'] == 1) {
            $perkStatus = "Granted";
        } elseif (isset($row['perk_requested']) && $row['perk_requested'] == 1) {
            $perkStatus = "Requested";
        } elseif ($approvedWithManual >= $effectivePerkThreshold) {
            $perkStatus = "Goal Met";
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
            $e['total'],
            $e['approved'],
            $e['pending'],
            $s['total'],
            $s['approved'],
            $totalWithManual,
            $manualCredit,
            $perkOverride,
            $effectivePerkThreshold,
            $perkStatus,
            $p['confirmed'],
            $p['submitted'],
            $p['not_submitted'],
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