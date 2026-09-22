<?php
// 1. START SESSION
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit();
}

include 'header.php';
include 'db_connection.php';
require_once __DIR__ . '/social_registration_settings.php';
require_once __DIR__ . '/event_registration_settings.php';
require_once __DIR__ . '/election_settings.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Participant';

// FETCH SETTINGS
$socialOpen = social_registrations_open($conn);
$eventOpen = event_registrations_open($conn);
$electionVisible = election_portal_visible($conn);
$electionOpen = election_portal_open($conn);

$latestElection = null;
$latestElectionRes = $conn->query("SELECT id, title, start_time, end_time, status, show_results FROM elections ORDER BY id DESC LIMIT 1");
if ($latestElectionRes && $latestElectionRes->num_rows > 0) {
    $latestElection = $latestElectionRes->fetch_assoc();
}

$latestElectionResults = [];
if ($latestElection) {
    $resultVisible = election_portal_results_visible($conn) || (($latestElection['status'] ?? '') === 'ended') || !empty($latestElection['show_results']);
    if ($resultVisible) {
        $resultSql = "SELECT c.name, COUNT(v.id) AS total_votes
                      FROM election_candidates c
                      LEFT JOIN election_votes v ON c.id = v.candidate_id AND v.is_valid = 1
                      WHERE c.election_id = " . (int) ($latestElection['id'] ?? 0) . " AND c.is_active = 1
                      GROUP BY c.id
                      ORDER BY total_votes DESC, c.name ASC";
        $resultRes = $conn->query($resultSql);
        if ($resultRes) {
            while ($row = $resultRes->fetch_assoc()) {
                $latestElectionResults[] = $row;
            }
        }
    }
}

// 2. FETCH ACTIVE EVENT LABEL (Using prepared statement)
$activeEventLabel = 'proxion_2026'; // fallback
$eventRes = $conn->query("SELECT title FROM events WHERE status = 'upcoming' ORDER BY event_date DESC LIMIT 1");
if ($eventRes && $eventRes->num_rows > 0) {
    $activeEventLabel = $eventRes->fetch_assoc()['title'];
}

// 3. FETCH MODULE REGISTRATIONS - SECURED WITH PREPARED STATEMENT
$registrations = [];
$sql = "SELECT * FROM event_registrations WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $registrations[] = $row;
    }
}
$stmt->close();

// 4. FETCH SOCIAL EVENT REGISTRATION - SECURED WITH PREPARED STATEMENT
$social_reg = null;
$checkTable = $conn->query("SHOW TABLES LIKE 'social_registrations'");
if($checkTable && $checkTable->num_rows > 0) {
    $s_sql = "SELECT * FROM social_registrations WHERE user_id = ? AND event_label = ? LIMIT 1";
    $stmt = $conn->prepare($s_sql);
    $stmt->bind_param("is", $user_id, $activeEventLabel);
    $stmt->execute();
    $s_result = $stmt->get_result();
    if ($s_result && $s_result->num_rows > 0) {
        $social_reg = $s_result->fetch_assoc();
    }
    $stmt->close();
}
?>

