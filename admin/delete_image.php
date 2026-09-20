<?php
include 'db_connection.php';

$image = $_GET['image'] ?? '';
$id = $_GET['id'] ?? 0;

if ($image && $id) {
    // Remove image from the filesystem
    $baseDir = realpath(__DIR__ . '/..');
    $normalized = str_replace(['\\', '//'], '/', $image);
    $normalized = ltrim($normalized, '/');
    if (strpos($normalized, '../') === 0) {
        $normalized = ltrim(substr($normalized, 3), '/');
    }
    if ($baseDir) {
        $absolutePath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }

    // Fetch the gallery item to get the current additional image URLs
    $query = "SELECT additional_image_url FROM gallery WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $gallery_item = $result->fetch_assoc();

    if ($gallery_item) {
        // Remove the image URL from the comma-separated list of additional images
        $additional_images = explode(',', $gallery_item['additional_image_url']);
        $updated_images = array_filter($additional_images, function($img) use ($image) {
            return trim($img) !== $image; // Remove the deleted image from the list
        });

        // Rebuild the comma-separated list of remaining images
        $updated_images_str = implode(',', $updated_images);

        // Update the database with the new list of images
        $update_query = "UPDATE gallery SET additional_image_url = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $updated_images_str, $id);
        if ($stmt->execute()) {
            if (function_exists('invalidate_cache')) invalidate_cache('public_gallery_data');
            // Redirect back to the edit page
            header("Location: edit_gallery.php?id=$id");
            exit;
        } else {
            echo "Error updating the database.";
        }
    } else {
        echo "Gallery item not found.";
    }
} else {
    echo "Invalid parameters.";
}
?>
