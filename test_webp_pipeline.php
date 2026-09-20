<?php
/**
 * Automated Test Suite for Client-Side WebP Compression Pipeline
 * Tests the backend handling of event_registration.php and image_utils.php
 * Runs entirely via static source analysis + config checks (no GD required for CLI)
 */

echo "=============================================================\n";
echo "  SENTEC WebP Pipeline Test Suite\n";
echo "  Date: " . date('Y-m-d H:i:s') . "\n";
echo "=============================================================\n\n";

$passed = 0;
$failed = 0;

function test($name, $result, $detail = '') {
    global $passed, $failed;
    if ($result) {
        echo "  [PASS] $name\n";
        $passed++;
    } else {
        echo "  [FAIL] $name\n";
        if ($detail) echo "         >> $detail\n";
        $failed++;
    }
}

// ===============================================================
// SECTION 1: File & Function Existence
// ===============================================================
echo "--- Section 1: Core File & Function Checks ---\n";

test("image_utils.php exists", file_exists(__DIR__ . '/image_utils.php'));
test("event_registration.php exists", file_exists(__DIR__ . '/event_registration.php'));

require_once __DIR__ . '/image_utils.php';
test("save_image_as_webp() function is defined", function_exists('save_image_as_webp'));

// ===============================================================
// SECTION 2: image_utils.php Source Analysis (WebP Passthrough)
// ===============================================================
echo "\n--- Section 2: Backend WebP Passthrough Logic (image_utils.php) ---\n";

$imgSrc = file_get_contents(__DIR__ . '/image_utils.php');

test(
    "Allowed MIME types include image/webp",
    strpos($imgSrc, "'image/webp'") !== false,
    "The allowed MIME array must include image/webp"
);

test(
    "WebP passthrough branch exists (checks mime === image/webp)",
    preg_match('/\$mime\s*===\s*[\'"]image\/webp[\'"]/', $imgSrc) === 1,
    "Must detect incoming WebP and skip GD re-encoding"
);

test(
    "WebP passthrough uses move_uploaded_file (no GD processing)",
    preg_match('/image\/webp.*?move_uploaded_file/s', $imgSrc) === 1,
    "When file is already WebP, it should be moved directly to storage"
);

test(
    "GD fallback still exists for JPEG",
    strpos($imgSrc, 'imagecreatefromjpeg') !== false,
    "Server-side JPEG->WebP conversion must remain as fallback"
);

test(
    "GD fallback still exists for PNG",
    strpos($imgSrc, 'imagecreatefrompng') !== false,
    "Server-side PNG->WebP conversion must remain as fallback"
);

test(
    "imagewebp() is used for server-side conversion",
    strpos($imgSrc, 'imagewebp(') !== false,
    "GD's imagewebp must be called for non-WebP fallback path"
);

test(
    "Alpha channel handling for PNG/GIF",
    strpos($imgSrc, 'imagealphablending') !== false && strpos($imgSrc, 'imagesavealpha') !== false,
    "PNG transparency must be preserved during GD conversion"
);

// ===============================================================
// SECTION 3: Frontend JavaScript Pipeline (event_registration.php)
// ===============================================================
echo "\n--- Section 3: Frontend Client-Side Compression Pipeline ---\n";

$frontSrc = file_get_contents(__DIR__ . '/event_registration.php');

test(
    "browser-image-compression CDN v2.0.1 is injected",
    strpos($frontSrc, 'browser-image-compression@2.0.1') !== false,
    "CDN script tag must load the compression library"
);

test(
    "Form submit handler is async",
    strpos($frontSrc, 'async function(e)') !== false,
    "Must be async for sequential await-based compression"
);

test(
    "Does NOT use Promise.all (sequential only)",
    strpos($frontSrc, 'Promise.all') === false,
    "Promise.all would crash mobile browsers with 12 concurrent compressions"
);

test(
    "Uses sequential for loop for compression",
    preg_match('/for\s*\(\s*let\s+i\s*=\s*0;\s*i\s*<\s*fileEntries\.length/', $frontSrc) === 1,
    "Must iterate files one-by-one with a for loop"
);

test(
    "imageCompression() function is called",
    strpos($frontSrc, 'imageCompression(') !== false
);

test(
    "maxSizeMB is set to 0.3 (300KB)",
    strpos($frontSrc, 'maxSizeMB: 0.3') !== false,
    "Each compressed file must target max 300KB"
);

test(
    "maxWidthOrHeight is set to 1600",
    strpos($frontSrc, 'maxWidthOrHeight: 1600') !== false,
    "Dimensions capped at 1600px to reduce pixel count"
);

