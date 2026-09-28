<?php
include 'header.php';
require_once __DIR__ . '/../db_connection.php';

// 1. Column detection & self-healing migrations
$hasTable = false;
$hasPwd = true;
$hasInitialPwd = false;
$hasPerk = false;
$hasManualCredit = false;
$hasPerkOverride = false;
$hasTypeColumn = false;
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
        if ($cols6 && $cols6->num_rows === 0) {
            @$conn->query("ALTER TABLE brand_ambassadors ADD COLUMN ambassador_type ENUM('volunteer','brand') NOT NULL DEFAULT 'brand'");
            $cols6 = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'ambassador_type'");
        }
        $hasTypeColumn = $cols6 && $cols6->num_rows > 0;
    }
} catch (Exception $e) {}

// 2. Load system settings & perk thresholds
$defaultThresholds = ['volunteer' => 2, 'brand' => 5];
$volunteerThreshold = $defaultThresholds['volunteer'];
$brandThreshold = $defaultThresholds['brand'];

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

    $setRes = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('volunteer_perk_threshold', 'brand_perk_threshold', 'ambassador_perk_threshold')");
    if ($setRes) {
        while ($sRow = $setRes->fetch_assoc()) {
            $val = (int)$sRow['setting_value'];
            if ($val > 0) {
                if ($sRow['setting_key'] === 'volunteer_perk_threshold') $volunteerThreshold = $val;
                if ($sRow['setting_key'] === 'brand_perk_threshold') $brandThreshold = $val;
            }
        }
    }
} catch (Exception $e) {}

// 3. Determine Ambassador Type Context
$validTypes = [
    'all' => 'All Ambassadors',
    'brand' => 'Brand Ambassadors',
    'volunteer' => 'Volunteer Ambassadors'
];
$type = strtolower($_GET['type'] ?? 'all');
if (!array_key_exists($type, $validTypes)) {
    $type = 'all';
}
$typeLabel = $validTypes[$type];
$typeDescription = $type === 'brand'
    ? 'Track performance and perks for campus brand partners.'
    : ($type === 'volunteer'
        ? 'Manage volunteer leads working on ground outreach.'
        : 'Overview of all brand and volunteer ambassadors, team registrations, social passes, and perk qualification.');

// Active threshold for context
$perkThreshold = ($type === 'volunteer') ? $volunteerThreshold : $brandThreshold;

// 4. Count Ambassadors by Type (Single Query)
$countsByType = ['all' => 0, 'brand' => 0, 'volunteer' => 0];
if ($hasTable) {
    $cQuery = $conn->query("SELECT ambassador_type, COUNT(*) as c FROM brand_ambassadors GROUP BY ambassador_type");
    if ($cQuery) {
        while ($cr = $cQuery->fetch_assoc()) {
            $t = strtolower($cr['ambassador_type'] ?? 'brand');
            if (isset($countsByType[$t])) {
                $countsByType[$t] = (int)$cr['c'];
            }
            $countsByType['all'] += (int)$cr['c'];
        }
    }
}

// 5. Pre-fetch Aggregate Referral & Payment Stats (Eliminates N+1 queries, drops load from 25s to 0.2s)
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
if ($hasSocialTable) {
    $resS = $conn->query("SELECT ambassador_code, status, COUNT(*) as c FROM social_registrations WHERE ambassador_code IS NOT NULL AND ambassador_code != '' GROUP BY ambassador_code, status");
    if ($resS) {
        while ($r = $resS->fetch_assoc()) {
            $cd = $r['ambassador_code'];
            if (!isset($socialStats[$cd])) $socialStats[$cd] = ['total' => 0, 'pending' => 0, 'approved' => 0];
            $st = strtolower($r['status'] ?? '');
            $cnt = (int)$r['c'];
            $socialStats[$cd]['total'] += $cnt;
            if ($st === 'approved' || $st === 'confirmed') $socialStats[$cd]['approved'] += $cnt;
            else $socialStats[$cd]['pending'] += $cnt;
        }
    }
}

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

// 6. Fetch Ambassador Rows & Process KPIs
$rows = [];
$perksDue = [];
$kpi = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'event_teams' => 0,
    'social_passes' => 0,
    'total_impact' => 0,
    'perks_qualified' => 0,
    'perks_granted' => 0
];

