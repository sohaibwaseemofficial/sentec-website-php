<?php
/**
 * Migration Tool: Upload all local WebP social images to Cloudinary (res.cloudinary.com)
 * and update the database records in social_registrations & social_attendees.
 */
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/image_utils.php';

echo "========================================================\n";
echo "   MIGRATING SOCIAL IMAGES TO CLOUDINARY (RES.CLOUDINARY.COM)\n";
echo "========================================================\n\n";

// 1. Gather all unique local image paths from social_registrations & social_attendees
$uniquePaths = [];

$res1 = $conn->query("SELECT payment_proof, face_image, id_card_image FROM social_registrations");
while ($r = $res1->fetch_assoc()) {
    foreach (['payment_proof', 'face_image', 'id_card_image'] as $col) {
        $p = trim($r[$col] ?? '');
        if (!empty($p) && str_starts_with($p, 'images/')) {
            $uniquePaths[$p] = true;
        }
    }
}

$res2 = $conn->query("SELECT payment_proof, face_image, id_card_image FROM social_attendees");
while ($r = $res2->fetch_assoc()) {
    foreach (['payment_proof', 'face_image', 'id_card_image'] as $col) {
        $p = trim($r[$col] ?? '');
        if (!empty($p) && str_starts_with($p, 'images/')) {
            $uniquePaths[$p] = true;
        }
    }
}

$localList = array_keys($uniquePaths);
echo "Found " . count($localList) . " unique local images to upload to Cloudinary.\n\n";

if (empty($localList)) {
    echo "No local images found. All images are already on Cloudinary!\n";
    exit(0);
}

// 2. Upload each file to Cloudinary and record mapping
$mapping = []; // [local_path => cloudinary_url]
$uploaded = 0;
$failed = 0;

$baseDir = rtrim(__DIR__, '/\\') . '/';

foreach ($localList as $idx => $relPath) {
    $fullPath = $baseDir . $relPath;
    $num = $idx + 1;
    echo "[$num/" . count($localList) . "] Uploading: $relPath ... ";

    if (!file_exists($fullPath)) {
        echo "FAILED (file does not exist on disk!)\n";
        $failed++;
        continue;
    }

    $cUrl = upload_to_cloudinary($fullPath, 'sentec_uploads/social_registrations');
    if ($cUrl) {
        $mapping[$relPath] = $cUrl;
        $uploaded++;
        echo "OK -> $cUrl\n";
    } else {
        echo "FAILED\n";
        $failed++;
    }

    // Small delay to prevent rate limits
    usleep(150000);
}

echo "\n--------------------------------------------------------\n";
echo "Cloudinary Upload Complete: $uploaded succeeded, $failed failed.\n";
echo "Updating Database Records...\n";
echo "--------------------------------------------------------\n";

// 3. Update database records in social_registrations & social_attendees
$updateRegProof = $conn->prepare("UPDATE social_registrations SET payment_proof = ? WHERE payment_proof = ?");
$updateRegFace  = $conn->prepare("UPDATE social_registrations SET face_image = ? WHERE face_image = ?");
$updateRegCard  = $conn->prepare("UPDATE social_registrations SET id_card_image = ? WHERE id_card_image = ?");

$updateAttProof = $conn->prepare("UPDATE social_attendees SET payment_proof = ? WHERE payment_proof = ?");
$updateAttFace  = $conn->prepare("UPDATE social_attendees SET face_image = ? WHERE face_image = ?");
$updateAttCard  = $conn->prepare("UPDATE social_attendees SET id_card_image = ? WHERE id_card_image = ?");

$regUpdated = 0;
$attUpdated = 0;

foreach ($mapping as $localPath => $cloudUrl) {
    // social_registrations
    $updateRegProof->bind_param("ss", $cloudUrl, $localPath);
    $updateRegProof->execute();
    $regUpdated += $updateRegProof->affected_rows;

    $updateRegFace->bind_param("ss", $cloudUrl, $localPath);
    $updateRegFace->execute();
    $regUpdated += $updateRegFace->affected_rows;

    $updateRegCard->bind_param("ss", $cloudUrl, $localPath);
    $updateRegCard->execute();
    $regUpdated += $updateRegCard->affected_rows;

    // social_attendees
    $updateAttProof->bind_param("ss", $cloudUrl, $localPath);
    $updateAttProof->execute();
    $attUpdated += $updateAttProof->affected_rows;

    $updateAttFace->bind_param("ss", $cloudUrl, $localPath);
    $updateAttFace->execute();
    $attUpdated += $updateAttFace->affected_rows;

    $updateAttCard->bind_param("ss", $cloudUrl, $localPath);
    $updateAttCard->execute();
    $attUpdated += $updateAttCard->affected_rows;
}

echo "Database Updated Successfully!\n";
echo "  social_registrations fields updated: $regUpdated\n";
echo "  social_attendees fields updated:     $attUpdated\n\n";

// 4. Verify remaining local paths
$check1 = $conn->query("SELECT COUNT(*) as c FROM social_registrations WHERE payment_proof LIKE 'images/%' OR face_image LIKE 'images/%' OR id_card_image LIKE 'images/%'")->fetch_assoc()['c'];
$check2 = $conn->query("SELECT COUNT(*) as c FROM social_attendees WHERE payment_proof LIKE 'images/%' OR face_image LIKE 'images/%' OR id_card_image LIKE 'images/%'")->fetch_assoc()['c'];

echo "========================================================\n";
echo "VERIFICATION:\n";
echo "Remaining local paths in social_registrations: $check1\n";
echo "Remaining local paths in social_attendees:     $check2\n";
echo "========================================================\n";
