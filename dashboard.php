<?php
// 1. START SESSION
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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
    .dashboard-section {
        padding-top: 140px;
        min-height: 100vh;
        background: radial-gradient(circle at top, rgba(7, 17, 37, 0.6), rgba(3, 5, 13, 0.95));
    }
    .dashboard-section .text-muted {
        color: rgba(255, 255, 255, 0.7) !important;
    }
    
    .glass-panel {
        background: linear-gradient(135deg, rgba(12, 20, 42, 0.85), rgba(4, 9, 22, 0.95));
        backdrop-filter: blur(22px);
        -webkit-backdrop-filter: blur(22px);
        border: 1px solid rgba(0, 255, 148, 0.08);
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.55);
        border-radius: 24px;
        padding: 40px;
        margin-bottom: 30px;
        height: 100%; 
        display: flex;
        flex-direction: column;
    }

    .glass-panel h3, .glass-panel h1 { text-shadow: 0 0 18px rgba(0, 255, 148, 0.15); }
    .glass-panel.highlight { box-shadow: 0 30px 70px rgba(0, 255, 148, 0.15); }

    .btn-clear {
        display: inline-flex; justify-content: center; align-items: center;
        padding: 12px 26px; border-radius: 999px;
        border: 1px solid rgba(0, 255, 148, 0.5);
        color: #00ffd1; text-transform: uppercase; letter-spacing: 0.7px;
        transition: 0.3s ease; width: auto; text-decoration: none;
    }
    .btn-clear:hover { background: rgba(0, 255, 148, 0.18); color: #fff; box-shadow: 0 0 32px rgba(0, 255, 148, 0.45); }

    .status-badge {
        padding: 6px 12px; border-radius: 50px; font-weight: 700; font-size: 0.8rem;
        text-transform: uppercase; letter-spacing: 0.5px; display: inline-block;
    }
    .status-pending, .status-submitted {
        color: #ffbb33; border: 1px solid rgba(255, 187, 51, 0.6);
        background: radial-gradient(circle, rgba(255, 187, 51, 0.2) 0%, rgba(15, 12, 3, 0.85) 80%);
    }
    .status-approved, .status-confirmed, .status-present {
        color: #00ffd1; border: 1px solid rgba(0, 255, 148, 0.6);
        background: radial-gradient(circle, rgba(0, 255, 148, 0.2) 0%, rgba(9, 24, 32, 0.85) 80%);
    }
    .status-rejected {
        color: #ff6a6a; border: 1px solid rgba(255, 68, 68, 0.6);
        background: radial-gradient(circle, rgba(255, 68, 68, 0.2) 0%, rgba(25, 2, 6, 0.85) 80%);
    }

    @keyframes pulse-green {
        0% { box-shadow: 0 0 0 0 rgba(0, 255, 148, 0.4); }
        70% { box-shadow: 0 0 0 12px rgba(0, 255, 148, 0); }
        100% { box-shadow: 0 0 0 0 rgba(0, 255, 148, 0); }
    }

    .btn-pay {
        background: rgba(0, 255, 148, 0.15); color: #00ffd1; border: 1px solid rgba(0, 255, 148, 0.6);
        font-weight: 700; padding: 9px 22px; border-radius: 999px; text-decoration: none;
        font-size: 0.82rem; display: inline-flex; align-items: center; justify-content: center;
        margin-top: 10px; transition: 0.3s; animation: pulse-green 2.5s infinite;
    }
    .btn-pay:hover { background: rgba(0, 255, 148, 0.27); color: #fff; }

    .reg-table table { width: 100%; border-collapse: collapse; color: #fff; }
    .reg-table thead th { border-bottom: 1px solid rgba(0, 255, 148, 0.2); padding: 15px; text-align: left; color: var(--accent); }
    .reg-table tbody tr { border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
    .reg-table td { padding: 15px; vertical-align: middle; }

    @media (max-width: 767px) {
        .glass-panel { padding: 24px; }
        .reg-table thead { display: none; }
        .reg-table tbody tr { display: block; margin-bottom: 18px; padding: 18px; border-radius: 18px; background: rgba(5, 11, 25, 0.85); border: 1px solid rgba(0, 255, 148, 0.08); }
        .reg-table tbody tr td { display: flex; justify-content: space-between; padding: 5px 0; border: none; }
        .reg-table tbody tr td::before { content: attr(data-label); color: rgba(255,255,255,0.5); font-size: 0.8rem; font-weight: bold; }
    }
    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 25px;
        gap: 15px;
        flex-wrap: nowrap;
    }

    .btn-new-reg {
        padding: 8px 16px !important;
        font-size: 0.75rem !important;
        height: 38px;
        display: flex;
        align-items: center;
        background: rgba(0, 255, 148, 0.05) !important;
        border: 1px solid rgba(0, 255, 148, 0.4) !important;
        border-radius: 999px;
        white-space: nowrap;
        color: #00ffd1 !important;
        text-decoration: none;
        transition: 0.3s ease;
    }

    .btn-new-reg:hover {
        background: rgba(0, 255, 148, 0.15) !important;
        box-shadow: 0 0 15px rgba(0, 255, 148, 0.2);
    }
</style>

<section class="dashboard-section">
    <div class="container">
        
        <div class="glass-panel highlight mb-5 text-center" style="height: auto;">
            <h1 style="font-family: 'Outfit'; font-size: 3rem; color: #fff;">
                Welcome, <span style="color: var(--accent);"><?php echo htmlspecialchars($user_name); ?></span>!
            </h1>
            <p style="color: #ccc;">Manage your event registrations and social activities.</p>
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
                        <h3 style="color: #fff; margin:0; font-size: 1.25rem;">
                            <i class="fas fa-cubes text-warning me-2"></i> Competitions</h3>
                        <?php if (!empty($registrations) && $eventOpen): ?>
                            <a href="event_registration.php" class="btn-new-reg">
                                <i class="fas fa-plus me-2"></i> New Registration
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex-grow-1">
                        <?php if (empty($registrations)): ?>
                            <div class="text-center py-5">
                                <?php if($eventOpen): ?>
                                    <i class="fas fa-rocket mb-3" style="font-size:3rem; color:var(--accent);"></i>
                                    <h4 class="text-white">Ready to Compete?</h4>
                                    <p class="mb-0" style="color: #ccc;">Register your team for the upcoming events.</p>
                                    <a href="event_registration.php" class="btn-clear mt-3">Register Now</a>
                                <?php else: ?>
                                    <i class="fas fa-ban" style="font-size: 3rem; color: #7a1a1a; margin-bottom: 15px;"></i>
                                    <p class="mb-0" style="color: #ccc;">Team registrations are closed.</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive reg-table">
                                <table class="table table-dark table-hover" style="background: transparent;">
                                    <thead>
                                        <tr>
                                            <th>Team / Module</th>
                                            <th class="text-end">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($registrations as $reg): ?>
                                            <tr>
                                                <td data-label="Event">
                                                    <strong style="color: #fff; font-size: 1.1rem;"><?php echo htmlspecialchars($reg['team_name']); ?></strong><br>
                                                    <small style="color: #888;"><?php echo htmlspecialchars($reg['module_selection']); ?></small>
                                                </td>
                                                <td class="text-end" data-label="Status">
                                                    <span class="status-badge status-<?php echo $reg['status']; ?>">
                                                        <?php echo ucfirst($reg['status']); ?>
                                                    </span>
                                                    <?php if ($reg['status'] === 'approved'): ?>
                                                        <div style="margin-top: 8px;">
                                                            <?php if ($reg['payment_status'] === 'confirmed'): ?>
                                                                <small style="color:#00FF94; font-weight:bold;"><i class="fas fa-check-circle"></i> Paid</small>
                                                            <?php else: ?>
                                                                <a href="payment_upload.php?id=<?php echo $reg['id']; ?>" class="btn-pay" style="padding: 6px 15px; font-size: 0.7rem;">Pay Now</a>
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
                    <h3 style="color: #fff; margin-bottom: 20px;"><i class="fas fa-music me-2" style="color: #00d2ff;"></i> Social Event</h3>
                    
                    <?php if ($social_reg): ?>
                        <div class="py-4">
                            <?php 
                                $s_status = strtolower($social_reg['status']);
                                if($s_status == 'approved' || $s_status == 'confirmed') { 
                                    echo '<i class="fas fa-check-circle" style="font-size: 4rem; color: #00FF94; margin-bottom: 20px;"></i>';
                                    echo '<h4 style="color:#00FF94;">E-Pass Issued</h4>';
                                } else {
                                    echo '<i class="fas fa-clock" style="font-size: 4rem; color: #ffbb33; margin-bottom: 20px;"></i>';
                                    echo '<h4 style="color:#ffbb33;">Pending Verification</h4>';
                                }
                            ?>
                            <div class="mt-3">
                                <span class="status-badge status-<?php echo $s_status; ?>"><?php echo ucfirst($s_status); ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="py-4">
                            <?php if ($socialOpen): ?>
                                <i class="fas fa-glass-cheers" style="font-size: 4rem; color: #00d2ff; margin-bottom: 20px;"></i>
                                <h4 class="text-white">Ruh-e-Raqs</h4>
                                <p style="color: #aaa; margin-bottom: 30px;">Join us for Qawwali and Mushaira.</p>
                                <a href="social_register.php" class="btn-clear w-100" style="border-color: #00d2ff; color: #00d2ff;">Get Social Pass</a>
                            <?php else: ?>
                                <i class="fas fa-ban" style="font-size: 3rem; color: #7a1a1a; margin-bottom: 15px;"></i>
                                <p class="mb-0" style="color: #ccc;">Social registrations are closed.</p>
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
                        <h3 style="color: #fff; margin:0; font-size: 1.25rem;">
                            <i class="fas fa-vote-yea text-info me-2"></i> Student Science Society Poll
                        </h3>
                        <span class="status-badge <?php echo $electionOpen ? 'status-approved' : 'status-rejected'; ?>">
                            <?php echo $electionOpen ? 'Polling Open' : 'Polling Locked'; ?>
                        </span>
                    </div>
                    <div class="py-3">
                        <i class="fas fa-shield-alt mb-3" style="font-size: 3rem; color: #00d2ff;"></i>
                        <h4 class="text-white mb-2"><?php echo htmlspecialchars($latestElection['title'] ?? 'Election Portal'); ?></h4>
                        <p class="text-muted mb-3">Anonymous polling for Student Society Leadership Role.</p>
                        <?php if (!empty($latestElection)): ?>
                            <div class="mb-3 text-muted small">
                                <?php echo date('M j, g:i A', strtotime($latestElection['start_time'])); ?> to <?php echo date('M j, g:i A', strtotime($latestElection['end_time'])); ?>
                            </div>
                        <?php endif; ?>
                        <a href="election_portal.php" class="btn-clear" style="border-color:#00d2ff; color:#00d2ff;">
                            <i class="fas fa-lock me-2"></i> Open Polling Portal
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($latestElectionResults)): ?>
            <div class="col-lg-8 offset-lg-2">
                <div class="glass-panel highlight" style="border-color: rgba(0, 210, 255, 0.2);">
                    <div class="panel-header">
                        <h3 style="color: #fff; margin:0; font-size: 1.25rem;">
                            <i class="fas fa-chart-bar text-info me-2"></i> Public Polling Results
                        </h3>
                        <span class="status-badge status-approved">Official Public View</span>
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
                        <a href="election_portal.php" class="btn-clear" style="border-color:#00d2ff; color:#00d2ff;">
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