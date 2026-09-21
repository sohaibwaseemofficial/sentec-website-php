<?php
// Error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'header.php';
include '../db_connection.php';
require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../election_settings.php';

$hasSocialRegistrations = false;
try {
    $checkSocial = $conn->query("SHOW TABLES LIKE 'social_registrations'");
    $hasSocialRegistrations = $checkSocial && $checkSocial->num_rows > 0;
} catch (Exception $e) {
    $hasSocialRegistrations = false;
}

function env_val($key, $default = '') {
    return getenv($key) !== false ? getenv($key) : $default;
}

// 1. AUTOMATED UPDATES (Keep your logic)
// Update past events
$conn->query("UPDATE events SET status = 'past' WHERE event_date < NOW() AND status = 'upcoming'");
// Update upcoming events
$conn->query("UPDATE events SET status = 'upcoming' WHERE event_date > NOW() AND status = 'past'");

// 2. FETCH LIVE STATISTICS - with error handling
$stats = [];
$socialStats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
];

$result = $conn->query("SELECT COUNT(*) as count FROM events");
$stats['total_events'] = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM events WHERE status = 'upcoming'");
$stats['upcoming_events'] = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM events WHERE status = 'past'");
$stats['past_events'] = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM gallery");
$stats['gallery_items'] = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM team_members");
$stats['team_members'] = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM partners");
$stats['partners'] = $result ? $result->fetch_assoc()['count'] : 0;

// Registrations Stats
$result = $conn->query("SELECT COUNT(*) as count FROM event_registrations");
$stats['total_registrations'] = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM event_registrations WHERE status = 'pending'");
$stats['pending_registrations'] = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM event_registrations WHERE status = 'approved'");
$stats['approved_registrations'] = $result ? $result->fetch_assoc()['count'] : 0;

$electionStats = [
    'portal_visible' => election_portal_visible($conn),
    'portal_open' => election_portal_open($conn),
    'results_visible' => election_portal_results_visible($conn),
];

if ($hasSocialRegistrations) {
    $result = $conn->query("SELECT COUNT(*) as count FROM social_registrations");
    $socialStats['total'] = $result ? (int)$result->fetch_assoc()['count'] : 0;

    $result = $conn->query("SELECT COUNT(*) as count FROM social_registrations WHERE status = 'pending'");
    $socialStats['pending'] = $result ? (int)$result->fetch_assoc()['count'] : 0;

    $result = $conn->query("SELECT COUNT(*) as count FROM social_registrations WHERE status IN ('approved','confirmed')");
    $socialStats['approved'] = $result ? (int)$result->fetch_assoc()['count'] : 0;
}

// Portal signups (users table)
$signupRow = $conn->query("SELECT COUNT(*) as count FROM users");
$stats['total_signups'] = $signupRow ? $signupRow->fetch_assoc()['count'] : 0;

// ========== NEW: ANALYTICS DATA ==========

// Registration Trends (Last 30 days)
$trendData = [];
$trendQuery = $conn->query("
    SELECT DATE(created_at) as reg_date, COUNT(*) as count 
    FROM event_registrations 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at) 
    ORDER BY reg_date ASC
");
if ($trendQuery) {
    while ($row = $trendQuery->fetch_assoc()) {
        $trendData[$row['reg_date']] = (int)$row['count'];
    }
}

// Fill in missing dates with 0
$last30Days = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $last30Days[$date] = isset($trendData[$date]) ? $trendData[$date] : 0;
}

// Ambassador Leaderboard (Top 10) - includes social registrations when available
$leaderboard = [];
$socialSelect = ", 0 AS total_social_referrals, 0 AS approved_social_referrals";
$socialJoin = '';
$orderBy = "ORDER BY COALESCE(er.total_referrals, 0) DESC";

