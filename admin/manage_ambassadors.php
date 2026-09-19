<?php
include 'header.php';
include '../db_connection.php';

// Determine ambassador type context
$validTypes = ['volunteer' => 'Volunteer Ambassadors', 'brand' => 'Brand Ambassadors'];
$type = strtolower($_GET['type'] ?? '');
if (!array_key_exists($type, $validTypes)) {
    $type = 'volunteer';
}
$typeLabel = $validTypes[$type];
$typeDescription = $type === 'brand'
    ? 'Track performance and perks for campus brand partners.'
    : 'Manage volunteer leads working on ground outreach.';
$defaultThresholds = ['volunteer' => 2, 'brand' => 5];
$perkThreshold = $defaultThresholds[$type];

// Check for table existence
$hasTable = false;
$hasPwd = true;
$hasInitialPwd = false; 
$hasPerk = false;
$hasManualCredit = false;
$hasPerkOverride = false;
$hasTypeColumn = false;

// Check for Social Table
$hasSocialTable = false;
try {
    $resS = $conn->query("SHOW TABLES LIKE 'social_registrations'");
    $hasSocialTable = $resS && $resS->num_rows > 0;
} catch (Exception $e) {}

try {
    $res = $conn->query("SHOW TABLES LIKE 'brand_ambassadors'");
    $hasTable = $res && $res->num_rows > 0;
    if ($hasTable) {
        $cols = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'password_hash'");
        $hasPwd = $cols && $cols->num_rows > 0;
        $cols2 = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'initial_password'");
        $hasInitialPwd = $cols2 && $cols2->num_rows > 0;
        $cols3 = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'perk_requested'");
        $hasPerk = $cols3 && $cols3->num_rows > 0;
        $cols4 = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'manual_registration_credit'");
        if ($cols4 && $cols4->num_rows === 0) {
            @$conn->query("ALTER TABLE brand_ambassadors ADD COLUMN manual_registration_credit INT DEFAULT 0");
            $cols4 = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'manual_registration_credit'");
        }
        $hasManualCredit = $cols4 && $cols4->num_rows > 0;
        $cols5 = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'perk_threshold_override'");
        if ($cols5 && $cols5->num_rows === 0) {
            @$conn->query("ALTER TABLE brand_ambassadors ADD COLUMN perk_threshold_override INT DEFAULT 0");
            $cols5 = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'perk_threshold_override'");
        }
        $hasPerkOverride = $cols5 && $cols5->num_rows > 0;
        $cols6 = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'ambassador_type'");
        $hasTypeColumn = $cols6 && $cols6->num_rows > 0;
    }
} catch (Exception $e) {}

try {
    $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $defaults = [
        'ambassador_perk_threshold' => '2',
        'volunteer_perk_threshold' => (string)$defaultThresholds['volunteer'],
        'brand_perk_threshold' => (string)$defaultThresholds['brand']
    ];
    foreach ($defaults as $settingKey => $settingValue) {
        $escapedKey = $conn->real_escape_string($settingKey);
        $escapedVal = $conn->real_escape_string($settingValue);
        $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('$escapedKey','$escapedVal')");
    }
    $thresholdKeys = [
        $type . '_perk_threshold',
        'ambassador_perk_threshold_' . $type,
        'ambassador_perk_threshold'
    ];
    foreach ($thresholdKeys as $settingKey) {
        if (!$settingKey) { continue; }
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key=? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $settingKey);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $thresholdVal = (int)$row['setting_value'];
                if ($thresholdVal > 0) {
                    $perkThreshold = $thresholdVal;
                    $stmt->close();
                    break;
                }
            }
            $stmt->close();
        }
    }
} catch (Exception $e) {}

?>

<style>
.perk-ready-row {
    background: rgba(0, 255, 148, 0.04);
}
.perk-ready-row:hover {
    background: rgba(0, 255, 148, 0.08) !important;
}
.perk-ready-row .badge.bg-success {
    box-shadow: 0 0 8px rgba(0, 255, 148, 0.45);
}
</style>

<div class="page-header">
    <h2><i class="fas fa-user-tie me-2"></i> Manage <?php echo htmlspecialchars($typeLabel); ?></h2>
    <p class="text-muted"><?php echo htmlspecialchars($typeDescription); ?></p>
</div>

