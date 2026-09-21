<?php
/**
 * Database Migration Script
 * Adds event_link column to events table
 * 
 * USAGE: Open this file in your browser once
 * URL: https://your-domain.com/admin/migrate_add_event_link.php
 * 
 * After successful execution, DELETE this file for security
 */

include '../db_connection.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Migration</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #2c3e50; }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #28a745;
            margin: 20px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #dc3545;
            margin: 20px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #17a2b8;
            margin: 20px 0;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
            margin: 20px 0;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🗄️ Database Migration: Add Event Link Column</h1>";

// Check if column already exists
$checkQuery = "SHOW COLUMNS FROM events LIKE 'event_link'";
$checkResult = mysqli_query($conn, $checkQuery);

if (mysqli_num_rows($checkResult) > 0) {
    echo "<div class='info'>
            <strong>ℹ️ Column Already Exists</strong><br>
            The <code>event_link</code> column already exists in the <code>events</code> table. No migration needed.
          </div>";
} else {
    // Column doesn't exist, proceed with migration
    echo "<div class='info'>
            <strong>🔄 Running Migration...</strong><br>
            Adding <code>event_link</code> column to <code>events</code> table...
          </div>";
    
    $migrationQuery = "ALTER TABLE `events` ADD COLUMN `event_link` VARCHAR(500) DEFAULT NULL AFTER `image_url`";
    
    if (mysqli_query($conn, $migrationQuery)) {
        echo "<div class='success'>
                <strong>✅ Migration Successful!</strong><br>
                The <code>event_link</code> column has been successfully added to your Azure database.<br><br>
                <strong>What's New:</strong>
                <ul>
                    <li>You can now add links to events in the admin panel</li>
                    <li>Event cards on the homepage are now clickable</li>
                    <li>Images display in full 1080x1350 portrait ratio</li>
                </ul>
              </div>";
        
        echo "<div class='warning'>
                <strong>⚠️ Important Security Step</strong><br>
                Please <strong>DELETE this file immediately</strong> after viewing this message:<br>
                <code>admin/migrate_add_event_link.php</code>
              </div>";
        
        echo "<p><a href='index' class='btn'>Go to Admin Dashboard</a></p>";
    } else {
        echo "<div class='error'>
                <strong>❌ Migration Failed</strong><br>
                Error: " . mysqli_error($conn) . "<br><br>
                <strong>Possible Solutions:</strong>
                <ul>
                    <li>Check your Azure database permissions</li>
                    <li>Ensure the events table exists</li>
                    <li>Contact your database administrator</li>
                </ul>
              </div>";
    }
}

// Show current database info
echo "<div style='margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee;'>
        <h3>📊 Database Information</h3>
        <p><strong>Server:</strong> sentecneduet-db-eu.mysql.database.azure.com</p>
        <p><strong>Database:</strong> sentec_db</p>
        <p><strong>Connection:</strong> <span style='color: green;'>✓ Active</span></p>
      </div>";

echo "  </div>
</body>
</html>";

mysqli_close($conn);
?>