if ($hasTable) {
    $sql = "SELECT * FROM brand_ambassadors";
    if ($hasTypeColumn && in_array($type, ['brand', 'volunteer'], true)) {
        $sql .= " WHERE ambassador_type = '" . $conn->real_escape_string($type) . "'";
    }
    $sql .= " ORDER BY created_at DESC";
    $ambassadors = $conn->query($sql);

    if ($ambassadors && $ambassadors->num_rows > 0) {
        while ($row = $ambassadors->fetch_assoc()) {
            $code = $row['code'];
            $ambType = strtolower($row['ambassador_type'] ?? 'brand');
            if (!in_array($ambType, ['volunteer', 'brand'], true)) { $ambType = 'brand'; }

            $c = $eventStats[$code] ?? ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
            $s = $socialStats[$code] ?? ['total' => 0, 'pending' => 0, 'approved' => 0];
            $p = $paymentStats[$code] ?? ['not_submitted' => 0, 'submitted' => 0, 'confirmed' => 0];

            $manualCredit = $hasManualCredit ? max(0, (int)($row['manual_registration_credit'] ?? 0)) : 0;
            $overrideTarget = $hasPerkOverride ? max(0, (int)($row['perk_threshold_override'] ?? 0)) : 0;

            $typeDefaultThreshold = ($ambType === 'volunteer') ? $volunteerThreshold : $brandThreshold;
            $effectiveTarget = $overrideTarget > 0 ? $overrideTarget : $typeDefaultThreshold;

            $totalWithManual = $c['total'] + $s['total'] + $manualCredit;
            $approvedWithManual = $c['approved'] + $s['approved'] + $manualCredit;

            $perkGranted = (int)($row['perk_granted'] ?? 0);
            $perkRequested = (int)($row['perk_requested'] ?? 0);
            $isPerkDue = ($perkGranted === 0 && $approvedWithManual >= $effectiveTarget);

            $row['__counts'] = $c;
            $row['__social'] = $s;
            $row['__payments'] = $p;
            $row['__amb_type'] = $ambType;
            $row['__manual_credit'] = $manualCredit;
            $row['__perk_override'] = $overrideTarget;
            $row['__effective_target'] = $effectiveTarget;
            $row['__total_with_manual'] = $totalWithManual;
            $row['__approved_with_manual'] = $approvedWithManual;
            $row['__perk_due'] = $isPerkDue;
            $row['__perk_granted'] = $perkGranted;
            $row['__perk_requested'] = $perkRequested;

            // KPI tallies
            $kpi['total']++;
            if ($row['status'] === 'active') { $kpi['active']++; } else { $kpi['inactive']++; }
            $kpi['event_teams'] += $c['total'];
            $kpi['social_passes'] += $s['total'];
            $kpi['total_impact'] += $totalWithManual;
            if ($perkGranted === 1) { $kpi['perks_granted']++; }
            if ($isPerkDue) {
                $kpi['perks_qualified']++;
                $perksDue[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'code' => $row['code'],
                    'email' => $row['email'],
                    'type' => $ambType,
                    'approved' => $approvedWithManual,
                    'target' => $effectiveTarget
                ];
            }

            $rows[] = $row;
        }
    }
}

// 7. Sort Rows
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'recent';
if (!in_array($sort, ['recent', 'name_asc', 'name_desc', 'referrals_desc', 'referrals_asc'], true)) {
    $sort = 'recent';
}
if ($sort === 'name_asc' || $sort === 'name_desc') {
    usort($rows, function($a, $b) use ($sort) {
        $cmp = strcasecmp($a['name'], $b['name']);
        return $sort === 'name_asc' ? $cmp : -$cmp;
    });
} elseif ($sort === 'referrals_desc' || $sort === 'referrals_asc') {
    usort($rows, function($a, $b) use ($sort) {
        $at = $a['__total_with_manual'];
        $bt = $b['__total_with_manual'];
        if ($at === $bt) return 0;
        return ($sort === 'referrals_desc') ? ($at < $bt ? 1 : -1) : ($at > $bt ? 1 : -1);
    });
}
?>

<style>
.kpi-card {
    background: rgba(13, 17, 23, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 16px 20px;
    position: relative;
    overflow: hidden;
    transition: transform 0.2s ease, border-color 0.2s ease;
}
.kpi-card:hover {
    transform: translateY(-2px);
    border-color: rgba(0, 195, 255, 0.3);
}
.kpi-card .kpi-num {
    font-size: 1.85rem;
    font-weight: 700;
    font-family: var(--font-mono, monospace);
    line-height: 1.1;
    margin-top: 4px;
}
.kpi-card .kpi-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted, #8b949e);
}
.kpi-card .kpi-icon {
    position: absolute;
    right: 18px;
    top: 18px;
    font-size: 1.75rem;
    opacity: 0.15;
}
.perk-ready-row {
    background: rgba(0, 255, 148, 0.05) !important;
}
.perk-ready-row:hover {
    background: rgba(0, 255, 148, 0.1) !important;
}
.code-chip {
    font-family: var(--font-mono, monospace);
    font-size: 0.95rem;
    color: #00ff94;
    background: rgba(0, 255, 148, 0.08);
    border: 1px solid rgba(0, 255, 148, 0.25);
    padding: 3px 8px;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.2s ease;
}
.code-chip:hover {
    background: rgba(0, 255, 148, 0.2);
    color: #fff;
}
</style>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="mb-1 text-white">
            <i class="fas fa-award me-2" style="color: #f15a24;"></i> Manage Ambassadors
        </h2>
        <p class="text-muted mb-0"><?php echo htmlspecialchars($typeDescription); ?></p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-dark border border-secondary px-3 py-2" style="font-size:0.85rem;">
            <i class="fas fa-database me-1 text-success"></i> <?php echo $kpi['total']; ?> Ambassadors Loaded
        </span>
    </div>
</div>

<?php if (($_GET['notice'] ?? '') === 'duplicate'): ?>
    <div class="alert alert-warning mb-4" role="alert">
        That referral code or email address is already in use. Enter a unique value and try again.
    </div>
<?php endif; ?>

<?php if (!$hasTable): ?>
    <div class="alert alert-warning mb-4" style="background: rgba(255,187,51,0.1); border: 1px solid #ffbb33; color: #ffbb33;">
        <strong>System Alert:</strong> The <code>brand_ambassadors</code> table is missing from your database.
    </div>
