<?php
include 'db_connection.php';
include 'header.php';
?>

<section id="partners">
    <div class="container">
        
        <div class="section-header"><h2>Our Current Partners</h2></div>
        <div class="row gx-4 gy-4">
            <?php
            $queryCurrent = "SELECT * FROM partners WHERE section = 'current'";
            if ($stmtCurrent = $conn->prepare($queryCurrent)) {
                $stmtCurrent->execute();
                $resultCurrent = $stmtCurrent->get_result();

                if ($resultCurrent->num_rows > 0) {
                    while ($partner = $resultCurrent->fetch_assoc()) {
            ?>
                <div class="col-md-6">
                    <div class="event-card"> <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 20px; display: flex; justify-content: center; height: 200px; align-items: center; margin-bottom: 20px;">
                            <img src="<?= htmlspecialchars(str_replace('../', './', $partner['image_url'])) ?>" 
                                 alt="<?= htmlspecialchars($partner['name']) ?>" 
                                 style="max-height: 100%; max-width: 100%; object-fit: contain; width: auto; height: auto;">
                        </div>
                        <h3><?= htmlspecialchars($partner['name']) ?></h3>
                        <p><?= htmlspecialchars($partner['description']) ?></p>
                    </div>
                </div>
            <?php
                    }
                } else {
                    echo "<p class='text-center text-muted'>No current partners found.</p>";
                }
                $stmtCurrent->close();
            }
            ?>
        </div>

        <div class="section-header mt-5"><h2>Our Past Partners</h2></div>
        <div class="row gx-4 gy-4">
            <?php
            $queryPast = "SELECT * FROM partners WHERE section = 'past'";
            if ($stmtPast = $conn->prepare($queryPast)) {
                $stmtPast->execute();
                $resultPast = $stmtPast->get_result();

                if ($resultPast->num_rows > 0) {
                    while ($partner = $resultPast->fetch_assoc()) {
            ?>
                <div class="col-md-6">
                    <div class="event-card">
                        <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 20px; display: flex; justify-content: center; height: 200px; align-items: center; margin-bottom: 20px;">
                            <img src="<?= htmlspecialchars(str_replace('../', './', $partner['image_url'])) ?>" 
                                 alt="<?= htmlspecialchars($partner['name']) ?>" 
                                 style="max-height: 100%; max-width: 100%; object-fit: contain; width: auto; height: auto;">
                        </div>
                        <h3><?= htmlspecialchars($partner['name']) ?></h3>
                        <p><?= htmlspecialchars($partner['description']) ?></p>
                    </div>
                </div>
            <?php
                    }
                } else {
                    echo "<p class='text-center text-muted'>No past partners found.</p>";
                }
                $stmtPast->close();
            }
            ?>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
