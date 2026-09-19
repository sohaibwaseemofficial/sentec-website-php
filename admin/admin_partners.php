<?php

include 'db_connection.php';
include 'header.php';
require_once __DIR__ . '/../image_utils.php';
// Handle create partner form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_partner'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    // $role = mysqli_real_escape_string($conn, $_POST['role']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $section = mysqli_real_escape_string($conn, $_POST['section']);

    // Handle image upload
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo "<script>alert(" . json_encode('No image uploaded or there was an error with the upload.') . ");</script>";
    } else {
        $uploadResult = save_image_as_webp($_FILES['image'], __DIR__ . '/../images/uploads/partners/', '../images/uploads/partners/');

        if (!$uploadResult['success']) {
            echo "<script>alert(" . json_encode($uploadResult['error']) . ");</script>";
        } else {
            $image_path = $uploadResult['path'];
            $query = "INSERT INTO partners (name, description, image_url, section) 
                          VALUES ('$name', '$description', '$image_path', '$section')";
            if (mysqli_query($conn, $query)) {
                echo "<script>alert('Partner created successfully!');</script>";
                if (!empty($uploadResult['warning'])) {
                    echo "<script>alert(" . json_encode($uploadResult['warning']) . ");</script>";
                }
            } else {
                echo "<script>alert('Error: " . mysqli_error($conn) . "');</script>";
            }
        }
    }
}


// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $editId = intval($_POST['edit_id']);
    $editName = mysqli_real_escape_string($conn, $_POST['name']);
    // $editRole = mysqli_real_escape_string($conn, $_POST['role']);
    $editDescription = mysqli_real_escape_string($conn, $_POST['description']);
    $editSection = mysqli_real_escape_string($conn, $_POST['section']);

    $newImagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = save_image_as_webp($_FILES['image'], __DIR__ . '/../images/uploads/partners/', '../images/uploads/partners/');
        if (!$uploadResult['success']) {
            echo "<p class='text-danger'>" . htmlspecialchars($uploadResult['error']) . "</p>";
            exit;
        }
        $newImagePath = $uploadResult['path'];
    }

    if ($newImagePath) {
        $updateQuery = "UPDATE partners SET 
                                name = '$editName', 
                                description = '$editDescription', 
                                image_url = '$newImagePath', 
                                section = '$editSection' 
                                WHERE id = $editId";
    } else {
        $updateQuery = "UPDATE partners SET 
                        name = '$editName', 
                        description = '$editDescription', 
                        section = '$editSection' 
                        WHERE id = $editId";
    }

    // Execute the update query
    if (mysqli_query($conn, $updateQuery)) {
        echo "<script>alert('Partner updated successfully!');</script>";
        echo "<script>window.location.href='admin_partners.php';</script>";
    } else {
        echo "<p class='text-danger'>Error updating partner: " . mysqli_error($conn) . "</p>";
    }
}

// Handle delete request
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    $query = "DELETE FROM partners WHERE id = '$id'";
    if (mysqli_query($conn, $query)) {
        echo "<script>alert('Partner deleted successfully!'); window.location.href='admin_partners';</script>";
    } else {
        echo "<script>alert('Error: " . mysqli_error($conn) . "');</script>";
    }
}

