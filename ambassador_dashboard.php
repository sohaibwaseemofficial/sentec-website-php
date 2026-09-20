<?php
include 'header.php';
include 'db_connection.php';

// 1. SECURITY: Redirect if not an Ambassador
if (!isset($_SESSION['ambassador_id'])) {
    echo "<script>window.location.href='ambassador_login.php';</script>";
    exit;
}

$amb_id = $_SESSION['ambassador_id'];
$amb_code = $_SESSION['ambassador_code'];
$amb_name = $_SESSION['ambassador_name'];

// 2. INITIALIZE STATS
$stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$paymentStats = ['not_submitted' => 0, 'submitted' => 0, 'confirmed' => 0];

// --- A. FETCH EVENT REGISTRATIONS STATS (SECURED) ---
$e_query = "SELECT status, COUNT(*) as count FROM event_registrations WHERE brand_ambassador_code = ? GROUP BY status";
if ($stmt = $conn->prepare($e_query)) {
    $stmt->bind_param("s", $amb_code);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $st = strtolower($row['status']);
        if(isset($stats[$st])) $stats[$st] += $row['count'];
        $stats['total'] += $row['count'];
    }
    $stmt->close();
}

// Payment Stats (Events) - SECURED
$e_paySql = "SELECT 
    SUM(CASE WHEN payment_status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
    SUM(CASE WHEN payment_status = 'submitted' THEN 1 ELSE 0 END) AS submitted,
    SUM(CASE WHEN payment_status IS NULL OR payment_status = '' OR payment_status = 'pending' THEN 1 ELSE 0 END) AS not_submitted
FROM event_registrations WHERE brand_ambassador_code = ?";
if ($stmt = $conn->prepare($e_paySql)) {
    $stmt->bind_param("s", $amb_code);
    $stmt->execute();
    if ($payRow = $stmt->get_result()->fetch_assoc()) {
        $paymentStats['confirmed'] += (int)($payRow['confirmed'] ?? 0);
        $paymentStats['submitted'] += (int)($payRow['submitted'] ?? 0);
        $paymentStats['not_submitted'] += (int)($payRow['not_submitted'] ?? 0);
    }
    $stmt->close();
}

// --- B. FETCH SOCIAL REGISTRATIONS STATS (SECURED) ---
$hasSocialTable = $conn->query("SHOW TABLES LIKE 'social_registrations'")->num_rows > 0;

if ($hasSocialTable) {
    // Status Stats (Social)
    $s_query = "SELECT status, COUNT(*) as count FROM social_registrations WHERE ambassador_code = ? GROUP BY status";
    if ($stmt = $conn->prepare($s_query)) {
        $stmt->bind_param("s", $amb_code);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $st = strtolower($row['status']);
            if(isset($stats[$st])) $stats[$st] += $row['count'];
            $stats['total'] += $row['count'];
        }
        $stmt->close();
    }

    // Payment Stats (Social) - SECURED
    $s_paySql = "SELECT 
        SUM(CASE WHEN payment_status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
        SUM(CASE WHEN payment_status = 'submitted' THEN 1 ELSE 0 END) AS submitted,
        SUM(CASE WHEN payment_status IS NULL OR payment_status = '' OR payment_status = 'pending' THEN 1 ELSE 0 END) AS not_submitted
    FROM social_registrations WHERE ambassador_code = ?";
    if ($stmt = $conn->prepare($s_paySql)) {
        $stmt->bind_param("s", $amb_code);
        $stmt->execute();
        if ($payRow = $stmt->get_result()->fetch_assoc()) {
            $paymentStats['confirmed'] += (int)($payRow['confirmed'] ?? 0);
            $paymentStats['submitted'] += (int)($payRow['submitted'] ?? 0);
            $paymentStats['not_submitted'] += (int)($payRow['not_submitted'] ?? 0);
        }
        $stmt->close();
    }
}

// --- C. PERK & MANUAL CREDIT LOGIC ---
$manualCredit = 0;
$perkOverride = 0;
$validAmbassadorTypes = ['volunteer' => 2, 'brand' => 5];
$ambType = strtolower($_SESSION['ambassador_type'] ?? '');
if (!array_key_exists($ambType, $validAmbassadorTypes)) {
    $ambType = 'brand';
}
$perkThreshold = $validAmbassadorTypes[$ambType];

try {
    // Check for manual credit columns in brand_ambassadors table
    $cols = $conn->query("SHOW COLUMNS FROM brand_ambassadors");
    $existingCols = [];
    while($c = $cols->fetch_assoc()) { $existingCols[] = $c['Field']; }

    $selectParts = [];
    if (in_array('manual_registration_credit', $existingCols)) $selectParts[] = 'manual_registration_credit';
    if (in_array('perk_threshold_override', $existingCols)) $selectParts[] = 'perk_threshold_override';
    if (in_array('ambassador_type', $existingCols)) $selectParts[] = 'ambassador_type';

    if (!empty($selectParts)) {
        $fields = implode(', ', $selectParts);
        $stmt = $conn->prepare("SELECT $fields FROM brand_ambassadors WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $amb_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $manualCredit = max(0, (int)($row['manual_registration_credit'] ?? 0));
            $perkOverride = max(0, (int)($row['perk_threshold_override'] ?? 0));
            if (isset($row['ambassador_type'])) {
                $dbType = strtolower($row['ambassador_type']);
                if (array_key_exists($dbType, $validAmbassadorTypes)) {
                    $ambType = $dbType;
                    $_SESSION['ambassador_type'] = $ambType;
                }
            }
        }
        $stmt->close();
    }
    
    // Check System Settings for Thresholds
    $conn->query("CREATE TABLE IF NOT EXISTS system_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value VARCHAR(255) NOT NULL)");
    $settingKey = $ambType . '_perk_threshold';
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key=? LIMIT 1");
    if($stmt) {
        $stmt->bind_param('s', $settingKey);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if ((int)$row['setting_value'] > 0) $perkThreshold = (int)$row['setting_value'];
        }
        $stmt->close();
    }
    
    if ($perkOverride > 0) $perkThreshold = $perkOverride;

} catch (Exception $e) {}

