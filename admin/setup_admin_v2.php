<?php
session_start();
if (!isset($_SESSION['admin'])) die("Unauthorized");

include '../db_connection.php';

// Add columns if they don't exist
$col_names = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'full_name'");
if ($col_names->num_rows == 0) {
    $conn->query("ALTER TABLE admin_users ADD COLUMN full_name VARCHAR(150) DEFAULT NULL AFTER password");
}
$col_role = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'role'");
if ($col_role->num_rows == 0) {
    $conn->query("ALTER TABLE admin_users ADD COLUMN role ENUM('super_admin','moderator') DEFAULT 'moderator' AFTER full_name");
}
$col_created = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'created_at'");
if ($col_created->num_rows == 0) {
    $conn->query("ALTER TABLE admin_users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER role");
}
$col_last = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'last_login'");
if ($col_last->num_rows == 0) {
    $conn->query("ALTER TABLE admin_users ADD COLUMN last_login DATETIME DEFAULT NULL AFTER created_at");
}

// Create admin_logs table
$conn->query("CREATE TABLE IF NOT EXISTS admin_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  action VARCHAR(255) NOT NULL,
  details TEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Set a name for the existing admin (abc)
$conn->query("UPDATE admin_users SET full_name = 'Super Admin', role = 'super_admin' WHERE username = 'abc'");

echo "Setup complete. Delete this file.";
$conn->close();
?>