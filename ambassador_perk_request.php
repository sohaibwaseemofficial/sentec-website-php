<?php
session_start();
require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/mailer.php';
if (!isset($_SESSION['ambassador_id'])) { header('Location: ambassador_login.php'); exit; }
header('Content-Type: text/html; charset=utf-8');

$aid = (int)$_SESSION['ambassador_id'];
$code = $_SESSION['ambassador_code'];
$name = $_SESSION['ambassador_name'];
$action = $_POST['action'] ?? '';
$validTypes = ['volunteer' => 2, 'brand' => 5];
$ambType = strtolower($_SESSION['ambassador_type'] ?? '');
if (!array_key_exists($ambType, $validTypes)) {
  $ambType = 'brand';
}
$perkThreshold = $validTypes[$ambType];

// Detect columns
$hasPerk = false; $hasInitialPwd=false;
try { $c=$conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'perk_requested'"); $hasPerk = $c && $c->num_rows>0; } catch(Exception $e){}
if (!$hasPerk) { echo '<script>alert("Perk tracking not configured yet. Contact admin.");window.location.href="ambassador_dashboard.php";</script>'; exit; }

// Check approved teams count
$approved = 0; $stmt = $conn->prepare("SELECT COUNT(*) c FROM event_registrations WHERE brand_ambassador_code=? AND status='approved'");
$stmt->bind_param('s',$code); $stmt->execute(); $rs=$stmt->get_result(); $approved = (int)$rs->fetch_assoc()['c']; $stmt->close();
$manualCredit = 0; $perkOverride = 0;
try {
  $hasManualColumn = false; $hasPerkOverrideColumn = false;
  $col = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'manual_registration_credit'");
  $hasManualColumn = $col && $col->num_rows>0;
  $col2 = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'perk_threshold_override'");
  $hasPerkOverrideColumn = $col2 && $col2->num_rows>0;
  $hasTypeColumn = false;
  $col3 = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'ambassador_type'");
  $hasTypeColumn = $col3 && $col3->num_rows>0;
  if ($hasManualColumn || $hasPerkOverrideColumn || $hasTypeColumn) {
    $select = [];
    if ($hasManualColumn) { $select[] = 'manual_registration_credit'; }
    if ($hasPerkOverrideColumn) { $select[] = 'perk_threshold_override'; }
    if ($hasTypeColumn) { $select[] = 'ambassador_type'; }
    $fieldList = implode(', ', $select);
    if ($fieldList) {
      $mc = $conn->prepare("SELECT $fieldList FROM brand_ambassadors WHERE id=? LIMIT 1");
      if ($mc) {
        $mc->bind_param('i',$aid);
        $mc->execute();
        $mr = $mc->get_result()->fetch_assoc();
        $mc->close();
        if ($mr) {
          if ($hasManualColumn) { $manualCredit = max(0,(int)($mr['manual_registration_credit'] ?? 0)); }
          if ($hasPerkOverrideColumn) { $perkOverride = max(0,(int)($mr['perk_threshold_override'] ?? 0)); }
          if ($hasTypeColumn && isset($mr['ambassador_type'])) {
            $dbType = strtolower($mr['ambassador_type']);
            if (array_key_exists($dbType, $validTypes)) {
              $ambType = $dbType;
              $_SESSION['ambassador_type'] = $ambType;
            }
          }
        }
      }
    }
  }
  $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
      setting_key VARCHAR(100) PRIMARY KEY,
      setting_value VARCHAR(255) NOT NULL,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $defaultSettingSeeds = [
      'ambassador_perk_threshold' => '2',
      'volunteer_perk_threshold' => (string)$validTypes['volunteer'],
      'brand_perk_threshold' => (string)$validTypes['brand']
  ];
  foreach ($defaultSettingSeeds as $settingKey => $settingValue) {
      $escapedKey = $conn->real_escape_string($settingKey);
      $escapedVal = $conn->real_escape_string($settingValue);
      $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('$escapedKey','$escapedVal')");
  }
  $thresholdKeys = [
      $ambType . '_perk_threshold',
      'ambassador_perk_threshold_' . $ambType,
      'ambassador_perk_threshold'
  ];
  foreach ($thresholdKeys as $settingKey) {
      if (!$settingKey) { continue; }
      $th = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key=? LIMIT 1");
      if ($th) {
          $th->bind_param('s', $settingKey);
          $th->execute();
          $tv = $th->get_result()->fetch_assoc();
          $th->close();
          if ($tv) {
              $val = (int)$tv['setting_value'];
              if ($val > 0) { $perkThreshold = $val; break; }
          }
      }
  }
  if ($perkOverride > 0) { $perkThreshold = $perkOverride; }
} catch(Exception $e){}
$approvedWithManual = $approved + $manualCredit;

if ($action==='request') {
  if ($approvedWithManual < $perkThreshold) {
    $msg = "You need at least {$perkThreshold} approved team" . ($perkThreshold > 1 ? 's' : '') . " to request the perk. Current effective count: {$approvedWithManual}";
    if ($manualCredit > 0) { $msg .= " (includes {$manualCredit} manual bonus)"; }
    echo '<script>alert('.json_encode($msg).');window.location.href="ambassador_dashboard.php";</script>'; exit;
  }
  // Update request status if not already requested/granted
  $chk = $conn->prepare("SELECT perk_requested, perk_granted FROM brand_ambassadors WHERE id=? LIMIT 1");
  $chk->bind_param('i',$aid); $chk->execute(); $ri=$chk->get_result()->fetch_assoc(); $chk->close();
  if ($ri && $ri['perk_granted']) { echo '<script>alert("Perk already granted.");window.location.href="ambassador_dashboard.php";</script>'; exit; }
  if ($ri && $ri['perk_requested']) { echo '<script>alert("Perk already requested and pending.");window.location.href="ambassador_dashboard.php";</script>'; exit; }
  $up = $conn->prepare("UPDATE brand_ambassadors SET perk_requested=1, perk_requested_at=NOW() WHERE id=?"); $up->bind_param('i',$aid); $up->execute(); $up->close();
  // Notify admin via email (if FROM_EMAIL configured)
  try {
    $mailer = sentec_mailer();
    $adminEmail = getenv('ADMIN_NOTIFY_EMAIL') ?: getenv('FROM_EMAIL');
    if ($adminEmail) {
      $mailer->addAddress($adminEmail,'Admin');
      $mailer->Subject = "Perk Request: " . ucfirst($ambType) . " Ambassador $code";
      $mailer->isHTML(true);
      $mailer->Body = "<p>" . ucfirst($ambType) . " ambassador <strong>".htmlspecialchars($name)."</strong> (Code <strong>$code</strong>) has requested the DataCamp Premium perk. Threshold: $perkThreshold. Approved teams: $approved (manual bonus: $manualCredit; effective total: $approvedWithManual).</p>";
      $mailer->AltBody = ucfirst($ambType) . " ambassador $name ($code) requested perk. Threshold: $perkThreshold. Approved: $approved. Manual bonus: $manualCredit. Effective: $approvedWithManual.";
      @$mailer->send();
    }
  } catch(Exception $e) {}
  echo '<script>alert("Perk request submitted successfully.");window.location.href="ambassador_dashboard.php";</script>'; exit;
}

echo '<script>window.location.href="ambassador_dashboard.php";</script>';