if ($hasSocialRegistrations) {
    $socialSelect = ",
        COALESCE(sr.total_social_referrals, 0) AS total_social_referrals,
        COALESCE(sr.approved_social_referrals, 0) AS approved_social_referrals
    ";
    $socialJoin = "
        LEFT JOIN (
            SELECT ambassador_code COLLATE utf8mb4_unicode_ci AS code,
                   COUNT(*) AS total_social_referrals,
                   SUM(CASE WHEN status IN ('approved','confirmed') THEN 1 ELSE 0 END) AS approved_social_referrals
            FROM social_registrations
            WHERE ambassador_code IS NOT NULL AND ambassador_code != ''
            GROUP BY ambassador_code COLLATE utf8mb4_unicode_ci
        ) sr ON sr.code = ba.code COLLATE utf8mb4_unicode_ci
    ";
    $orderBy = "ORDER BY (COALESCE(er.total_referrals, 0) + COALESCE(sr.total_social_referrals, 0)) DESC";
}

$leaderSql = "
    SELECT ba.id, ba.name, ba.code, ba.institution,
           COALESCE(er.total_referrals, 0) as total_referrals,
           COALESCE(er.approved_referrals, 0) as approved_referrals
           $socialSelect
    FROM brand_ambassadors ba
    LEFT JOIN (
        SELECT brand_ambassador_code COLLATE utf8mb4_unicode_ci AS code,
               COUNT(*) as total_referrals,
               SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_referrals
        FROM event_registrations
        WHERE brand_ambassador_code IS NOT NULL AND brand_ambassador_code != ''
        GROUP BY brand_ambassador_code COLLATE utf8mb4_unicode_ci
    ) er ON er.code = ba.code COLLATE utf8mb4_unicode_ci
    $socialJoin
    $orderBy
    LIMIT 10
";
$leaderQuery = $conn->query($leaderSql);
if ($leaderQuery) {
    while ($row = $leaderQuery->fetch_assoc()) {
        $eventTotal = (int)$row['total_referrals'];
        $socialTotal = (int)($row['total_social_referrals'] ?? 0);
        $row['combined_total'] = $eventTotal + $socialTotal;
        $leaderboard[] = $row;
    }
}

