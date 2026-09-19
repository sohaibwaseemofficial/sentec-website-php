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
    .dashboard-section { padding-top: 140px; min-height: 100vh; background: radial-gradient(circle at top, rgba(7, 17, 37, 0.6), rgba(3, 5, 13, 0.95)); }
    .glass-panel { background: linear-gradient(135deg, rgba(12, 20, 42, 0.85), rgba(4, 9, 22, 0.95)); backdrop-filter: blur(22px); border: 1px solid rgba(0, 255, 148, 0.08); border-radius: 24px; padding: 40px; margin-bottom: 30px; color: #fff; }
    .crypto-banner { background: rgba(0, 255, 148, 0.05); border: 1px solid rgba(0, 255, 148, 0.4); border-radius: 12px; padding: 15px 20px; display: flex; align-items: center; gap: 15px; margin-bottom: 30px; }
    .candidate-card { border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 20px; transition: 0.3s; cursor: pointer; }
    .candidate-card:hover { border-color: #00ffd1; background: rgba(0, 255, 148, 0.03); }
    .candidate-card input[type="radio"]:checked + .card-body { color: #00ffd1; }
    .btn-neon { background: rgba(0, 255, 148, 0.15); color: #00ffd1; border: 1px solid rgba(0, 255, 148, 0.6); font-weight: 700; padding: 12px 28px; border-radius: 999px; transition: 0.3s; }
    .btn-neon:hover { background: rgba(0, 255, 148, 0.3); color: #fff; box-shadow: 0 0 25px rgba(0, 255, 148, 0.4); }
    .category-pill { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; border: 1px solid rgba(0, 210, 255, 0.35); color: #00d2ff; background: rgba(0, 210, 255, 0.08); font-size: 0.78rem; letter-spacing: 0.06em; text-transform: uppercase; }
</style>

<section class="dashboard-section">
    <div class="container">
        <?php if (!$portalVisible): ?>
            <div class="glass-panel text-center py-5">
                <i class="fas fa-vote-yea mb-3" style="font-size:4rem; color:#666;"></i>
                <h2>Polling Portal Hidden</h2>
                <p style="color:#ccc;">The polling card is currently hidden by administrators.</p>
            </div>
        <?php elseif (!$election): ?>
            <div class="glass-panel text-center py-5">
                <i class="fas fa-vote-yea mb-3" style="font-size:4rem; color:#666;"></i>
                <h2>No Active Polls</h2>
                <p style="color:#ccc;">Check back soon for upcoming student science society polls.</p>
            </div>
        <?php elseif ($has_voted && !($portalResultsVisible || $voteWindowClosed || $election['show_results'])): ?>
            <div class="glass-panel text-center py-5">
                <i class="fas fa-check-circle mb-3" style="font-size:4rem; color:#00ffd1;"></i>
                <h1 style="font-family: 'Outfit';">Vote Casted Successfully</h1>
                <p style="color:#ccc;">Your ballot has been securely encrypted and recorded. Results will be published once polling concludes.</p>
            </div>
        <?php elseif ($portalResultsVisible || $voteWindowClosed || $election['show_results']): ?>
            <div class="glass-panel">
                <h2 class="text-center mb-4" style="color:#00ffd1;"><i class="fas fa-chart-bar me-2"></i> Official Polling Results</h2>
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
                    <div class="mb-5 p-4" style="background: rgba(255,255,255,0.02); border-radius: 16px; border: 1px solid rgba(0, 255, 148, 0.12);">
                        <h4 class="text-uppercase mb-4" style="color:#ffbb33; letter-spacing: 0.05em; border-bottom: 1px solid rgba(255,187,51,0.2); padding-bottom: 10px;">
                            <i class="fas fa-award me-2"></i> Position: <?php echo htmlspecialchars($posName); ?>
                        </h4>
                        <?php foreach ($candidates as $row): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span style="font-size:1.1rem; font-weight:bold;"><?php echo htmlspecialchars($row['name']); ?></span>
                                    <span style="color:#00ffd1;"><?php echo (int) $row['total_votes']; ?> Votes</span>
                                </div>
                                <div class="progress" style="height: 12px; background: rgba(255,255,255,0.05); border-radius: 6px;">
                                    <div class="progress-bar" style="width: <?php echo max(4, round(((int) $row['total_votes'] / $maxPosVotes) * 100)); ?>%; background: #00ffd1;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; 
                endif; ?>
            </div>
        <?php elseif (!$voteWindowOpen): ?>
            <div class="glass-panel text-center py-5">
                <i class="fas fa-clock mb-3" style="font-size:4rem; color:#ffbb33;"></i>
                <?php if (!empty($votingNotStarted)): ?>
                    <h2>Polling Has Not Started Yet</h2>
                    <p style="color:#ccc;">The ballot opens on <?php echo date('M j, g:i A', strtotime($election['start_time'])); ?>.</p>
                <?php else: ?>
                    <h2>Polling Locked</h2>
                    <p style="color:#ccc;">The polling panel is currently locked by administrators or outside the polling window.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="glass-panel">
                <div class="crypto-banner">
                    <i class="fas fa-shield-alt fa-2x" style="color: #00ffd1;"></i>
                    <div>
                        <strong style="color: #00ffd1; display:block;">End-to-End Encrypted & Anonymous Ballot</strong>
                        <small style="color:#bbb;">Your identification tokens are stripped at the ingress gateway. Your choices remain entirely confidential.</small>
                    </div>
                </div>

                <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                    <span class="category-pill"><i class="fas fa-user-secret"></i> Anonymous Polling</span>
                    <span class="category-pill"><i class="fas fa-lock"></i> Sealed Ballot</span>
                </div>

                <h1 class="mb-2" style="font-family: 'Outfit';"><?php echo htmlspecialchars($election['title']); ?></h1>
                <p style="color: #ccc; margin-bottom: 30px;">Select your preferred candidate for each office position below.</p>

                <form method="POST">
                    <h4 style="color: #00ffd1; margin-bottom: 20px;"><i class="fas fa-user-tie me-2"></i> Select Candidates by Office</h4>
                    
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
                            <h5 class="mb-3 text-uppercase" style="color: #ffbb33; letter-spacing: 0.05em;">
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
                        <button type="submit" name="cast_vote" class="btn-neon"><i class="fas fa-lock me-2"></i> Encrypt & Cast Ballot</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>

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
