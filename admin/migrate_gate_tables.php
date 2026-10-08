<?php
/**
 * Migration Script: Initialize SENTEC Gate Scanning Engine Tables
 * Creates `gate_stations` and `scan_audit_logs` tables and seeds default stations.
 */
require_once __DIR__ . '/../db_connection.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== SENTEC Gate Scanner Database Migration ===\n\n";

if (!$conn || $conn->connect_error) {
    die("Database connection failed: " . ($conn ? $conn->connect_error : 'Unknown error') . "\n");
}

// 1. Create `gate_stations` table
$sqlStations = "CREATE TABLE IF NOT EXISTS `gate_stations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `station_id` VARCHAR(64) NOT NULL UNIQUE,
    `station_pin` VARCHAR(8) NOT NULL,
    `station_name` VARCHAR(128) NOT NULL,
    `role` VARCHAR(32) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_active_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_station_pin` (`station_pin`),
    INDEX `idx_station_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sqlStations)) {
    echo "✓ Table `gate_stations` checked/created successfully.\n";
} else {
    echo "✗ Failed to create `gate_stations`: " . $conn->error . "\n";
}

// Seed default stations if table is empty
$checkStations = $conn->query("SELECT COUNT(*) AS cnt FROM `gate_stations`");
$count = $checkStations ? (int)$checkStations->fetch_assoc()['cnt'] : 0;

if ($count === 0) {
    $seedSql = "INSERT INTO `gate_stations` (`station_id`, `station_pin`, `station_name`, `role`) VALUES
        ('GATE_ENG_01', '1011', 'Gate 1 - Engineer Entry', 'engineer'),
        ('GATE_ENG_02', '1012', 'Gate 2 - Engineer Entry', 'engineer'),
        ('GATE_SOC_01', '2011', 'Gate 1 - Social Ruh-e-Raqs', 'social'),
        ('GATE_SOC_02', '2012', 'Gate 2 - Social Ruh-e-Raqs', 'social'),
        ('GATE_ALL_01', '9999', 'Universal Master Station', 'all');";
    if ($conn->query($seedSql)) {
        echo "✓ Seeded 5 default gate stations (PINs: 1011, 1012, 2011, 2012, 9999).\n";
    } else {
        echo "✗ Failed to seed stations: " . $conn->error . "\n";
    }
} else {
    echo "ℹ `gate_stations` already has {$count} station(s).\n";
}

// 2. Create `scan_audit_logs` table
$sqlLogs = "CREATE TABLE IF NOT EXISTS `scan_audit_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `log_id` VARCHAR(64) NOT NULL UNIQUE,
    `ticket_id` VARCHAR(64) NOT NULL,
    `attendee_name` VARCHAR(255) NULL,
    `volunteer_id` VARCHAR(64) NOT NULL,
    `station_id` VARCHAR(64) NOT NULL,
    `device_id` VARCHAR(64) NOT NULL,
    `gate_type` VARCHAR(32) NOT NULL,
    `status` VARCHAR(32) NOT NULL,
    `notes` TEXT NULL,
    `device_timestamp` BIGINT NOT NULL,
    `synced_timestamp` BIGINT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_log_ticket` (`ticket_id`),
    INDEX `idx_log_station` (`station_id`),
    INDEX `idx_log_status` (`status`),
    INDEX `idx_log_device_ts` (`device_timestamp`),
    INDEX `idx_log_synced_ts` (`synced_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sqlLogs)) {
    echo "✓ Table `scan_audit_logs` checked/created successfully.\n";
} else {
    echo "✗ Failed to create `scan_audit_logs`: " . $conn->error . "\n";
}

// 3. Ensure `event_attendees` has required attendance tracking columns
if ($conn->query("SHOW TABLES LIKE 'event_attendees'")->num_rows > 0) {
    $colCheck = $conn->query("SHOW COLUMNS FROM `event_attendees` LIKE 'day1_status'");
    if ($colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE `event_attendees` ADD COLUMN `day1_status` ENUM('pending','present','absent') DEFAULT 'pending'");
        $conn->query("ALTER TABLE `event_attendees` ADD COLUMN `day2_status` ENUM('pending','present','absent') DEFAULT 'pending'");
        echo "✓ Added day1_status and day2_status columns to `event_attendees`.\n";
    } else {
        echo "✓ `event_attendees` columns verified.\n";
    }
}

// 4. Ensure `social_attendees` has required attendance tracking columns
if ($conn->query("SHOW TABLES LIKE 'social_attendees'")->num_rows > 0) {
    $colCheck = $conn->query("SHOW COLUMNS FROM `social_attendees` LIKE 'attendance_status'");
    if ($colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE `social_attendees` ADD COLUMN `attendance_status` VARCHAR(32) DEFAULT 'pending'");
        $conn->query("ALTER TABLE `social_attendees` ADD COLUMN `entry_time` DATETIME NULL");
        echo "✓ Added attendance_status and entry_time columns to `social_attendees`.\n";
    } else {
        echo "✓ `social_attendees` columns verified.\n";
    }
}

echo "\n=== Migration Completed Successfully ===\n";
