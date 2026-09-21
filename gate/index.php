<?php
session_start();
// 1. SECURITY CHECK
if (!isset($_SESSION['is_gatekeeper'])) { header("Location: login.php"); exit; }

// 2. LOGOUT LOGIC
if (isset($_GET['logout'])) { session_destroy(); header("Location: login.php"); exit; }

include '../db_connection.php';
require_once __DIR__ . '/../social_attendees_helper.php';

// 3. SEARCH LOGIC
$results = [];
$search = $_GET['search'] ?? '';

if (!empty($search)) {
    $term = "%" . $conn->real_escape_string($search) . "%";
    if (social_attendees_table_exists($conn)) {
        $sql = "SELECT sa.id AS attendee_id, sa.full_name, sa.cnic, sa.face_image, sa.status, sr.status AS group_status
                FROM social_attendees sa
                JOIN social_registrations sr ON sr.id = sa.registration_id
                WHERE sa.full_name LIKE ? OR sa.cnic LIKE ? OR sa.phone LIKE ?
                LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $term, $term, $term);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()) {
            $results[] = $row;
        }
    } else {
        $sql = "SELECT * FROM social_registrations WHERE full_name LIKE ? OR cnic LIKE ? OR phone LIKE ? LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $term, $term, $term);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()) {
            $results[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #050505; color: #fff; font-family: 'Outfit', sans-serif; padding: 20px; margin: 0; }
        
        /* HEADER */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .logout { color: #ff4444; text-decoration: none; font-size: 0.9rem; border: 1px solid #333; padding: 8px 15px; border-radius: 50px; }
        
        /* CARDS */
        .card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); padding: 30px; border-radius: 20px; text-align: center; margin-bottom: 20px; }
        
        /* SCAN AREA */
        .scan-box { border: 2px dashed #00FF94; padding: 30px; border-radius: 20px; color: #00FF94; margin-bottom: 10px; cursor: pointer; transition: 0.3s; }
        .scan-box:hover { background: rgba(0,255,148,0.05); }
        .scan-box i { font-size: 2.5rem; margin-bottom: 10px; }
        
        /* SEARCH BOX */
        .search-container { display: flex; margin-top: 10px; }
        input { width: 100%; padding: 15px 20px; background: #0b1120; border: 1px solid #333; color: #fff; border-radius: 50px 0 0 50px; outline: none; font-size: 1rem; }
        .btn-search { padding: 15px 25px; background: #333; color: #fff; border: 1px solid #333; border-radius: 0 50px 50px 0; cursor: pointer; }
        .btn-search:hover { background: #00FF94; color: #000; border-color: #00FF94; }

        /* RESULT LIST */
        .result-item { 
            background: #111; border: 1px solid #333; padding: 15px; border-radius: 12px; margin-bottom: 10px; 
            text-align: left; display: flex; align-items: center; text-decoration: none; color: #fff; transition: 0.2s;
        }
        .result-item:hover { border-color: #00FF94; background: #0b1120; }
        .res-img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 15px; border: 2px solid #333; }
        
        .badge { font-size: 0.7rem; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; font-weight: bold; margin-left: auto; }
        .bg-approved { background: rgba(0,255,148,0.2); color: #00FF94; }
        .bg-pending { background: rgba(255,187,51,0.2); color: #ffbb33; }
        .bg-rejected { background: rgba(255,68,68,0.2); color: #ff4444; }

    </style>
</head>
<body>

    <div class="header">
        <h2 style="margin:0;">SENTEC<span style="color:#00FF94">.</span></h2>
        <a href="index?logout=1" class="logout"><i class="fas fa-sign-out-alt"></i> Exit</a>
    </div>

    <div class="card">
        <div class="scan-box" onclick="alert('Open your phone camera to scan!');">
            <i class="fas fa-qrcode"></i>
            <h3>Scan QR Code</h3>
            <p style="color:#ccc; font-size:0.9rem; margin:0;">Use your phone camera.</p>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top:0; margin-bottom: 5px;">Manual Check-In</h3>
        <p style="color:#888; font-size:0.9rem; margin-bottom:20px;">Search if guest forgot QR code.</p>
        
        <form method="GET" class="search-container">
            <input type="text" name="search" placeholder="Name or CNIC..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
        </form>

        <?php if (!empty($search)): ?>
            <div style="margin-top: 20px; text-align: left;">
                <p style="color:#888; font-size: 0.9rem; margin-bottom: 10px;">Results for "<?php echo htmlspecialchars($search); ?>"</p>
                
                <?php if (empty($results)): ?>
                    <div style="color:#ff4444; padding:10px; background:rgba(255,68,68,0.1); border-radius:8px; text-align:center;">No guests found.</div>
                <?php else: ?>
                    <?php foreach ($results as $r): 
                        $imgPath = $r['face_image'] ?? '';
                        $img = !empty($imgPath) ? "../" . ltrim($imgPath, '/') : "../assets/img/default_user.png";
                        $statusKey = isset($r['status']) ? $r['status'] : ($r['group_status'] ?? 'pending');
                        $cls = 'bg-' . $statusKey;
                        $link = isset($r['attendee_id']) ? "verify_social.php?attendee=" . $r['attendee_id'] : "verify_social.php?id=" . $r['id'];
                        $displayName = $r['full_name'] ?? 'Guest';
                        $displayCnic = $r['cnic'] ?? '—';
                    ?>
                    <a href="<?php echo $link; ?>" class="result-item">
                        <img src="<?php echo $img; ?>" class="res-img">
                        <div>
                            <strong style="display:block; font-size:1.1rem;"><?php echo htmlspecialchars($displayName); ?></strong>
                            <span style="color:#888; font-size:0.85rem;"><?php echo htmlspecialchars($displayCnic); ?></span>
                        </div>
                        <span class="badge <?php echo $cls; ?>"><?php echo ucfirst($statusKey); ?></span>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
