<?php
session_start();
// 1. SECURITY: Check for VOLUNTEER login, not Admin
if (!isset($_SESSION['is_gatekeeper'])) { 
    // Redirect to volunteer login if session expired
    header("Location: login.php"); 
    exit; 
}

// 2. CONNECT TO DATABASE
// Ensure correct path (up one level)
if (file_exists('../db_connection.php')) {
    include '../db_connection.php';
} else {
    die("Error: db_connection.php not found.");
}

require_once __DIR__ . '/../social_attendees_helper.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$attendeeId = isset($_GET['attendee']) ? intval($_GET['attendee']) : 0;
$attendeeMode = social_attendees_table_exists($conn);
$msg = "";
$p = null;
$participants = [];
$memberIndex = isset($_GET['member']) ? max(1, intval($_GET['member'])) : 1;
$currentMember = null;
$attendeeRecord = null;
$rosterTabs = [];
$displayStatus = 'pending';
$displayAttendance = 'pending';
$displayEntry = null;

// 3. HANDLE "ADMIT" BUTTON CLICK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_present'])) {
    if ($attendeeMode && !empty($_POST['attendee_id'])) {
        $attendeeId = intval($_POST['attendee_id']);
        if (social_mark_attendance($conn, $attendeeId)) {
            $msg = "SUCCESS";
        }
    } else {
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE social_registrations SET attendance_status = 'present', entry_time = ? WHERE id = ?");
        $stmt->bind_param("si", $now, $id);
        if ($stmt->execute()) { 
            $msg = "SUCCESS"; 
        }
    }
}

// 4. FETCH GUEST DATA
if ($attendeeMode) {
    if ($attendeeId > 0) {
        $attendeeRecord = social_attendee_fetch_with_registration($conn, $attendeeId);
        if ($attendeeRecord) {
            $p = $attendeeRecord;
            $id = (int) $attendeeRecord['registration_id'];
            $currentMember = [
                'label' => $attendeeRecord['label'] ?? 'Guest',
                'name' => $attendeeRecord['full_name'],
                'cnic' => $attendeeRecord['cnic'],
                'email' => $attendeeRecord['email'],
                'phone' => $attendeeRecord['phone'],
                'face' => $attendeeRecord['face_image'],
                'card' => $attendeeRecord['id_card_image'],
                'status' => $attendeeRecord['status'],
                'attendance_status' => $attendeeRecord['attendance_status'],
                'entry_time' => $attendeeRecord['entry_time'] ?? null
            ];
            $group = social_attendees_fetch_group($conn, $id);
            foreach ($group as $g) {
                $participants[] = $g;
            }
        }
    } elseif ($id > 0) {
        $group = social_attendees_fetch_group($conn, $id);
        if (!empty($group)) {
            $participants = $group;
            $memberIdx = max(0, min(count($group) - 1, $memberIndex - 1));
            $selected = $group[$memberIdx] ?? $group[0];
            $attendeeId = $selected['id'];
            $attendeeRecord = social_attendee_fetch_with_registration($conn, $attendeeId);
            if ($attendeeRecord) {
                $p = $attendeeRecord;
                $currentMember = [
                    'label' => $attendeeRecord['label'] ?? 'Guest',
                    'name' => $attendeeRecord['full_name'],
                    'cnic' => $attendeeRecord['cnic'],
                    'email' => $attendeeRecord['email'],
                    'phone' => $attendeeRecord['phone'],
                    'face' => $attendeeRecord['face_image'],
                    'card' => $attendeeRecord['id_card_image'],
                    'status' => $attendeeRecord['status'],
                    'attendance_status' => $attendeeRecord['attendance_status'],
                    'entry_time' => $attendeeRecord['entry_time'] ?? null
                ];
            }
        }
    }
} elseif ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM social_registrations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();

    if ($p) {
        $participants[] = [
            'label' => 'Primary',
            'name' => $p['full_name'],
            'cnic' => $p['cnic'],
            'email' => $p['email'],
            'phone' => $p['phone'],
            'face' => $p['face_image'],
            'card' => $p['id_card_image']
        ];

        if (!empty($p['participant2_name'])) {
            $participants[] = [
                'label' => 'Person 2',
                'name' => $p['participant2_name'],
                'cnic' => $p['participant2_cnic'],
                'email' => $p['participant2_email'],
                'phone' => $p['participant2_phone'],
                'face' => $p['participant2_face'],
                'card' => $p['participant2_card']
            ];
        }

        if (!empty($p['participant3_name'])) {
            $participants[] = [
                'label' => 'Person 3',
                'name' => $p['participant3_name'],
                'cnic' => $p['participant3_cnic'],
                'email' => $p['participant3_email'],
                'phone' => $p['participant3_phone'],
                'face' => $p['participant3_face'],
                'card' => $p['participant3_card']
            ];
        }

        $memberIdx = $memberIndex - 1;
        if (!isset($participants[$memberIdx])) {
            $memberIndex = 1;
            $memberIdx = 0;
        }
        $currentMember = $participants[$memberIdx];
    }
}

