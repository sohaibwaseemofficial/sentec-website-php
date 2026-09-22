<?php
/**
 * Shared Admin Action Logger
 * Provides a standardized method to log administrative actions to the admin_logs table.
 */

if (!function_exists('log_admin_action')) {
    function log_admin_action(string $action, string $details = '', ?mysqli $databaseConnection = null): void {
        global $conn;
        $db = $databaseConnection ?? $conn;

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $adminId = $_SESSION['admin_id'] ?? null;
        if (!$adminId || !$db || !($db instanceof mysqli)) {
            return;
        }

        // Check if connection is still alive/open
        if (!@$db->ping()) {
            return;
        }

        try {
            $stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                $ip = substr($ip, 0, 45); // IPv6 max length
                $stmt->bind_param("isss", $adminId, $action, $details, $ip);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $t) {
            // Silently suppress logging errors to prevent breaking core operations
            error_log("Failed to log admin action: " . $t->getMessage());
        }
    }
}
