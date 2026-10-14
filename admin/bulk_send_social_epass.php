<?php
/**
 * Bulk E-Pass Sender for Confirmed Social Event Attendees
 * Sends (or resends) unique QR E-Passes to all confirmed attendees.
 */
ob_start();
session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (!isset($_SESSION['admin']) && !isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../social_attendees_helper.php';

set_time_limit(0);
ignore_user_abort(true);

$response = ['success' => false, 'message' => 'Invalid request'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $rawIds = $_POST['ids'] ?? [];
    $targetMode = $_POST['target'] ?? 'all_confirmed'; // 'all_confirmed' or 'selected'

    $targetRegIds = [];

    if ($targetMode === 'all_confirmed' || empty($rawIds)) {
        // Query all confirmed/approved registrations
        $res = $conn->query("SELECT id FROM social_registrations WHERE status = 'approved'");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $targetRegIds[] = (int)$r['id'];
            }
        }
    } else {
        foreach ((array)$rawIds as $v) {
            $id = intval($v);
            if ($id > 0) {
                $targetRegIds[] = $id;
            }
        }
        $targetRegIds = array_values(array_unique($targetRegIds));
    }

    if (empty($targetRegIds)) {
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'sent_count' => 0,
            'message' => 'No confirmed registrations found to send E-Passes.'
        ]);
        exit;
    }

    $tableHasAttendees = social_attendees_table_exists($conn);
    $fetchRegStmt = $conn->prepare("SELECT * FROM social_registrations WHERE id = ? LIMIT 1");

    $appUrl = env('APP_URL', 'https://sentecneduet.live');
    $emailsSent = 0;
    $errors = [];

    foreach ($targetRegIds as $regId) {
        $attendeeList = [];
        if ($tableHasAttendees) {
            $attRes = $conn->query("SELECT * FROM social_attendees WHERE registration_id = {$regId}");
            if ($attRes && $attRes->num_rows > 0) {
                while ($attRow = $attRes->fetch_assoc()) {
                    $attendeeList[] = [
                        'attendee_id' => (int)$attRow['id'],
                        'name' => $attRow['full_name'] ?: 'Guest',
                        'email' => trim($attRow['email'] ?? ''),
                        'cnic' => $attRow['cnic'] ?? '',
                        'label' => $attRow['label'] ?? 'Person'
                    ];
                }
            }
        }

        // Fallback to social_registrations columns
        if (empty($attendeeList)) {
            $fetchRegStmt->bind_param("i", $regId);
            $fetchRegStmt->execute();
            $regData = $fetchRegStmt->get_result()->fetch_assoc();
            if ($regData) {
                if (!empty($regData['email'])) {
                    $attendeeList[] = [
                        'attendee_id' => 0,
                        'name' => $regData['full_name'] ?: 'Guest',
                        'email' => trim($regData['email']),
                        'cnic' => $regData['cnic'] ?? '',
                        'label' => 'Person 1'
                    ];
                }
                for ($p = 2; $p <= 3; $p++) {
                    if (!empty($regData["participant{$p}_email"])) {
                        $attendeeList[] = [
                            'attendee_id' => 0,
                            'name' => $regData["participant{$p}_name"] ?: "Guest {$p}",
                            'email' => trim($regData["participant{$p}_email"]),
                            'cnic' => $regData["participant{$p}_cnic"] ?? '',
                            'label' => "Person {$p}"
                        ];
                    }
                }
            }
        }

        // Send E-Pass email to each attendee
        foreach ($attendeeList as $person) {
            $toEmail = $person['email'];
            if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $personName = $person['name'];
            $attId = $person['attendee_id'];

            if ($attId > 0) {
                $qrLink = rtrim($appUrl, '/') . "/gate/verify_social.php?attendee=" . $attId;
            } else {
                $qrLink = rtrim($appUrl, '/') . "/gate/verify_social.php?id=" . $regId;
            }

            $qrImg = "https://api.qrserver.com/v1/create-qr-code/?size=320x320&color=000000&bgcolor=00ff94&data=" . urlencode($qrLink);
            $safeName = htmlspecialchars($personName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $cnicLine = !empty($person['cnic']) && $person['cnic'] !== 'N/A'
                ? "<p style='margin:4px 0;'><strong style='color:#00ff94;'>CNIC / Roll:</strong> " . htmlspecialchars($person['cnic'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>"
                : '';

            $qrBlock = "<div style='text-align:center; margin:22px 0;'><img src='{$qrImg}' alt='Gate QR' style='width:220px;height:220px;border:2px solid #00ff94;border-radius:12px;background:#000;' loading='lazy'></div>"
                . "<p style='margin:0 0 16px 0;color:#cfd3dc;text-align:center;'>Please present this unique QR code at the entrance gate.</p>";

            $detailHtml = "<div style='background:#0b1120;border:1px solid #1b2235;border-radius:12px;padding:18px;margin:20px 0;'>"
                . "<p style='margin:4px 0;'><strong style='color:#00ff94;'>Event:</strong> RUH-E-RAQS (SENTEC Qawwali Evening)</p>"
                . $cnicLine
                . "<p style='margin:4px 0;'><strong style='color:#00ff94;'>Date:</strong> 15th October 2026</p>"
                . "<p style='margin:4px 0;'><strong style='color:#00ff94;'>Venue:</strong> Syed Mahmood Alam Auditorium (Main Auditorium), NED University</p>"
                . "<p style='margin:4px 0;'><strong style='color:#00ff94;'>Time:</strong> 02:00 PM to 04:00 PM</p>"
                . "</div>"
                . "<p style='margin:0;color:#ff7878;font-size:13px;text-align:center;'>Note: This pass is unique and allows single entry only.</p>";

            try {
                $mail = sentec_mailer();
                $mail->addAddress($toEmail, $personName);
                $mail->Subject = "Your Social Event E-Pass - " . $personName;
                $mail->Body = sentec_build_email_html(
                    'RUH-E-RAQS 2026',
                    'OFFICIAL GATE E-PASS',
                    "Dear <strong>{$safeName}</strong>,",
                    "<p style='margin:0 0 12px 0;'>Here is your official entrance E-Pass for the <strong>SENTEC Qawwali Social Evening</strong>.</p>" . $qrBlock . $detailHtml,
                    $qrLink,
                    'View My Pass',
                    'Show this pass at the gate along with your Student ID / CNIC.'
                );
                $mail->AltBody = "Hello {$personName},\n\nYour E-Pass for RUH-E-RAQS (SENTEC Qawwali Evening) is ready!\nOpen your pass: {$qrLink}\nVenue: NED University Main Auditorium\nTime: 02:00 PM - 04:00 PM";
                $mail->send();
                $emailsSent++;
                sentec_mail_log('bulk_epass_social', 'sent', "to={$toEmail} reg_id={$regId}");
            } catch (Throwable $e) {
                $errors[] = "Email failed for {$personName} ({$toEmail}): " . $e->getMessage();
                sentec_mail_log('bulk_epass_social', 'error', "to={$toEmail} error=" . $e->getMessage());
            }

            usleep(100000); // 0.10s safety delay for SMTP
        }
    }

    $response['success'] = true;
    $response['sent_count'] = $emailsSent;
    $response['errors'] = $errors;
    $response['message'] = "Dispatched {$emailsSent} E-Pass email(s) to confirmed attendees!";

    ob_end_clean();
    echo json_encode($response);
    exit;

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    exit;
}