$totalWithManual = $stats['total'] + $manualCredit;
$approvedWithManual = $stats['approved'] + $manualCredit;

// --- D. FETCH RECENT REGISTRATIONS (MERGED) - SECURED ---
$recent_regs = [];

// 1. Fetch Events - SECURED
$e_list_sql = "SELECT team_name as name, institution_type as institution, module_selection as category, created_at, status, payment_status, 'Competition' as type 
               FROM event_registrations WHERE brand_ambassador_code = ? ORDER BY created_at DESC LIMIT 10";
if ($stmt = $conn->prepare($e_list_sql)) {
    $stmt->bind_param("s", $amb_code);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $recent_regs[] = $row;
    $stmt->close();
}

// 2. Fetch Socials - SECURED
if ($hasSocialTable) {
    $s_list_sql = "SELECT full_name as name, 'Social Event' as institution, CONCAT('Pass: ', registration_type) as category, created_at, status, payment_status, 'Social' as type 
                   FROM social_registrations WHERE ambassador_code = ? ORDER BY created_at DESC LIMIT 10";
    if ($stmt = $conn->prepare($s_list_sql)) {
        $stmt->bind_param("s", $amb_code);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $recent_regs[] = $row;
        $stmt->close();
    }
}

// 3. Sort & Slice
usort($recent_regs, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$recent_regs = array_slice($recent_regs, 0, 10);
?>

<style>
    /* Dashboard Specific Styles */
    .welcome-banner {
        background: linear-gradient(135deg, rgba(0, 255, 148, 0.1), rgba(0,0,0,0));
        border: 1px solid #00FF94;
        border-radius: 20px;
        padding: 40px;
        margin-bottom: 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .code-badge {
        background: rgba(0, 255, 148, 0.05);
        border: 1px dashed #00FF94;
        color: #fff;
        padding: 10px 20px;
        border-radius: 10px;
        cursor: pointer;
        font-family: monospace;
        font-size: 1.2rem;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    .code-badge:hover { background: #00FF94; color: #000; }

    .stat-box {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 15px;
        padding: 25px;
        text-align: center;
        transition: 0.3s;
    }
    .stat-box:hover { border-color: rgba(0,255,148,0.3); transform: translateY(-3px); }
    .stat-box h3 { font-size: 2.5rem; margin: 0; font-weight: 700; color: #fff; }
    .stat-box p { color: #aaa; margin: 0; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }
    
    .accent-theme { color: #00FF94 !important; }

    .payment-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 18px;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.85rem;
        letter-spacing: 0.4px;
        text-transform: uppercase;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.08);
        color: #e5e7ff;
        box-shadow: 0 18px 35px rgba(0,0,0,0.35);
    }
    .payment-chip[data-variant="not-submitted"] { border-color: rgba(241, 90, 36, 0.5); color: #f15a24; }
    .payment-chip[data-variant="submitted"] { border-color: rgba(0, 173, 255, 0.5); color: #00c3ff; }
    .payment-chip[data-variant="confirmed"] { border-color: rgba(0, 255, 148, 0.6); color: #00ff94; }
    .payment-chip span { font-size: 1.2rem; font-weight: 700; margin-left: 8px; }

    @media (max-width: 768px) {
        .welcome-banner { padding: 24px 18px; border-radius: 16px; }
        .welcome-banner h1 { font-size: 1.6rem; }
        .code-badge { font-size: 1rem; padding: 8px 14px; }
        .stat-box { padding: 18px 14px; }
        .payment-chip { width: 100%; justify-content: space-between; padding: 10px 16px; }
    }
</style>

<section class="ambassador-dashboard-wrapper" style="padding-top: 120px; padding-bottom: 70px; min-height: 100vh;">
    <div class="container">
        
        <div class="welcome-banner">
            <div>
                <h1 class="text-white mb-2">Welcome, <span class="accent-theme"><?php echo htmlspecialchars($amb_name); ?></span></h1>
                <p class="text-muted mb-0">Track your impact for both Competitions & Social Night</p>
            </div>
            <div class="code-badge" onclick="copyToClipboard('<?php echo htmlspecialchars($amb_code); ?>', 'Referral Code Copied!')">
                <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($amb_code); ?> <i class="far fa-copy ms-2"></i>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-3"><div class="stat-box"><h3><?php echo $totalWithManual; ?></h3><p>Total Referrals</p></div></div>
            <div class="col-md-3"><div class="stat-box"><h3 style="color:#f15a24;"><?php echo $stats['pending']; ?></h3><p>Pending</p></div></div>
            <div class="col-md-3"><div class="stat-box"><h3 style="color:#00FF94;"><?php echo $approvedWithManual; ?></h3><p>Approved</p></div></div>
            <div class="col-md-3"><div class="stat-box"><h3 style="color:#ff4444;"><?php echo $stats['rejected']; ?></h3><p>Rejected</p></div></div>
        </div>

        <div class="glass-panel mb-4" style="background: rgba(4, 9, 20, 0.75);">
            <h5 class="text-white mb-3"><i class="fas fa-credit-card accent-theme me-2"></i> Payment Progress</h5>
            <div class="d-flex flex-wrap gap-3">
                <div class="payment-chip" data-variant="not-submitted">
                    Not Submitted
                    <span><?php echo $paymentStats['not_submitted']; ?></span>
                </div>
                <div class="payment-chip" data-variant="submitted">
                    Awaiting Verification
                    <span><?php echo $paymentStats['submitted']; ?></span>
                </div>
                <div class="payment-chip" data-variant="confirmed">
                    Confirmed &amp; Paid
                    <span><?php echo $paymentStats['confirmed']; ?></span>
                </div>
            </div>
        </div>

        <div class="glass-panel mb-5" style="border-left: 4px solid #00FF94;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="text-white mb-2"><i class="fas fa-gift accent-theme me-2"></i> DataCamp Premium Perk</h4>
                    <p style="color: #ddd; font-size: 1.1rem; margin-bottom: 0;">Get <?php echo $perkThreshold; ?> approved registrations to earn a premium account.</p>
                </div>
                <div class="text-end">
                    <?php if ($approvedWithManual >= $perkThreshold): ?>
                        <span class="badge bg-success p-2" style="font-size: 0.9rem; background-color: #00FF94 !important; color: #000;">Goal Met! Contact Admin</span>
                    <?php else: ?>
                        <span class="badge bg-secondary p-2" style="font-size: 0.9rem;">Progress: <?php echo $approvedWithManual; ?>/<?php echo $perkThreshold; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($manualCredit > 0): ?>
                <p class="mt-3 mb-0" style="color:#9da5c2; font-size:0.9rem;">Includes a manual bonus of <?php echo $manualCredit; ?> credits granted by admin.</p>
            <?php endif; ?>
        </div>

        <div class="glass-panel">
            <h4 class="text-white mb-4">Recent Registrations (Event & Social)</h4>
            <div class="table-responsive">
                <table class="table table-dark table-hover" style="background: transparent;">
                    <thead>
                        <tr style="color: #fff; font-size: 0.9rem; border-bottom: 1px solid #333;">
                            <th>Name / Team</th>
                            <th>Category / Inst</th>
                            <th>Detail</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_regs)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No registrations found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_regs as $reg): 
                                $statusColor = ($reg['status'] == 'approved' || $reg['status'] == 'confirmed') ? '#00ff94' : (($reg['status']=='rejected') ? '#ff4444' : '#ffbb33');
                                $payLabel = 'Not Submitted';
                                $payColor = '#8891a7';
                                if ($reg['payment_status'] === 'submitted') {
                                    $payLabel = 'Awaiting Verification';
                                    $payColor = '#2ecfff';
                                } elseif ($reg['payment_status'] === 'confirmed') {
                                    $payLabel = 'Confirmed';
                                    $payColor = '#00ff94';
                                }
                                
                                $catBadge = ($reg['type'] == 'Social') ? 'badge bg-warning text-dark' : 'badge bg-info text-dark';
                            ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td class="text-white fw-bold py-3"><?php echo htmlspecialchars($reg['name']); ?></td>
                                    <td style="color: #ccc;">
                                        <span class="<?php echo $catBadge; ?>" style="font-size:0.7rem; margin-right:5px;"><?php echo htmlspecialchars($reg['type']); ?></span>
                                        <?php echo htmlspecialchars($reg['institution']); ?>
                                    </td>
                                    <td style="color: #ccc;"><?php echo htmlspecialchars($reg['category']); ?></td>
                                    <td style="color: #ccc;"><?php echo date('M d', strtotime($reg['created_at'])); ?></td>
                                    <td style="color:<?php echo $statusColor; ?>; text-transform:uppercase; font-weight:bold; font-size:0.8rem;">
                                        <?php echo htmlspecialchars($reg['status']); ?>
                                    </td>
                                    <td style="color: <?php echo $payColor; ?>; font-weight: 600; font-size: 0.85rem;">
                                        <?php echo $payLabel; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</section>

<script>
    function copyToClipboard(text, msg) {
        navigator.clipboard.writeText(text).then(() => {
            alert(msg);
        }).catch(err => {
            console.error('Failed to copy: ', err);
        });
    }
</script>

<?php include 'footer.php'; ?>