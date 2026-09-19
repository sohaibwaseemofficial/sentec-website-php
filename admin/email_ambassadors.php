<?php
session_start();
if (!isset($_SESSION['admin'])) { 
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); 
    exit; 
}

header('Content-Type: application/json');
require_once '../db_connection.php';
require_once __DIR__ . '/../mailer.php';

$ids = $_POST['ids'] ?? [];
$mode = $_POST['mode'] ?? 'bulk';
$requestedType = strtolower($_POST['ambassador_type'] ?? '');
$typeFilter = in_array($requestedType, ['volunteer','brand'], true) ? $requestedType : '';
$sent = 0;
$errors = [];

try {
    // Build query with prepared statement
    $sql = "SELECT * FROM brand_ambassadors WHERE status = 'active'";
    if ($typeFilter) {
        $sql .= " AND ambassador_type = ?";
    }
    
    if ($mode === 'single' && !empty($ids)) {
        $sql = "SELECT * FROM brand_ambassadors WHERE id = ?";
        $typeFilter = ''; // Override for single
    }
    
    $stmt = $conn->prepare($sql);
    
    if ($mode === 'single' && !empty($ids)) {
        $id = intval($ids[0]);
        $stmt->bind_param("i", $id);
    } elseif ($typeFilter) {
        $stmt->bind_param("s", $typeFilter);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $to = $row['email'];
            $name = $row['name'];
            $code = $row['code'];
            $ambType = strtolower($row['ambassador_type'] ?? '');
            $typeLabel = $ambType === 'volunteer' ? 'Volunteer Ambassador' : 'Brand Ambassador';
            $pwd = !empty($row['initial_password']) ? $row['initial_password'] : "Contact Admin";

            try {
                $mail = sentec_mailer();
                $mail->addAddress($to, $name);
                $mail->Subject = "Your Official Ambassador Credentials";

                $greeting = 'Dear ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',';
                $bodyHtml = "<p>You have been registered as an official SENTEC " . htmlspecialchars($typeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ".</p>
                    <div style='margin:24px 0;padding:18px 22px;border-radius:14px;border:1px dashed #1f2937;background:#020617;'>
                    <p style='margin:0 0 6px 0;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#9ca3af;'>Login ID</p>
                    <p style='margin:0 0 12px 0;font-size:16px;font-weight:600;color:#f9fafb;'>{$code}</p>
                    <p style='margin:0 0 6px 0;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#9ca3af;'>Password</p>
                    <p style='margin:0;font-size:16px;font-weight:600;color:#00ff94;'>{$pwd}</p>
                    </div>
                    <p>Please use these credentials to access your ambassador dashboard and keep your details secure.</p>";

                $loginUrl = env('APP_URL', 'https://sentenceduet.live') . '/ambassador_login.php';
                $mail->Body = sentec_build_email_html(
                    'Ambassador Program',
                    'Welcome Aboard!',
                    $greeting,
                    $bodyHtml,
                    $loginUrl,
                    'Access Dashboard',
                    null
                );

                $mail->AltBody = "Your ambassador login ID is {$code} and password is {$pwd}. Login at: {$loginUrl}";
                $mail->send();
                $sent++;
                sentec_mail_log('ambassador_credentials', 'sent', "to=$to");
            } catch (Exception $e) {
                $errors[] = "Failed for $name";
                sentec_mail_log('ambassador_credentials', 'error', $e->getMessage());
            }
        }
    }
    $stmt->close();

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

echo json_encode(['sent' => $sent, 'errors' => $errors]);
?>