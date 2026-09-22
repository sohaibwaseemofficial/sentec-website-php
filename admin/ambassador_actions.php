<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: admin_login.php'); exit; }
require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../mailer.php';

function bind_dynamic_params(mysqli_stmt $stmt, string $types, array &$values): void {
    $params = [$types];
    foreach ($values as $index => &$value) {
        $params[] = &$values[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $params);
}

$validTypes = ['volunteer', 'brand'];
$requestedType = strtolower($_REQUEST['ambassador_type'] ?? '');
if (!in_array($requestedType, $validTypes, true)) {
    $requestedType = 'volunteer';
}

function redirect_back(string $type) {
    header('Location: manage_ambassadors.php?type=' . urlencode($type));
    exit;
}

$action = $_REQUEST['action'] ?? '';

function ensure_settings_table($conn) {
    @$conn->query("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
if ($action === 'update_threshold') {
    $threshold = (int)($_POST['threshold'] ?? 2);
    if ($threshold < 1) { $threshold = 1; }
    ensure_settings_table($conn);
    try {
        $value = (string)$threshold;
        $keysToUpdate = [
            $requestedType . '_perk_threshold',
            'ambassador_perk_threshold_' . $requestedType
        ];
        foreach ($keysToUpdate as $settingKey) {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            if ($stmt) {
                $stmt->bind_param('ss', $settingKey, $value);
                @$stmt->execute();
                $stmt->close();
            }
        }
    } catch (Exception $e) {}
    redirect_back($requestedType);
}

// Column detection (initial_password, manual credit, perk overrides)
$hasInitialPwd=false; $hasPerk=false; $hasManualCredit=false; $hasPerkOverride=false; $hasTypeColumn=false;
try { $c=$conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'initial_password'"); $hasInitialPwd = $c && $c->num_rows>0; } catch(Exception $e){}
try { $c2=$conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'perk_requested'"); $hasPerk = $c2 && $c2->num_rows>0; } catch(Exception $e){}
try {
    $c3=$conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'manual_registration_credit'");
    if ($c3 && $c3->num_rows===0) {
        @$conn->query("ALTER TABLE brand_ambassadors ADD COLUMN manual_registration_credit INT DEFAULT 0");
        $c3=$conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'manual_registration_credit'");
    }
    $hasManualCredit = $c3 && $c3->num_rows>0;
} catch(Exception $e){}
try {
    $c5=$conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'ambassador_type'");
    $hasTypeColumn = $c5 && $c5->num_rows>0;
} catch(Exception $e){}
try {
    $c4=$conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'perk_threshold_override'");
    if ($c4 && $c4->num_rows===0) {
        @$conn->query("ALTER TABLE brand_ambassadors ADD COLUMN perk_threshold_override INT DEFAULT 0");
        $c4=$conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'perk_threshold_override'");
    }
    $hasPerkOverride = $c4 && $c4->num_rows>0;
} catch(Exception $e){}

if ($action === 'add') {
        // Fetch active event label
        $activeEventLabel = '';
        $eventRes = $conn->query("SELECT title FROM events WHERE status = 'upcoming' ORDER BY event_date DESC LIMIT 1");
        if ($eventRes && $eventRes->num_rows > 0) {
            $activeEventLabel = $eventRes->fetch_assoc()['title'];
        } else {
            $activeEventLabel = 'proxion_2026'; // fallback
        }
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $institution = trim($_POST['institution'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    $password = trim($_POST['password'] ?? '');
    $manualCredit = (int)($_POST['manual_registration_credit'] ?? 0);
    if ($manualCredit < 0) { $manualCredit = 0; }
    $thresholdOverride = (int)($_POST['perk_threshold_override'] ?? 0);
    if ($thresholdOverride < 0) { $thresholdOverride = 0; }

    if ($name && $email && $code) {
        $hash = $password ? password_hash($password, PASSWORD_BCRYPT) : null;
        $columns = ['name','email','phone','institution','code','status'];
        $types = 'ssssss';
        $values = [&$name,&$email,&$phone,&$institution,&$code,&$status];
        if ($hasTypeColumn) {
            $columns[] = 'ambassador_type';
            $types .= 's';
            $values[] = &$requestedType;
        }
        $columns[] = 'password_hash';
        $types .= 's';
        $values[] = &$hash;
        if ($hasInitialPwd) {
            $columns[] = 'initial_password';
            $types .= 's';
            $values[] = &$password;
        }
        if ($hasManualCredit) {
            $columns[] = 'manual_registration_credit';
            $types .= 'i';
            $values[] = &$manualCredit;
        }
        if ($hasPerkOverride) {
            $columns[] = 'perk_threshold_override';
            $types .= 'i';
            $values[] = &$thresholdOverride;
        }
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO brand_ambassadors (" . implode(', ', $columns) . ", created_at) VALUES ($placeholders, NOW())";
        // Add event_label column and value
        $columns[] = 'event_label';
        $types .= 's';
        $values[] = &$activeEventLabel;
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO brand_ambassadors (" . implode(', ', $columns) . ", created_at) VALUES ($placeholders, NOW())";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            bind_dynamic_params($stmt, $types, $values);
            @$stmt->execute();
            $stmt->close();
        }
    }
    redirect_back($requestedType);
}

if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $institution = trim($_POST['institution'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    $password = trim($_POST['password'] ?? '');

    $manualCredit = (int)($_POST['manual_registration_credit'] ?? 0);
    if ($manualCredit < 0) { $manualCredit = 0; }
    $thresholdOverride = (int)($_POST['perk_threshold_override'] ?? 0);
    if ($thresholdOverride < 0) { $thresholdOverride = 0; }

    if ($id > 0 && $name && $email && $code) {
        $setParts = ['name=?','email=?','phone=?','institution=?','code=?','status=?'];
        $types = 'ssssss';
        $values = [&$name,&$email,&$phone,&$institution,&$code,&$status];
        if ($hasTypeColumn) {
            $setParts[] = 'ambassador_type=?';
            $types .= 's';
            $values[] = &$requestedType;
        }
        if ($password) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $setParts[] = 'password_hash=?';
            $types .= 's';
            $values[] = &$hash;
            if ($hasInitialPwd) {
                $setParts[] = 'initial_password=?';
                $types .= 's';
                $values[] = &$password;
            }
        }
        if ($hasManualCredit) {
            $setParts[] = 'manual_registration_credit=?';
            $types .= 'i';
            $values[] = &$manualCredit;
        }
        if ($hasPerkOverride) {
            $setParts[] = 'perk_threshold_override=?';
            $types .= 'i';
            $values[] = &$thresholdOverride;
        }
        $types .= 'i';
        $values[] = &$id;
        $sql = "UPDATE brand_ambassadors SET " . implode(', ', $setParts) . " WHERE id=?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            bind_dynamic_params($stmt, $types, $values);
            @$stmt->execute();
            $stmt->close();
        }
    }
    redirect_back($requestedType);
}

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM brand_ambassadors WHERE id = ?");
        if ($stmt) { $stmt->bind_param('i',$id); @$stmt->execute(); $stmt->close(); }
    }
    redirect_back($requestedType);
}

if ($action === 'grant_perk' && $hasPerk) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        // Mark granted
        $stmt = $conn->prepare("UPDATE brand_ambassadors SET perk_granted=1, perk_granted_at=NOW() WHERE id=? AND perk_requested=1 AND perk_granted=0");
        if ($stmt) { $stmt->bind_param('i',$id); @$stmt->execute(); $stmt->close(); }
        // Email ambassador about perk granting
        $info = $conn->prepare("SELECT email,name,code FROM brand_ambassadors WHERE id=? LIMIT 1");
        $info->bind_param('i',$id); $info->execute(); $r=$info->get_result()->fetch_assoc(); $info->close();
        if ($r && $r['email']) {
            try {
                $mailer = sentec_mailer();
                $mailer->addAddress($r['email'],$r['name']);
                $mailer->Subject = 'Your DataCamp Premium Perk Has Been Granted';
                $mailer->isHTML(true);
                $mailer->Body = "<div style='font-family:Outfit,Arial,sans-serif;background:#0d1117;padding:24px;color:#e0e6ed'>"
                  ."<h2 style='color:#7c4dff;margin-top:0'>Congratulations!</h2>"
                  ."<p>Your DataCamp Premium perk request has been approved for Ambassador Code <strong>".htmlspecialchars($r['code'])."</strong>.</p>"
                  ."<p>Follow the instructions shared separately to activate your account. If you do not receive them shortly, reply to this email.</p>"
                  ."<p style='font-size:12px;color:#8892a0'>SENTEC Ambassador Program</p></div>";
                $mailer->AltBody = 'Your DataCamp Premium perk has been granted.';
                @$mailer->send();
            } catch(Exception $e) {}
        }
    }
    redirect_back($requestedType);
}

redirect_back($requestedType);