?>
<div class="container my-5">
    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="current-tab" data-bs-toggle="tab" data-bs-target="#current" type="button" role="tab">Current Partners</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab">Past Partners</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="create-tab" data-bs-toggle="tab" data-bs-target="#create" type="button" role="tab">Create Partner</button>
        </li>
    </ul>
    <div class="tab-content" id="partnerTabsContent">
        <!-- View Partners Tab -->
        <!-- Current Partners Tab -->
        <div class="tab-pane fade show active" id="current" role="tabpanel">
            <div class="row">
                <?php
                // Query to fetch current partners
                $queryCurrent = "SELECT * FROM partners WHERE section = 'current'";
                $resultCurrent = mysqli_query($conn, $queryCurrent);

                if (mysqli_num_rows($resultCurrent) > 0) {
                    while ($partner = mysqli_fetch_assoc($resultCurrent)) {
                        echo "
                <div class='col-md-6 mb-4'>
                    <div class='card shadow-sm'>
                        <img class='card-img-top' src='" . htmlspecialchars($partner['image_url']) . "' alt='" . htmlspecialchars($partner['name']) . "' style='height: 200px; object-fit: cover;'>
                        <div class='card-body'>
                            <h5 class='card-title'>" . htmlspecialchars($partner['name']) . "</h5>
                            <p class='card-text'>" . htmlspecialchars($partner['description']) . "</p>
                            <a href='?delete=" . $partner['id'] . "' class='btn btn-danger btn-sm' onclick='return confirm(`Are you sure you want to delete this partner?`)'>Delete</a>
                            <button class='btn btn-primary btn-sm' data-bs-toggle='modal' data-bs-target='#editModal" . $partner['id'] . "'>Edit</button>
                        </div>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div class='modal fade' id='editModal" . $partner['id'] . "' tabindex='-1' aria-labelledby='editModalLabel" . $partner['id'] . "' aria-hidden='true'>
                    <div class='modal-dialog'>
                        <div class='modal-content'>
                            <div class='modal-header'>
                                <h5 class='modal-title' id='editModalLabel" . $partner['id'] . "'>Edit Partner</h5>
                                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                            </div>
                            <div class='modal-body'>
                                <form action='edit_partner.php' method='POST' enctype='multipart/form-data'>
                                    <input type='hidden' name='edit_id' value='" . $partner['id'] . "'>
                                    <div class='mb-3'>
                                        <label for='name" . $partner['id'] . "' class='form-label'>Name</label>
                                        <input type='text' class='form-control' id='name" . $partner['id'] . "' name='name' value='" . htmlspecialchars($partner['name']) . "' required>
                                    </div>
                                    <div class='mb-3'>
                                        <label for='description" . $partner['id'] . "' class='form-label'>Description</label>
                                        <textarea class='form-control' id='description" . $partner['id'] . "' name='description' rows='3' required>" . htmlspecialchars($partner['description']) . "</textarea>
                                    </div>
                                    <div class='mb-3'>
                                        <label for='image" . $partner['id'] . "' class='form-label'>Image</label>
                                        <input type='file' class='form-control' id='image" . $partner['id'] . "' name='image'>
                                    </div>
                                    <div class='mb-3'>
                                        <label for='section" . $partner['id'] . "' class='form-label'>Section</label>
                                        <input type='text' class='form-control' id='section" . $partner['id'] . "' name='section' value='" . htmlspecialchars($partner['section']) . "' required>
                                    </div>
                                    <button type='submit' class='btn btn-primary'>Update Partner</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                ";
                    }
                } else {
                    echo "<p>No current partners found.</p>";
                }
                ?>
            </div>
        </div>

        <div class="tab-pane fade show active" id="past" role="tabpanel">
            <div class="row">
                <?php
                $queryPast = "SELECT * FROM partners WHERE section = 'past'";
                $resultPast = mysqli_query($conn, $queryPast);

                if (mysqli_num_rows($resultPast) > 0) {
                    while ($partner = mysqli_fetch_assoc($resultPast)) {
                        echo "
                        <div class='col-md-6 mb-4'>
                            <div class='card shadow-sm'>
                                <img class='card-img-top' src='" . htmlspecialchars($partner['image_url']) . "' alt='" . htmlspecialchars($partner['name']) . "' style='height: 200px; object-fit: cover;'>
                                <div class='card-body'>
                                    <h5 class='card-title'>" . htmlspecialchars($partner['name']) . "</h5>
                                    <p class='card-text'>" . htmlspecialchars($partner['description']) . "</p>
                                    <a href='?delete=" . $partner['id'] . "' class='btn btn-danger btn-sm' onclick='return confirm(`Are you sure you want to delete this partner?`)'>Delete</a>
                                    <button class='btn btn-primary btn-sm' data-bs-toggle='modal' data-bs-target='#editModal" . $partner['id'] . "'>Edit</button>
                                </div>
                            </div>
                        </div>
                        ";

                        // Edit Modal
                        echo "
                         <!-- Edit Modal -->
                <div class='modal fade' id='editModal" . $partner['id'] . "' tabindex='-1' aria-labelledby='editModalLabel" . $partner['id'] . "' aria-hidden='true'>
                    <div class='modal-dialog'>
                        <div class='modal-content'>
                            <div class='modal-header'>
                                <h5 class='modal-title' id='editModalLabel" . $partner['id'] . "'>Edit Partner</h5>
                                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                            </div>
                            <div class='modal-body'>
                                <form action='edit_partner.php' method='POST' enctype='multipart/form-data'>
                                    <input type='hidden' name='edit_id' value='" . $partner['id'] . "'>
                                    <div class='mb-3'>
                                        <label for='name" . $partner['id'] . "' class='form-label'>Name</label>
                                        <input type='text' class='form-control' id='name" . $partner['id'] . "' name='name' value='" . htmlspecialchars($partner['name']) . "' required>
                                    </div>
                                    <div class='mb-3'>
                                        <label for='description" . $partner['id'] . "' class='form-label'>Description</label>
                                        <textarea class='form-control' id='description" . $partner['id'] . "' name='description' rows='3' required>" . htmlspecialchars($partner['description']) . "</textarea>
                                    </div>
                                    <div class='mb-3'>
                                        <label for='image" . $partner['id'] . "' class='form-label'>Image</label>
                                        <input type='file' class='form-control' id='image" . $partner['id'] . "' name='image'>
                                    </div>
                                    <div class='mb-3'>
                                        <label for='section" . $partner['id'] . "' class='form-label'>Section</label>
                                        <input type='text' class='form-control' id='section" . $partner['id'] . "' name='section' value='" . htmlspecialchars($partner['section']) . "' required>
                                    </div>
                                    <button type='submit' class='btn btn-primary'>Update Partner</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                        ";
                    }
                } else {
                    echo "<p class='text-center'>No past partners found.</p>";
                }
                ?>
            </div>
        </div>
        <!-- Create Partner Tab -->
        <div class="tab-pane fade" id="create" role="tabpanel" aria-labelledby="create-tab">
            <h3 class="my-4">Create New Partner</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="image" class="form-label">Image</label>
                    <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                </div>
                <div class="mb-3">
                    <label for="section" class="form-label">Section</label>
                    <select class="form-select" id="section" name="section" required>
                        <option value="current">Current</option>
                        <option value="past">Past</option>
                    </select>
                </div>
                <button type="submit" name="create_partner" class="btn btn-primary">Create Partner</button>
            </form>
        </div>

    </div>
</div>
<?php include 'footer.php'; ?>