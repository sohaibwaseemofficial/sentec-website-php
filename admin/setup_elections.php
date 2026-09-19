Here is the complete, updated `setup_elections.php` file configured for multi-position elections (e.g., President and Vice President).

This version updates the `CREATE TABLE` statement for `election_votes` to include the `position` column and replaces the old single-vote unique key with `UNIQUE KEY unique_user_position_vote (election_id, user_id, position)`. Additionally, it includes safe automatic `ALTER TABLE` migration checks at the bottom so that if your database tables already exist, it will safely patch them without deleting your data.

```php
<?php
// 1. Include your existing Aiven connection
require_once 'db_connection.php';

echo "<h3>Connecting to Aiven Database...</h3>";

// 2. Define the exact SQL queries
$queries = [
    "CREATE TABLE IF NOT EXISTS elections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        show_results TINYINT(1) DEFAULT 0,
        status ENUM('draft', 'active', 'ended') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS election_candidates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        election_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        position VARCHAR(100) NOT NULL,
        bio TEXT,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        image_url VARCHAR(255),
        FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS election_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        election_id INT NOT NULL,
        category VARCHAR(100) DEFAULT 'Leadership & Vision',
        question_text TEXT NOT NULL,
        options_json JSON NOT NULL,
        FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS election_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        election_id INT NOT NULL,
        user_id INT NOT NULL,
        position VARCHAR(100) NOT NULL,
        candidate_id INT NOT NULL,
        mcq_responses JSON NOT NULL,
        is_valid TINYINT(1) DEFAULT 1,
        casted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_position_vote (election_id, user_id, position),
        FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS election_portal_settings (
        id INT PRIMARY KEY,
        is_open TINYINT(1) DEFAULT 0,
        is_visible TINYINT(1) DEFAULT 1,
        show_results TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

// 3. Execute queries one by one
foreach ($queries as $index => $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✔ Table #" . ($index + 1) . " checked/created successfully!</p>";
    } else {
        echo "<p style='color: red;'>✘ Error creating Table #" . ($index + 1) . ": " . $conn->error . "</p>";
    }
}

// 4. Default Portal Settings Initialization
$conn->query("INSERT IGNORE INTO election_portal_settings (id, is_open, is_visible, show_results) VALUES (1, 0, 1, 0)");

// 5. Safe Migrations for Existing Tables
// Ensure candidate active flag exists
$candidateColumn = $conn->query("SHOW COLUMNS FROM election_candidates LIKE 'is_active'");
if (!$candidateColumn || $candidateColumn->num_rows === 0) {
    @$conn->query("ALTER TABLE election_candidates ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER bio");
    echo "<p style='color: blue;'>ℹ Migrated election_candidates: added 'is_active' column.</p>";
}

// Ensure vote position column exists for multi-position voting
$votePosColumn = $conn->query("SHOW COLUMNS FROM election_votes LIKE 'position'");
if (!$votePosColumn || $votePosColumn->num_rows === 0) {
    @$conn->query("ALTER TABLE election_votes ADD COLUMN position VARCHAR(100) NOT NULL AFTER user_id");
    echo "<p style='color: blue;'>ℹ Migrated election_votes: added 'position' column.</p>";
}

// Ensure unique index is updated from single-vote to multi-position
$indexCheck = $conn->query("SHOW INDEX FROM election_votes WHERE Key_name = 'unique_user_vote'");
if ($indexCheck && $indexCheck->num_rows > 0) {
    @$conn->query("ALTER TABLE election_votes DROP INDEX unique_user_vote");
    @$conn->query("ALTER TABLE election_votes ADD UNIQUE KEY unique_user_position_vote (election_id, user_id, position)");
    echo "<p style='color: blue;'>ℹ Migrated election_votes keys: updated to allow one vote per user per position.</p>";
}

echo "<hr><strong style='color:orange;'>IMPORTANT: Delete this setup_elections.php file from your server right now!</strong>";
?>

```