<?php
include 'db_connection.php';
include 'header.php';
require_once __DIR__ . '/../image_utils.php';

$id = $_GET['id'] ?? 0;
$query = "SELECT * FROM gallery WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$gallery_item = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $group_title = $_POST['group_title'];
    $description = $_POST['description'];
    $additional_images = $_FILES['additional_images'] ?? null;

    $galleryDir = __DIR__ . '/../images/uploads/gallery/';
    $publicPrefix = 'images/uploads/gallery/';
    $mainImagePath = $gallery_item['main_image_url'];
    $warnings = [];

    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $mainUpload = save_image_as_webp($_FILES['main_image'], $galleryDir, $publicPrefix);
        if ($mainUpload['success']) {
            $mainImagePath = $mainUpload['path'];
            if (!empty($mainUpload['warning'])) {
                $warnings[] = $mainUpload['warning'];
            }
        } else {
            $error = $mainUpload['error'];
        }
    }

    $existingAdditional = [];
    if (!empty($gallery_item['additional_image_url'])) {
        $existingAdditional = array_filter(array_map('trim', explode(',', $gallery_item['additional_image_url'])));
    }

    if (empty($error) && $additional_images && !empty($additional_images['name'][0])) {
        foreach ($additional_images['name'] as $key => $image_name) {
            $file = [
                'name' => $additional_images['name'][$key],
                'type' => $additional_images['type'][$key],
                'tmp_name' => $additional_images['tmp_name'][$key],
                'error' => $additional_images['error'][$key],
                'size' => $additional_images['size'][$key],
            ];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $warnings[] = 'One of the additional images could not be uploaded.';
                continue;
            }

            $upload = save_image_as_webp($file, $galleryDir, $publicPrefix);
            if ($upload['success']) {
                $existingAdditional[] = $upload['path'];
                if (!empty($upload['warning'])) {
                    $warnings[] = $upload['warning'];
                }
            } else {
                $warnings[] = $upload['error'];
            }
        }
    }

    if (empty($error)) {
        $additional_images_str = implode(',', array_filter($existingAdditional));
        $stmt = $conn->prepare("UPDATE gallery SET group_title = ?, description = ?, main_image_url = ?, additional_image_url = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $group_title, $description, $mainImagePath, $additional_images_str, $id);

        if ($stmt->execute()) {
            header("Location: manage_gallery");
            exit;
        } else {
            $error = "Failed to update gallery item.";
        }
    }
}

?>

<div class="container mt-4">
    <h2>Edit Gallery Item</h2>

    <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Group Title</label>
            <input type="text" class="form-control" name="group_title" value="<?php echo htmlspecialchars($gallery_item['group_title']); ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" required><?php echo htmlspecialchars($gallery_item['description']); ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Main Image</label><br>
            <img src="../<?php echo htmlspecialchars($gallery_item['main_image_url']); ?>" alt="Main Image" width="150"><br>
            <small>Current Image</small>
            <input type="file" class="form-control mt-2" name="main_image" accept="image/*">
        </div>

        <div class="mb-3">
            <label class="form-label">Additional Images</label><br>
            <?php
            $additional_images = explode(',', $gallery_item['additional_image_url']);
            foreach ($additional_images as $image) {
                if (!empty($image)) {
                    echo "<div class='mb-2'>";
                    $cleanImage = htmlspecialchars($image);
                    echo "<img src='../$cleanImage' alt='Additional Image' width='150' class='mr-2'>";
                    echo "<a href='delete_image.php?image=" . urlencode($image) . "&id=$id' class='btn btn-danger btn-sm'>Delete</a>";
                    echo "</div>";
                }
            }
            ?>
            <input type="file" class="form-control mt-2" name="additional_images[]" accept="image/*" multiple>
        </div>

        <button type="submit" class="btn btn-primary">Update Gallery Item</button>
    </form>
</div>

<?php include 'footer.php'; ?>