<style>
    .dashboard-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px 80px;
        position: relative;
        z-index: 1;
    }
    .profile-card {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        background: #0d0d0d;
        border: 1px solid var(--border);
        padding: 24px 28px;
        margin-bottom: 32px;
        position: relative;
    }
    .profile-card::before {
        content: '';
        position: absolute;
        top: -1px;
        left: -1px;
        width: 14px;
        height: 14px;
        border-top: 2px solid #f15a24;
        border-left: 2px solid #f15a24;
    }
    .user-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: rgba(241, 90, 36, 0.12);
        border: 1px solid #f15a24;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f15a24;
        font-size: 1.25rem;
    }
    .glass-panel {
        background: #0d0d0d;
        border: 1px solid var(--border);
        padding: 30px;
        margin-bottom: 24px;
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border);
        margin-bottom: 20px;
    }
    .panel-header h3 {
        color: #f5f5f5;
        margin: 0;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.15rem;
        font-weight: 600;
    }
    .status-badge {
        padding: 4px 10px;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 600;
        display: inline-block;
        border-radius: 0;
    }
    .status-pending, .status-submitted {
        color: #f15a24;
        border: 1px solid rgba(241, 90, 36, 0.4);
        background: rgba(241, 90, 36, 0.08);
    }
    .status-approved, .status-confirmed, .status-present {
        color: #34d399;
        border: 1px solid rgba(52, 211, 153, 0.4);
        background: rgba(52, 211, 153, 0.08);
    }
    .status-rejected {
        color: #f87171;
        border: 1px solid rgba(248, 113, 113, 0.4);
        background: rgba(248, 113, 113, 0.08);
    }
    .btn-action-primary {
        background: #f15a24;
        color: #000;
        font-weight: 700;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 0.78rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 10px 18px;
        border: none;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: 0.2s;
    }
    .btn-action-primary:hover {
        background: #fff;
        color: #000;
    }
    .btn-action-outline {
        background: transparent;
        color: #ddd;
        border: 1px solid var(--border);
        font-weight: 600;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 0.78rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 9px 16px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: 0.2s;
    }
    .btn-action-outline:hover {
        border-color: #f15a24;
        color: #f15a24;
    }
    .btn-pay-now {
        background: #f15a24;
        color: #000;
        font-weight: 700;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 0.72rem;
        letter-spacing: 0.08em;
        padding: 5px 12px;
        text-transform: uppercase;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: 0.2s;
    }
    .btn-pay-now:hover {
        background: #fff;
        color: #000;
    }
</style>

<section class="secondary-hero">
    <div class="secondary-hero-grid">
        <div>
            <div class="eyebrow">SENTEC // PARTICIPANT PORTAL</div>
            <h1 class="secondary-hero-title">Participant <span style="color: #f15a24 !important;">Dashboard.</span></h1>
            <p class="secondary-hero-lead">Welcome back, <?php echo htmlspecialchars($user_name); ?>. Manage your module registrations, social night credentials, and council ballot access.</p>
        </div>
        <div class="secondary-hero-index">
            <div>SYS.DASH // ACTIVE</div>
            <div style="color:var(--text-dim); margin-top:4px;">STATUS: VERIFIED ACCESS</div>
        </div>
    </div>
</section>

