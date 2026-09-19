<?php
include 'header.php';
include 'db_connection.php';

// Filtering Logic
$selected_group = isset($_GET['group']) ? $_GET['group'] : '';
$groups_query = "SELECT DISTINCT group_title FROM gallery";
$groups_result = $conn->query($groups_query);

if ($selected_group) {
    $query = "SELECT * FROM gallery WHERE group_title = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $selected_group);
} else {
    $query = "SELECT * FROM gallery";
    $stmt = $conn->prepare($query);
}
$stmt->execute();
$result = $stmt->get_result();

// Group Data Logic
$gallery_data = [];
while ($row = $result->fetch_assoc()) {
    $gallery_data[$row['group_title']]['description'] = $row['description'];
    $gallery_data[$row['group_title']]['main_image'] = str_replace('../', './', $row['main_image_url']);
    $additional_images = explode(',', $row['additional_image_url']);
    $trimmed_images = array_map(function ($url) {
        return htmlspecialchars(str_replace('../', './', trim($url)));
    }, $additional_images);
    $gallery_data[$row['group_title']]['additional_images'] = $trimmed_images;
}
?>

<section id="gallery">
    <div class="container">
        <div class="section-header"><h2>Our Gallery</h2></div>

        <div class="glass-panel mb-5" style="padding: 20px; text-align: center;">
            <form method="GET">
                <select name="group" class="form-select w-50 mx-auto" onchange="this.form.submit()" 
                        style="background: #0b1120; color: #fff; border: 1px solid #333;">
                    <option value="">Show All Events</option>
                    <?php while ($group = $groups_result->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($group['group_title']) ?>" <?= $selected_group == $group['group_title'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($group['group_title']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>

        <?php foreach ($gallery_data as $title => $data): ?>
            <div class="glass-panel mb-5">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <a href="<?= htmlspecialchars($data['main_image']) ?>" data-lightbox="<?= htmlspecialchars($title) ?>">
                            <img class="img-fluid rounded shadow-lg" src="<?= htmlspecialchars($data['main_image']) ?>" 
                                 style="border: 1px solid rgba(255,255,255,0.1); border-radius: 15px;">
                        </a>
                    </div>
                    <div class="col-md-6">
                        <h3 style="color: #fff; margin-top: 20px;"><?= htmlspecialchars($title) ?></h3>
                        <p style="color: #aaa;"><?= htmlspecialchars($data['description']) ?></p>
                    </div>
                </div>

                <div class="row mt-4 g-3">
                    <?php foreach ($data['additional_images'] as $image_url): ?>
                        <div class="col-6 col-md-3">
                            <a href="<?= htmlspecialchars($image_url) ?>" data-lightbox="<?= htmlspecialchars($title) ?>">
                                <img class="img-fluid rounded" src="<?= htmlspecialchars($image_url) ?>" 
                                     style="height: 100px; width: 100%; object-fit: cover; border: 1px solid rgba(255,255,255,0.1); transition: 0.3s;">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php include 'footer.php'; ?>
