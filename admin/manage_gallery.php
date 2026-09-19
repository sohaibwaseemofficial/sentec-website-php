<?php
include 'header.php';
include '../db_connection.php';
require_once __DIR__ . '/../image_utils.php';

$msg = "";

// HANDLE ADD
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $group = $_POST['group_title'];
    $desc = $_POST['description'];

    $galleryDir = __DIR__ . '/../images/uploads/gallery/';
    $publicPrefix = 'images/uploads/gallery/';

    if (!isset($_FILES['main_image']) || $_FILES['main_image']['error'] !== UPLOAD_ERR_OK) {
        $msg = "<div class='alert alert-danger'>Main cover image is required.</div>";
    } else {
        $mainUpload = save_image_as_webp($_FILES['main_image'], $galleryDir, $publicPrefix);
        if (!$mainUpload['success']) {
            $msg = "<div class='alert alert-danger'>" . htmlspecialchars($mainUpload['error']) . "</div>";
        } else {
            $mainPath = $mainUpload['path'];
            $warnings = [];
            if (!empty($mainUpload['warning'])) {
                $warnings[] = $mainUpload['warning'];
            }

            $addImages = [];
            if (!empty($_FILES['additional_images']['name'][0])) {
                foreach ($_FILES['additional_images']['name'] as $key => $name) {
                    $file = [
                        'name' => $_FILES['additional_images']['name'][$key],
                        'type' => $_FILES['additional_images']['type'][$key],
                        'tmp_name' => $_FILES['additional_images']['tmp_name'][$key],
                        'error' => $_FILES['additional_images']['error'][$key],
                        'size' => $_FILES['additional_images']['size'][$key],
                    ];

                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        $warnings[] = 'One of the additional images could not be uploaded.';
                        continue;
                    }

                    $upload = save_image_as_webp($file, $galleryDir, $publicPrefix);
                    if ($upload['success']) {
                        $addImages[] = $upload['path'];
                        if (!empty($upload['warning'])) {
                            $warnings[] = $upload['warning'];
                        }
                    } else {
                        $warnings[] = $upload['error'];
                    }
                }
            }

            $addImages = array_filter($addImages);
            $addStr = implode(',', $addImages);

            $stmt = $conn->prepare("INSERT INTO gallery (group_title, description, main_image_url, additional_image_url) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $group, $desc, $mainPath, $addStr);

            if($stmt->execute()) {
                $msg = "<div class='alert alert-success'>Gallery Added!</div>";
                if (!empty($warnings)) {
                    $msg .= "<div class='alert alert-warning mt-2'>" . htmlspecialchars(implode(' ', array_unique($warnings))) . "</div>";
                }
            } else {
                $msg = "<div class='alert alert-danger'>Error saving.</div>";
            }
        }
    }
}

// FETCH ALL
$gallery = $conn->query("SELECT * FROM gallery ORDER BY id DESC");
?>

<div class="page-header">
    <h2>Manage Gallery</h2>
    <p>Showcase past event photos.</p>
</div>

<?php echo $msg; ?>

<div class="row">
    <div class="col-md-4">
        <div class="glass-panel">
            <h4 class="text-white mb-4">Add New Album</h4>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="text-white">Event Title</label>
                    <input type="text" name="group_title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="text-white">Description</label>
                    <textarea name="description" class="form-control" rows="2" style="background:#0b1120; color:#fff; border:1px solid #333;"></textarea>
                </div>
                <div class="mb-3">
                    <label class="text-white">Main Cover</label>
                    <input type="file" name="main_image" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="text-white">Extra Photos</label>
                    <input type="file" name="additional_images[]" class="form-control" multiple>
                </div>
                <button type="submit" class="btn-neon w-100">Upload Album</button>
            </form>
        </div>
    </div>

    <div class="col-md-8">
        <div class="glass-panel">
            <h4 class="text-white mb-4">Gallery List</h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Cover</th>
                            <th>Event</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $gallery->fetch_assoc()): ?>
                        <tr>
                            <td><img src="../<?php echo $row['main_image_url']; ?>" alt="img"></td>
                            <td>
                                <strong class="text-white"><?php echo $row['group_title']; ?></strong><br>
                                <small><?php echo substr($row['description'], 0, 50); ?>...</small>
                            </td>
                            <td>
                                <a href="delete_gallery.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this album?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