<?php else: ?>

    <!-- Executive KPI Metric Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card">
                <i class="fas fa-users kpi-icon text-primary"></i>
                <div class="kpi-label">Ambassadors</div>
                <div class="kpi-num text-white"><?php echo $kpi['total']; ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card">
                <i class="fas fa-user-check kpi-icon text-success"></i>
                <div class="kpi-label">Active Accounts</div>
                <div class="kpi-num text-success"><?php echo $kpi['active']; ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card">
                <i class="fas fa-users-cog kpi-icon text-info"></i>
                <div class="kpi-label">Event Teams</div>
                <div class="kpi-num text-info"><?php echo $kpi['event_teams']; ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card">
                <i class="fas fa-ticket-alt kpi-icon text-warning"></i>
                <div class="kpi-label">Social Passes</div>
                <div class="kpi-num text-warning"><?php echo $kpi['social_passes']; ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card">
                <i class="fas fa-chart-line kpi-icon" style="color:#00ff94;"></i>
                <div class="kpi-label">Total Impact</div>
                <div class="kpi-num" style="color:#00ff94;"><?php echo $kpi['total_impact']; ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card" style="<?php echo $kpi['perks_qualified'] > 0 ? 'border-color: rgba(0,255,148,0.5); box-shadow: 0 0 15px rgba(0,255,148,0.15);' : ''; ?>">
                <i class="fas fa-gift kpi-icon" style="color:#ffbb33;"></i>
                <div class="kpi-label">Perks Qualified</div>
                <div class="kpi-num" style="color: <?php echo $kpi['perks_qualified'] > 0 ? '#00ff94' : '#888'; ?>;">
                    <?php echo $kpi['perks_qualified']; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Ambassador Type Navigation Tabs -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn <?php echo $type === 'all' ? 'btn-neon' : 'btn-outline-light'; ?>" href="manage_ambassadors.php?type=all" style="min-width:180px;">
            <i class="fas fa-globe me-2"></i> All Ambassadors <span class="badge bg-dark ms-1"><?php echo $countsByType['all']; ?></span>
        </a>
        <a class="btn <?php echo $type === 'brand' ? 'btn-neon' : 'btn-outline-light'; ?>" href="manage_ambassadors.php?type=brand" style="min-width:180px;">
            <i class="fas fa-user-tie me-2"></i> Brand Ambassadors <span class="badge bg-dark ms-1"><?php echo $countsByType['brand']; ?></span>
        </a>
        <a class="btn <?php echo $type === 'volunteer' ? 'btn-neon' : 'btn-outline-light'; ?>" href="manage_ambassadors.php?type=volunteer" style="min-width:180px;">
            <i class="fas fa-hands-helping me-2"></i> Volunteers <span class="badge bg-dark ms-1"><?php echo $countsByType['volunteer']; ?></span>
        </a>
    </div>

    <!-- Threshold & Perk Policy Banner -->
    <div class="glass-panel mb-4 p-3" style="background: rgba(2, 14, 30, 0.6); border-left: 4px solid #00c3ff;">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="text-white mb-1"><i class="fas fa-sliders-h me-2 text-info"></i> Perk Qualification Policy</h5>
                <p class="text-muted mb-0" style="font-size:0.85rem;">
                    Default thresholds: <strong>Brand: <?php echo $brandThreshold; ?></strong> approved teams &bull; <strong>Volunteer: <?php echo $volunteerThreshold; ?></strong> approved teams.
                    Manual bonus credits boost approved totals for unlocking rewards.
                </p>
            </div>
            <form method="post" action="ambassador_actions.php" class="d-flex align-items-center gap-2 flex-wrap">
                <input type="hidden" name="action" value="update_threshold">
                <input type="hidden" name="ambassador_type" value="<?php echo htmlspecialchars($type); ?>">
                <select name="target_type" class="form-select form-select-sm" style="width:140px; background:#000; border-color:#444; color:#fff;">
                    <option value="brand" <?php echo ($type === 'brand' || $type === 'all') ? 'selected' : ''; ?>>Brand Target</option>
                    <option value="volunteer" <?php echo ($type === 'volunteer') ? 'selected' : ''; ?>>Volunteer Target</option>
                </select>
                <input type="number" name="threshold" min="1" value="<?php echo ($type === 'volunteer') ? (int)$volunteerThreshold : (int)$brandThreshold; ?>" class="form-control form-control-sm" style="width:80px; background:#000; border-color:#444; color:#fff;" required>
                <button type="submit" class="btn-neon btn-sm">Save</button>
            </form>
        </div>
    </div>

    <!-- Perks Due Notification Alert -->
    <?php if (!empty($perksDue)): ?>
        <div class="alert mb-4" style="background: rgba(0, 255, 148, 0.08); border: 1px solid rgba(0, 255, 148, 0.35); color: #6df7c3; border-radius: 12px;">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h6 class="text-white mb-2"><i class="fas fa-trophy text-warning me-2"></i> Perks Ready for Approval (<?php echo count($perksDue); ?>)</h6>
                    <ul class="mb-0 ps-3" style="font-size: 0.9rem;">
                        <?php foreach ($perksDue as $eligible): ?>
                            <li class="mb-1">
                                <strong class="text-white"><?php echo htmlspecialchars($eligible['name']); ?></strong>
                                (<code style="color:#00ff94;"><?php echo htmlspecialchars($eligible['code']); ?></code>) &mdash;
                                <span class="badge bg-dark border border-secondary"><?php echo ucfirst($eligible['type']); ?></span>
                                Reached <strong><?php echo (int)$eligible['approved']; ?></strong> / <?php echo (int)$eligible['target']; ?> teams approved.
                                <a href="ambassador_actions.php?action=grant_perk&id=<?php echo $eligible['id']; ?>&ambassador_type=<?php echo urlencode($type); ?>" class="badge bg-success text-dark text-decoration-none ms-2" onclick="return confirm('Grant DataCamp Premium perk to <?php echo htmlspecialchars(addslashes($eligible['name'])); ?>?');">
                                    <i class="fas fa-check me-1"></i> Grant Perk
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="d-flex flex-column gap-2">
                    <button id="perkAlertEmailBtn" class="btn btn-sm btn-neon" data-recipient-ids="<?php echo implode(',', array_column($perksDue, 'id')); ?>">
                        <i class="fas fa-paper-plane me-1"></i> Compose Email to Qualified (<?php echo count($perksDue); ?>)
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Table Container -->
    <div class="glass-panel p-4">
        
        <!-- Controls: Live Search, Sorting & Actions -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <!-- Real-time Filter Input -->
                <div class="input-group input-group-sm" style="max-width: 280px;">
                    <span class="input-group-text bg-dark border-secondary text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" id="ambSearchInput" class="form-control" placeholder="Search name, code, email, uni..." style="background:#000; border-color:#444; color:#fff;">
                </div>

                <!-- Sort Control -->
                <form method="get" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                    <select name="sort" id="sort" class="form-select form-select-sm" style="background:#000; border-color:#444; color:#fff; min-width: 160px;" onchange="this.form.submit()">
                        <option value="recent" <?php echo ($sort === 'recent') ? 'selected' : ''; ?>>Most Recent</option>
                        <option value="name_asc" <?php echo ($sort === 'name_asc') ? 'selected' : ''; ?>>Name A-Z</option>
                        <option value="name_desc" <?php echo ($sort === 'name_desc') ? 'selected' : ''; ?>>Name Z-A</option>
                        <option value="referrals_desc" <?php echo ($sort === 'referrals_desc') ? 'selected' : ''; ?>>Max Registrations</option>
                        <option value="referrals_asc" <?php echo ($sort === 'referrals_asc') ? 'selected' : ''; ?>>Min Registrations</option>
                    </select>
                </form>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn-neon btn-sm" href="upload_ambassadors_csv.php?type=<?php echo urlencode($type); ?>">
                    <i class="fas fa-file-upload me-1"></i> Import CSV
                </a>
                <a class="btn-neon btn-sm" href="export_ambassadors_csv.php?type=<?php echo urlencode($type); ?>">
                    <i class="fas fa-file-download me-1"></i> Export CSV
                </a>
                <button id="bulkEmailBtn" class="btn-neon btn-sm" data-type="<?php echo htmlspecialchars($type); ?>">
                    <i class="fas fa-envelope-open me-1"></i> Bulk Credentials
                </button>
                <button id="customEmailBtn" class="btn-neon btn-sm" type="button">
                    <i class="fas fa-pen-to-square me-1"></i> Custom Email
                </button>
                <button class="btn-neon btn-sm" data-bs-toggle="modal" data-bs-target="#addAmbassadorModal">
                    <i class="fas fa-plus-circle me-1"></i> Add Ambassador
                </button>
            </div>
        </div>

        <!-- Table View -->
        <div class="table-responsive">
            <table class="table table-hover text-white align-middle" id="ambassadorsTable">
                <thead>
                    <tr class="border-secondary text-muted" style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em;">
                        <th>#</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Contact</th>
                        <th>Institution</th>
                        <th>Code</th>
                        <th>Status</th>
                        <th class="text-center" title="Total event teams referred / Approved count">Event Teams</th>
                        <th class="text-center" title="Total social passes referred / Approved count">Social Passes</th>
                        <th class="text-center" title="Combined impact: Events + Social + Manual Bonus">Total Impact</th>
                        <?php if ($hasManualCredit): ?>
                            <th class="text-center" title="Manual approved credits granted">Bonus</th>
                        <?php endif; ?>
                        <th class="text-center">Perk Target</th>
                        <th>Payments</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sr = 1;
                    if (!empty($rows)):
                        foreach ($rows as $row):
                            $c = $row['__counts'];
                            $s = $row['__social'];
                            $p = $row['__payments'];
                            $manualCredit = (int)$row['__manual_credit'];
                            $overrideTarget = (int)$row['__perk_override'];
                            $effectiveTarget = (int)$row['__effective_target'];
                            $totalWithManual = (int)$row['__total_with_manual'];
                            $approvedWithManual = (int)$row['__approved_with_manual'];
                            $perkDue = !empty($row['__perk_due']);
                            $perkGranted = (int)$row['__perk_granted'];
                            $perkRequested = (int)$row['__perk_requested'];
                            $ambType = $row['__amb_type'];
                    ?>
                        <tr data-id="<?php echo (int)$row['id']; ?>" class="amb-row <?php echo $perkDue ? 'perk-ready-row' : ''; ?>"
                            data-search="<?php echo htmlspecialchars(strtolower($row['name'] . ' ' . $row['code'] . ' ' . $row['email'] . ' ' . $row['institution'] . ' ' . $row['phone'] . ' ' . $ambType)); ?>">
                            
                            <td class="text-muted small"><?php echo $sr++; ?></td>
                            <td>
                                <strong class="text-white d-block"><?php echo htmlspecialchars($row['name']); ?></strong>
                                <small class="text-muted" style="font-size:0.75rem;">ID: #<?php echo (int)$row['id']; ?></small>
                            </td>
                            <td>
                                <span class="badge <?php echo $ambType === 'volunteer' ? 'bg-secondary' : 'bg-primary'; ?>" style="font-size:0.75rem;">
                                    <?php echo ucfirst($ambType); ?>
                                </span>
                            </td>
                            <td>
                                <small class="d-block text-white"><?php echo htmlspecialchars($row['email']); ?></small>
                                <small class="d-block text-muted" style="font-size:0.78rem;"><?php echo htmlspecialchars($row['phone'] ?: 'No Phone'); ?></small>
                            </td>
                            <td>
                                <span class="small text-muted"><?php echo htmlspecialchars($row['institution'] ?: '&mdash;'); ?></span>
                            </td>
                            <td>
                                <span class="code-chip" title="Click to copy code" onclick="copyCode('<?php echo htmlspecialchars($row['code']); ?>', this)">
                                    <?php echo htmlspecialchars($row['code']); ?>
                                    <i class="fas fa-copy" style="font-size:0.7rem; opacity:0.7;"></i>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $row['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            
                            <!-- Event Teams: Total (Approved) -->
                            <td class="text-center">
                                <span class="badge bg-dark border border-secondary" title="<?php echo (int)$c['approved']; ?> approved out of <?php echo (int)$c['total']; ?>">
                                    <?php echo (int)$c['total']; ?>
                                    <span class="text-success ms-1">(<?php echo (int)$c['approved']; ?>)</span>
                                </span>
                            </td>

                            <!-- Social Passes: Total (Approved) -->
                            <td class="text-center">
                                <span class="badge bg-dark border border-warning" style="color:#ffbb33;" title="<?php echo (int)$s['approved']; ?> approved out of <?php echo (int)$s['total']; ?>">
                                    <?php echo (int)$s['total']; ?>
                                    <span class="text-success ms-1">(<?php echo (int)$s['approved']; ?>)</span>
                                </span>
                            </td>

                            <!-- Total Impact -->
                            <td class="text-center">
                                <span class="badge bg-dark border border-success" title="Combined Total: <?php echo $totalWithManual; ?> | Approved: <?php echo $approvedWithManual; ?>" style="font-size:0.95rem; color:#00ff94;">
                                    <?php echo $totalWithManual; ?>
                                </span>
                            </td>

                            <!-- Manual Bonus -->
                            <?php if ($hasManualCredit): ?>
                                <td class="text-center">
                                    <?php if ($manualCredit > 0): ?>
                                        <span class="badge bg-primary" title="+<?php echo $manualCredit; ?> bonus approved credits">+<?php echo $manualCredit; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">0</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <!-- Perk Target & Status -->
                            <td class="text-center">
                                <div class="small mb-1">
                                    <?php if ($overrideTarget > 0): ?>
                                        <span class="badge bg-info text-dark" title="Custom override goal"><?php echo $overrideTarget; ?> Goal</span>
                                    <?php else: ?>
                                        <span class="badge bg-dark border border-secondary" title="Default"><?php echo $effectiveTarget; ?> Goal</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($perkGranted === 1): ?>
                                    <span class="badge bg-success text-dark" style="font-size:0.7rem;"><i class="fas fa-check-double me-1"></i> Perk Granted</span>
                                <?php elseif ($perkRequested === 1): ?>
                                    <span class="badge bg-warning text-dark" style="font-size:0.7rem;"><i class="fas fa-bell me-1"></i> Requested</span>
                                <?php elseif ($perkDue): ?>
                                    <span class="badge text-dark" style="background:#00ff94 !important; font-size:0.7rem;"><i class="fas fa-trophy me-1"></i> Goal Met!</span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:0.75rem;"><?php echo $approvedWithManual; ?> / <?php echo $effectiveTarget; ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Payments Breakdown -->
                            <td>
                                <div class="d-flex flex-column gap-1" style="font-size:0.75rem;">
                                    <span class="text-success"><i class="fas fa-circle me-1" style="font-size:0.5rem;"></i> Paid: <?php echo $p['confirmed']; ?></span>
                                    <span class="text-info"><i class="fas fa-circle me-1" style="font-size:0.5rem;"></i> Pending: <?php echo $p['submitted']; ?></span>
                                    <span class="text-warning"><i class="fas fa-circle me-1" style="font-size:0.5rem;"></i> No Proof: <?php echo $p['not_submitted']; ?></span>
                                </div>
                            </td>

                            <!-- Action Buttons -->
                            <td class="text-end text-nowrap">
                                <!-- Send Credentials Email -->
                                <button type="button" class="btn btn-sm btn-outline-light send-cred" data-id="<?php echo $row['id']; ?>" data-type="<?php echo htmlspecialchars($type); ?>" title="Send Login Credentials to <?php echo htmlspecialchars($row['email']); ?>">
                                    <i class="fas fa-paper-plane"></i>
                                </button>

                                <!-- Grant Perk Button -->
                                <?php if ($perkGranted === 0 && ($perkDue || $perkRequested === 1)): ?>
                                    <a href="ambassador_actions.php?action=grant_perk&id=<?php echo $row['id']; ?>&ambassador_type=<?php echo urlencode($type); ?>" class="btn btn-sm btn-outline-success" title="Grant DataCamp Premium Perk" onclick="return confirm('Grant DataCamp Premium perk to <?php echo htmlspecialchars(addslashes($row['name'])); ?>?');">
                                        <i class="fas fa-trophy"></i>
                                    </a>
                                <?php endif; ?>

                                <!-- Edit Ambassador -->
                                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#editAmbassadorModal"
                                    data-id="<?php echo $row['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                    data-type="<?php echo htmlspecialchars($ambType); ?>"
                                    data-email="<?php echo htmlspecialchars($row['email']); ?>"
                                    data-phone="<?php echo htmlspecialchars($row['phone']); ?>"
                                    data-inst="<?php echo htmlspecialchars($row['institution']); ?>"
                                    data-code="<?php echo htmlspecialchars($row['code']); ?>"
                                    data-status="<?php echo htmlspecialchars($row['status']); ?>"
                                    data-manual="<?php echo (int)$manualCredit; ?>"
                                    data-perk="<?php echo (int)$overrideTarget; ?>"
                                    title="Edit Ambassador">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <!-- Delete Ambassador -->
                                <a href="ambassador_actions.php?action=delete&id=<?php echo $row['id']; ?>&ambassador_type=<?php echo urlencode($type); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this ambassador permanently?');" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr id="emptyTableRow">
                            <td colspan="<?php echo 13 + ($hasManualCredit ? 1 : 0); ?>" class="text-center text-muted p-5">
                                <i class="fas fa-user-slash fa-2x mb-3 d-block opacity-50"></i>
                                No ambassadors found for this category.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr id="noSearchResultsRow" style="display:none;">
                        <td colspan="<?php echo 13 + ($hasManualCredit ? 1 : 0); ?>" class="text-center text-muted p-5">
                            <i class="fas fa-search fa-2x mb-3 d-block opacity-50"></i>
                            No matching ambassadors found for your search query.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- 1. ADD AMBASSADOR MODAL -->
<div class="modal fade" id="addAmbassadorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background:#111; border:1px solid #333; color:#fff;">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2 text-primary"></i> Add New Ambassador</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="ambassador_actions.php" id="addForm">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="ambassador_type" value="<?php echo htmlspecialchars($type); ?>">

                    <div class="mb-3">
                        <label class="form-label small text-muted">Ambassador Role</label>
                        <select class="form-select" name="ambassador_type" style="background:#000; border-color:#444; color:#fff;">
                            <option value="brand" <?php echo ($type === 'brand' || $type === 'all') ? 'selected' : ''; ?>>Brand Ambassador</option>
                            <option value="volunteer" <?php echo ($type === 'volunteer') ? 'selected' : ''; ?>>Volunteer Ambassador</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">Full Name *</label>
                        <input class="form-control" name="name" placeholder="e.g. Sarah Ahmed" style="background:#000; border-color:#444; color:#fff;" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">Email Address *</label>
                        <input class="form-control" name="email" type="email" placeholder="sarah@example.com" style="background:#000; border-color:#444; color:#fff;" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Phone Number</label>
                            <input class="form-control" name="phone" placeholder="03001234567" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Institution</label>
                            <input class="form-control" name="institution" placeholder="NED University" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Referral Code *</label>
                            <input class="form-control" name="code" placeholder="SARAH01" style="background:#000; border-color:#444; color:#fff; text-transform:uppercase;" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Status</label>
                            <select class="form-select" name="status" style="background:#000; border-color:#444; color:#fff;">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">Initial Password (Leave blank for auto-generate)</label>
                        <input class="form-control" name="password" type="text" placeholder="Auto-generated if empty" style="background:#000; border-color:#444; color:#fff;">
                        <small class="text-muted" style="font-size:0.75rem;">If left empty, a secure password will be created automatically.</small>
                    </div>

                    <?php if ($hasManualCredit): ?>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Initial Bonus Credits</label>
                            <input class="form-control" name="manual_registration_credit" type="number" min="0" value="0" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                    <?php endif; ?>

                    <?php if ($hasPerkOverride): ?>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Custom Perk Goal (0 = Use default)</label>
                            <input class="form-control" name="perk_threshold_override" type="number" min="0" value="0" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn-neon btn-sm" onclick="document.getElementById('addForm').submit()">Save Ambassador</button>
            </div>
        </div>
    </div>
</div>

<!-- 2. EDIT AMBASSADOR MODAL -->
<div class="modal fade" id="editAmbassadorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background:#111; border:1px solid #333; color:#fff;">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-user-edit me-2 text-info"></i> Edit Ambassador</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="ambassador_actions.php" id="editAmbassadorForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="ambassador_type" value="<?php echo htmlspecialchars($type); ?>">

                    <div class="mb-3">
                        <label class="form-label small text-muted">Ambassador Role</label>
                        <select class="form-select" name="ambassador_type" id="edit_type" style="background:#000; border-color:#444; color:#fff;">
                            <option value="brand">Brand Ambassador</option>
                            <option value="volunteer">Volunteer Ambassador</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">Name *</label>
                        <input class="form-control" name="name" id="edit_name" style="background:#000; border-color:#444; color:#fff;" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">Email *</label>
                        <input class="form-control" name="email" id="edit_email" type="email" style="background:#000; border-color:#444; color:#fff;" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Phone</label>
                            <input class="form-control" name="phone" id="edit_phone" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Institution</label>
                            <input class="form-control" name="institution" id="edit_institution" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Referral Code *</label>
                            <input class="form-control" name="code" id="edit_code" style="background:#000; border-color:#444; color:#fff; text-transform:uppercase;" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Status</label>
                            <select class="form-select" name="status" id="edit_status" style="background:#000; border-color:#444; color:#fff;">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">Reset Password (leave empty to keep existing)</label>
                        <input class="form-control" name="password" type="text" placeholder="Enter new password or leave blank" style="background:#000; border-color:#444; color:#fff;">
                    </div>

                    <?php if ($hasManualCredit): ?>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Manual Bonus Credits</label>
                            <input class="form-control" name="manual_registration_credit" id="edit_manual" type="number" min="0" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                    <?php endif; ?>

                    <?php if ($hasPerkOverride): ?>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Custom Perk Goal (0 = global default)</label>
                            <input class="form-control" name="perk_threshold_override" id="edit_perk" type="number" min="0" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button class="btn-neon btn-sm" onclick="document.getElementById('editAmbassadorForm').submit()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- 3. CUSTOM EMAIL COMPOSER MODAL -->
<div class="modal fade" id="customEmailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:#111; border:1px solid #333; color:#fff;">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-envelope-open-text me-2 text-warning"></i> Send Custom Email Broadcast</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="customEmailForm" method="post">
                <div class="modal-body">
                    <input type="hidden" name="ambassador_type" value="<?php echo htmlspecialchars($type); ?>">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0 small text-muted">Recipients</label>
                            <div>
                                <button type="button" class="btn btn-link btn-sm text-info p-0 me-2" id="selectAllRecipientsBtn">Select All</button>
                                <button type="button" class="btn btn-link btn-sm text-muted p-0" id="clearAllRecipientsBtn">Clear</button>
                            </div>
                        </div>
                        <select class="form-select" name="recipient_ids[]" id="customEmailRecipients" multiple required style="min-height: 140px; background:#000; border-color:#444; color:#fff;">
                            <?php if (!empty($rows)): ?>
                                <?php foreach ($rows as $rowOption): ?>
                                    <option value="<?php echo (int)$rowOption['id']; ?>" data-email="<?php echo htmlspecialchars($rowOption['email']); ?>" <?php echo !empty($rowOption['__perk_due']) ? 'data-perk-due="1"' : ''; ?>>
                                        <?php echo htmlspecialchars($rowOption['name']); ?> &mdash; <?php echo htmlspecialchars($rowOption['code']); ?> (<?php echo ucfirst($rowOption['__amb_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted d-block mt-1" id="customEmailSelectedCount" style="font-size:0.75rem;">Hold Ctrl / Cmd to select multiple ambassadors.</small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Email Subject *</label>
                            <input class="form-control" type="text" name="subject" placeholder="Update for SENTEC Ambassadors" style="background:#000; border-color:#444; color:#fff;" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Header Badge</label>
                            <input class="form-control" type="text" name="title" value="Ambassador Program" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Headline *</label>
                            <input class="form-control" type="text" name="heading" placeholder="Exciting Announcement!" style="background:#000; border-color:#444; color:#fff;" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Greeting Line</label>
                            <input class="form-control" type="text" name="greeting" value="Hello {{name}}," style="background:#000; border-color:#444; color:#fff;">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label small text-muted">Message Body *</label>
                        <textarea class="form-control" name="body" rows="5" placeholder="Write message here. Supported tags: {{name}}, {{code}}." style="background:#000; border-color:#444; color:#fff;" required></textarea>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">CTA Button Label (optional)</label>
                            <input class="form-control" type="text" name="cta_label" placeholder="View Ambassador Portal" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">CTA Button URL (optional)</label>
                            <input class="form-control" type="url" name="cta_url" placeholder="https://" style="background:#000; border-color:#444; color:#fff;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="customEmailSendBtn" class="btn-neon btn-sm">Send Broadcast</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
// Copy Code to Clipboard with feedback tooltip
function copyCode(code, el) {
    if (!code) return;
    navigator.clipboard.writeText(code).then(function() {
        const original = el.innerHTML;
        el.innerHTML = '<i class="fas fa-check text-success"></i> Copied!';
        setTimeout(function() {
            el.innerHTML = original;
        }, 1500);
    }).catch(function() {
        alert('Copied: ' + code);
    });
}

document.addEventListener("DOMContentLoaded", function() {

    // 1. Live Instant Table Search Filter
    const searchInput = document.getElementById('ambSearchInput');
    const tableRows = document.querySelectorAll('.amb-row');
    const noResultsRow = document.getElementById('noSearchResultsRow');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            let visibleCount = 0;

            tableRows.forEach(row => {
                const text = row.getAttribute('data-search') || '';
                if (!query || text.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noResultsRow) {
                noResultsRow.style.display = (visibleCount === 0 && query) ? '' : 'none';
            }
        });
    }

    // 2. BULK EMAIL BUTTON
    const bulkBtn = document.getElementById('bulkEmailBtn');
    if (bulkBtn) {
        bulkBtn.addEventListener('click', function() {
            if (!confirm('Send login credentials to ALL active ambassadors in this view?')) return;
            
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
                alert('Sent: ' + d.sent + ' emails.\n' + (d.errors && d.errors.length ? 'Errors:\n' + d.errors.join('\n') : ''));
                location.reload();
            })
            .catch(e => {
                alert('Bulk send failed: ' + e);
                location.reload();
            });
        });
    }

    // 3. SINGLE EMAIL BUTTON
    document.querySelectorAll('.send-cred').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type') || '';
            if (!id) return;

            if (!confirm('Send login credentials to this ambassador?')) return;

            const icon = this.querySelector('i');
            const originalClass = icon.className;
            icon.className = 'fas fa-spinner fa-spin';

            fetch('email_ambassadors.php', {
                method: 'POST', 
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
                body: 'mode=single&ids[]=' + encodeURIComponent(id) + (type ? '&ambassador_type=' + encodeURIComponent(type) : '')
            })
            .then(r => r.json())
            .then(d => {
                if (d.sent > 0) {
                    alert('Credentials Email Sent Successfully!');
                } else {
                    alert('Failed: ' + (d.errors.join('\n') || 'Unknown error'));
                }
                icon.className = originalClass;
            })
            .catch(e => {
                alert('Network error: ' + e);
                icon.className = originalClass;
            });
        });
    });

    // 4. CUSTOM EMAIL COMPOSER
    const customEmailBtn = document.getElementById('customEmailBtn');
    const perkAlertEmailBtn = document.getElementById('perkAlertEmailBtn');
    const customEmailModalEl = document.getElementById('customEmailModal');
    const customEmailForm = document.getElementById('customEmailForm');
    const recipientsSelect = document.getElementById('customEmailRecipients');
    const selectedCountEl = document.getElementById('customEmailSelectedCount');
    const selectAllBtn = document.getElementById('selectAllRecipientsBtn');
    const clearAllBtn = document.getElementById('clearAllRecipientsBtn');
    let customEmailModal = null;

    function updateSelectedCount() {
        if (!recipientsSelect || !selectedCountEl) return;
        const selected = Array.from(recipientsSelect.options || []).filter(opt => opt.selected).length;
        const total = recipientsSelect.options ? recipientsSelect.options.length : 0;
        const message = total === 0
            ? 'No ambassadors available for selection.'
            : (selected === 0
                ? 'No recipients selected out of ' + total + '.'
                : selected + ' selected out of ' + total + '.');
        selectedCountEl.textContent = message;
    }

    function setRecipientsByIds(ids) {
        if (!recipientsSelect) return;
        const idSet = new Set(ids);
        Array.from(recipientsSelect.options || []).forEach(opt => {
            opt.selected = idSet.has(opt.value);
        });
        updateSelectedCount();
    }

    if (selectAllBtn && recipientsSelect) {
        selectAllBtn.addEventListener('click', function() {
            Array.from(recipientsSelect.options).forEach(opt => opt.selected = true);
            updateSelectedCount();
        });
    }

    if (clearAllBtn && recipientsSelect) {
        clearAllBtn.addEventListener('click', function() {
            Array.from(recipientsSelect.options).forEach(opt => opt.selected = false);
            updateSelectedCount();
        });
    }

    if (customEmailModalEl && typeof bootstrap !== 'undefined') {
        customEmailModal = new bootstrap.Modal(customEmailModalEl);
    }

    if (recipientsSelect) {
        recipientsSelect.addEventListener('change', updateSelectedCount);
        updateSelectedCount();
    }

    if (customEmailBtn && customEmailModal && customEmailForm) {
        customEmailBtn.addEventListener('click', function() {
            customEmailForm.reset();
            setRecipientsByIds([]);
            customEmailModal.show();
        });
    }

    if (perkAlertEmailBtn && customEmailModal && customEmailForm) {
        perkAlertEmailBtn.addEventListener('click', function() {
            const ids = (this.getAttribute('data-recipient-ids') || '')
                .split(',')
                .map(function(id) { return id.trim(); })
                .filter(Boolean);
            customEmailForm.reset();
            setRecipientsByIds(ids);
            customEmailModal.show();
        });
    }

    if (customEmailForm) {
        customEmailForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!recipientsSelect || Array.from(recipientsSelect.options || []).filter(opt => opt.selected).length === 0) {
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
            .then(res => res.json())
            .then(data => {
                const sent = data && typeof data.sent !== 'undefined' ? data.sent : 0;
                const errors = data && Array.isArray(data.errors) && data.errors.length ? '\nErrors:\n' + data.errors.join('\n') : '';
                alert('Sent: ' + sent + ' emails.' + errors);
                if (customEmailModal) {
                    customEmailModal.hide();
                }
                customEmailForm.reset();
                setRecipientsByIds([]);
            })
            .catch(err => {
                alert('Custom email failed: ' + err);
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Send Broadcast';
                }
            });
        });
    }

    // 5. POPULATE EDIT MODAL
    const editModal = document.getElementById('editAmbassadorModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('edit_id').value = button.getAttribute('data-id') || '';
            document.getElementById('edit_name').value = button.getAttribute('data-name') || '';
            document.getElementById('edit_email').value = button.getAttribute('data-email') || '';
            document.getElementById('edit_phone').value = button.getAttribute('data-phone') || '';
            document.getElementById('edit_institution').value = button.getAttribute('data-inst') || '';
            document.getElementById('edit_code').value = button.getAttribute('data-code') || '';
            document.getElementById('edit_status').value = button.getAttribute('data-status') || 'active';
            
            const typeField = document.getElementById('edit_type');
            if (typeField) {
                typeField.value = button.getAttribute('data-type') || 'brand';
            }
            const manualField = document.getElementById('edit_manual');
            if (manualField) {
                manualField.value = button.getAttribute('data-manual') || 0;
            }
            const perkField = document.getElementById('edit_perk');
            if (perkField) {
                perkField.value = button.getAttribute('data-perk') || 0;
            }
        });
    }
});
</script>
