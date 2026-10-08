<?php
/**
 * Image Utilities with Cloudinary Cloud Storage & Local Fallback
 * 
 * If Cloudinary environment variables are set:
 *   - Uploads are saved permanently to Cloudinary CDN (immune to Docker redeploys).
 *   - Returns secure HTTPS Cloudinary URL (e.g. https://res.cloudinary.com/...)
 * 
 * If Cloudinary is not configured or fails:
 *   - Automatically falls back to local disk storage in WebP format.
 */

if (!function_exists('get_cloudinary_config')) {
    function get_cloudinary_config(): ?array
    {
        require_once __DIR__ . '/env_loader.php';

        // Check CLOUDINARY_URL format: cloudinary://api_key:api_secret@cloud_name
        $cloudinaryUrl = env('CLOUDINARY_URL') ?: getenv('CLOUDINARY_URL') ?: ($_ENV['CLOUDINARY_URL'] ?? '') ?: ($_SERVER['CLOUDINARY_URL'] ?? '');
        if ($cloudinaryUrl) {
            $parsed = parse_url($cloudinaryUrl);
            if ($parsed && !empty($parsed['host']) && !empty($parsed['user']) && !empty($parsed['pass'])) {
                return [
                    'cloud_name' => trim($parsed['host']),
                    'api_key'    => trim($parsed['user']),
                    'api_secret' => trim($parsed['pass']),
                ];
            }
        }

        $cloudName = env('CLOUDINARY_CLOUD_NAME') ?: getenv('CLOUDINARY_CLOUD_NAME') ?: ($_ENV['CLOUDINARY_CLOUD_NAME'] ?? '') ?: ($_SERVER['CLOUDINARY_CLOUD_NAME'] ?? '');
        $apiKey    = env('CLOUDINARY_API_KEY') ?: getenv('CLOUDINARY_API_KEY') ?: ($_ENV['CLOUDINARY_API_KEY'] ?? '') ?: ($_SERVER['CLOUDINARY_API_KEY'] ?? '');
        $apiSecret = env('CLOUDINARY_API_SECRET') ?: getenv('CLOUDINARY_API_SECRET') ?: ($_ENV['CLOUDINARY_API_SECRET'] ?? '') ?: ($_SERVER['CLOUDINARY_API_SECRET'] ?? '');

        if (!empty($cloudName) && !empty($apiKey) && !empty($apiSecret)) {
            return [
                'cloud_name' => trim($cloudName),
                'api_key'    => trim($apiKey),
                'api_secret' => trim($apiSecret),
            ];
        }

        return null;
    }
}

if (!function_exists('upload_to_cloudinary')) {
    /**
     * Upload an image directly to Cloudinary using standard PHP cURL (no composer package required)
     * Returns secure HTTPS URL on success, or null on failure.
     */
    function upload_to_cloudinary(string $filePath, string $folder = 'sentec_uploads'): ?string
    {
        $config = get_cloudinary_config();
        if (!$config) {
            return null;
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

        if (!function_exists('curl_init')) {
            error_log("cURL extension not available for Cloudinary upload.");
            return null;
        }

        $timestamp = time();
        $folder = trim($folder, '/');
        
        // Build signature string (sorted alphabetically: folder, timestamp)
        $signStr = "folder={$folder}&timestamp={$timestamp}" . $config['api_secret'];
        $signature = sha1($signStr);

        $postData = [
            'file'      => new CURLFile($filePath),
            'api_key'   => $config['api_key'],
            'timestamp' => $timestamp,
            'folder'    => $folder,
            'signature' => $signature,
        ];

        $apiUrl = "https://api.cloudinary.com/v1_1/" . rawurlencode($config['cloud_name']) . "/auto/upload";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (!empty($data['secure_url'])) {
                return $data['secure_url'];
            }
        }

        error_log("Cloudinary upload failed (HTTP {$httpCode}): " . ($response ?: $curlErr));
        return null;
    }
}

if (!function_exists('resolve_image_url')) {
    /**
     * Resolve image URL for HTML rendering.
     * Works with both Cloudinary full URLs (https://...) and local paths (images/...).
     */
    function resolve_image_url(?string $path, string $adminPrefix = '../', string $fallback = ''): string
    {
        if (empty($path)) {
            return $fallback;
        }
        $path = trim($path);
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, 'data:') === 0) {
            return $path;
        }
        return $adminPrefix . ltrim($path, '/');
    }
}

