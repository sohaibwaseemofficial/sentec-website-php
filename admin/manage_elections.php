<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: admin_login.php"); exit(); }
include_once '../db_connection.php';
require_once __DIR__ . '/../election_settings.php';

election_ensure_candidate_active_flag($conn);

// Handle Election Creation[cite: 2]
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_election'])) {
    $title = $_POST['title'];
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $stmt = $conn->prepare("INSERT INTO elections (title, start_time, end_time, status) VALUES (?, ?, ?, 'active')");
    $stmt->bind_param("sss", $title, $start, $end);
    $stmt->execute();
}

// Handle Election Update
if (isset($_POST['update_election'])) {
    $eid = intval($_POST['election_id']);
    $title = $_POST['election_title'];
    $start = $_POST['election_start_time'];
    $end = $_POST['election_end_time'];
    $stmt = $conn->prepare("UPDATE elections SET title = ?, start_time = ?, end_time = ? WHERE id = ?");
    $stmt->bind_param("sssi", $title, $start, $end, $eid);
    $stmt->execute();
    header("Location: manage_elections.php?msg=election_updated");
    exit();
}

// Handle Election Deletion
if (isset($_POST['delete_election'])) {
    $eid = intval($_POST['election_id']);
    $stmt = $conn->prepare("DELETE FROM elections WHERE id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    header("Location: manage_elections.php?msg=election_deleted");
    exit();
}

// Handle Candidate Addition[cite: 2]
if (isset($_POST['add_candidate'])) {
    $eid = intval($_POST['election_id']);
    $stmt = $conn->prepare("INSERT INTO election_candidates (election_id, name, position, bio) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $eid, $_POST['c_name'], $_POST['c_pos'], $_POST['c_bio']);
    $stmt->execute();
}

if (isset($_POST['update_candidate'])) {
    $candidateId = intval($_POST['candidate_id']);
    $stmt = $conn->prepare("UPDATE election_candidates SET name = ?, position = ?, bio = ? WHERE id = ?");
    $stmt->bind_param("sssi", $_POST['candidate_name'], $_POST['candidate_position'], $_POST['candidate_bio'], $candidateId);
    $stmt->execute();
}

if (isset($_POST['toggle_candidate_active'])) {
    $candidateId = intval($_POST['candidate_id']);
    $nextStatus = intval($_POST['candidate_active']);
    $stmt = $conn->prepare("UPDATE election_candidates SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $nextStatus, $candidateId);
    $stmt->execute();
}

if (isset($_POST['delete_candidate'])) {
    $candidateId = intval($_POST['candidate_id']);
    $voteCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM election_votes WHERE candidate_id = ? AND is_valid = 1");
    $voteCountStmt->bind_param("i", $candidateId);
    $voteCountStmt->execute();
    $voteCountRow = $voteCountStmt->get_result()->fetch_assoc();
    $voteCountStmt->close();

    $hasVotes = (int) ($voteCountRow['total'] ?? 0) > 0;
    if ($hasVotes) {
        $stmt = $conn->prepare("UPDATE election_candidates SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $candidateId);
    } else {
        $stmt = $conn->prepare("DELETE FROM election_candidates WHERE id = ?");
        $stmt->bind_param("i", $candidateId);
    }
    $stmt->execute();
}

// Toggle Results[cite: 2]
if (isset($_GET['toggle_results'])) {
    $eid = intval($_GET['toggle_results']);
    $conn->query("UPDATE elections SET show_results = 1 - show_results WHERE id = $eid");
}

$portalVisible = election_portal_visible($conn);
$portalOpen = election_portal_open($conn);
$portalResults = election_portal_results_visible($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Elections | SENTEC Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <style>
        .election-shell {
            background: linear-gradient(180deg, rgba(7, 11, 23, 0.96), rgba(4, 8, 18, 0.98));
            border: 1px solid rgba(0, 255, 148, 0.08);
            border-radius: 20px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
        }
        .election-card {
            background: linear-gradient(135deg, rgba(11, 19, 36, 0.96), rgba(5, 10, 20, 0.94));
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 18px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.02);
        }
        .election-title {
            font-family: 'Outfit', sans-serif;
            letter-spacing: 0.02em;
        }
    </style>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="election-title mb-1"><i class="fas fa-vote-yea me-2" style="color:#00ffd1;"></i> Election Management Controls</h2>
            <p class="text-muted mb-0">Configure multi-position ballots, candidates, and published results[cite: 2].</p>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="badge <?php echo $portalVisible ? 'bg-success' : 'bg-secondary'; ?>" id="election-visible-badge">
                <?php echo $portalVisible ? 'Tile Visible' : 'Tile Hidden'; ?>
            </span>
            <span class="badge <?php echo $portalOpen ? 'bg-success' : 'bg-danger'; ?>" id="election-open-badge">
                <?php echo $portalOpen ? 'Voting Open' : 'Voting Locked'; ?>
            </span>
            <span class="badge <?php echo $portalResults ? 'bg-info' : 'bg-warning'; ?>" id="election-results-badge">
                <?php echo $portalResults ? 'Results Visible' : 'Results Hidden'; ?>
            </span>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 flex-wrap mb-4">
        <button class="btn <?php echo $portalOpen ? 'btn-outline-danger' : 'btn-success'; ?> btn-sm" id="toggle-election-open" data-open="<?php echo $portalOpen ? '1' : '0'; ?>">
            <i class="fas <?php echo $portalOpen ? 'fa-lock' : 'fa-lock-open'; ?> me-1"></i>
            <?php echo $portalOpen ? 'Lock Voting' : 'Unlock Voting'; ?>
        </button>
        <button class="btn <?php echo $portalVisible ? 'btn-outline-danger' : 'btn-success'; ?> btn-sm" id="toggle-election-visible" data-visible="<?php echo $portalVisible ? '1' : '0'; ?>">
            <i class="fas <?php echo $portalVisible ? 'fa-eye-slash' : 'fa-eye'; ?> me-1"></i>
            <?php echo $portalVisible ? 'Hide Tile' : 'Show Tile'; ?>
        </button>
        <button class="btn <?php echo $portalResults ? 'btn-info' : 'btn-outline-info'; ?> btn-sm" id="toggle-election-results" data-results="<?php echo $portalResults ? '1' : '0'; ?>">
            <i class="fas fa-chart-bar me-1"></i>
            <?php echo $portalResults ? 'Hide Public Results' : 'Show Public Results'; ?>
        </button>
    </div>

    <div class="row g-4 mb-5">
        <!-- Create Election Box[cite: 2] -->
        <div class="col-md-4">
            <div class="election-card p-4 h-100">
                <h4 class="text-white mb-3">Launch New Election</h4>
                <form method="POST">
                    <input type="text" name="title" class="form-control mb-3 bg-dark text-white border-secondary" placeholder="Election Title (e.g. SENTEC '26)" required>
                    <label class="text-muted small">Start Time</label>
                    <input type="datetime-local" name="start_time" class="form-control mb-3 bg-dark text-white border-secondary" required>
                    <label class="text-muted small">End Time</label>
                    <input type="datetime-local" name="end_time" class="form-control mb-3 bg-dark text-white border-secondary" required>
                    <button type="submit" name="create_election" class="btn btn-outline-success w-100">Activate Election</button>
                </form>
            </div>
        </div>

        <!-- Active Elections Control[cite: 2] -->
        <div class="col-md-8">
            <div class="election-card p-4 h-100">
                <h4 class="text-white mb-3">Active & Drafted Elections</h4>
                <?php
                $el_stmt = $conn->query("SELECT * FROM elections ORDER BY id DESC");
                while ($el = $el_stmt->fetch_assoc()):
                    $candidateRows = [];
                    
                    $candidateStmt = $conn->prepare("SELECT id, name, position, bio, is_active FROM election_candidates WHERE election_id = ? ORDER BY is_active DESC, position ASC, name ASC");
                    $candidateStmt->bind_param("i", $el['id']);
                    $candidateStmt->execute();
                    $candidateResult = $candidateStmt->get_result();
                    if ($candidateResult) {
                        while ($row = $candidateResult->fetch_assoc()) {
                            $candidateRows[] = $row;
                        }
                    }
                    $candidateStmt->close();

                    $resultRows = [];
                    $resultStmt = $conn->prepare("SELECT c.id, c.name, c.position, c.is_active, COUNT(v.id) AS total_votes FROM election_candidates c LEFT JOIN election_votes v ON c.id = v.candidate_id AND v.is_valid = 1 WHERE c.election_id = ? GROUP BY c.id ORDER BY c.position ASC, total_votes DESC, c.name ASC");
                    $resultStmt->bind_param("i", $el['id']);
                    $resultStmt->execute();
                    $resultResult = $resultStmt->get_result();
                    if ($resultResult) {
                        while ($row = $resultResult->fetch_assoc()) {
                            $resultRows[] = $row;
                        }
                    }
                    $resultStmt->close();
                ?>
                    <div class="p-3 mb-4 election-shell">
                        <!-- Election Header & Action Buttons -->
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h5 class="text-white m-0 election-title"><?php echo htmlspecialchars($el['title']); ?></h5>
                                <small class="text-muted">Starts: <?php echo date('M j, Y g:i A', strtotime($el['start_time'])); ?> · Ends: <?php echo date('M j, Y g:i A', strtotime($el['end_time'])); ?></small>
                            </div>
                            <div class="d-flex gap-2 flex-wrap justify-content-end">
                                <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#editElection<?php echo $el['id']; ?>">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </button>
                                <a href="download_election_results_csv.php?election_id=<?php echo $el['id']; ?>" class="btn btn-sm btn-outline-light">
                                    <i class="fas fa-file-csv me-1"></i> Export
                                </a>
                                <a href="?toggle_results=<?php echo $el['id']; ?>" class="btn btn-sm <?php echo $el['show_results'] ? 'btn-success' : 'btn-outline-warning'; ?>">
                                    <?php echo $el['show_results'] ? 'Results Public' : 'Results Hidden'; ?>
                                </a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('CRITICAL WARNING: Are you sure you want to permanently delete this election? All candidates and voter records attached to it will be lost immediately.');">
                                    <input type="hidden" name="election_id" value="<?php echo $el['id']; ?>">
                                    <button type="submit" name="delete_election" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Collapse Panel to Edit Election Details -->
                        <div class="collapse mt-3" id="editElection<?php echo $el['id']; ?>">
                            <div class="p-3" style="background: rgba(0, 210, 255, 0.05); border: 1px solid rgba(0, 210, 255, 0.2); border-radius: 12px;">
                                <h6 class="text-info mb-2">Edit Election Details</h6>
                                <form method="POST" class="row g-2 align-items-end">
                                    <input type="hidden" name="election_id" value="<?php echo $el['id']; ?>">
                                    <div class="col-md-4">
                                        <label class="small text-muted mb-1">Title</label>
                                        <input type="text" name="election_title" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?php echo htmlspecialchars($el['title']); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small text-muted mb-1">Start Time</label>
                                        <input type="datetime-local" name="election_start_time" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?php echo date('Y-m-d\TH:i', strtotime($el['start_time'])); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small text-muted mb-1">End Time</label>
                                        <input type="datetime-local" name="election_end_time" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?php echo date('Y-m-d\TH:i', strtotime($el['end_time'])); ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" name="update_election" class="btn btn-sm btn-info w-100">Save</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <hr style="border-color: rgba(255,255,255,0.1);">

                        <!-- Add Candidate Form[cite: 2] -->
                        <form method="POST" class="row g-2 mb-2">
                            <input type="hidden" name="election_id" value="<?php echo $el['id']; ?>">
                            <div class="col-4"><input type="text" name="c_name" class="form-control form-control-sm bg-dark text-white" placeholder="Candidate Name" required></div>
                            <div class="col-3"><input type="text" name="c_pos" class="form-control form-control-sm bg-dark text-white" placeholder="Role (e.g. President)" required></div>
                            <div class="col-3"><input type="text" name="c_bio" class="form-control form-control-sm bg-dark text-white" placeholder="Short Bio"></div>
                            <div class="col-2"><button type="submit" name="add_candidate" class="btn btn-sm btn-info w-100">+ Cand</button></div>
                        </form>

                        <!-- Candidate Control Section[cite: 2] -->
                        <div class="mt-3 p-3" style="background: rgba(0, 210, 255, 0.03); border: 1px solid rgba(0, 210, 255, 0.08); border-radius: 14px;">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <h6 class="text-white m-0"><i class="fas fa-users me-2" style="color:#00d2ff;"></i> Candidate Control</h6>
                                <small class="text-muted">Manage candidates across all positions[cite: 2].</small>
                            </div>
                            <?php if (empty($candidateRows)): ?>
                                <p class="text-muted mb-0">No candidates added yet[cite: 2].</p>
                            <?php else: ?>
                                <div class="d-grid gap-3">
                                    <?php foreach ($candidateRows as $candidate): ?>
                                        <div class="p-3" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; <?php echo !$candidate['is_active'] ? 'opacity:0.55;' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                                <div>
                                                    <div class="text-white fw-bold"><?php echo htmlspecialchars($candidate['name']); ?></div>
                                                    <span class="badge bg-dark border border-secondary text-info"><?php echo htmlspecialchars($candidate['position']); ?></span>
                                                </div>
                                                <span class="badge <?php echo $candidate['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $candidate['is_active'] ? 'Active on Ballot' : 'Hidden'; ?>
                                                </span>
                                            </div>
                                            <form method="POST" class="row g-2 align-items-end">
                                                <input type="hidden" name="candidate_id" value="<?php echo $candidate['id']; ?>">
                                                <input type="hidden" name="election_id" value="<?php echo $el['id']; ?>">
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label text-muted small mb-1">Candidate Name</label>
                                                    <input type="text" name="candidate_name" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?php echo htmlspecialchars($candidate['name']); ?>" required>
                                                </div>
                                                <div class="col-lg-3 col-md-6">
                                                    <label class="form-label text-muted small mb-1">Position</label>
                                                    <input type="text" name="candidate_position" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?php echo htmlspecialchars($candidate['position']); ?>" required>
                                                </div>
                                                <div class="col-lg-3 col-md-8">
                                                    <label class="form-label text-muted small mb-1">Bio</label>
                                                    <input type="text" name="candidate_bio" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?php echo htmlspecialchars($candidate['bio']); ?>" placeholder="Short bio">
                                                </div>
                                                <div class="col-lg-2 col-md-4 d-flex gap-2">
                                                    <button type="submit" name="update_candidate" class="btn btn-sm btn-info flex-grow-1">Save</button>
                                                </div>
                                            </form>
                                            <form method="POST" class="mt-2 text-end">
                                                <input type="hidden" name="candidate_id" value="<?php echo $candidate['id']; ?>">
                                                <input type="hidden" name="candidate_active" value="<?php echo $candidate['is_active'] ? 0 : 1; ?>">
                                                <div class="d-flex gap-2 justify-content-end flex-wrap">
                                                    <button type="submit" name="toggle_candidate_active" class="btn btn-sm <?php echo $candidate['is_active'] ? 'btn-outline-warning' : 'btn-success'; ?>">
                                                        <i class="fas <?php echo $candidate['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?> me-1"></i>
                                                        <?php echo $candidate['is_active'] ? 'Hide Candidate' : 'Restore Candidate'; ?>
                                                    </button>
                                                    <button type="submit" name="delete_candidate" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this candidate? It will be hidden if votes already exist.');">
                                                        <i class="fas fa-trash me-1"></i> Delete
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Result Snapshot Section Grouped by Position[cite: 2] -->
                        <div class="mt-3 p-3" style="background: rgba(0, 255, 148, 0.03); border: 1px solid rgba(0, 255, 148, 0.08); border-radius: 14px;">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <h6 class="text-white m-0"><i class="fas fa-chart-pie me-2" style="color:#00ffd1;"></i> Result Snapshot (By Office)</h6>
                                <small class="text-muted">Visible in admin only[cite: 2].</small>
                            </div>
                            <?php if (empty($resultRows)): ?>
                                <p class="text-muted mb-0">No vote data yet[cite: 2].</p>
                            <?php else: 
                                $groupedResults = [];
                                foreach ($resultRows as $rRow) {
                                    $groupedResults[$rRow['position']][] = $rRow;
                                }

                                foreach ($groupedResults as $posGroup => $posCandidates):
                                    $topVotes = max(array_map(static function ($r) { return (int) $r['total_votes']; }, $posCandidates));
                                    $topVotes = max($topVotes, 1);
                            ?>
                                <div class="mb-4 p-3" style="background: rgba(255,255,255,0.015); border-radius: 10px; border: 1px solid rgba(255,255,255,0.05);">
                                    <h6 class="text-uppercase mb-3" style="color:#ffbb33; font-size: 0.85rem; letter-spacing: 0.05em;">
                                        <i class="fas fa-id-badge me-1"></i> <?php echo htmlspecialchars($posGroup); ?>
                                    </h6>
                                    <div class="d-grid gap-3">
                                        <?php foreach ($posCandidates as $resultRow): ?>
                                            <div>
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="text-white fw-bold"><?php echo htmlspecialchars($resultRow['name']); ?></span>
                                                    <span style="color:#00ffd1;"><?php echo (int) $resultRow['total_votes']; ?> Votes</span>
                                                </div>
                                                <div class="progress" style="height: 10px; background: rgba(255,255,255,0.05);">
                                                    <div class="progress-bar" style="width: <?php echo max(4, round(((int)$resultRow['total_votes'] / $topVotes) * 100)); ?>%; background: #00ffd1;"></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php 
                                endforeach; 
                            endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
async function postElectionToggle(payload) {
    const body = new URLSearchParams(payload);
    const response = await fetch('toggle_election_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString()
    });
    const data = await response.json();
    alert(data.message || 'Updated.');
    if (data.success) {
        window.location.reload();
    }
}

document.getElementById('toggle-election-open')?.addEventListener('click', function() {
    const current = this.getAttribute('data-open');
    postElectionToggle({ open: current === '1' ? '0' : '1' });
});

document.getElementById('toggle-election-visible')?.addEventListener('click', function() {
    const current = this.getAttribute('data-visible');
    postElectionToggle({ visible: current === '1' ? '0' : '1' });
});

document.getElementById('toggle-election-results')?.addEventListener('click', function() {
    const current = this.getAttribute('data-results');
    postElectionToggle({ results: current === '1' ? '0' : '1' });
});
</script>
</body>
</html>