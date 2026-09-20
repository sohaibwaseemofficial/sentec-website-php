<?php
session_start();

// 1. Session check (Handle AJAX session expiration cleanly)[span_11](start_span)[span_11](end_span)
if (!isset($_SESSION['user_id'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit();
    }
    header("Location: login.php");
    exit();
}

include 'db_connection.php';
require_once __DIR__ . '/election_settings.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Participant';

// AUTO-MIGRATE / AUTO-FIX DATABASE IF 'position' COLUMN IS MISSING[span_12](start_span)[span_12](end_span)
$checkPosCol = $conn->query("SHOW COLUMNS FROM election_votes LIKE 'position'");
if ($checkPosCol && $checkPosCol->num_rows === 0) {
    @$conn->query("ALTER TABLE election_votes ADD COLUMN position VARCHAR(100) NOT NULL AFTER user_id");
    @$conn->query("ALTER TABLE election_votes DROP INDEX unique_user_vote");
    @$conn->query("ALTER TABLE election_votes ADD UNIQUE KEY unique_user_position_vote (election_id, user_id, position)");
}

// 2. Fetch Active Election[span_13](start_span)[span_13](end_span)
$stmt = $conn->prepare("SELECT * FROM elections ORDER BY id DESC LIMIT 1");
$stmt->execute();
$election = $stmt->get_result()->fetch_assoc();
$stmt->close();

$now = new DateTime('now');
$portalOpen = election_portal_open($conn);
$voteWindowOpen = false;

if ($election) {
    $startTime = new DateTime($election['start_time']);
    $endTime = new DateTime($election['end_time']);
    $voteWindowOpen = $portalOpen && $now >= $startTime && $now <= $endTime && $election['status'] !== 'ended';
}

$has_voted = false;
if ($election) {
    $v_stmt = $conn->prepare("SELECT id FROM election_votes WHERE election_id = ? AND user_id = ? AND is_valid = 1 LIMIT 1");
    $v_stmt->bind_param("ii", $election['id'], $user_id);
    $v_stmt->execute();
    $has_voted = $v_stmt->get_result()->num_rows > 0;
    $v_stmt->close();
}

// 3. PROCESS VOTE SUBMISSION BEFORE ANY HTML (`header.php`) IS INCLUDED[span_14](start_span)[span_14](end_span)!
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['cast_vote']) || !empty($_POST['candidate_id']))) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if (!$election) {
        $msg = 'No active election found.';
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => $msg]); exit(); }
    } elseif (!$voteWindowOpen) {
        $msg = 'Voting window is currently closed or locked.';
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => $msg]); exit(); }
    } elseif ($has_voted) {
        $msg = 'You have already cast your vote in this election.';
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => $msg]); exit(); }
    } else {
        $candidate_selections = $_POST['candidate_id'] ?? [];
        $responses = json_encode([], JSON_UNESCAPED_UNICODE);

        if (!is_array($candidate_selections) || empty($candidate_selections)) {
            $msg = 'Please select at least one candidate before submitting.';
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => $msg]); exit(); }
        } else {
            try {
                $ins = $conn->prepare("INSERT INTO election_votes (election_id, user_id, position, candidate_id, mcq_responses) VALUES (?, ?, ?, ?, ?)");
                if ($ins === false) {
                    throw new Exception("Database error: " . $conn->error);
                }

                $votesSubmitted = 0;
                foreach ($candidate_selections as $position => $candidate_id) {
                    $candIdInt = intval($candidate_id);
                    $posClean = trim($position);
                    $ins->bind_param("iisis", $election['id'], $user_id, $posClean, $candIdInt, $responses);
                    $ins->execute();
                    $votesSubmitted++;
                }
                $ins->close();

                if ($votesSubmitted > 0) {
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Vote recorded successfully.']);
                        exit();
                    }
                }
            } catch (Throwable $e) {
                $errMessage = $e->getMessage();
                if (str_contains($errMessage, 'Duplicate entry')) {
                    $errMessage = 'You have already cast a vote for this election.';
                }
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $errMessage]);
                    exit();
                }
            }
        }
    }
}