if ($attendeeMode && !empty($participants)) {
    foreach ($participants as $member) {
        $rosterTabs[] = [
            'label' => !empty($member['label']) ? $member['label'] : ('Person ' . $member['person_index']),
            'name' => $member['full_name'],
            'link' => 'verify_social.php?attendee=' . $member['id'],
            'active' => $member['id'] == $attendeeId
        ];
    }
    if ($currentMember) {
        $displayStatus = $currentMember['status'] ?? 'pending';
        $displayAttendance = $currentMember['attendance_status'] ?? 'pending';
        $displayEntry = $currentMember['entry_time'] ?? null;
    }
} elseif (!empty($participants)) {
    foreach ($participants as $index => $member) {
        $rosterTabs[] = [
            'label' => $member['label'],
            'name' => $member['name'],
            'link' => 'verify_social.php?id=' . $id . '&member=' . ($index + 1),
            'active' => ($memberIndex == $index + 1)
        ];
    }
    if ($p) {
        $displayStatus = $p['status'];
        $displayAttendance = $p['attendance_status'];
        $displayEntry = $p['entry_time'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Social Guest</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #050505; color: #fff; font-family: 'Outfit', sans-serif; margin: 0; padding: 20px; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        
        /* CARD STYLE */
        .glass-panel {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px; padding: 30px; width: 100%; max-width: 400px; text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5); position: relative;
        }

        /* IMAGE STYLES */
        .img-wrap { width: 140px; height: 140px; margin: 0 auto 20px; position: relative; }
        .user-img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; border: 4px solid #00FF94; box-shadow: 0 0 30px rgba(0, 255, 148, 0.3); }
        
        h2 { margin: 10px 0 5px; color: #fff; font-size: 1.8rem; }
        p { color: #888; margin: 0; font-size: 1rem; }
        
        /* BADGES */
        .badge { display: inline-block; padding: 8px 20px; border-radius: 50px; font-weight: bold; margin: 20px 0; font-size: 0.9rem; letter-spacing: 1px; text-transform: uppercase; }
        .st-valid { background: rgba(0, 255, 148, 0.1); color: #00FF94; border: 1px solid #00FF94; }
        .st-used  { background: rgba(255, 68, 68, 0.1); color: #ff4444; border: 1px solid #ff4444; }

        /* BUTTONS */
        .btn-action { 
            background: #00FF94; color: #000; border: none; padding: 18px; width: 100%; 
            border-radius: 50px; font-size: 1.1rem; font-weight: 800; text-transform: uppercase; 
            cursor: pointer; margin-top: 20px; box-shadow: 0 0 30px rgba(0, 255, 148, 0.4); transition: 0.3s;
        }
        .btn-action:active { transform: scale(0.95); }
        
        .success-overlay {
            background: #00FF94; color: #000; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 100;
        }
        
        .back-link { display: block; margin-top: 20px; color: #666; text-decoration: none; font-size: 0.9rem; }

        .member-tabs { display: flex; gap: 8px; justify-content: center; margin-bottom: 20px; flex-wrap: wrap; }
        .member-tabs a { padding: 8px 14px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.2); color: #aaa; text-decoration: none; font-size: 0.8rem; letter-spacing: 0.5px; text-transform: uppercase; }
        .member-tabs a.active { background: #00FF94; color: #000; border-color: #00FF94; font-weight: 700; }
        .member-details { text-align: left; margin-top: 15px; font-size: 0.9rem; color: #bbb; }
        .member-details span { display: block; margin-bottom: 5px; }
    </style>
</head>
<body>

    <?php if ($msg === "SUCCESS"): ?>
        <div class="success-overlay">
            <i class="fas fa-check-circle" style="font-size: 6rem; margin-bottom: 20px;"></i>
            <h1 style="margin: 0; font-size: 3.5rem; letter-spacing: 2px;">WELCOME!</h1>
            <p style="color: #000; font-weight: bold; opacity: 0.7; font-size: 1.2rem; margin-top: 10px;">Guest Confirmed</p>
            <a href="index.php" style="margin-top: 50px; background: #000; color: #00FF94; padding: 15px 40px; border-radius: 50px; font-weight: bold; font-size: 1.1rem; text-transform: uppercase; text-decoration:none;">Scan Next</a>
        </div>
    <?php endif; ?>

    <div class="glass-panel">
        <?php if (!$p): ?>
            <i class="fas fa-search" style="font-size: 3rem; color: #333; margin-bottom: 20px;"></i>
            <h3 style="color:#ff4444;">No Record Found</h3>
            <p>Request could not be located.</p>
            <a href="index.php" class="back-link">Back to Dashboard</a>
        <?php else: ?>
            <?php if (count($rosterTabs) > 1): ?>
                <div class="member-tabs">
                    <?php foreach ($rosterTabs as $tab): ?>
                        <a href="<?php echo $tab['link']; ?>" class="<?php echo $tab['active'] ? 'active' : ''; ?>"><?php echo htmlspecialchars($tab['label']); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="img-wrap">
                <?php 
                $img = (!empty($currentMember['face'])) ? "../".$currentMember['face'] : "../assets/img/default_user.png"; 
                ?>
                <img src="<?php echo $img; ?>" class="user-img">
            </div>

            <h2><?php echo htmlspecialchars($currentMember['name'] ?? $p['full_name']); ?></h2>
            <?php if (!empty($currentMember['cnic'])): ?>
                <p>CNIC: <?php echo htmlspecialchars($currentMember['cnic']); ?></p>
            <?php endif; ?>
            <div class="member-details">
                <?php if (!empty($currentMember['phone'])): ?><span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($currentMember['phone']); ?></span><?php endif; ?>
                <?php if (!empty($currentMember['email'])): ?><span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($currentMember['email']); ?></span><?php endif; ?>
            </div>

            <?php if ($displayStatus !== 'approved'): ?>
                <div class="badge st-used">NOT APPROVED</div>
                <p style="color:#ff4444; font-size:0.9rem;">Status is "<?php echo ucfirst($displayStatus); ?>"</p>
            
            <?php elseif ($displayAttendance === 'present'): ?>
                <div class="badge st-used">ALREADY ENTERED</div>
                <p style="font-size:0.8rem; color:#ff4444;">
                    Scanned at: <?php echo $displayEntry ? date('h:i A', strtotime($displayEntry)) : '—'; ?>
                </p>
                <a href="index.php" class="back-link">Scan Next</a>
            
            <?php else: ?>
                <div class="badge st-valid">VALID PASS</div>
                <form method="POST">
                    <input type="hidden" name="mark_present" value="1">
                    <?php if ($attendeeMode): ?>
                        <input type="hidden" name="attendee_id" value="<?php echo $attendeeId; ?>">
                    <?php else: ?>
                        <input type="hidden" name="member" value="<?php echo $memberIndex; ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn-action">ADMIT GUEST</button>
                </form>
            <?php endif; ?>

            <?php if (count($rosterTabs) > 1): ?>
                <div class="member-details" style="margin-top:25px; border-top:1px solid rgba(255,255,255,0.08); padding-top:15px;">
                    <strong style="color:#fff; display:block; margin-bottom:8px;">Group Roster</strong>
                    <?php foreach ($rosterTabs as $tab): ?>
                        <span style="display:block; margin-bottom:4px; color: <?php echo $tab['active'] ? '#00FF94' : '#888'; ?>;">
                            <?php echo htmlspecialchars($tab['label']); ?> · <?php echo htmlspecialchars($tab['name']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

</body>
</html>
