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

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Participant';

// FETCH SETTINGS
$socialOpen = social_registrations_open($conn);
$eventOpen = event_registrations_open($conn);

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
            <span class="btn-action-outline" aria-disabled="true" style="opacity: 0.65; cursor: default;">
                <i class="fas fa-ticket-alt"></i> Social Pass: Coming Soon
            </span>
        </div>
    </div>

        <?php 
        $eventVisible = event_registrations_visible($conn);
        $socialVisible = social_registrations_visible($conn);

        $showCompetitions = ($eventVisible || !empty($registrations));
        $showSocial = false;
        
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

        </div>
    </div>
</section>

<?php include 'footer.php'; ?>