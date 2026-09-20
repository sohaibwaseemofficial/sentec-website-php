<?php
include_once 'db_connection.php';
require_once __DIR__ . '/../image_utils.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $designation = trim($_POST['designation']);
    $category = $_POST['category'];
    $linkedin = $_POST['linkedin'] ?? null;
    $domain = $_POST['domain'] ?? null; // Add domain field

    // Validate required fields: name, designation, and category
    if (empty($name) || empty($designation) || empty($category)) {
        echo "Name, designation, and category are required.";
        exit;
    }

    // Validate domain for non-presiding/executive categories
    if (in_array($category, ['Directorate', 'Co-Directorate', 'Alumni']) && empty($domain)) {
        echo "Domain is required for this category.";
        exit;
    }

    // Handle Image Upload
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo "Image upload is required.";
        exit;
    }

    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        echo "File is too large. Maximum size is 5MB.";
        exit;
    }

    $uploadResult = save_image_as_webp($_FILES['image'], __DIR__ . '/../images/uploads/team/', 'images/uploads/team/');

    if (!$uploadResult['success']) {
        echo htmlspecialchars($uploadResult['error']);
        exit;
    }

    $image = $uploadResult['path'];

    // Prepare SQL statement to insert team member with domain field
    $query = "INSERT INTO team_members (name, designation, category, image, linkedin, domain) VALUES (?, ?, ?, ?, ?, ?)";
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("ssssss", $name, $designation, $category, $image, $linkedin, $domain); // Include domain parameter

        // Execute the query and check for success
        if ($stmt->execute()) {
            if (function_exists('invalidate_cache')) {
                invalidate_cache('team_members');
            }
            header("Location: manage_team.php?success=Member added successfully");
            exit;
        } else {
            echo "Error: " . $stmt->error;
        }

        // Close the statement
        $stmt->close();
    } else {
        echo "Error preparing the query: " . $conn->error;
    }
}

$conn->close();
?>