test(
    "useWebWorker is enabled",
    strpos($frontSrc, 'useWebWorker: true') !== false,
    "WebWorker offloads compression from main thread"
);

test(
    "Output fileType is forced to image/webp",
    strpos($frontSrc, "fileType: 'image/webp'") !== false,
    "Must force WebP output regardless of input format"
);

test(
    "Compressed files renamed to .webp extension",
    strpos($frontSrc, '.webp"') !== false && strpos($frontSrc, 'new File(') !== false,
    "Blob must be wrapped in File() with .webp filename"
);

// ===============================================================
// SECTION 4: UX State Management
// ===============================================================
echo "\n--- Section 4: UX State Management ---\n";

test(
    "Submit button disabled on click",
    strpos($frontSrc, 'btn.disabled = true') !== false
);

test(
    "Progressive status text (COMPRESSING IMAGE X OF Y)",
    strpos($frontSrc, 'COMPRESSING IMAGE') !== false,
    "User must see per-image progress feedback"
);

test(
    "Button shows TRANSMITTING after compression",
    strpos($frontSrc, 'TRANSMITTING REGISTRATION') !== false
);

test(
    "Button re-enables on server error",
    substr_count($frontSrc, 'btn.disabled = false') >= 2,
    "Must re-enable in both error response and catch block"
);

test(
    "Button text resets on error",
    substr_count($frontSrc, '"SUBMIT REGISTRATION"') >= 2,
    "Must reset text in both error paths"
);

test(
    "Error is logged to console",
    strpos($frontSrc, 'console.error(') !== false,
    "Compression/upload failures must be logged for debugging"
);

// ===============================================================
// SECTION 5: FormData Integrity
// ===============================================================
echo "\n--- Section 5: FormData Integrity ---\n";

test(
    "Text fields are preserved in finalFormData",
    strpos($frontSrc, 'finalFormData.append(key, value)') !== false,
    "Non-file fields (user_id, teamName, etc.) must be carried forward"
);

test(
    "Empty file inputs are skipped",
    strpos($frontSrc, "value.name !== ''") !== false,
    "Optional participant slots with no file must not be compressed"
);

test(
    "Hidden user_id field exists in form",
    strpos($frontSrc, 'name="user_id"') !== false,
    "user_id must be a hidden input populated from PHP session"
);

// ===============================================================
// SECTION 6: Backend Safety Guards
// ===============================================================
echo "\n--- Section 6: Backend Safety Guards ---\n";

test(
    "post_max_size overflow detection",
    strpos($frontSrc, 'empty($_POST)') !== false && strpos($frontSrc, 'CONTENT_LENGTH') !== false,
    "Must detect when PHP silently drops payload exceeding post_max_size"
);

test(
    "Overflow returns clean JSON (not raw PHP warning)",
    preg_match('/empty\(\$_POST\).*?json_encode/s', $frontSrc) === 1,
    "Must respond with valid JSON so frontend can display the error"
);

test(
    "user_id <= 0 rejection guard",
    preg_match('/user_id\s*<=\s*0/', $frontSrc) === 1,
    "Prevents ghost registrations with null/0 user_id"
);

test(
    "Response header is Content-Type: application/json",
    strpos($frontSrc, "Content-Type: application/json") !== false
);

// ===============================================================
// SECTION 7: GD Extension Status (Informational)
// ===============================================================
echo "\n--- Section 7: Server Environment (Informational) ---\n";

$gdLoaded = extension_loaded('gd');
echo "  [INFO] GD Extension (CLI): " . ($gdLoaded ? "LOADED" : "NOT LOADED (normal for CLI, loaded in Apache/mod_php)") . "\n";
echo "  [INFO] PHP Version: " . PHP_VERSION . "\n";
echo "  [INFO] PHP SAPI: " . php_sapi_name() . "\n";

if ($gdLoaded) {
    $gdInfo = gd_info();
    echo "  [INFO] GD WebP Support: " . ($gdInfo['WebP Support'] ? 'YES' : 'NO') . "\n";
}

// ===============================================================
// SUMMARY
// ===============================================================
echo "\n=============================================================\n";
if ($failed === 0) {
    echo "  RESULT: ALL $passed TESTS PASSED ✓\n";
} else {
    echo "  RESULT: $passed PASSED / $failed FAILED / " . ($passed + $failed) . " TOTAL\n";
}
echo "=============================================================\n";

exit($failed > 0 ? 1 : 0);
