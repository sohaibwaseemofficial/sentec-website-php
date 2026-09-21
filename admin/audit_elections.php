<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: admin_login.php"); exit(); }

if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    die("<h1 style='color:red; text-align:center; margin-top:50px;'>ACCESS DENIED: Audit privileges required.</h1>");
}

include_once '../db_connection.php';

// Handle Vote Deletion
if (isset($_GET['delete_vote'])) {
    $vote_id = intval($_GET['delete_vote']);
    $conn->query("UPDATE election_votes SET is_valid = 0 WHERE id = $vote_id");
}

// Handle Vote Update
if (isset($_POST['update_vote'])) {
    $voteId = intval($_POST['vote_id']);
    $candidateId = intval($_POST['candidate_id']);
    $isValid = isset($_POST['vote_is_valid']) ? 1 : 0;
    
    // 1. Get new position from candidate
    $candStmt = $conn->prepare("SELECT position FROM election_candidates WHERE id = ? LIMIT 1");
    $candStmt->bind_param("i", $candidateId);
    $candStmt->execute();
    $candRow = $candStmt->get_result()->fetch_assoc();
    $candStmt->close();
    $newPosition = $candRow['position'] ?? '';

    // 2. Update vote with candidate and position
    $stmt = $conn->prepare("UPDATE election_votes SET candidate_id = ?, position = ?, is_valid = ? WHERE id = ?");
    $stmt->bind_param("isii", $candidateId, $newPosition, $isValid, $voteId);
    $success = $stmt->execute();
    $stmt->close();
    
    // 3. Return JSON response for AJAX
    header('Content-Type: application/json');
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Vote updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    exit();
}

$editVote = null;
$editVoteCandidates = [];
if (isset($_GET['edit_vote'])) {
    $editVoteId = intval($_GET['edit_vote']);
    $stmt = $conn->prepare("SELECT v.id, v.election_id, v.user_id, v.position, v.candidate_id, v.is_valid, v.casted_at,
                                   u.full_name as voter_name, u.email as voter_email,
                                   e.title as election_title
                            FROM election_votes v
                            JOIN users u ON v.user_id = u.id
                            JOIN elections e ON v.election_id = e.id
                            WHERE v.id = ? LIMIT 1");
    $stmt->bind_param("i", $editVoteId);
    $stmt->execute();
    $editVote = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($editVote) {
        $candStmt = $conn->prepare("SELECT id, name, position FROM election_candidates WHERE election_id = ? ORDER BY position ASC, name ASC");
        $candStmt->bind_param("i", $editVote['election_id']);
        $candStmt->execute();
        $candResult = $candStmt->get_result();
        while ($candResult && ($cand = $candResult->fetch_assoc())) {
            $editVoteCandidates[] = $cand;
        }
        $candStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Confidential Election Audit | SENTEC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="alert alert-danger d-flex align-items-center mb-4">
        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
        <div><strong>CONFIDENTIAL EXECUTIVE AUDIT PANEL</strong></div>
    </div>

    <?php if ($editVote): ?>
        <div class="glass-panel p-4 mb-4" style="background: rgba(12, 20, 42, 0.95); border: 1px solid #00ffd1; border-radius:16px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="text-white">Edit Vote #<?php echo (int) $editVote['id']; ?> (<?php echo htmlspecialchars($editVote['position']); ?>)</h4>
                <a href="audit_elections" class="btn btn-sm btn-outline-light">Close Editor</a>
            </div>

            <form id="editVoteForm" method="POST">
                <input type="hidden" name="vote_id" value="<?php echo (int) $editVote['id']; ?>">
                <input type="hidden" name="update_vote" value="1">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label text-muted small">Select Candidate (Grouped by Position)</label>
                        <select name="candidate_id" class="form-select bg-dark text-white border-secondary" required>
                            <?php foreach ($editVoteCandidates as $candidate): ?>
                                <option value="<?php echo (int) $candidate['id']; ?>" <?php echo ((int) $candidate['id'] === (int) $editVote['candidate_id']) ? 'selected' : ''; ?>>
                                    [<?php echo htmlspecialchars($candidate['position']); ?>] — <?php echo htmlspecialchars($candidate['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-6 d-flex align-items-end">
                        <div class="form-check form-switch text-white">
                            <input class="form-check-input" type="checkbox" id="voteIsValid" name="vote_is_valid" <?php echo ((int) $editVote['is_valid'] === 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="voteIsValid">Keep vote valid</label>
                        </div>
                    </div>
                </div>
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
        <script>
        document.getElementById('editVoteForm').addEventListener('submit', async function(e){
            e.preventDefault();
            const btn = this.querySelector('button');
            btn.disabled = true;
            const res = await fetch('audit_elections.php', { method: 'POST', body: new FormData(this) });
            const json = await res.json();
            if(json.success) {
                window.location.href = 'audit_elections.php?saved=1';
            } else {
                alert('Error: ' + json.message);
                btn.disabled = false;
            }
        });
        </script>
    <?php endif; ?>

    <div class="glass-panel p-4" style="background: rgba(12, 20, 42, 0.85); border: 1px solid rgba(255,255,255,0.1); border-radius:16px;">
        <h4 class="text-white mb-4">Voter Tracking Ledger</h4>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead>
                    <tr style="color: #ffbb33;">
                        <th>Vote ID</th>
                        <th>Voter</th>
                        <th>Position</th>
                        <th>Candidate</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT v.id, v.user_id, v.position, u.full_name as voter_name, c.name as candidate_name, v.is_valid 
                            FROM election_votes v 
                            JOIN users u ON v.user_id = u.id 
                            JOIN election_candidates c ON v.candidate_id = c.id 
                            ORDER BY v.casted_at DESC";
                    $audit_stmt = $conn->query($sql);
                    
                    if ($audit_stmt && $audit_stmt->num_rows > 0):
                        while ($row = $audit_stmt->fetch_assoc()):
                    ?>
                        <tr style="opacity: <?php echo $row['is_valid'] ? '1' : '0.4'; ?>;">
                            <td>#<?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['voter_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['position']); ?></td>
                            <td><?php echo htmlspecialchars($row['candidate_name']); ?></td>
                            <td>
                                <a href="?edit_vote=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-info">Edit</a>
                                <a href="?delete_vote=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Revoke this vote?');">Revoke</a>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>