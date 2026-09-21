<?php
session_start();
if (!isset($_SESSION['is_event_gatekeeper'])) { header("Location: login.php"); exit; }

include '../db_connection.php';
require_once __DIR__ . '/../event_attendees_helper.php';

$attendeeId = isset($_GET['attendee']) ? intval($_GET['attendee']) : 0;
$msg = '';
$markedDay = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_day'])) {
    $attendeeId = intval($_POST['attendee_id'] ?? 0);
    $day = intval($_POST['mark_day']);
    if (event_mark_attendance($conn, $attendeeId, $day)) {
        $msg = 'SUCCESS';
        $markedDay = $day;
    }
}

$attendee = $attendeeId ? event_attendee_fetch_with_registration($conn, $attendeeId) : null;
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Event Attendee</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #050505; color: #fff; font-family: 'Outfit', sans-serif; margin: 0; padding: 20px; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .glass-panel { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 24px; padding: 30px; width: 100%; max-width: 420px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.5); position: relative; }
        .img-wrap { width: 140px; height: 140px; margin: 0 auto 20px; position: relative; }
        .user-img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; border: 4px solid #00FF94; box-shadow: 0 0 30px rgba(0, 255, 148, 0.3); }
        h2 { margin: 10px 0 5px; color: #fff; font-size: 1.8rem; }
        p { color: #888; margin: 0; font-size: 1rem; }
        .badge { display: inline-block; padding: 8px 20px; border-radius: 50px; font-weight: bold; margin: 20px 0 8px 0; font-size: 0.9rem; letter-spacing: 1px; }
        .st-valid { background: rgba(0, 255, 148, 0.1); color: #00FF94; border: 1px solid #00FF94; }
        .st-used  { background: rgba(255, 68, 68, 0.1); color: #ff4444; border: 1px solid #ff4444; }
        .st-pending { background: rgba(255, 187, 51, 0.1); color: #ffbb33; border: 1px solid #ffbb33; }
        .btn-action { background: #00FF94; color: #000; border: none; padding: 16px; width: 100%; border-radius: 50px; font-size: 1.05rem; font-weight: 800; text-transform: uppercase; cursor: pointer; margin-top: 12px; box-shadow: 0 0 30px rgba(0, 255, 148, 0.4); transition: 0.3s; }
        .btn-action:active { transform: scale(0.97); }
        .success-overlay { background: #00FF94; color: #000; position: fixed; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 100; }
        .back-link { display: block; margin-top: 20px; color: #666; text-decoration: none; font-size: 0.9rem; }
        .member-details { text-align: left; margin-top: 15px; font-size: 0.9rem; color: #bbb; }
        .member-details span { display: block; margin-bottom: 5px; }
        .day-row { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; padding: 10px 12px; border-radius: 12px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); }
        .day-pill { padding: 6px 12px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; }
        .day-pill.present { background: rgba(0, 255, 148, 0.15); color: #00FF94; border: 1px solid #00FF94; }
        .day-pill.pending { background: rgba(255, 187, 51, 0.12); color: #ffbb33; border: 1px solid #ffbb33; }
    </style>
</head>
<body>

    <?php if ($msg === 'SUCCESS'): ?>
        <div class="success-overlay">
            <i class="fas fa-check-circle" style="font-size: 6rem; margin-bottom: 20px;"></i>
            <h1 style="margin: 0; font-size: 3.3rem; letter-spacing: 2px;">Attendance Saved</h1>
            <?php if ($markedDay): ?>
                <p style="color: #000; font-weight: bold; opacity: 0.7; font-size: 1.2rem; margin-top: 10px;">Day <?php echo $markedDay; ?> marked as PRESENT</p>
            <?php endif; ?>
            <a href="index" style="margin-top: 40px; background: #000; color: #00FF94; padding: 15px 40px; border-radius: 50px; font-weight: bold; font-size: 1.05rem; text-transform: uppercase; text-decoration:none;">Scan Next</a>
        </div>
    <?php endif; ?>

    <div class="glass-panel">
        <?php if (!$attendee): ?>
            <i class="fas fa-search" style="font-size: 3rem; color: #333; margin-bottom: 20px;"></i>
            <h3 style="color:#ff4444;">No Record Found</h3>
            <p>Request could not be located.</p>
            <a href="index" class="back-link">Back to Dashboard</a>
        <?php else: ?>
            <?php 
                $img = (!empty($attendee['face_image'])) ? "../" . ltrim($attendee['face_image'], '/') : "../assets/img/default_user.png";
                $approved = ($attendee['registration_status'] === 'approved');
                $day1Status = $attendee['day1_status'] ?? 'pending';
                $day2Status = $attendee['day2_status'] ?? 'pending';
            ?>

            <div class="img-wrap">
                <img src="<?php echo $img; ?>" class="user-img" alt="attendee photo">
            </div>

            <h2><?php echo htmlspecialchars($attendee['full_name']); ?></h2>
            <p style="color:#888;">Team: <?php echo htmlspecialchars($attendee['team_name'] ?? ''); ?></p>
            <p style="color:#666; font-size:0.9rem; margin-top:4px;">Role: <?php echo htmlspecialchars($attendee['label'] ?? 'Member'); ?></p>

            <?php if (!$approved): ?>
                <div class="badge st-used">NOT APPROVED</div>
                <p style="color:#ff4444; font-size:0.9rem;">Status is "<?php echo ucfirst($attendee['registration_status']); ?>"</p>
                <a href="index" class="back-link">Back to Dashboard</a>
            <?php else: ?>
                <div class="badge st-valid">APPROVED PASS</div>
                <div class="member-details">
                    <?php if (!empty($attendee['phone'])): ?><span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($attendee['phone']); ?></span><?php endif; ?>
                    <?php if (!empty($attendee['email'])): ?><span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($attendee['email']); ?></span><?php endif; ?>
                    <?php if (!empty($attendee['cnic'])): ?><span><i class="fas fa-id-card"></i> CNIC: <?php echo htmlspecialchars($attendee['cnic']); ?></span><?php endif; ?>
                    <?php if (!empty($attendee['roll_number'])): ?><span><i class="fas fa-graduation-cap"></i> Roll: <?php echo htmlspecialchars($attendee['roll_number']); ?></span><?php endif; ?>
                </div>

                <div class="day-row">
                    <div>
                        <strong>Day 1</strong>
                        <div style="color:#888; font-size:0.85rem;">Check-in: <?php echo $attendee['day1_entry_time'] ? date('h:i A', strtotime($attendee['day1_entry_time'])) : 'Not yet'; ?></div>
                    </div>
                    <span class="day-pill <?php echo ($day1Status === 'present') ? 'present' : 'pending'; ?>"><?php echo ucfirst($day1Status); ?></span>
                </div>
                <?php if ($day1Status !== 'present'): ?>
                    <form method="POST">
                        <input type="hidden" name="mark_day" value="1">
                        <input type="hidden" name="attendee_id" value="<?php echo $attendeeId; ?>">
                        <button type="submit" class="btn-action">Mark Day 1 Present</button>
                    </form>
                <?php endif; ?>

                <div class="day-row" style="margin-top:16px;">
                    <div>
                        <strong>Day 2</strong>
                        <div style="color:#888; font-size:0.85rem;">Check-in: <?php echo $attendee['day2_entry_time'] ? date('h:i A', strtotime($attendee['day2_entry_time'])) : 'Not yet'; ?></div>
                    </div>
                    <span class="day-pill <?php echo ($day2Status === 'present') ? 'present' : 'pending'; ?>"><?php echo ucfirst($day2Status); ?></span>
                </div>
                <?php if ($day2Status !== 'present'): ?>
                    <form method="POST">
                        <input type="hidden" name="mark_day" value="2">
                        <input type="hidden" name="attendee_id" value="<?php echo $attendeeId; ?>">
                        <button type="submit" class="btn-action">Mark Day 2 Present</button>
                    </form>
                <?php endif; ?>

                <?php if ($day1Status === 'present' && $day2Status === 'present'): ?>
                    <p style="color:#00FF94; font-weight:700; margin-top:14px;">Both days completed.</p>
                    <a href="index" class="back-link">Scan Next</a>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
