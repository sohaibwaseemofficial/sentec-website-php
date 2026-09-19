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

$predefinedRaw = $_POST['predefined_recipients'] ?? [];
if (!is_array($predefinedRaw)) {
    $predefinedRaw = [];
}
$manualEntries = trim($_POST['manual_recipients'] ?? '');

$ambassadorIds = [];
$teamSelections = [];
foreach ($predefinedRaw as $value) {
    $value = trim((string)$value);
    if ($value === '') {
        continue;
    }
    $parts = explode(':', $value);
    if (count($parts) < 2) {
        $response['errors'][] = 'Unrecognized recipient key: ' . $value;
        continue;
    }
    $category = strtolower(array_shift($parts));
    if ($category === 'ambassador') {
        $id = (int)($parts[0] ?? 0);
        if ($id > 0) {
            $ambassadorIds[] = $id;
        }
    } elseif ($category === 'team') {
        if (count($parts) < 2) {
            $response['errors'][] = 'Incomplete team recipient key: ' . $value;
            continue;
        }
        $registrationId = (int)$parts[0];
        $participantKey = trim($parts[1]);
        if ($registrationId > 0 && preg_match('/^participant[1-4]$/', $participantKey)) {
            if (!isset($teamSelections[$registrationId])) {
                $teamSelections[$registrationId] = [];
            }
            $teamSelections[$registrationId][] = $participantKey;
        }
    } else {
        $response['errors'][] = 'Unsupported recipient category: ' . $category;
    }
}

$ambassadorIds = array_values(array_unique(array_filter($ambassadorIds)));
foreach ($teamSelections as $registrationId => $keys) {
    $teamSelections[$registrationId] = array_values(array_unique(array_filter($keys)));
}

$recipients = [];
$seenEmails = [];

if (!empty($ambassadorIds)) {
    try {
        $table = $conn->query("SHOW TABLES LIKE 'brand_ambassadors'");
        if ($table && $table->num_rows > 0) {
            $hasTypeColumn = false;
            $col = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'ambassador_type'");
            if ($col && $col->num_rows > 0) {
                $hasTypeColumn = true;
            }
            $idList = implode(',', array_map('intval', $ambassadorIds));
            $columns = 'id, name, email, code';
            if ($hasTypeColumn) {
                $columns .= ', ambassador_type';
            }
            $ambRes = $conn->query("SELECT {$columns} FROM brand_ambassadors WHERE id IN ({$idList})");
            if ($ambRes) {
                while ($row = $ambRes->fetch_assoc()) {
                    $email = trim($row['email'] ?? '');
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }
                    $lower = strtolower($email);
                    if (isset($seenEmails[$lower])) {
                        continue;
                    }
                    $seenEmails[$lower] = true;
                    $recipients[] = [
                        'name' => trim($row['name'] ?? ''),
                        'email' => $email,
                        'code' => trim($row['code'] ?? ''),
                        'team_name' => '',
                        'module' => '',
                        'institution' => '',
                        'leader_name' => '',
                        'source' => 'ambassador'
                    ];
                }
            }
        } else {
            $response['errors'][] = 'Ambassador records are not available in the database.';
        }
    } catch (Exception $e) {
        $response['errors'][] = 'Failed to load ambassador contacts.';
    }
}