<div class="dashboard-container">
    <!-- Profile Action Card -->
    <div class="profile-card">
        <div class="d-flex align-items-center gap-3">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div>
                <div style="font-size: 1.15rem; font-weight: 700; color: #f5f5f5; font-family:'Space Grotesk', sans-serif;">
                    <?php echo htmlspecialchars($user_name); ?>
                </div>
                <div style="font-size: 0.8rem; color: #888; font-family:'IBM Plex Mono', monospace;">
                    PARTICIPANT ID: #<?php echo str_pad($user_id, 4, '0', STR_PAD_LEFT); ?>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <?php if ($eventOpen): ?>
                <a href="event_registration" class="btn-action-primary">
                    <i class="fas fa-cubes"></i> Register Module
                </a>
            <?php endif; ?>
            <?php if ($socialOpen): ?>
                <a href="social_register" class="btn-action-outline">
                    <i class="fas fa-ticket-alt"></i> Social Pass
                </a>
            <?php endif; ?>
            <?php if ($electionVisible): ?>
                <a href="election_portal" class="btn-action-outline">
                    <i class="fas fa-vote-yea"></i> Elections
                </a>
            <?php endif; ?>
        </div>
    </div>

        <?php 
        $eventVisible = event_registrations_visible($conn);
        $socialVisible = social_registrations_visible($conn);

        $showCompetitions = ($eventVisible || !empty($registrations));
        $showSocial = ($socialVisible || $social_reg);
        
        $colClass = ($showCompetitions && $showSocial) ? 'col-lg-6' : 'col-lg-8 offset-lg-2';
        ?>

        <div class="row g-4 justify-content-center">
            
            <?php if ($showCompetitions): ?>
            <div class="<?php echo $colClass; ?>">
                <div class="glass-panel">
                    <div class="panel-header">
                        <h3 style="margin:0;">
                            <i class="fas fa-cubes text-orange-500 me-2"></i> Arena Competitions
                        </h3>
                        <?php if (!empty($registrations) && $eventOpen): ?>
                            <a href="event_registration" class="btn-action-outline">
                                <i class="fas fa-plus me-1"></i> New Registration
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex-grow-1">
                        <?php if (empty($registrations)): ?>
                            <div class="text-center py-5">
                                <?php if($eventOpen): ?>
                                    <i class="fas fa-rocket mb-3 text-orange-500" style="font-size:2.8rem;"></i>
                                    <h4 class="text-white">Ready to Compete?</h4>
                                    <p class="mb-3" style="color: #888;">Register your team across software, esports, or engineering modules.</p>
                                    <a href="event_registration" class="btn-action-primary">Register Now</a>
                                <?php else: ?>
                                    <i class="fas fa-lock" style="font-size: 2.5rem; color: #666; margin-bottom: 15px;"></i>
                                    <p class="mb-0" style="color: #888;">Team registrations are currently closed.</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive reg-table">
                                <table class="table table-dark table-hover" style="background: transparent; border-color: var(--border);">
                                    <thead>
                                        <tr style="border-bottom: 1px solid var(--border);">
                                            <th style="font-family:'IBM Plex Mono', monospace; font-size: 0.72rem; text-transform: uppercase; color: #888; letter-spacing: 0.08em;">Team / Module</th>
                                            <th class="text-end" style="font-family:'IBM Plex Mono', monospace; font-size: 0.72rem; text-transform: uppercase; color: #888; letter-spacing: 0.08em;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($registrations as $reg): ?>
                                            <tr style="border-bottom: 1px solid var(--border);">
                                                <td data-label="Event">
                                                    <strong style="color: #f5f5f5; font-size: 1rem;"><?php echo htmlspecialchars($reg['team_name']); ?></strong><br>
                                                    <small style="color: #888; font-family:'IBM Plex Mono', monospace;"><?php echo htmlspecialchars($reg['module_selection']); ?></small>
                                                </td>
                                                <td class="text-end" data-label="Status">
                                                    <span class="status-badge status-<?php echo $reg['status']; ?>">
                                                        <?php echo ucfirst($reg['status']); ?>
                                                    </span>
                                                    <?php if ($reg['status'] === 'approved'): ?>
                                                        <div style="margin-top: 8px;">
                                                            <?php if ($reg['payment_status'] === 'confirmed'): ?>
                                                                <small style="color:#34d399; font-weight:600; font-family:'IBM Plex Mono', monospace;"><i class="fas fa-check-circle"></i> Paid</small>
                                                            <?php else: ?>
                                                                <a href="payment_upload?id=<?php echo $reg['id']; ?>" class="btn-pay-now">Upload Proof</a>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($showSocial): ?> 
            <div class="<?php echo $colClass; ?>">
                <div class="glass-panel text-center d-flex flex-column justify-content-center">
                    <h3 style="color: #fff; margin-bottom: 20px;"><i class="fas fa-ticket-alt text-orange-500 me-2"></i> Social Night Access</h3>
                    
                    <?php if ($social_reg): ?>
                        <div class="py-4">
                            <?php 
                                $s_status = strtolower($social_reg['status']);
                                if($s_status == 'approved' || $s_status == 'confirmed') { 
                                    echo '<i class="fas fa-check-circle text-orange-500" style="font-size: 3.5rem; margin-bottom: 16px;"></i>';
                                    echo '<h4 style="color:#f5f5f5;">E-Pass Issued</h4>';
                                } else {
                                    echo '<i class="fas fa-clock" style="font-size: 3.5rem; margin-bottom: 16px; color: #f15a24;"></i>';
                                    echo '<h4 style="color:#f15a24; font-family:\'Space Grotesk\', sans-serif;">Pending Verification</h4>';
                                }
                            ?>
                            <div class="mt-3">
                                <span class="status-badge status-<?php echo $s_status; ?>"><?php echo ucfirst($s_status); ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="py-4">
                            <?php if ($socialOpen): ?>
                                <i class="fas fa-glass-cheers text-orange-500" style="font-size: 3.5rem; margin-bottom: 16px;"></i>
                                <h4 class="text-white">Ruh-e-Raqs</h4>
                                <p style="color: #888; margin-bottom: 24px;">Join the annual SENTEC banquet, networking, and cultural evening.</p>
                                <a href="social_register" class="btn-action-primary w-100 justify-content-center">Get Social Pass</a>
                            <?php else: ?>
                                <i class="fas fa-lock" style="font-size: 2.5rem; color: #666; margin-bottom: 15px;"></i>
                                <p class="mb-0" style="color: #888;">Social night bookings are closed.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($electionVisible): ?>
            <div class="col-lg-8 offset-lg-2">
                <div class="glass-panel text-center">
                    <div class="panel-header">
                        <h3 style="margin:0;">
                            <i class="fas fa-vote-yea text-orange-500 me-2"></i> Student Council Ballot
                        </h3>
                        <span class="status-badge <?php echo $electionOpen ? 'status-approved' : 'status-rejected'; ?>">
                            <?php echo $electionOpen ? 'Polling Active' : 'Polling Locked'; ?>
                        </span>
                    </div>
                    <div class="py-3">
                        <i class="fas fa-shield-alt mb-3 text-orange-500" style="font-size: 2.8rem;"></i>
                        <h4 class="text-white mb-2" style="font-family:'Space Grotesk', sans-serif;"><?php echo htmlspecialchars($latestElection['title'] ?? 'Election Portal'); ?></h4>
                        <p style="color: #888; margin-bottom: 16px;">Encrypted ballot portal for verified NED University students.</p>
                        <?php if (!empty($latestElection)): ?>
                            <div class="mb-3 small" style="color: #888; font-family:'IBM Plex Mono', monospace;">
                                <?php echo date('M j, g:i A', strtotime($latestElection['start_time'])); ?> to <?php echo date('M j, g:i A', strtotime($latestElection['end_time'])); ?>
                            </div>
                        <?php endif; ?>
                        <a href="election_portal" class="btn-action-primary">
                            <i class="fas fa-lock me-2"></i> Open Polling Portal
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($latestElectionResults)): ?>
            <div class="col-lg-8 offset-lg-2">
                <div class="glass-panel">
                    <div class="panel-header">
                        <h3 style="margin:0;">
                            <i class="fas fa-chart-bar text-orange-500 me-2"></i> Official Polling Tally
                        </h3>
                        <span class="status-badge status-approved">Verified Count</span>
                    </div>
                    <div class="mb-4 p-3" style="border-radius: 18px; background: linear-gradient(135deg, rgba(0,210,255,0.08), rgba(0,255,148,0.06)); border: 1px solid rgba(0,210,255,0.14);">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <div class="text-uppercase small" style="letter-spacing:0.12em; color:#8edbf0;">Student Science Society</div>
                                <h4 class="text-white mb-0">Official Polling Results</h4>
                            </div>
                            <div class="text-end">
                                <div class="text-muted small">Results are public</div>
                                <div class="election-chip mt-1"><i class="fas fa-badge-check"></i> Verified tally</div>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted mb-4">These are the official live results visible to all users when results are published.</p>
                    <?php
                        $topVotes = max(array_map(static function ($row) { return (int) $row['total_votes']; }, $latestElectionResults));
                        $topVotes = max($topVotes, 1);
                    ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($latestElectionResults as $resultRow): ?>
                            <div class="result-row-highlight">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-white fw-bold"><?php echo htmlspecialchars($resultRow['name']); ?></span>
                                    <span style="color:#00ffd1;"><?php echo (int) $resultRow['total_votes']; ?> Votes</span>
                                </div>
                                <div class="progress" style="height: 12px; background: rgba(255,255,255,0.05); border-radius: 6px;">
                                    <div class="progress-bar" style="width: <?php echo max(4, round(((int) $resultRow['total_votes'] / $topVotes) * 100)); ?>%; background: #00ffd1;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-4">
                        <a href="election_portal" class="btn-clear" style="border-color:#00d2ff; color:#00d2ff;">
                            <i class="fas fa-eye me-2"></i> Open Full Results Page
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</section>

<?php include 'footer.php'; ?>