// Institution Breakdown - check column name
$institutionData = [];
$instQuery = $conn->query("
    SELECT institution_type as inst, COUNT(*) as count 
    FROM event_registrations 
    WHERE institution_type IS NOT NULL AND institution_type != ''
    GROUP BY institution_type 
    ORDER BY count DESC
    LIMIT 8
");
if ($instQuery) {
    while ($row = $instQuery->fetch_assoc()) {
        $label = $row['inst'] ?: 'Unknown';
        $institutionData[$label] = (int)$row['count'];
    }
}

// Recent Notifications (New registrations in last 24 hours)
$recentRegistrations = [];
$notifQuery = $conn->query("
    SELECT id, team_name, participant1_name, created_at, status
    FROM event_registrations 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");
if ($notifQuery) {
    while ($row = $notifQuery->fetch_assoc()) {
        $recentRegistrations[] = $row;
    }
}
$newRegCount = count($recentRegistrations);

// Fetch Lists
$upcoming_events = $conn->query("SELECT * FROM events WHERE status = 'upcoming' ORDER BY event_date ASC LIMIT 3");
$past_events = $conn->query("SELECT * FROM events WHERE status = 'past' ORDER BY event_date DESC LIMIT 3");
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h2><i class="fas fa-tachometer-alt me-2"></i> Command Center</h2>
        <p>Welcome back, Admin. Here is your society's live status.</p>
    </div>
    
    <!-- Notification Bell -->
    <div class="position-relative">
        <button class="btn btn-outline-light position-relative" id="notificationBell" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 50%; width: 50px; height: 50px;">
            <i class="fas fa-bell"></i>
            <?php if ($newRegCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                    <?php echo $newRegCount; ?>
                </span>
            <?php endif; ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" style="min-width: 350px; background: #111; border: 1px solid #333; max-height: 400px; overflow-y: auto;">
            <li class="dropdown-header text-white border-bottom border-secondary pb-2 mb-2">
                <i class="fas fa-bell me-2" style="color: var(--accent);"></i> Recent Notifications
            </li>
            <?php if (empty($recentRegistrations)): ?>
                <li class="px-3 py-2 text-muted text-center">No new registrations in the last 24 hours.</li>
            <?php else: ?>
                <?php foreach ($recentRegistrations as $notif): ?>
                    <li>
                        <a class="dropdown-item d-flex align-items-start gap-2 py-2" href="manage_registrations" style="background: transparent; color: #ccc;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px; background: rgba(241,90,36,0.15);">
                                <i class="fas fa-user-plus" style="color: var(--accent);"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold text-white" style="font-size: 0.9rem;"><?php echo htmlspecialchars($notif['team_name']); ?></div>
                                <small class="text-muted">by <?php echo htmlspecialchars($notif['participant1_name']); ?></small>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="text-muted"><?php echo date('M j, g:i A', strtotime($notif['created_at'])); ?></small>
                                    <span class="badge <?php echo $notif['status'] === 'pending' ? 'bg-warning' : ($notif['status'] === 'approved' ? 'bg-success' : 'bg-secondary'); ?>" style="font-size: 0.65rem;">
                                        <?php echo ucfirst($notif['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
            <li class="border-top border-secondary mt-2 pt-2 text-center">
                <a class="dropdown-item text-center" href="manage_registrations" style="color: var(--accent);">View All Registrations</a>
            </li>
        </ul>
    </div>
</div>

<!-- Push Notification Permission Banner -->
<div id="pushNotifBanner" class="alert d-none mb-4" style="background: rgba(0,255,148,0.1); border: 1px solid var(--accent); color: #fff;">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-bell me-2" style="color: var(--accent);"></i>
            <strong>Enable Push Notifications</strong> — Get instant alerts when new teams register.
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-light" id="enablePushBtn">Enable</button>
            <button class="btn btn-sm btn-outline-secondary" id="dismissPushBtn">Dismiss</button>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-md-3">
        <div class="glass-panel text-center p-4 h-100" style="border-color: #ffbb33;">
            <i class="fas fa-user-clock mb-3" style="font-size: 2.5rem; color: #ffbb33;"></i>
            <h1 style="color: #fff; font-size: 3.5rem; margin: 0;"><?php echo $stats['pending_registrations']; ?></h1>
            <p style="color: #ffbb33;">Pending Reviews</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="glass-panel text-center p-4 h-100" style="border-color: var(--accent);">
            <i class="fas fa-check-circle mb-3" style="font-size: 2.5rem; color: var(--accent);"></i>
            <h1 style="color: #fff; font-size: 3.5rem; margin: 0;"><?php echo $stats['approved_registrations']; ?></h1>
            <p style="color: var(--accent);">Approved Teams</p>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-box">
            <h3 style="color: #fff;"><?php echo $stats['upcoming_events']; ?></h3>
            <p>Upcoming Events</p>
        </div>
        <div class="stat-box mt-3">
            <h3 style="color: #fff;"><?php echo $stats['team_members']; ?></h3>
            <p>Team Members</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-box">
            <h3 style="color: #fff;"><?php echo $stats['total_registrations']; ?></h3>
            <p>Total Registrations</p>
        </div>
        <div class="stat-box mt-3">
            <h3 style="color: #fff;"><?php echo $stats['gallery_items']; ?></h3>
            <p>Gallery Photos</p>
        </div>
    </div>
</div>

<?php if ($hasSocialRegistrations): ?>
<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="glass-panel text-center p-4 h-100" style="border-color: #7c4dff;">
            <i class="fas fa-users mb-3" style="font-size: 2.5rem; color: #7c4dff;"></i>
            <h1 style="color: #fff; font-size: 3.5rem; margin: 0;"><?php echo $socialStats['total']; ?></h1>
            <p style="color: #7c4dff;">Social Guests</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-panel text-center p-4 h-100" style="border-color: #ffbb33;">
            <i class="fas fa-hourglass-half mb-3" style="font-size: 2.5rem; color: #ffbb33;"></i>
            <h1 style="color: #fff; font-size: 3.5rem; margin: 0;"><?php echo $socialStats['pending']; ?></h1>
            <p style="color: #ffbb33;">Awaiting Approval</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-panel text-center p-4 h-100" style="border-color: var(--accent);">
            <i class="fas fa-user-check mb-3" style="font-size: 2.5rem; color: var(--accent);"></i>
            <h1 style="color: #fff; font-size: 3.5rem; margin: 0;"><?php echo $socialStats['approved']; ?></h1>
            <p style="color: var(--accent);">Approved / Confirmed</p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="glass-panel mb-5">
    <h4 class="text-white mb-3"><i class="fas fa-envelope me-2" style="color: var(--accent);"></i> Email Status</h4>
    <?php
    $smtpHost = env_val('SMTP_HOST');
    $smtpUser = env_val('SMTP_USERNAME');
    $smtpPass = env_val('SMTP_PASSWORD');
    $smtpPort = env_val('SMTP_PORT', '');
    $smtpSecure = env_val('SMTP_SECURE', '');
    $adminEmail = env_val('ADMIN_EMAIL', '');
        $senderEmail = env_val('FROM_EMAIL', 'neduetsentec@gmail.com');
        $senderName = env_val('FROM_NAME', 'SENTEC');

    $smtpConfigured = ($smtpHost && $smtpUser && $smtpPass);

    $logPath = __DIR__ . '/../storage/contact_messages.log';
        $logPath = __DIR__ . '/../storage/mail.log';
    $logCount = 0;
    $latestLogs = [];
    if (file_exists($logPath)) {
        $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            $logCount = count($lines);
            $latestLogs = array_slice($lines, max(0, $logCount - 5));
        }
    }
    ?>
    <div class="row g-3">
        <div class="col-md-3">
            <div class="stat-box">
                <h3 style="color: #fff;"><?php echo $smtpConfigured ? 'Yes' : 'No'; ?></h3>
                <p>SMTP Configured</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                    <h3 style="color: #fff; font-size: 1.1rem; word-break: break-all;">
                        <?php echo htmlspecialchars($senderEmail ?: '—'); ?>
                    </h3>
                    <p>Sender Email</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <h3 style="color: #fff;"><?php echo $logCount; ?></h3>
                    <p>Mail Log Entries</p>
            </div>
        </div>
        <div class="col-md-3">
            <form method="post" action="send_test_email.php" class="d-flex gap-2 align-items-center">
                <input type="email" name="to" placeholder="Enter email to send test" required class="form-control">
                <button type="submit" class="btn-neon" style="white-space:nowrap;">Send Test</button>
            </form>
            <small class="text-muted d-block mt-1">Sends a test email using current SMTP.</small>
        </div>
    </div>
    <?php if (!empty($latestLogs)) : ?>
        <details class="mt-3">
            <summary class="text-muted">Latest mail log entries</summary>
            <pre style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 8px; color: #cfd3dc; white-space: pre-wrap;">
<?php echo htmlspecialchars(implode("\n", $latestLogs)); ?>
            </pre>
        </details>
    <?php else: ?>
            <p class="text-muted mt-2">No recent mail log entries.</p>
    <?php endif; ?>
</div>

<div class="glass-panel mb-5">
    <h4 class="text-white mb-4"><i class="fas fa-bolt text-warning me-2"></i> Quick Actions</h4>
    <div class="row g-3">
        <div class="col-md-3">
            <a href="add_event" class="btn-neon w-100 text-center" style="padding: 20px;">
                <i class="fas fa-plus-circle fa-2x mb-2 d-block"></i> Add Event
            </a>
        </div>
        <div class="col-md-3">
            <a href="manage_registrations" class="btn-neon w-100 text-center" style="padding: 20px; border-color: #ffbb33; color: #ffbb33;">
                <i class="fas fa-clipboard-check fa-2x mb-2 d-block"></i> Review Applications
            </a>
        </div>
        <div class="col-md-3">
            <a href="manage_team" class="btn-neon w-100 text-center" style="padding: 20px; border-color: #00d2ff; color: #00d2ff;">
                <i class="fas fa-users fa-2x mb-2 d-block"></i> Manage Team
            </a>
        </div>
        <div class="col-md-3">
            <a href="manage_gallery" class="btn-neon w-100 text-center" style="padding: 20px; border-color: #ff5555; color: #ff5555;">
                <i class="fas fa-images fa-2x mb-2 d-block"></i> Upload Photos
            </a>
        </div>
        <div class="col-md-3">
            <a href="manage_ambassadors" class="btn-neon w-100 text-center" style="padding: 20px; border-color: #7c4dff; color: #7c4dff;">
                <i class="fas fa-user-tie fa-2x mb-2 d-block"></i> Manage Ambassadors
            </a>
        </div>
        <div class="col-md-3">
            <a href="manage_elections" class="btn-neon w-100 text-center" style="padding: 20px; border-color: #00d2ff; color: #00d2ff;">
                <i class="fas fa-vote-yea fa-2x mb-2 d-block"></i> Manage Elections
            </a>
        </div>
    </div>
</div>

<!-- ========== ANALYTICS SECTION ========== -->
<div class="row g-4 mb-5">
    <!-- Registration Trends Chart -->
    <div class="col-lg-8">
        <div class="glass-panel" style="min-height:160px; max-height:200px; padding:12px; overflow:hidden;">
            <div class="d-flex justify-content-between align-items-center mb-3"> 
                <h4 class="text-white m-0"><i class="fas fa-chart-line me-2" style="color: var(--accent);"></i> Registration Trends</h4>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-light active" id="btn7Days">7 Days</button>
                    <button type="button" class="btn btn-outline-light" id="btn30Days">30 Days</button>
                </div>
            </div>
            <div style="height:140px; max-height:160px;">
                <canvas id="trendsChart" style="width:100%; height:100%;"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Institution Breakdown -->
    <div class="col-lg-4">
        <div class="glass-panel" style="min-height:160px; max-height:200px; padding:12px; overflow:hidden;">
            <h4 class="text-white mb-3"><i class="fas fa-university me-2" style="color: #00d2ff;"></i> By Institution Type</h4>
            <div style="height:140px; max-height:160px;">
                <canvas id="institutionChart" style="width:100%; height:100%;"></canvas>
            </div>
            <div class="mt-2" id="institutionLegend"></div>
        </div>
    </div>
</div>

<!-- Ambassador Leaderboard -->
<div class="glass-panel mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white m-0"><i class="fas fa-trophy me-2" style="color: gold;"></i> Ambassador Leaderboard</h4>
        <a href="manage_ambassadors" class="btn btn-sm btn-outline-light">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0" style="background: transparent;">
            <thead>
                <link rel="icon" href="favicon2.png" type="png">
                <link rel="shortcut icon" href="favicon2.png" type="png">
                <link rel="apple-touch-icon" href="favicon2.png">
                <tr style="border-bottom: 2px solid var(--accent);">
                    <th style="width: 60px;">Rank</th>
                    <th>Ambassador</th>
                    <th>Institution</th>
                    <th>Code</th>
                    <th class="text-center">Event Teams</th>
                    <th class="text-center">Social Guests</th>
                    <th class="text-center">Approved (Events)</th>
                    <th class="text-center">Impact</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaderboard)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No ambassador data available.</td></tr>
                <?php else: ?>
                    <?php $rank = 1; foreach ($leaderboard as $amb): 
                        $eventTotal = (int)$amb['total_referrals'];
                        $eventApproved = (int)$amb['approved_referrals'];
                        $socialTotal = (int)($amb['total_social_referrals'] ?? 0);
                        $socialApproved = (int)($amb['approved_social_referrals'] ?? 0);
                        $combinedImpact = (int)($amb['combined_total'] ?? ($eventTotal + $socialTotal));
                    ?>
                        <tr>
                            <td>
                                <?php if ($rank == 1): ?>
                                    <span style="font-size: 1.5rem;">🥇</span>
                                <?php elseif ($rank == 2): ?>
                                    <span style="font-size: 1.5rem;">🥈</span>
                                <?php elseif ($rank == 3): ?>
                                    <span style="font-size: 1.5rem;">🥉</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo $rank; ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-white fw-bold"><?php echo htmlspecialchars($amb['name']); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($amb['institution'] ?? '—'); ?></td>
                            <td><code style="background: rgba(0,255,148,0.1); color: var(--accent); padding: 2px 8px; border-radius: 4px;"><?php echo htmlspecialchars($amb['code']); ?></code></td>
                            <td class="text-center">
                                <span class="badge" style="background: rgba(0,255,148,0.2); color: var(--accent); font-size: 1rem;"><?php echo $eventTotal; ?></span>
                            </td>
                            <td class="text-center">
                                <div>
                                    <span class="badge bg-dark border border-warning" title="Approved social guests: <?php echo $socialApproved; ?>"><?php echo $socialTotal; ?></span>
                                    <div class="text-muted" style="font-size:0.75rem;">Approved: <?php echo $socialApproved; ?></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success"><?php echo $eventApproved; ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge" style="background: rgba(124,77,255,0.2); color: #cbb1ff; font-size: 1rem;"><?php echo $combinedImpact; ?></span>
                            </td>
                        </tr>
                    <?php $rank++; endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Events Row -->
<div class="row g-4 mb-5">
    <div class="col-md-3">
        <a href="manage_signups" class="text-decoration-none">
            <div class="stat-box" style="cursor:pointer;">
                <h3 style="color: #fff;"><?php echo $stats['total_signups']; ?></h3>
                <p>Portal Signups</p>
            </div>
        </a>
        <div class="stat-box mt-3">
            <h3 style="color: #fff;"><?php echo $stats['total_registrations']; ?></h3>
            <p>Total Registrations</p>
        </div>
    </div>
    
    <div class="col-lg-5">
        <div class="glass-panel h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="text-white m-0">🚀 Upcoming Events</h4>
                <a href="add_event" class="btn btn-sm btn-outline-success"><i class="fas fa-plus"></i></a>
            </div>
            
            <?php if ($upcoming_events->num_rows > 0): ?>
                <?php while ($event = $upcoming_events->fetch_assoc()): ?>
                    <div class="d-flex align-items-center mb-3 p-3" style="background: rgba(255,255,255,0.05); border-radius: 12px;">
                        <img src="../<?php echo $event['image_url']; ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; margin-right: 15px;">
                        <div class="flex-grow-1">
                            <h5 class="m-0 text-white" style="font-size: 1rem;"><?php echo htmlspecialchars($event['title']); ?></h5>
                            <small style="color: var(--accent);"><?php echo date("M j, Y", strtotime($event['event_date'])); ?></small>
                        </div>
                        <a href="edit_event.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-light"><i class="fas fa-edit"></i></a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-muted text-center py-4">No upcoming events.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass-panel h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="text-white m-0">📂 Recent History</h4>
                <a href="#" class="text-muted small text-decoration-none">View All</a>
            </div>
            
            <?php if ($past_events->num_rows > 0): ?>
                <?php while ($event = $past_events->fetch_assoc()): ?>
                    <div class="d-flex align-items-center mb-3 p-3" style="background: rgba(255,255,255,0.02); border-radius: 12px; opacity: 0.7;">
                        <img src="../<?php echo $event['image_url']; ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; margin-right: 15px; filter: grayscale(100%);">
                        <div class="flex-grow-1">
                            <h5 class="m-0 text-white" style="font-size: 1rem;"><?php echo htmlspecialchars($event['title']); ?></h5>
                            <small class="text-muted">Ended: <?php echo date("M j, Y", strtotime($event['event_date'])); ?></small>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-muted text-center py-4">No past events recorded.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
    // Clock
    (function(){
        const TZ = <?php echo json_encode(getenv('APP_TIMEZONE') ?: 'Asia/Karachi'); ?>;
        function tickClock(){
            const now = new Date();
            const opts = { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true, timeZone: TZ };
            const el = document.getElementById('adminClock');
            if (el) el.textContent = new Intl.DateTimeFormat('en-GB', opts).format(now);
        }
        tickClock(); setInterval(tickClock, 1000);
    })();

    // ========== CHARTS ==========
    const trendData30 = <?php echo json_encode(array_values($last30Days)); ?>;
    const trendLabels30 = <?php echo json_encode(array_map(function($d) { return date('M j', strtotime($d)); }, array_keys($last30Days))); ?>;
    
    // Get last 7 days
    const trendData7 = trendData30.slice(-7);
    const trendLabels7 = trendLabels30.slice(-7);
    
    let currentTrendData = trendData7;
    let currentTrendLabels = trendLabels7;

    // Registration Trends Chart
    const trendsCtx = document.getElementById('trendsChart').getContext('2d');
    const trendsChart = new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: currentTrendLabels,
            datasets: [{
                label: 'Registrations',
                data: currentTrendData,
                borderColor: '#00ff94',
                backgroundColor: 'rgba(0, 255, 148, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#00ff94',
                pointBorderColor: '#00ff94',
                pointHoverRadius: 8,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#888' }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#888', stepSize: 1 }
                }
            }
        }
    });

    // Toggle 7/30 days
    document.getElementById('btn7Days').addEventListener('click', function() {
        this.classList.add('active');
        document.getElementById('btn30Days').classList.remove('active');
        trendsChart.data.labels = trendLabels7;
        trendsChart.data.datasets[0].data = trendData7;
        trendsChart.update();
    });
    
    document.getElementById('btn30Days').addEventListener('click', function() {
        this.classList.add('active');
        document.getElementById('btn7Days').classList.remove('active');
        trendsChart.data.labels = trendLabels30;
        trendsChart.data.datasets[0].data = trendData30;
        trendsChart.update();
    });

    // Institution Breakdown Chart
    const instData = <?php echo json_encode(array_values($institutionData)); ?>;
    const instLabels = <?php echo json_encode(array_keys($institutionData)); ?>;
    const instColors = ['#00ff94', '#00d2ff', '#ffbb33', '#ff5555', '#7c4dff', '#ff69b4', '#40e0d0', '#ffd700'];
    
    const instCtx = document.getElementById('institutionChart').getContext('2d');
    const instChart = new Chart(instCtx, {
        type: 'doughnut',
        data: {
            labels: instLabels,
            datasets: [{
                data: instData,
                backgroundColor: instColors.slice(0, instData.length),
                borderColor: '#0a0a0a',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    display: true,
                    position: 'bottom',
                    labels: { color: '#888', boxWidth: 12, padding: 10 }
                }
            },
            cutout: '60%'
        }
    });

    // ========== PUSH NOTIFICATIONS ==========
    const pushBanner = document.getElementById('pushNotifBanner');
    const enablePushBtn = document.getElementById('enablePushBtn');
    const dismissPushBtn = document.getElementById('dismissPushBtn');
    
    // Check if push notifications are supported and not already granted/dismissed
    if ('Notification' in window && 'serviceWorker' in navigator) {
        const dismissed = localStorage.getItem('pushNotifDismissed');
        if (!dismissed && Notification.permission === 'default') {
            pushBanner.classList.remove('d-none');
        }
    }
    
    if (enablePushBtn) {
        enablePushBtn.addEventListener('click', async function() {
            try {
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    pushBanner.classList.add('d-none');
                    // Show test notification
                    new Notification('SENTEC Admin', {
                        body: 'Push notifications enabled! You\'ll be notified of new registrations.',
                        icon: '../images/favicon/android-chrome-192x192.png'
                    });
                }
            } catch (err) {
                console.error('Notification error:', err);
            }
        });
    }
    
    if (dismissPushBtn) {
        dismissPushBtn.addEventListener('click', function() {
            pushBanner.classList.add('d-none');
            localStorage.setItem('pushNotifDismissed', 'true');
        });
    }

    // Auto-refresh notifications every 60 seconds
    setInterval(function() {
        fetch(window.location.href)
            .then(res => res.text())
            .then(html => {
                // Simple badge update - could be improved with dedicated API
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newBadge = doc.querySelector('#notificationBell .badge');
                const currentBadge = document.querySelector('#notificationBell .badge');
                
                if (newBadge && currentBadge) {
                    const newCount = parseInt(newBadge.textContent);
                    const oldCount = parseInt(currentBadge.textContent);
                    
                    if (newCount > oldCount && Notification.permission === 'granted') {
                        new Notification('New Registration!', {
                            body: `${newCount - oldCount} new team(s) registered.`,
                            icon: '../images/favicon/android-chrome-192x192.png'
                        });
                    }
                    currentBadge.textContent = newBadge.textContent;
                }
            })
            .catch(err => console.log('Refresh error:', err));
    }, 60000);
</script>
