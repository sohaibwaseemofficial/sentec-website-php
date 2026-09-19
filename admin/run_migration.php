<?php
session_start();
if (!isset($_SESSION['admin'])) die("Unauthorized");

include '../db_connection.php';

$sql = "
ALTER TABLE `admin_users` 
ADD COLUMN `full_name` VARCHAR(150) DEFAULT NULL AFTER `password`,
ADD COLUMN `role` ENUM('super_admin','moderator') DEFAULT 'moderator' AFTER `full_name`,
ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `role`,
ADD COLUMN `last_login` DATETIME DEFAULT NULL AFTER `created_at`
";

if ($conn->query($sql)) {
    echo "Migration successful. Please delete this file.";
} else {
    echo "Error: " . $conn->error;
}
$conn->close();
?>