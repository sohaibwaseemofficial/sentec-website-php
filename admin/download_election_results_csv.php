<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

include '../db_connection.php';

$electionId = isset($_GET['election_id']) ? (int) $_GET['election_id'] : 0;
if ($electionId <= 0) {
    $latest = $conn->query("SELECT id FROM elections ORDER BY id DESC LIMIT 1");
    if ($latest && $latest->num_rows > 0) {
        $electionId = (int) $latest->fetch_assoc()['id'];
    }
}

$election = null;
if ($electionId > 0) {
    $stmt = $conn->prepare("SELECT id, title, start_time, end_time, show_results, status FROM elections WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $electionId);
    $stmt->execute();
    $election = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$election) {
    die('Election not found.');
}

if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=election_results_' . $electionId . '_' . date('Y-m-d_His') . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

$headers = [
    'Election ID',
    'Election Title',
    'Start Time',
    'End Time',
    'Status',
    'Results Visible',
    'Candidate ID',
    'Candidate Name',
    'Position',
    'Active on Ballot',
    'Valid Votes',
    'Vote Percentage'
];

fputcsv($output, $headers);

$summarySql = "SELECT c.id AS candidate_id, c.name, c.position, c.is_active, COUNT(v.id) AS total_votes
               FROM election_candidates c
               LEFT JOIN election_votes v ON v.candidate_id = c.id AND v.is_valid = 1
               WHERE c.election_id = ?
               GROUP BY c.id
               ORDER BY total_votes DESC, c.name ASC";
$stmt = $conn->prepare($summarySql);
$stmt->bind_param("i", $electionId);
$stmt->execute();
$result = $stmt->get_result();

$topVotes = 0;
$rows = [];
while ($result && ($row = $result->fetch_assoc())) {
    $topVotes = max($topVotes, (int) $row['total_votes']);
    $rows[] = $row;
}
$stmt->close();

$topVotes = max($topVotes, 1);

foreach ($rows as $row) {
    fputcsv($output, [
        $election['id'],
        $election['title'],
        $election['start_time'],
        $election['end_time'],
        $election['status'],
        (int) $election['show_results'],
        $row['candidate_id'],
        $row['name'],
        $row['position'],
        (int) $row['is_active'],
        (int) $row['total_votes'],
        round(((int) $row['total_votes'] / $topVotes) * 100, 2)
    ]);
}

if (empty($rows)) {
    fputcsv($output, [
        $election['id'],
        $election['title'],
        $election['start_time'],
        $election['end_time'],
        $election['status'],
        (int) $election['show_results'],
        '',
        'No candidates found',
        '',
        '',
        0,
        0
    ]);
}

fclose($output);
$conn->close();
exit();
?>