if (!empty($teamSelections)) {
    $registrationIds = array_keys($teamSelections);
    $idList = implode(',', array_map('intval', $registrationIds));
    if ($idList !== '') {
        $teamRes = $conn->query("SELECT id, team_name, module_selection, institution_type,
                participant1_name, participant1_email,
                participant2_name, participant2_email,
                participant3_name, participant3_email,
                participant4_name, participant4_email
            FROM event_registrations WHERE id IN ({$idList})");
        if ($teamRes) {
            while ($row = $teamRes->fetch_assoc()) {
                $registrationId = (int)$row['id'];
                $selectedKeys = $teamSelections[$registrationId] ?? [];
                $teamName = trim($row['team_name'] ?? '');
                $module = trim($row['module_selection'] ?? '');
                $institution = trim($row['institution_type'] ?? '');
                $leaderName = trim($row['participant1_name'] ?? '');
                foreach ($selectedKeys as $key) {
                    $email = trim($row[$key . '_email'] ?? '');
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $response['errors'][] = 'Skipped team member without valid email (registration #' . $registrationId . ').';
                        continue;
                    }
                    $lower = strtolower($email);
                    if (isset($seenEmails[$lower])) {
                        continue;
                    }
                    $seenEmails[$lower] = true;
                    $name = trim($row[$key . '_name'] ?? '');
                    $recipients[] = [
                        'name' => $name !== '' ? $name : ucfirst($key),
                        'email' => $email,
                        'code' => '',
                        'team_name' => $teamName,
                        'module' => $module,
                        'institution' => $institution,
                        'leader_name' => $leaderName,
                        'source' => 'team'
                    ];
                }
            }
            $teamRes->free();
        } else {
            $response['errors'][] = 'Unable to load team recipient data.';
        }
    }
}

if ($manualEntries !== '') {
    $lines = preg_split('/\r\n|\r|\n/', $manualEntries);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $name = '';
        $email = '';
        if (preg_match('/^(.+?)<([^>]+)>$/', $line, $matches)) {
            $name = trim($matches[1]);
            $email = trim($matches[2]);
        } else {
            $email = $line;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['errors'][] = 'Skipped invalid email: ' . $line;
            continue;
        }
        $lower = strtolower($email);
        if (isset($seenEmails[$lower])) {
            continue;
        }
        $seenEmails[$lower] = true;
        $recipients[] = [
            'name' => $name,
            'email' => $email,
            'code' => '',
            'team_name' => '',
            'module' => '',
            'institution' => '',
            'leader_name' => '',
            'source' => 'manual'
        ];
    }
}

if (empty($recipients)) {
    $response['errors'][] = 'No valid recipients were provided.';
    echo json_encode($response);
    exit;
}

$defaultGreeting = $greetingTemplate !== '' ? $greetingTemplate : 'Hello {{name}},';
$titleFinal = $title !== '' ? $title : 'SENTEC Update';
$buttonTextTemplate = $ctaLabel !== '' ? $ctaLabel : null;
$buttonUrlTemplate = $ctaUrl !== '' ? $ctaUrl : null;

foreach ($recipients as $recipient) {
    $name = $recipient['name'] !== '' ? $recipient['name'] : 'there';
    $email = $recipient['email'];

    $tokens = [
        '{{name}}' => $name,
        '{{participant_name}}' => $name,
        '{{email}}' => $email,
        '{{participant_email}}' => $email,
        '{{code}}' => $recipient['code'] ?? '',
        '{{team_name}}' => $recipient['team_name'] ?? '',
        '{{team}}' => $recipient['team_name'] ?? '',
        '{{module}}' => $recipient['module'] ?? '',
        '{{institution}}' => $recipient['institution'] ?? '',
        '{{leader_name}}' => $recipient['leader_name'] ?? '',
        '{{source}}' => $recipient['source'] ?? ''
    ];

    $greetingLine = htmlspecialchars(strtr($defaultGreeting, $tokens), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $personalBody = strtr($bodyTemplate, $tokens);
    $bodyHtml = nl2br(htmlspecialchars($personalBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    $footer = $footerNote !== '' ? htmlspecialchars(strtr($footerNote, $tokens), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : null;
    $buttonText = $buttonTextTemplate !== null ? strtr($buttonTextTemplate, $tokens) : null;
    $buttonUrl = $buttonUrlTemplate !== null ? strtr($buttonUrlTemplate, $tokens) : null;

    try {
        $mail = sentec_mailer();
        $mail->addAddress($email, $recipient['name']);
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
        sentec_mail_log('custom_email_anyone', 'sent', 'to=' . $email);
    } catch (Exception $e) {
        $response['errors'][] = 'Failed for ' . ($recipient['name'] !== '' ? $recipient['name'] : $email) . '.';
        sentec_mail_log('custom_email_anyone', 'error', $e->getMessage());
    }
}

echo json_encode($response);
exit;