if (!function_exists('save_image_as_webp')) {
    /**
     * Store image or PDF in Cloudinary if configured, otherwise convert/save locally.
     * Handles JPG, PNG, WebP, GIF, HEIC/HEIF, BMP, and PDF receipts.
     * Returns an array with success status, stored path (URL or local path), and optional warnings.
     */
    function save_image_as_webp(array $file, string $destinationDir, string $publicPrefix = '', int $quality = 82): array
    {
        $result = [
            'success'   => false,
            'path'      => null,
            'error'     => null,
            'warning'   => null,
            'converted' => false,
        ];

        // 1. Basic Upload Validation
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $result['error'] = 'Upload failed or no file provided (Code: ' . ($file['error'] ?? 'none') . ').';
            return $result;
        }

        $tmpName = $file['tmp_name'] ?? '';
        if (empty($tmpName) || (!is_uploaded_file($tmpName) && !file_exists($tmpName))) {
            $result['error'] = 'No valid uploaded file found on server.';
            return $result;
        }

        // 2. Prepare Destination Directory
        $destinationDir = rtrim(str_replace('\\', '/', $destinationDir), '/') . '/';
        $publicPrefix   = $publicPrefix !== '' ? (rtrim(str_replace('\\', '/', $publicPrefix), '/') . '/') : '';

        if (!is_dir($destinationDir)) {
            @mkdir($destinationDir, 0777, true);
        }
        if (!is_dir($destinationDir)) {
            $result['error'] = 'Unable to prepare upload destination folder.';
            return $result;
        }
        @chmod($destinationDir, 0777);

        // 3. Multi-Layer File Format Detection (Magic Bytes + Finfo + Extension + MIME)
        $magic = '';
        $fh = @fopen($tmpName, 'rb');
        if ($fh) {
            $magic = fread($fh, 32);
            fclose($fh);
        }

        $rawName = trim($file['name'] ?? '');
        $ext = strtolower(pathinfo($rawName, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') $ext = 'jpg';

        $info = @getimagesize($tmpName);
        $mime = ($info && !empty($info['mime'])) ? strtolower($info['mime']) : '';

        if (empty($mime) && function_exists('mime_content_type')) {
            $mct = @mime_content_type($tmpName);
            if ($mct) $mime = strtolower($mct);
        }
        if (empty($mime) && function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $fmime = @finfo_file($finfo, $tmpName);
                if ($fmime) $mime = strtolower($fmime);
                @finfo_close($finfo);
            }
        }

        $browserType = strtolower($file['type'] ?? '');

        // Detect Format Flags
        $isPdf = (strncmp($magic, '%PDF', 4) === 0)
                 || ($ext === 'pdf')
                 || (stripos($browserType, 'pdf') !== false)
                 || (stripos($mime, 'pdf') !== false);

        $isJpeg = (strncmp($magic, "\xFF\xD8\xFF", 3) === 0)
                  || in_array($ext, ['jpg', 'jpeg', 'jfif'], true)
                  || ($mime === 'image/jpeg')
                  || ($browserType === 'image/jpeg');

        $isPng = (strncmp($magic, "\x89PNG\r\n\x1a\n", 8) === 0)
                 || ($ext === 'png')
                 || ($mime === 'image/png')
                 || ($browserType === 'image/png');

        $isGif = (strncmp($magic, "GIF87a", 6) === 0 || strncmp($magic, "GIF89a", 6) === 0)
                 || ($ext === 'gif')
                 || ($mime === 'image/gif')
                 || ($browserType === 'image/gif');

        $isWebp = (strncmp($magic, 'RIFF', 4) === 0 && substr($magic, 8, 4) === 'WEBP')
                  || ($ext === 'webp')
                  || ($mime === 'image/webp')
                  || ($browserType === 'image/webp');

        $isHeic = (stripos($magic, 'ftyp') !== false && (stripos($magic, 'heic') !== false || stripos($magic, 'mif1') !== false || stripos($magic, 'heix') !== false))
                  || in_array($ext, ['heic', 'heif'], true)
                  || (stripos($mime, 'heic') !== false || stripos($mime, 'heif') !== false);

        $isBmp = (strncmp($magic, 'BM', 2) === 0)
                 || ($ext === 'bmp')
                 || ($mime === 'image/bmp')
                 || ($browserType === 'image/bmp');

        // Check if format is recognized
        if (!$isPdf && !$isJpeg && !$isPng && !$isGif && !$isWebp && !$isHeic && !$isBmp) {
            $result['error'] = 'Unsupported or unreadable file format. Please upload JPG, PNG, WebP, or PDF.';
            return $result;
        }

        // Determine Cloudinary folder if applicable
        $folder = 'sentec_uploads';
        if (stripos($publicPrefix, 'team') !== false || stripos($destinationDir, 'team') !== false) {
            $folder = 'sentec_uploads/team';
        } elseif (stripos($publicPrefix, 'payment') !== false || stripos($destinationDir, 'payment') !== false) {
            $folder = 'sentec_uploads/payments';
        } elseif (stripos($publicPrefix, 'event_registration') !== false || stripos($destinationDir, 'event_registration') !== false) {
            $folder = 'sentec_uploads/event_registrations';
        } elseif (stripos($publicPrefix, 'partner') !== false || stripos($destinationDir, 'partner') !== false) {
            $folder = 'sentec_uploads/partners';
        } elseif (stripos($publicPrefix, 'gallery') !== false || stripos($destinationDir, 'gallery') !== false) {
            $folder = 'sentec_uploads/gallery';
        } elseif (stripos($publicPrefix, 'social') !== false || stripos($destinationDir, 'social') !== false) {
            $folder = 'sentec_uploads/social_registrations';
        }

        // =========================================================
        // 4. PDF HANDLING (Document Receipts)
        // =========================================================
        if ($isPdf) {
            // Try Cloudinary first
            $cloudinaryConfig = get_cloudinary_config();
            if ($cloudinaryConfig) {
                $cloudinaryUrl = upload_to_cloudinary($tmpName, $folder);
                if ($cloudinaryUrl) {
                    $result['success'] = true;
                    $result['path']    = $cloudinaryUrl;
                    return $result;
                }
            }

            // Local disk fallback
            $filename = uniqid('doc_', true) . '.pdf';
            $targetPath = $destinationDir . $filename;
            if (@move_uploaded_file($tmpName, $targetPath) || @copy($tmpName, $targetPath)) {
                $result['success'] = true;
                $result['path']    = $publicPrefix . $filename;
                return $result;
            }

            $result['error'] = 'Failed to store uploaded PDF document. Please verify server directory permissions.';
            return $result;
        }

        // =========================================================
        // 5. IMAGE HANDLING (JPG, PNG, WebP, GIF, HEIC, BMP)
        // =========================================================

        // Try Cloudinary first for all images
        $cloudinaryConfig = get_cloudinary_config();
        if ($cloudinaryConfig) {
            $cloudinaryUrl = upload_to_cloudinary($tmpName, $folder);
            if ($cloudinaryUrl) {
                $result['success']   = true;
                $result['path']      = $cloudinaryUrl;
                $result['converted'] = true;
                return $result;
            }
            $result['warning'] = 'Cloudinary upload unreachable; saved to local disk.';
        }

        // Direct storage for already-optimized WebP
        if ($isWebp || $mime === 'image/webp') {
            $filename = uniqid('img_', true) . '.webp';
            $targetPath = $destinationDir . $filename;
            if (@move_uploaded_file($tmpName, $targetPath) || @copy($tmpName, $targetPath)) {
                $result['success']   = true;
                $result['converted'] = true;
                $result['path']      = $publicPrefix . $filename;
                return $result;
            }
            $result['error'] = 'Failed to store WebP image.';
            return $result;
        }

        // Direct storage for HEIC/HEIF and BMP (original format)
        if ($isHeic || $isBmp) {
            $saveExt = $isHeic ? 'heic' : ($ext ?: 'bmp');
            $filename = uniqid('img_', true) . '.' . $saveExt;
            $targetPath = $destinationDir . $filename;
            if (@move_uploaded_file($tmpName, $targetPath) || @copy($tmpName, $targetPath)) {
                $result['success'] = true;
                $result['path']    = $publicPrefix . $filename;
                $result['warning'] = 'Stored in original format without WebP conversion.';
                return $result;
            }
            $result['error'] = 'Failed to store uploaded image.';
            return $result;
        }

        // Convert JPEG / PNG / GIF to WebP using GD if available
        $hasWebp = function_exists('imagewebp');
        if ($hasWebp) {
            $resource = null;
            if ($isJpeg && function_exists('imagecreatefromjpeg')) {
                $resource = @imagecreatefromjpeg($tmpName);
            } elseif ($isPng && function_exists('imagecreatefrompng')) {
                $resource = @imagecreatefrompng($tmpName);
            } elseif ($isGif && function_exists('imagecreatefromgif')) {
                $resource = @imagecreatefromgif($tmpName);
            }

            if ($resource) {
                if ($isPng || $isGif) {
                    if (function_exists('imagepalettetotruecolor')) {
                        @imagepalettetotruecolor($resource);
                    }
                    imagealphablending($resource, true);
                    imagesavealpha($resource, true);
                }

                $filename = uniqid('img_', true) . '.webp';
                $targetPath = $destinationDir . $filename;

                if (@imagewebp($resource, $targetPath, $quality)) {
                    imagedestroy($resource);
                    if (isset($file['tmp_name']) && is_file($file['tmp_name'])) {
                        @unlink($file['tmp_name']);
                    }
                    $result['success']   = true;
                    $result['converted'] = true;
                    $result['path']      = $publicPrefix . $filename;
                    return $result;
                }
                imagedestroy($resource);
            }
        }

        // Fallback: save raw image format locally
        $fallbackExt = $ext ?: ($isJpeg ? 'jpg' : ($isPng ? 'png' : ($isGif ? 'gif' : 'img')));
        $filename = uniqid('img_', true) . '.' . $fallbackExt;
        $targetPath = $destinationDir . $filename;
        if (@move_uploaded_file($tmpName, $targetPath) || @copy($tmpName, $targetPath)) {
            $result['success'] = true;
            $result['path']    = $publicPrefix . $filename;
            $result['warning'] = 'Stored in original format without WebP conversion.';
            return $result;
        }

        $result['error'] = 'Failed to save uploaded image to local storage.';
        return $result;
    }
}
