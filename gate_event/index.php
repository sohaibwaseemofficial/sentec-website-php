<?php
session_start();
if (!isset($_SESSION['is_event_gatekeeper'])) { header("Location: login.php"); exit; }
if (isset($_GET['logout'])) { session_destroy(); header("Location: login.php"); exit; }

include '../db_connection.php';
require_once __DIR__ . '/../event_attendees_helper.php';

$results = [];
$search = $_GET['search'] ?? '';

if (!empty($search) && event_attendees_table_exists($conn)) {
    $term = "%" . $conn->real_escape_string($search) . "%";
    $sql = "SELECT ea.id, ea.full_name, ea.cnic, ea.phone, ea.face_image, ea.day1_status, ea.day2_status, ea.label,
                   er.team_name, er.module_selection, er.status AS registration_status
            FROM event_attendees ea
            JOIN event_registrations er ON er.id = ea.registration_id
            WHERE ea.full_name LIKE ? OR ea.cnic LIKE ? OR ea.phone LIKE ? OR er.team_name LIKE ?
            ORDER BY ea.full_name ASC
            LIMIT 15";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $term, $term, $term, $term);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Gate Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #050505; color: #fff; font-family: 'Outfit', sans-serif; padding: 20px; margin: 0; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .logout { color: #ff4444; text-decoration: none; font-size: 0.9rem; border: 1px solid #333; padding: 8px 15px; border-radius: 50px; }
        .card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); padding: 30px; border-radius: 20px; text-align: center; margin-bottom: 20px; }
        .scan-box { border: 2px dashed #00FF94; padding: 30px; border-radius: 20px; color: #00FF94; margin-bottom: 10px; cursor: pointer; transition: 0.3s; }
        .scan-box:hover { background: rgba(0,255,148,0.05); }
        .scan-box i { font-size: 2.5rem; margin-bottom: 10px; }
        .search-container { display: flex; margin-top: 10px; }
        input { width: 100%; padding: 15px 20px; background: #0b1120; border: 1px solid #333; color: #fff; border-radius: 50px 0 0 50px; outline: none; font-size: 1rem; }
        .btn-search { padding: 15px 25px; background: #333; color: #fff; border: 1px solid #333; border-radius: 0 50px 50px 0; cursor: pointer; }
        .btn-search:hover { background: #00FF94; color: #000; border-color: #00FF94; }
        .result-item { background: #111; border: 1px solid #333; padding: 15px; border-radius: 12px; margin-bottom: 10px; text-align: left; display: flex; align-items: center; text-decoration: none; color: #fff; transition: 0.2s; }
        .result-item:hover { border-color: #00FF94; background: #0b1120; }
        .res-img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 15px; border: 2px solid #333; }
        .badge { font-size: 0.7rem; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; font-weight: bold; margin-left: auto; }
        .bg-approved { background: rgba(0,255,148,0.2); color: #00FF94; }
        .bg-pending { background: rgba(255,187,51,0.2); color: #ffbb33; }
        .bg-rejected { background: rgba(255,68,68,0.2); color: #ff4444; }
        .day-chip { margin-left: 8px; padding: 4px 8px; border-radius: 12px; font-size: 0.7rem; border: 1px solid #333; }
        .day-chip.present { background: rgba(0,255,148,0.12); border-color: #00FF94; color: #00FF94; }
    </style>
</head>
<body>
    <div class="header">
        <h2 style="margin:0;">SENTEC<span style="color:#00FF94">.</span></h2>
        <a href="index?logout=1" class="logout"><i class="fas fa-sign-out-alt"></i> Exit</a>
    </div>

    <div class="card">
        <div class="scan-box" onclick="alert('Open your phone camera to scan the QR.');">
            <i class="fas fa-qrcode"></i>
            <h3>Scan QR Code</h3>
            <p style="color:#ccc; font-size:0.9rem; margin:0;">Use your phone camera.</p>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top:0; margin-bottom: 5px;">Manual Check-In</h3>
        <p style="color:#888; font-size:0.9rem; margin-bottom:20px;">Search if team member forgot QR.</p>
        <form method="GET" class="search-container">
            <input type="text" name="search" placeholder="Name, Team, or CNIC" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
        </form>

        <?php if (!empty($search)): ?>
            <div style="margin-top: 20px; text-align: left;">
                <p style="color:#888; font-size: 0.9rem; margin-bottom: 10px;">Results for "<?php echo htmlspecialchars($search); ?>"</p>
                <?php if (empty($results)): ?>
                    <div style="color:#ff4444; padding:10px; background:rgba(255,68,68,0.1); border-radius:8px; text-align:center;">No matches found.</div>
                <?php else: ?>
                    <?php foreach ($results as $r): 
                        $imgPath = $r['face_image'] ?? '';
                        $img = !empty($imgPath) ? "../" . ltrim($imgPath, '/') : "../assets/img/default_user.png";
                        $statusKey = $r['registration_status'] ?? 'pending';
                        $cls = 'bg-' . $statusKey;
                    ?>
                    <a href="verify_event.php?attendee=<?php echo (int)$r['id']; ?>" class="result-item">
                        <img src="<?php echo $img; ?>" class="res-img" alt="attendee photo">
                        <div style="flex:1;">
                            <strong style="display:block; font-size:1.05rem;"><?php echo htmlspecialchars($r['full_name']); ?></strong>
                            <span style="color:#888; font-size:0.85rem;">Team: <?php echo htmlspecialchars($r['team_name']); ?></span><br>
                            <span style="color:#666; font-size:0.8rem;">CNIC: <?php echo htmlspecialchars($r['cnic'] ?: '—'); ?></span>
                        </div>
                        <span class="badge <?php echo $cls; ?>"><?php echo ucfirst($statusKey); ?></span>
                        <span class="day-chip <?php echo ($r['day1_status'] === 'present') ? 'present' : ''; ?>">Day 1: <?php echo ucfirst($r['day1_status']); ?></span>
                        <span class="day-chip <?php echo ($r['day2_status'] === 'present') ? 'present' : ''; ?>">Day 2: <?php echo ucfirst($r['day2_status']); ?></span>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
