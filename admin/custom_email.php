<?php
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['sent' => 0, 'errors' => ['Unauthorized access.']]);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../mailer.php';

$response = ['sent' => 0, 'errors' => []];

$validTypes = ['volunteer', 'brand'];
$ambassadorType = strtolower($_POST['ambassador_type'] ?? '');
if (!in_array($ambassadorType, $validTypes, true)) {
    $ambassadorType = '';
}

$recipientIds = $_POST['recipient_ids'] ?? [];
if (!is_array($recipientIds)) {
    $recipientIds = [];
}

$idList = [];
foreach ($recipientIds as $value) {
    $id = (int)$value;
    if ($id > 0) { $idList[] = $id; }
}
$idList = array_values(array_unique($idList));
if (empty($idList)) {
    $response['errors'][] = 'No recipients selected.';
    echo json_encode($response);
    exit;
}

$subject = trim($_POST['subject'] ?? '');
$title = trim($_POST['title'] ?? '');
$heading = trim($_POST['heading'] ?? '');
$greetingTemplate = trim($_POST['greeting'] ?? '');
$bodyTemplate = trim($_POST['body'] ?? '');
$ctaLabel = trim($_POST['cta_label'] ?? '');
$ctaUrl = trim($_POST['cta_url'] ?? '');
$footerNote = trim($_POST['footer_note'] ?? '');

if ($subject === '' || $heading === '' || $bodyTemplate === '') {
    $response['errors'][] = 'Subject, headline, and message body are required.';
    echo json_encode($response);
    exit;
}

if (($ctaLabel !== '' && $ctaUrl === '') || ($ctaLabel === '' && $ctaUrl !== '')) {
    $response['errors'][] = 'CTA label and URL must both be provided or left empty together.';
    echo json_encode($response);
    exit;
}

if ($ctaUrl !== '' && !filter_var($ctaUrl, FILTER_VALIDATE_URL)) {
    $response['errors'][] = 'CTA URL is not a valid link.';
    echo json_encode($response);
    exit;
}

$hasTypeColumn = false;
try {
    $col = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'ambassador_type'");
    $hasTypeColumn = $col && $col->num_rows > 0;
} catch (Exception $e) {}

$idSql = implode(',', $idList);
$sql = "SELECT id, name, email, code";
if ($hasTypeColumn) { $sql .= ", ambassador_type"; }
$sql .= " FROM brand_ambassadors WHERE id IN ($idSql)";
if ($hasTypeColumn && $ambassadorType) {
    $sql .= " AND ambassador_type = '" . $conn->real_escape_string($ambassadorType) . "'";
}

$result = $conn->query($sql);
if (!$result) {
    $response['errors'][] = 'Unable to load ambassador records.';
    echo json_encode($response);
    exit;
}

if ($result->num_rows === 0) {
    $response['errors'][] = 'No matching ambassadors were found.';
    echo json_encode($response);
    exit;
}

$defaultGreeting = $greetingTemplate !== '' ? $greetingTemplate : 'Hello {{name}},';
$title = $title !== '' ? $title : 'Ambassador Update';
$buttonTextTemplate = $ctaLabel !== '' ? $ctaLabel : null;
$buttonUrlTemplate = $ctaUrl !== '' ? $ctaUrl : null;

while ($row = $result->fetch_assoc()) {
    $email = trim($row['email'] ?? '');
    if ($email === '') { continue; }
    $name = $row['name'] ?? '';
    $code = $row['code'] ?? '';

    if ($hasTypeColumn && $ambassadorType && strtolower($row['ambassador_type'] ?? '') !== $ambassadorType) {
        continue;
    }

    $tokens = [
        '{{name}}' => $name,
        '{{code}}' => $code,
    ];

    $greetingLine = htmlspecialchars(strtr($defaultGreeting, $tokens), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $personalBody = strtr($bodyTemplate, $tokens);
    $bodyHtml = nl2br(htmlspecialchars($personalBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    $footer = $footerNote !== '' ? htmlspecialchars(strtr($footerNote, $tokens), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : null;
    $buttonText = $buttonTextTemplate ? strtr($buttonTextTemplate, $tokens) : null;
    $buttonUrl = $buttonUrlTemplate ? strtr($buttonUrlTemplate, $tokens) : null;

    try {
        $mail = sentec_mailer();
        $mail->addAddress($email, $name);
        $mail->Subject = $subject;
        $mail->Body = sentec_build_email_html(
            $title,
            $heading,
            $greetingLine,
            $bodyHtml,
            $buttonUrl ?: null,
            $buttonText ?: null,
            $footer
        );
        $plainGreeting = strtr($defaultGreeting, $tokens);
        $plainBody = strip_tags(str_replace(['<br />', '<br>', '<br/>'], "\n", $bodyHtml));
        $mail->AltBody = $plainGreeting . "\n\n" . $plainBody;
        $mail->send();
        $response['sent']++;
        sentec_mail_log('custom_email', 'sent', "to={$email}");
    } catch (Exception $e) {
        $response['errors'][] = "Failed for {$name} ({$email})";
        sentec_mail_log('custom_email', 'error', $e->getMessage());
    }
}

echo json_encode($response);
exit;