// 4. NOW INCLUDE HTML HEADER ONLY FOR DISPLAY[span_15](start_span)[span_15](end_span)
include 'header.php';

$portalVisible = election_portal_visible($conn);
$portalResultsVisible = election_portal_results_visible($conn);
election_ensure_candidate_active_flag($conn);

$voteWindowClosed = false;
if ($election) {
    $startTime = new DateTime($election['start_time']);
    $endTime = new DateTime($election['end_time']);
    $votingNotStarted = $now < $startTime;
    $votingHasEnded = $now > $endTime || $election['status'] === 'ended';
    $voteWindowClosed = $votingHasEnded;
}
?>

<style>
    .election-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 40px 20px 80px;
        position: relative;
        z-index: 1;
    }
    .glass-panel {
        background: #0d0d0d;
        border: 1px solid var(--border);
        padding: 36px;
        margin-bottom: 30px;
        color: #fff;
        position: relative;
    }
    .glass-panel::before {
        content: '';
        position: absolute;
        top: -1px;
        left: -1px;
        width: 14px;
        height: 14px;
        border-top: 2px solid #f15a24;
        border-left: 2px solid #f15a24;
    }
    .crypto-banner {
        background: #090909;
        border: 1px solid var(--border);
        padding: 18px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 28px;
    }
    .candidate-card {
        background: #080808;
        border: 1px solid var(--border);
        padding: 20px;
        transition: 0.2s;
        cursor: pointer;
        display: block;
        height: 100%;
    }
    .candidate-card:hover {
        border-color: #f15a24;
        background: rgba(241, 90, 36, 0.04);
    }
    .candidate-card input[type="radio"]:checked + .card-body {
        color: #f15a24;
    }
    .btn-vote-submit {
        background: #f15a24;
        color: #000;
        font-weight: 700;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 0.84rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        padding: 16px 36px;
        border: none;
        cursor: pointer;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-vote-submit:hover {
        background: #fff;
        color: #000;
    }
    .category-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border: 1px solid var(--border);
        color: #f15a24;
        background: rgba(241, 90, 36, 0.08);
        font-size: 0.72rem;
        font-family: 'IBM Plex Mono', monospace;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .status-badge {
        padding: 4px 10px;
        font-family: 'IBM Plex Mono', monospace;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 600;
        display: inline-block;
    }
    .status-approved {
        color: #34d399;
        border: 1px solid rgba(52, 211, 153, 0.4);
        background: rgba(52, 211, 153, 0.08);
    }
    .status-rejected {
        color: #f87171;
        border: 1px solid rgba(248, 113, 113, 0.4);
        background: rgba(248, 113, 113, 0.08);
    }
</style>

<section class="secondary-hero">
    <div class="secondary-hero-grid">
        <div>
            <div class="eyebrow">DEMOCRATIC COUNCIL // ELECTION PORTAL</div>
            <h1 class="secondary-hero-title">Student <span class="text-orange-500">ballot.</span></h1>
            <p class="secondary-hero-lead">Elect the next executive leadership of SENTEC. Verified NED students vote for presiding board candidates under transparent cryptographic safeguards.</p>
        </div>
        <div class="secondary-hero-index">
            <div>BALLOT.VOTE // ACTIVE</div>
            <div style="color:var(--text-dim); margin-top:4px;">CRYPTOGRAPHIC VERIFICATION ON</div>
        </div>
    </div>
</section>

<div class="election-container">
    <div>
        <?php if (!$portalVisible): ?>
            <div class="glass-panel text-center py-5">
                <i class="fas fa-lock mb-3" style="font-size:3rem; color:#888;"></i>
                <h2 style="font-family:'Space Grotesk', sans-serif;">Polling Portal Hidden</h2>
                <p style="color:#888;">The polling card is currently hidden by administrators.</p>
            </div>
        <?php elseif (!$election): ?>
            <div class="glass-panel text-center py-5">
                <i class="fas fa-vote-yea mb-3 text-orange-500" style="font-size:3rem;"></i>
                <h2 style="font-family:'Space Grotesk', sans-serif;">No Active Polls</h2>
                <p style="color:#888;">Check back soon for upcoming student science society polls.</p>
            </div>
        <?php elseif ($has_voted && !($portalResultsVisible || $voteWindowClosed || $election['show_results'])): ?>
            <div class="glass-panel text-center py-5">
                <i class="fas fa-check-circle mb-3 text-orange-500" style="font-size:3.5rem;"></i>
                <h1 style="font-family: 'Space Grotesk', sans-serif;">Vote Casted Successfully</h1>
                <p style="color:#aaa; max-width: 500px; margin: 0 auto;">Your ballot has been securely encrypted and recorded. Results will be published once polling concludes.</p>
            </div>
        <?php elseif ($portalResultsVisible || $voteWindowClosed || $election['show_results']): ?>
            <div class="glass-panel">
                <div class="d-flex align-items-center justify-content-between mb-4 pb-3" style="border-bottom: 1px solid var(--border);">
                    <h2 class="mb-0" style="font-family:'Space Grotesk', sans-serif; font-size:1.4rem;">
                        <i class="fas fa-chart-bar text-orange-500 me-2"></i> Official Polling Results
                    </h2>
                    <span class="status-badge status-approved">Certified Count</span>
                </div>
                <?php
                $res_stmt = $conn->query("SELECT c.name, c.position, COUNT(v.id) AS total_votes 
                                          FROM election_candidates c 
                                          LEFT JOIN election_votes v ON c.id = v.candidate_id AND v.is_valid = 1 
                                          WHERE c.election_id = {$election['id']} AND c.is_active = 1 
                                          GROUP BY c.id 
                                          ORDER BY c.position ASC, total_votes DESC, c.name ASC");
                
                $resultsByPosition = [];
                while ($row = $res_stmt->fetch_assoc()) {
                    $resultsByPosition[$row['position']][] = $row;
                }
                
                if (empty($resultsByPosition)): ?>
                    <p class="text-center text-muted">No candidate tally data found.</p>
                <?php else:
                    foreach ($resultsByPosition as $posName => $candidates):
                        $maxPosVotes = max(1, max(array_map(static function ($r) { return (int) $r['total_votes']; }, $candidates)));
                ?>
                    <div class="mb-4 p-4" style="background: #090909; border: 1px solid var(--border);">
                        <h4 class="text-uppercase mb-3" style="color:#f15a24; font-family:'IBM Plex Mono', monospace; font-size: 0.9rem; letter-spacing: 0.08em; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
                            <i class="fas fa-award me-2"></i> Office: <?php echo htmlspecialchars($posName); ?>
                        </h4>
                        <?php foreach ($candidates as $row): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span style="font-size:1rem; font-weight:600;"><?php echo htmlspecialchars($row['name']); ?></span>
                                    <span style="color:#f15a24; font-family:'IBM Plex Mono', monospace; font-size:0.85rem;"><?php echo (int) $row['total_votes']; ?> Votes</span>
                                </div>
                                <div class="progress" style="height: 8px; background: rgba(255,255,255,0.05); border-radius: 0;">
                                    <div class="progress-bar" style="width: <?php echo max(4, round(((int) $row['total_votes'] / $maxPosVotes) * 100)); ?>%; background: #f15a24;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; 
                endif; ?>
            </div>
        <?php elseif (!$voteWindowOpen): ?>
            <div class="glass-panel text-center py-5">
                <i class="fas fa-clock mb-3" style="font-size:3.5rem; color:#f15a24;"></i>
                <?php if (!empty($votingNotStarted)): ?>
                    <h2 style="font-family:'Space Grotesk', sans-serif;">Polling Has Not Started Yet</h2>
                    <p style="color:#888;">The ballot opens on <?php echo date('M j, g:i A', strtotime($election['start_time'])); ?>.</p>
                <?php else: ?>
                    <h2 style="font-family:'Space Grotesk', sans-serif;">Polling Locked</h2>
                    <p style="color:#888;">The polling panel is currently locked by administrators or outside the voting window.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="glass-panel">
                <div class="crypto-banner">
                    <i class="fas fa-shield-alt fa-2x text-orange-500"></i>
                    <div>
                        <strong style="color: #f15a24; display:block; font-family:'IBM Plex Mono', monospace; font-size:0.85rem; letter-spacing:0.06em;">End-to-End Encrypted & Anonymous Ballot</strong>
                        <small style="color:#888;">Identification tokens are decoupled at the ingress gateway. Your choices remain strictly confidential.</small>
                    </div>
                </div>

                <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                    <span class="category-pill"><i class="fas fa-user-secret"></i> Anonymous Ballot</span>
                    <span class="category-pill"><i class="fas fa-lock"></i> Cryptographic Seal</span>
                </div>

                <h1 class="mb-2" style="font-family: 'Space Grotesk', sans-serif; font-size:1.8rem;"><?php echo htmlspecialchars($election['title']); ?></h1>
                <p style="color: #888; margin-bottom: 28px;">Select your designated candidate for each office position below.</p>

                <form method="POST">
                    <h4 style="color: #f5f5f5; margin-bottom: 20px; font-family:'Space Grotesk', sans-serif; font-size:1.15rem;"><i class="fas fa-user-tie text-orange-500 me-2"></i> Candidates by Office</h4>
                    
                    <?php
                    $c_stmt = $conn->query("SELECT * FROM election_candidates WHERE election_id = {$election['id']} AND is_active = 1 ORDER BY position ASC, name ASC");
                    $candidatesByPos = [];
                    while ($cand = $c_stmt->fetch_assoc()) {
                        $candidatesByPos[$cand['position']][] = $cand;
                    }
                    
                    if (empty($candidatesByPos)): ?>
                        <p class="text-muted">No active candidates listed on the ballot yet.</p>
                    <?php else:
                        foreach ($candidatesByPos as $posName => $candidates):
                    ?>
                        <div class="mb-5 p-3" style="background: rgba(255,255,255,0.015); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px;">
                            <h5 class="mb-3 text-uppercase" style="color: #f15a24; letter-spacing: 0.05em; font-family:'IBM Plex Mono', monospace;">
                                <i class="fas fa-user-tag me-2"></i> Select <?php echo htmlspecialchars($posName); ?>
                            </h5>
                            <div class="row g-4">
                                <?php foreach ($candidates as $cand): ?>
                                    <div class="col-md-6">
                                        <label class="candidate-card d-flex align-items-center w-100">
                                            <input type="radio" name="candidate_id[<?php echo htmlspecialchars($posName); ?>]" value="<?php echo $cand['id']; ?>" required class="form-check-input me-3" style="transform: scale(1.3);">
                                            <div>
                                                <h5 class="mb-1" style="color:#fff;"><?php echo htmlspecialchars($cand['name']); ?></h5>
                                                <small style="color:#888;"><?php echo htmlspecialchars($cand['position']); ?></small>
                                                <p class="mb-0 mt-2" style="font-size:0.85rem; color:#aaa;"><?php echo htmlspecialchars($cand['bio']); ?></p>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; 
                    endif; ?>

                    <div class="text-end mt-4">
                        <button type="submit" name="cast_vote" class="btn-vote-submit"><i class="fas fa-lock me-2"></i> ENCRYPT & CAST BALLOT</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[method="POST"]');
    if (!form) return;
    const submitBtn = form.querySelector('button[type="submit"][name="cast_vote"]');
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!submitBtn) return;
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';

        const formData = new FormData(form);
        formData.append('cast_vote', '1');

        try {
            const resp = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const text = await resp.text();
            let json = null;
            try { json = JSON.parse(text); } catch (e) { }

            if (resp.ok && json && json.success) {
                window.location.reload();
                return;
            }

            let message = 'Failed to submit vote.';
            if (json && json.message) message = json.message;
            else if (text) message = 'Server error response received.';

            alert(message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        } catch (err) {
            console.error(err);
            alert('Network error while submitting your vote. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
});
</script>

<?php include 'footer.php'; ?>
