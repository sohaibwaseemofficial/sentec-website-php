<?php
// 1. PREVENT SILENT CRASHES
ob_start();
session_start();

ini_set('display_errors', 0);
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Unknown Error'];

try {
    // 2. SECURITY & INCLUDES
    if (!isset($_SESSION['admin'])) throw new Exception("Unauthorized Access");
    
    if (!file_exists('../db_connection.php')) throw new Exception("DB File missing");
    include '../db_connection.php';
    
    if (!file_exists('../mailer.php')) throw new Exception("Mailer File missing");
    require_once '../mailer.php';
    require_once __DIR__ . '/../social_attendees_helper.php';

    // 3. GET INPUT
    $id = intval($_POST['id'] ?? 0);
    $attendeeId = intval($_POST['attendee_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!in_array($status, ['approved', 'rejected'])) {
        throw new Exception("Invalid Data");
    }

    $tableHasAttendees = social_attendees_table_exists($conn);
    $attendeeMode = $tableHasAttendees && $attendeeId > 0;

    if ($attendeeMode) {
        $stmt = $conn->prepare("UPDATE social_attendees SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $attendeeId);
        if (!$stmt->execute()) {
            throw new Exception("SQL Error: " . $stmt->error);
        }
        $stmt->close();

        $attendee = social_attendee_fetch_with_registration($conn, $attendeeId);
        if (!$attendee) {
            throw new Exception("Attendee not found");
        }

        $email_msg = "Email Sent";
        try {
            if ($attendee['email']) {
                $mail = sentec_mailer();
                $mail->addAddress($attendee['email'], $attendee['full_name']);

                if ($status === 'approved') {
                    $qrLink = "https://sentecneduet.live/gate/verify_social.php?attendee=" . $attendeeId;
                    $qrImg = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&color=000000&bgcolor=00ff94&data=" . urlencode($qrLink);
                    $recipientName = htmlspecialchars($attendee['full_name'] ?? 'Guest', ENT_QUOTES);
                    $cnicLine = !empty($attendee['cnic'])
                        ? "<p style='margin:4px 0'><strong style='color:#00ff94;'>CNIC:</strong> " . htmlspecialchars($attendee['cnic'], ENT_QUOTES) . "</p>"
                        : '';

                    $detailHtml = "<div style='background:#0b1120;border:1px solid #1b2235;border-radius:12px;padding:18px;margin:24px 0;'>"
                        . $cnicLine .
                        "<p style='margin:4px 0'><strong style='color:#00ff94;'>Date:</strong> 28th December, 2025</p>"
                        . "<p style='margin:4px 0'><strong style='color:#00ff94;'>Venue:</strong>Syed Mahmood Alam Auditorium (Main Auditorium), NED University Main Campus </p>"
                        . "<p style='margin:4px 0'><strong style='color:#00ff94;'>Time:</strong> 04:00 onwards PM</p>"
                        . "</div>"
                        . "<p style='margin:0 0 12px 0;color:#cfd3dc;text-align:center;'>Present this QR code at the gate. Each QR is unique.</p>"
                        . "<div style='text-align:center;margin-bottom:12px;'>"
                        . "<span style='display:inline-block;background:#ffffff;padding:10px;border-radius:12px;'><img src='{$qrImg}' width='180' height='180' alt='Entry QR Code' style='display:block;'></span>"
                        . "</div>"
                        . "<p style='margin:0;color:#ff7878;font-size:13px;text-align:center;'>Note: This pass allows single entry only.</p>";

                    $mail->Subject = "Social Event E-Pass - " . ($attendee['full_name'] ?: 'Guest');
                    $mail->Body = sentec_build_email_html(
                        'Social Event 2025',
                        'E-PASS READY',
                        "Dear <strong>{$recipientName}</strong>",
                        "<p style='margin:0 0 12px 0;'>Your entry pass for RUH-E-RAQS Social Event has been approved.</p>" . $detailHtml,
                        null,
                        null,
                        'Show this email to gate staff along with your CNIC.'
                    );
                } else {
                    $recipientName = htmlspecialchars($attendee['full_name'] ?? 'Guest', ENT_QUOTES);
                    $bodyCopy = "<p>Thank you for your interest in RUH-E-RAQS Social Event.</p>"
                        . "<p>After reviewing your application, we regret to inform you that we cannot confirm your registration at this time.</p>"
                        . "<p>If you believe this decision was made in error, please reply to this email with your details.</p>"
                        . "<p>Regards,<br>SENTEC Team</p>";

                    $mail->Subject = "Registration Update - RUH-E-RAQS Social Event";
                    $mail->Body = sentec_build_email_html(
                        'Social Event 2025',
                        'Registration Update',
                        "Dear <strong>{$recipientName}</strong>,",
                        $bodyCopy,
                        null,
                        null,
                        'SENTEC Support | social@sentecneduet.live'
                    );
                }

                $mail->send();
            } else {
                $email_msg = "No email for attendee";
            }
        } catch (Exception $e) {
            $email_msg = "DB Updated, but Email Failed: " . $e->getMessage();
        }

        social_refresh_parent_status($conn, (int) $attendee['registration_id']);

        $response['success'] = true;
        $response['message'] = "Success: $status. ($email_msg)";
        ob_end_clean();
        echo json_encode($response);
        exit;
    }

    if ($id <= 0) {
        throw new Exception("Invalid Data");
    }

    // 4. UPDATE DATABASE
    $stmt = $conn->prepare("UPDATE social_registrations SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    
    if ($stmt->execute()) {
        if ($tableHasAttendees) {
            $sync = $conn->prepare("UPDATE social_attendees SET status = ? WHERE registration_id = ?");
            $sync->bind_param("si", $status, $id);
            $sync->execute();
            $sync->close();
            social_refresh_parent_status($conn, $id);
        }
        
        $email_msg = "Email Sent";
        
        // 5. SEND EMAIL
        try {
            $u_stmt = $conn->prepare("SELECT * FROM social_registrations WHERE id = ?");
            $u_stmt->bind_param("i", $id);
            $u_stmt->execute();
            $user = $u_stmt->get_result()->fetch_assoc();

            if ($user && function_exists('sentec_mailer')) {
                $people = [[
                    'index' => 1,
                    'name' => $user['full_name'],
                    'email' => $user['email'],
                    'cnic' => $user['cnic']
                ]];

                if (!empty($user['participant2_name']) || !empty($user['participant2_email'])) {
                    $people[] = [
                        'index' => 2,
                        'name' => $user['participant2_name'],
                        'email' => $user['participant2_email'],
                        'cnic' => $user['participant2_cnic']
                    ];
                }

                if (!empty($user['participant3_name']) || !empty($user['participant3_email'])) {
                    $people[] = [
                        'index' => 3,
                        'name' => $user['participant3_name'],
                        'email' => $user['participant3_email'],
                        'cnic' => $user['participant3_cnic']
                    ];
                }

                $emailsSent = 0;
                foreach ($people as $person) {
                    if (empty($person['email'])) {
                        continue;
                    }

                    $mail = sentec_mailer();
                    $mail->addAddress($person['email'], $person['name']);

                    if ($status == 'approved') {
                        $qrLink = "https://sentecneduet.live/gate/verify_social.php?id=" . $id . "&member=" . $person['index'];
                        $qrImg = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&color=000000&bgcolor=00ff94&data=" . urlencode($qrLink);
                        $recipientName = htmlspecialchars($person['name'] ?? 'Guest', ENT_QUOTES);
                        $cnicLine = !empty($person['cnic'])
                            ? "<p style='margin:4px 0'><strong style='color:#00ff94;'>CNIC:</strong> " . htmlspecialchars($person['cnic'], ENT_QUOTES) . "</p>"
                            : '';

                        $detailHtml = "<div style='background:#0b1120;border:1px solid #1b2235;border-radius:12px;padding:18px;margin:24px 0;'>"
                            . $cnicLine .
                            "<p style='margin:4px 0'><strong style='color:#00ff94;'>Date:</strong> 28th December, 2025</p>"
                            . "<p style='margin:4px 0'><strong style='color:#00ff94;'>Venue:</strong> NED University Main Campus</p>"
                            . "<p style='margin:4px 0'><strong style='color:#00ff94;'>Time:</strong> 04:00 PM onwards</p>"
                            . "</div>"
                            . "<p style='margin:0 0 12px 0;color:#cfd3dc;text-align:center;'>Please arrive on time and carry your ID card and CNIC with you. Present this QR code at the gate, as each QR code is unique.</p>"
                            
                            . "<div style='text-align:center;margin-bottom:12px;'>"
                            . "<span style='display:inline-block;background:#ffffff;padding:10px;border-radius:12px;'><img src='{$qrImg}' width='180' height='180' alt='Entry QR Code' style='display:block;'></span>"
                            . "</div>"
                            . "<p style='margin:0;color:#ff7878;font-size:13px;text-align:center;'>Note: This pass allows single entry only.</p>";

                        $mail->Subject = "Social Event E-Pass - " . ($person['name'] ?: 'Guest');
                        $mail->Body = sentec_build_email_html(
                            'Social Event 2025',
                            'E-PASS READY',
                            "Dear <strong>{$recipientName}</strong>",
                            "<p style='margin:0 0 12px 0;'>Your entry pass for RUH-E-RAQS Social Event has been approved.</p>" . $detailHtml,
                            null,
                            null,
                            'Show this email to gate staff along with your CNIC.'
                        );
                    } else {
                        $recipientName = htmlspecialchars($person['name'] ?? 'Guest', ENT_QUOTES);
                        $bodyCopy = "<p>Thank you for your interest in RUH-E-RAQS Social Event.</p>"
                            . "<p>After reviewing your application, we regret to inform you that we cannot confirm your registration at this time.</p>"
                            . "<p>If you believe this decision was made in error, please reply to this email with your details.</p>"
                            . "<p>Regards,<br>SENTEC Team</p>";

                        $mail->Subject = "Registration Update - RUH-E-RAQS Social Event";
                        $mail->Body = sentec_build_email_html(
                            'Social Event 2025',
                            'Registration Update',
                            "Dear <strong>{$recipientName}</strong>,",
                            $bodyCopy,
                            null,
                            null,
                            'SENTEC Support | social@sentecneduet.live'
                        );
                    }

                    $mail->send();
                    $emailsSent++;
                }

                if ($emailsSent === 0) {
                    $email_msg = "DB updated but no email sent (missing addresses)";
                } else {
                    $email_msg = $emailsSent . " email(s) sent";
                }
            }
        } catch (Exception $e) {
            $email_msg = "DB Updated, but Email Failed: " . $e->getMessage();
        }

        $response['success'] = true;
        $response['message'] = "Success: $status. ($email_msg)";

    } else {
        throw new Exception("SQL Error: " . $stmt->error);
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
exit;
?>
