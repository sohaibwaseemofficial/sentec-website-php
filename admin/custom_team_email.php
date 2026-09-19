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

$registrationId = (int)($_POST['registration_id'] ?? 0);
if ($registrationId <= 0) {
    $response['errors'][] = 'No registration selected.';
    echo json_encode($response);
    exit;
}

$rawKeys = $_POST['recipient_keys'] ?? [];
if (!is_array($rawKeys)) {
    $rawKeys = [];
}
$recipientKeys = [];
foreach ($rawKeys as $value) {
    $value = trim((string)$value);
    if (preg_match('/^participant[1-6]$/', $value)) {
        $recipientKeys[] = $value;
    }
}
$recipientKeys = array_values(array_unique($recipientKeys));
if (empty($recipientKeys)) {
    $response['errors'][] = 'Select at least one team member.';
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

$stmt = $conn->prepare(
    "SELECT team_name, module_selection, institution_type,
        participant1_name, participant1_email, participant1_contact,
        participant2_name, participant2_email, participant2_contact,
        participant3_name, participant3_email, participant3_contact,
        participant4_name, participant4_email, participant4_contact
     FROM event_registrations WHERE id = ? LIMIT 1"
);

if (!$stmt) {
    $response['errors'][] = 'Unable to prepare registration lookup.';
    echo json_encode($response);
    exit;
}

$stmt->bind_param('i', $registrationId);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    $response['errors'][] = 'Registration record not found.';
    echo json_encode($response);
    $stmt->close();
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();

$teamName = trim($row['team_name'] ?? '');
$module = trim($row['module_selection'] ?? '');
$institution = trim($row['institution_type'] ?? '');

$participants = [];
for ($i = 1; $i <= 4; $i++) {
    $participants["participant{$i}"] = [
        'name' => trim($row["participant{$i}_name"] ?? ''),
        'email' => trim($row["participant{$i}_email"] ?? ''),
        'contact' => trim($row["participant{$i}_contact"] ?? '')
    ];
}
$leaderName = $participants['participant1']['name'] ?? '';

$defaultGreeting = $greetingTemplate !== '' ? $greetingTemplate : 'Hello {{participant_name}},';
$titleFinal = $title !== '' ? $title : 'Team Update';
$buttonTextTemplate = $ctaLabel !== '' ? $ctaLabel : null;
$buttonUrlTemplate = $ctaUrl !== '' ? $ctaUrl : null;

foreach ($recipientKeys as $key) {
    if (!isset($participants[$key])) {
        $response['errors'][] = 'Participant record missing for ' . $key . '.';
        continue;
    }
    $participant = $participants[$key];
    $email = $participant['email'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $displayName = $participant['name'] !== '' ? $participant['name'] : ucfirst($key);
        $response['errors'][] = $displayName . ' does not have a valid email address on file.';
        continue;
    }
    $name = $participant['name'] !== '' ? $participant['name'] : 'Participant';

    $tokens = [
        '{{participant_name}}' => $name,
        '{{team_name}}' => $teamName,
        '{{team}}' => $teamName,
        '{{module}}' => $module,
        '{{institution}}' => $institution,
        '{{leader_name}}' => $leaderName,
        '{{participant_email}}' => $email,
        '{{email}}' => $email,
        '{{registration_id}}' => (string)$registrationId
    ];

    $greetingLine = htmlspecialchars(strtr($defaultGreeting, $tokens), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $personalBody = strtr($bodyTemplate, $tokens);
    $bodyHtml = nl2br(htmlspecialchars($personalBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    $footer = $footerNote !== '' ? htmlspecialchars(strtr($footerNote, $tokens), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : null;
    $buttonText = $buttonTextTemplate !== null ? strtr($buttonTextTemplate, $tokens) : null;
    $buttonUrl = $buttonUrlTemplate !== null ? strtr($buttonUrlTemplate, $tokens) : null;

    try {
        $mail = sentec_mailer();
        $mail->addAddress($email, $name);
        $mail->Subject = $subject;
        $mail->Body = sentec_build_email_html(
            $titleFinal,
            $heading,
            $greetingLine,
            $bodyHtml,
            ($buttonUrl !== null && $buttonText !== null) ? $buttonUrl : null,
            ($buttonUrl !== null && $buttonText !== null) ? $buttonText : null,
            $footer
        );
        $plainGreeting = strtr($defaultGreeting, $tokens);
        $plainBody = strtr($bodyTemplate, $tokens);
        $plainBody = preg_replace("/(\r\n|\r|\n)/", "\n", $plainBody);
        $mail->AltBody = $plainGreeting . "\n\n" . $plainBody;
        $mail->send();
        $response['sent']++;
        sentec_mail_log('custom_team_email', 'sent', "registration={$registrationId} to={$email}");
    } catch (Exception $e) {
        $response['errors'][] = 'Failed for ' . $name . ' (' . $email . ').';
        sentec_mail_log('custom_team_email', 'error', $e->getMessage());
    }
}

echo json_encode($response);
exit;