<div class="glass-panel mb-4">
    <?php if (!$hasTable): ?>
        <div class="alert alert-warning" style="background: rgba(255,187,51,0.1); border: 1px solid #ffbb33; color: #ffbb33;">
            <strong>System Check:</strong> The `brand_ambassadors` table is missing. Please create it in your database.
        </div>
    <?php elseif (!$hasTypeColumn): ?>
        <div class="alert alert-warning" style="background: rgba(255,187,51,0.1); border: 1px solid #ffbb33; color: #ffbb33;">
            <strong>Action Needed:</strong> The `ambassador_type` column is required to split volunteers and brand ambassadors. Confirm the database migration completed successfully.
        </div>
    <?php else: ?>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a class="btn <?php echo $type === 'volunteer' ? 'btn-neon' : 'btn-outline-light'; ?>" href="manage_ambassadors.php?type=volunteer" style="min-width:180px;">
                <i class="fas fa-hands-helping me-2"></i> Volunteers
            </a>
            <a class="btn <?php echo $type === 'brand' ? 'btn-neon' : 'btn-outline-light'; ?>" href="manage_ambassadors.php?type=brand" style="min-width:180px;">
                <i class="fas fa-user-tie me-2"></i> Brand Ambassadors
            </a>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h4 class="text-white m-0">Active <?php echo $type === 'brand' ? 'Brand' : 'Volunteer'; ?> Ambassadors</h4>
                <form method="get" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                    <label for="sort" class="text-muted small mb-0">Sort by</label>
                    <select name="sort" id="sort" class="form-select form-select-sm" style="background:#000; border-color:#444; color:#fff; min-width: 180px;">
                        <option value="recent" <?php echo (!isset($_GET['sort']) || $_GET['sort']==='recent') ? 'selected' : ''; ?>>Most Recent</option>
                        <option value="name_asc" <?php echo (isset($_GET['sort']) && $_GET['sort']==='name_asc') ? 'selected' : ''; ?>>Name A-Z</option>
                        <option value="name_desc" <?php echo (isset($_GET['sort']) && $_GET['sort']==='name_desc') ? 'selected' : ''; ?>>Name Z-A</option>
                        <option value="referrals_desc" <?php echo (isset($_GET['sort']) && $_GET['sort']==='referrals_desc') ? 'selected' : ''; ?>>Max Registrations</option>
                        <option value="referrals_asc" <?php echo (isset($_GET['sort']) && $_GET['sort']==='referrals_asc') ? 'selected' : ''; ?>>Min Registrations</option>
                    </select>
                    <button class="btn-neon btn-sm" type="submit">Apply</button>
                </form>
            </div>
            <div class="d-flex gap-2">
                <a class="btn-neon" href="upload_ambassadors_csv.php?type=<?php echo urlencode($type); ?>">
                    <i class="fas fa-file-upload me-1"></i> Import CSV
                </a>
                <a class="btn-neon" href="export_ambassadors_csv.php?type=<?php echo urlencode($type); ?>">
                    <i class="fas fa-file-download me-1"></i> Export CSV
                </a>
                <button id="bulkEmailBtn" class="btn-neon" data-type="<?php echo htmlspecialchars($type); ?>">
                    <i class="fas fa-envelope-open me-1"></i> Bulk Email All
                </button>
                <button id="customEmailBtn" class="btn-neon" type="button">
                    <i class="fas fa-pen-to-square me-1"></i> Custom Email
                </button>
                <button class="btn-neon" data-bs-toggle="modal" data-bs-target="#addAmbassadorModal">
                    <i class="fas fa-plus-circle me-1"></i> Add New
                </button>
            </div>
        </div>

        <div class="glass-panel mb-4" style="background: rgba(2, 14, 30, 0.6); border-left: 4px solid #00c3ff;">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="text-white mb-1">Perk Threshold</h5>
                    <p class="text-muted mb-0" style="font-size:0.85rem;">Ambassadors must reach this many approved registrations (including manual bonuses) to qualify for perks.</p>
                </div>
                <form method="post" action="ambassador_actions.php" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="action" value="update_threshold">
                    <input type="hidden" name="ambassador_type" value="<?php echo htmlspecialchars($type); ?>">
                    <input type="number" name="threshold" min="1" value="<?php echo (int)$perkThreshold; ?>" class="form-control" style="width:110px; background:#000; border-color:#444; color:#fff;">
                    <button type="submit" class="btn-neon btn-sm">Update</button>
                </form>
            </div>
        </div>

        <?php if ($hasManualCredit): ?>
            <div class="alert" style="background: rgba(0, 195, 255, 0.08); border: 1px solid rgba(0, 195, 255, 0.4); color: #8fe5ff;">
                Manual bonus credits boost the approved tally shown to ambassadors so they can unlock perks sooner. Raw totals remain unchanged for reporting.
            </div>
        <?php endif; ?>

        <?php if (!empty($perksDue)): ?>
            <div class="alert" style="background: rgba(0, 255, 148, 0.08); border: 1px solid rgba(0, 255, 148, 0.35); color: #6df7c3;">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <strong>Datacamp perk reminders:</strong>
                        <ul class="mb-0 mt-2" style="list-style: none; padding-left: 0; font-size: 0.9rem;">
                            <?php foreach ($perksDue as $eligible): ?>
                                <li class="mb-1">
                                    <span style="color:#c5ffd6; font-weight:600;"><?php echo htmlspecialchars($eligible['name']); ?></span>
                                    (<code style="color:#00ff94;"><?php echo htmlspecialchars($eligible['code']); ?></code>) &mdash;
                                    <?php echo (int)$eligible['approved']; ?> / <?php echo (int)$eligible['target']; ?> teams approved.
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <button id="perkAlertEmailBtn" class="btn btn-sm btn-neon" data-recipient-ids="<?php echo implode(',', array_column($perksDue, 'id')); ?>">
                            <i class="fas fa-envelope me-1"></i> Email Reminder
                        </button>
                        <small class="text-muted" style="font-size:0.75rem; color:#bdecd2 !important;">Use the custom composer to send perks-ready emails.</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover text-white align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email / Phone</th>
                        <th>Institution</th>
                        <th>Code</th>
                        <th>Status</th>
                        <th>Event Teams</th>
                        <th>Social Guests</th> <th>Total Impact</th>
                        <?php if ($hasManualCredit): ?>
                            <th>Manual Bonus</th>
                        <?php endif; ?>
                        <?php if ($hasPerkOverride): ?>
                            <th>Perk Target</th>
                        <?php endif; ?>
                        <th>Payments</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Determine sort option
                    $perksDue = [];
                    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'recent';

                    if (!in_array($sort, ['recent','name_asc','name_desc','referrals_desc','referrals_asc'], true)) {
                        $sort = 'recent';
                    }

                    // Base ambassador query
                    $ambassadors = null;
                    $rows = [];
                    if ($hasTypeColumn && $stmtList = $conn->prepare("SELECT * FROM brand_ambassadors WHERE ambassador_type = ? ORDER BY created_at DESC")) {
                        $stmtList->bind_param('s', $type);
                        $stmtList->execute();
                        $ambassadors = $stmtList->get_result();
                    }
                    if ($ambassadors instanceof mysqli_result) {
                        while ($row = $ambassadors->fetch_assoc()) {
                            $code = $conn->real_escape_string($row['code']);
                            // 1. EVENT REFERRALS
                            $c = ['total'=>0,'pending'=>0,'approved'=>0];
                            $q = $conn->query("SELECT status, COUNT(*) c FROM event_registrations WHERE brand_ambassador_code='{$code}' GROUP BY status");
                            if ($q) { while($r=$q->fetch_assoc()){ $c['total'] += (int)$r['c']; $c[$r['status']] = (int)$r['c']; } }
                            $row['__counts'] = $c;

                            // 2. SOCIAL REFERRALS (NEW LOGIC)
                            $s = ['total'=>0,'pending'=>0,'approved'=>0];
                            if ($hasSocialTable) {
                                $sq = $conn->query("SELECT status, COUNT(*) c FROM social_registrations WHERE ambassador_code='{$code}' GROUP BY status");
                                if ($sq) { 
                                    while($r=$sq->fetch_assoc()){ 
                                        $s['total'] += (int)$r['c']; 
                                        if($r['status'] == 'approved' || $r['status'] == 'confirmed') $s['approved'] += (int)$r['c'];
                                        else $s['pending'] += (int)$r['c'];
                                    } 
                                }
                            }
                            $row['__social'] = $s;

                            // PAYMENTS
                            $p = ['not_submitted'=>0,'submitted'=>0,'confirmed'=>0];
                            $pq = $conn->query("SELECT 
                                SUM(CASE WHEN payment_status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
                                SUM(CASE WHEN payment_status = 'submitted' THEN 1 ELSE 0 END) AS submitted,
                                SUM(CASE WHEN payment_status IS NULL OR payment_status = '' OR payment_status = 'pending' THEN 1 ELSE 0 END) AS not_submitted
                            FROM event_registrations WHERE brand_ambassador_code='{$code}'");
                            if ($pq && $pay = $pq->fetch_assoc()) {
                                $p['confirmed'] = (int)($pay['confirmed'] ?? 0);
                                $p['submitted'] = (int)($pay['submitted'] ?? 0);
                                $p['not_submitted'] = (int)($pay['not_submitted'] ?? 0);
                            }
                            $row['__payments'] = $p;

                            $row['__manual_credit'] = $hasManualCredit ? (int)($row['manual_registration_credit'] ?? 0) : 0;
                            $row['__perk_override'] = $hasPerkOverride ? (int)($row['perk_threshold_override'] ?? 0) : 0;
                            
                            $manualCredit = (int)$row['__manual_credit'];
                            $overrideTarget = (int)$row['__perk_override'];
                            $effectiveTarget = $overrideTarget > 0 ? $overrideTarget : $perkThreshold;
                            
                            // CALCULATE TOTALS (Event + Social + Manual)
                            $totalWithManual = $c['total'] + $s['total'] + $manualCredit;
                            $approvedWithManual = $c['approved'] + $s['approved'] + $manualCredit;
                            
                            $row['__effective_target'] = $effectiveTarget;
                            $row['__approved_with_manual'] = $approvedWithManual;
                            $row['__total_with_manual'] = $totalWithManual;
                            
                            $perkGranted = (int)($row['perk_granted'] ?? 0);
                            $row['__perk_due'] = ($perkGranted === 0 && $approvedWithManual >= $effectiveTarget);
                            if ($row['__perk_due']) {
                                $perksDue[] = [
                                    'id' => (int)$row['id'],
                                    'name' => $row['name'],
                                    'code' => $row['code'],
                                    'email' => $row['email'],
                                    'approved' => $approvedWithManual,
                                    'target' => $effectiveTarget
                                ];
                            }
                            $rows[] = $row;
                        }
                        if (isset($stmtList)) { $stmtList->close(); }
                    }

                    // Sort in PHP
                    if ($sort === 'name_asc' || $sort === 'name_desc') {
                        usort($rows, function($a,$b) use ($sort){
                            $cmp = strcasecmp($a['name'], $b['name']);
                            return $sort === 'name_asc' ? $cmp : -$cmp;
                        });
                    } elseif ($sort === 'referrals_desc' || $sort === 'referrals_asc') {
                        usort($rows, function($a,$b) use ($sort){
                            $at = ($a['__total_with_manual']);
                            $bt = ($b['__total_with_manual']);
                            if ($at === $bt) return 0;
                            if ($sort === 'referrals_desc') {
                                return ($at < $bt) ? 1 : -1;
                            }
                            return ($at > $bt) ? 1 : -1;
                        });
                    }

                    $sr = 1;
                    if (!empty($rows)): 
                        foreach ($rows as $row): 
                            $c = $row['__counts'];
                            $s = $row['__social'];
                            $p = $row['__payments'];
                            $manualCredit = (int)($row['__manual_credit'] ?? 0);
                            $overrideTarget = (int)($row['__perk_override'] ?? 0);
                            $effectiveTarget = $overrideTarget > 0 ? $overrideTarget : $perkThreshold;
                            $totalWithManual = $row['__total_with_manual'];
                            $approvedWithManual = $row['__approved_with_manual'];
                            $perkDue = !empty($row['__perk_due']);
                    ?>
                        <tr data-id="<?php echo (int)$row['id']; ?>" class="<?php echo $perkDue ? 'perk-ready-row' : ''; ?>">
                            <td><?php echo $sr++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                            <td>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($row['email']); ?></small>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($row['phone']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($row['institution']); ?></td>
                            <td><code style="color:var(--accent); font-size:1.1em;"><?php echo htmlspecialchars($row['code']); ?></code></td>
                            <td>
                                <span class="badge <?php echo $row['status']=='active'?'bg-success':'bg-secondary'; ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            
                            <td class="text-center">
                                <span class="badge bg-dark border border-secondary"><?php echo $c['total']; ?></span>
                            </td>

                            <td class="text-center">
                                <span class="badge bg-dark border border-warning" style="color:#ffbb33;"><?php echo $s['total']; ?></span>
                            </td>

                            <td class="text-center">
                                <span class="badge bg-success border border-success" title="Total (Event + Social + Manual): <?php echo $totalWithManual; ?>" style="font-size:1rem;">
                                    <?php echo $totalWithManual; ?>
                                </span>
                            </td>

                            <?php if ($hasManualCredit): ?>
                                <td>
                                    <?php if ($manualCredit > 0): ?>
                                        <span class="badge bg-primary border border-primary" title="Additional approved credits granted manually">+<?php echo $manualCredit; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-dark border border-secondary">0</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <?php if ($hasPerkOverride): ?>
                                <td>
                                    <?php if ($overrideTarget > 0): ?>
                                        <span class="badge bg-info text-dark border border-info" title="Custom perk target for this ambassador"><?php echo $overrideTarget; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-dark border border-secondary" title="Uses default threshold of <?php echo $perkThreshold; ?>">Default (<?php echo $perkThreshold; ?>)</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <span class="badge bg-warning text-dark border border-warning" title="No payment proof yet">No Proof: <?php echo $p['not_submitted']; ?></span>
                                <span class="badge bg-info text-dark border border-info" title="Submitted, awaiting verification">Pending: <?php echo $p['submitted']; ?></span>
                                <span class="badge bg-success border border-success" title="Payment confirmed">Paid: <?php echo $p['confirmed']; ?></span>
                            </td>
                            <td>
                                <?php if ($perkDue): ?>
                                    <span class="badge bg-success text-dark mb-1" style="background:#00ff94 !important; color:#001510 !important;">Goal Met</span>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-light send-cred" data-id="<?php echo $row['id']; ?>" data-type="<?php echo htmlspecialchars($type); ?>" title="Send Login Credentials">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                                
                                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#editAmbassadorModal" 
                                    data-id="<?php echo $row['id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($row['name']); ?>" 
                                    data-email="<?php echo htmlspecialchars($row['email']); ?>" 
                                    data-phone="<?php echo htmlspecialchars($row['phone']); ?>" 
                                    data-inst="<?php echo htmlspecialchars($row['institution']); ?>" 
                                    data-code="<?php echo htmlspecialchars($row['code']); ?>" 
                                    data-status="<?php echo htmlspecialchars($row['status']); ?>"
                                    <?php if ($hasManualCredit): ?>
                                        data-manual="<?php echo (int)$manualCredit; ?>"
                                    <?php endif; ?>
                                    <?php if ($hasPerkOverride): ?>
                                        data-perk="<?php echo (int)$overrideTarget; ?>"
                                    <?php endif; ?>
                                >
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <a href="ambassador_actions.php?action=delete&id=<?php echo $row['id']; ?>&ambassador_type=<?php echo urlencode($type); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this ambassador?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="<?php echo 11 + ($hasManualCredit ? 1 : 0) + ($hasPerkOverride ? 1 : 0); ?>" class="text-center text-muted p-4">No ambassadors found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="addAmbassadorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background:#111; border:1px solid #333; color:#fff;">
            <div class="modal-header border-secondary"><h5 class="modal-title">Add Ambassador</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form method="post" action="ambassador_actions.php" id="addForm">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="ambassador_type" value="<?php echo htmlspecialchars($type); ?>">
                    <input class="form-control mb-3" name="name" placeholder="Full Name" style="background:#000; border-color:#444; color:#fff;" required>
                    <input class="form-control mb-3" name="email" type="email" placeholder="Email Address" style="background:#000; border-color:#444; color:#fff;" required>
                    <input class="form-control mb-3" name="phone" placeholder="Phone Number" style="background:#000; border-color:#444; color:#fff;">
                    <input class="form-control mb-3" name="institution" placeholder="Institution / University" style="background:#000; border-color:#444; color:#fff;">
                    <input class="form-control mb-3" name="code" placeholder="Unique Code (e.g. AMB-001)" style="background:#000; border-color:#444; color:#fff;" required>
                    <input class="form-control mb-3" name="password" type="text" placeholder="Initial Password (Leave empty for auto)" style="background:#000; border-color:#444; color:#fff;">
                    <?php if ($hasManualCredit): ?>
                        <input class="form-control mb-3" name="manual_registration_credit" type="number" min="0" value="0" placeholder="Manual registration credit" style="background:#000; border-color:#444; color:#fff;">
                        <small class="text-muted d-block mb-3" style="font-size:0.8rem;">Adds bonus approved teams for perk eligibility without altering raw registrations.</small>
                    <?php endif; ?>
                    <?php if ($hasPerkOverride): ?>
                        <input class="form-control mb-3" name="perk_threshold_override" type="number" min="0" value="0" placeholder="Custom perk goal (0 to use default)" style="background:#000; border-color:#444; color:#fff;">
                        <small class="text-muted d-block mb-3" style="font-size:0.8rem;">Set a specific perk threshold for this ambassador. Leave at 0 to use the global value (<?php echo $perkThreshold; ?>).</small>
                    <?php endif; ?>
                    <select class="form-select mb-3" name="status" style="background:#000; border-color:#444; color:#fff;">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn-neon" onclick="document.getElementById('addForm').submit()">Save Ambassador</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editAmbassadorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background:#111; border:1px solid #333; color:#fff;">
            <div class="modal-header border-secondary"><h5 class="modal-title">Edit Ambassador</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form method="post" action="ambassador_actions.php" id="editAmbassadorForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="ambassador_type" value="<?php echo htmlspecialchars($type); ?>">
                    <input class="form-control mb-2" name="name" id="edit_name" placeholder="Name" style="background:#000; border-color:#444; color:#fff;" required>
                    <input class="form-control mb-2" name="email" id="edit_email" type="email" placeholder="Email" style="background:#000; border-color:#444; color:#fff;" required>
                    <input class="form-control mb-2" name="phone" id="edit_phone" placeholder="Phone" style="background:#000; border-color:#444; color:#fff;">
                    <input class="form-control mb-2" name="institution" id="edit_institution" placeholder="Institution" style="background:#000; border-color:#444; color:#fff;">
                    <input class="form-control mb-2" name="code" id="edit_code" placeholder="Unique Code" style="background:#000; border-color:#444; color:#fff;" required>
                    <input class="form-control mb-2" name="password" type="text" placeholder="Reset Password (leave blank to keep)" style="background:#000; border-color:#444; color:#fff;">
                    <?php if ($hasManualCredit): ?>
                        <input class="form-control mb-2" name="manual_registration_credit" id="edit_manual" type="number" min="0" placeholder="Manual registration credit" style="background:#000; border-color:#444; color:#fff;">
                        <small class="text-muted d-block mb-2" style="font-size:0.8rem;">Use to grant extra approved credits so ambassadors can attain perks.</small>
                    <?php endif; ?>
                    <?php if ($hasPerkOverride): ?>
                        <input class="form-control mb-2" name="perk_threshold_override" id="edit_perk" type="number" min="0" placeholder="Custom perk goal (0 = global)" style="background:#000; border-color:#444; color:#fff;">
                        <small class="text-muted d-block mb-2" style="font-size:0.8rem;">Override the global perk target for this ambassador. Use 0 to fall back to <?php echo $perkThreshold; ?>.</small>
                    <?php endif; ?>
                    <select class="form-select" name="status" id="edit_status" style="background:#000; border-color:#444; color:#fff;">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn-neon" onclick="document.getElementById('editAmbassadorForm').submit()">Update</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="customEmailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:#111; border:1px solid #333; color:#fff;">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Send Custom Email</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="customEmailForm" method="post">
                <div class="modal-body">
                    <input type="hidden" name="ambassador_type" value="<?php echo htmlspecialchars($type); ?>">
                    <div class="mb-3">
                        <label class="form-label">Recipients</label>
                        <select class="form-select" name="recipient_ids[]" id="customEmailRecipients" multiple required style="min-height: 160px; background:#000; border-color:#444; color:#fff;">
                            <?php if (!empty($rows)): ?>
                                <?php foreach ($rows as $rowOption): ?>
                                    <option value="<?php echo (int)$rowOption['id']; ?>" data-email="<?php echo htmlspecialchars($rowOption['email']); ?>" <?php echo !empty($rowOption['__perk_due']) ? 'data-perk-due="1"' : ''; ?>>
                                        <?php echo htmlspecialchars($rowOption['name']); ?> (<?php echo htmlspecialchars($rowOption['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted d-block" id="customEmailSelectedCount" style="font-size:0.75rem;">Select ambassadors to receive this message. Hold Ctrl / Cmd for multiple selection.</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Email Subject</label>
                            <input class="form-control" type="text" name="subject" placeholder="Subject" style="background:#000; border-color:#444; color:#fff;" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Header Badge</label>
                            <input class="form-control" type="text" name="title" value="Ambassador Program" placeholder="Badge above heading" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Headline</label>
                            <input class="form-control" type="text" name="heading" placeholder="e.g. Congratulations!" style="background:#000; border-color:#444; color:#fff;" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Greeting Line</label>
                            <input class="form-control" type="text" name="greeting" value="Hello {{name}}," placeholder="Use {{name}} to personalize" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Message Body</label>
                        <textarea class="form-control" name="body" rows="6" placeholder="Share details for the ambassador. Use {{name}} or {{code}} for personalization." style="background:#000; border-color:#444; color:#fff;" required></textarea>
                    </div>
                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label class="form-label">CTA Button Label <span class="text-muted" style="font-size:0.75rem;">(optional)</span></label>
                            <input class="form-control" type="text" name="cta_label" placeholder="e.g. View Dashboard" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CTA Button URL <span class="text-muted" style="font-size:0.75rem;">(optional)</span></label>
                            <input class="form-control" type="url" name="cta_url" placeholder="https://" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Footer Note <span class="text-muted" style="font-size:0.75rem;">(optional)</span></label>
                        <textarea class="form-control" name="footer_note" rows="2" placeholder="Override default footer if needed." style="background:#000; border-color:#444; color:#fff;"></textarea>
                    </div>
                    <small class="text-muted d-block mt-3" style="font-size:0.75rem;">Placeholders supported: <code>{{name}}</code>, <code>{{code}}</code>. They will be replaced per recipient.</small>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="customEmailSendBtn" class="btn-neon">Send Email</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. BULK EMAIL BUTTON
    const bulkBtn = document.getElementById('bulkEmailBtn');
    if(bulkBtn) {
        bulkBtn.addEventListener('click', function(){
            if(!confirm('Send login credentials to ALL active ambassadors?')) return;
            
            // Show loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            this.disabled = true;
            const type = this.getAttribute('data-type') || '';

            fetch('email_ambassadors.php', {
                method: 'POST', 
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
                body: 'mode=bulk' + (type ? '&ambassador_type=' + encodeURIComponent(type) : '')
            })
            .then(r => r.json())
            .then(d => {
                alert('Sent: ' + d.sent + ' emails.\n' + (d.errors.length ? 'Errors: ' + d.errors.join('\n') : ''));
                location.reload();
            })
            .catch(e => {
                alert('Bulk send failed: ' + e);
                location.reload();
            });
        });
    }

    // 2. SINGLE EMAIL BUTTON
    document.querySelectorAll('.send-cred').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type') || '';
            if(!id) return;

            if(!confirm('Send credentials to this ambassador?')) return;

            // Visual Feedback
            const icon = this.querySelector('i');
            icon.className = 'fas fa-spinner fa-spin';

            fetch('email_ambassadors.php', {
                method: 'POST', 
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
                body: 'mode=single&ids[]=' + encodeURIComponent(id) + (type ? '&ambassador_type=' + encodeURIComponent(type) : '')
            })
            .then(r => r.json())
            .then(d => {
                if(d.sent > 0) {
                    alert('Email Sent Successfully!');
                } else {
                    alert('Failed: ' + (d.errors.join('\n') || 'Unknown error'));
                }
                // Reset Icon
                icon.className = 'fas fa-paper-plane';
            })
            .catch(e => {
                alert('Network error: ' + e);
                icon.className = 'fas fa-exclamation-circle';
            });
        });
    });

    // 3. CUSTOM EMAIL COMPOSER
    const customEmailBtn = document.getElementById('customEmailBtn');
    const perkAlertEmailBtn = document.getElementById('perkAlertEmailBtn');
    const customEmailModalEl = document.getElementById('customEmailModal');
    const customEmailForm = document.getElementById('customEmailForm');
    const recipientsSelect = document.getElementById('customEmailRecipients');
    const selectedCountEl = document.getElementById('customEmailSelectedCount');
    let customEmailModal = null;

    function updateSelectedCount(){
        if(!recipientsSelect || !selectedCountEl) return;
        const selected = Array.from(recipientsSelect.options || []).filter(opt => opt.selected).length;
        const total = recipientsSelect.options ? recipientsSelect.options.length : 0;
        const message = total === 0
            ? 'No ambassadors available for selection.'
            : (selected === 0
                ? 'No recipients selected out of ' + total + '.'
                : selected + ' selected out of ' + total + '.');
        selectedCountEl.textContent = message;
    }

    function setRecipientsByIds(ids){
        if(!recipientsSelect) return;
        const idSet = new Set(ids);
        Array.from(recipientsSelect.options || []).forEach(opt => {
            opt.selected = idSet.has(opt.value);
        });
        updateSelectedCount();
    }

    if(customEmailModalEl && typeof bootstrap !== 'undefined') {
        customEmailModal = new bootstrap.Modal(customEmailModalEl);
    }

    if(recipientsSelect) {
        recipientsSelect.addEventListener('change', updateSelectedCount);
        updateSelectedCount();
    }

    if(customEmailBtn && customEmailModal && customEmailForm) {
        customEmailBtn.addEventListener('click', function(){
            customEmailForm.reset();
            setRecipientsByIds([]);
            customEmailModal.show();
        });
    }

    if(perkAlertEmailBtn && customEmailModal && customEmailForm) {
        perkAlertEmailBtn.addEventListener('click', function(){
            const ids = (this.getAttribute('data-recipient-ids') || '')
                .split(',')
                .map(function(id){ return id.trim(); })
                .filter(Boolean);
            customEmailForm.reset();
            setRecipientsByIds(ids);
            customEmailModal.show();
        });
    }

    if(customEmailForm) {
        customEmailForm.addEventListener('submit', function(e){
            e.preventDefault();
            if(!recipientsSelect || Array.from(recipientsSelect.options || []).filter(opt => opt.selected).length === 0) {
                alert('Please select at least one ambassador.');
                return;
            }
            const submitBtn = document.getElementById('customEmailSendBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            }
            fetch('custom_email.php', {
                method: 'POST',
                body: new FormData(customEmailForm)
            })
            .then(function(res){ return res.json(); })
            .then(function(data){
                const sent = data && typeof data.sent !== 'undefined' ? data.sent : 0;
                const errors = data && Array.isArray(data.errors) && data.errors.length ? '\nErrors:\n' + data.errors.join('\n') : '';
                alert('Sent: ' + sent + ' emails.' + errors);
                if (customEmailModal) {
                    customEmailModal.hide();
                }
                customEmailForm.reset();
                setRecipientsByIds([]);
            })
            .catch(function(err){
                alert('Custom email failed: ' + err);
            })
            .finally(function(){
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Send Email';
                }
            });
        });
    }

    // 4. POPULATE EDIT MODAL
    var editModal = document.getElementById('editAmbassadorModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('edit_id').value = button.getAttribute('data-id');
        document.getElementById('edit_name').value = button.getAttribute('data-name');
        document.getElementById('edit_email').value = button.getAttribute('data-email');
        document.getElementById('edit_phone').value = button.getAttribute('data-phone');
        document.getElementById('edit_institution').value = button.getAttribute('data-inst');
        document.getElementById('edit_code').value = button.getAttribute('data-code');
        document.getElementById('edit_status').value = button.getAttribute('data-status');
        <?php if ($hasManualCredit): ?>
            var manualField = document.getElementById('edit_manual');
            if (manualField) {
                manualField.value = button.getAttribute('data-manual') || 0;
            }
        <?php endif; ?>
        <?php if ($hasPerkOverride): ?>
            var perkField = document.getElementById('edit_perk');
            if (perkField) {
                perkField.value = button.getAttribute('data-perk') || 0;
            }
        <?php endif; ?>
    });
    }
});
</script>
