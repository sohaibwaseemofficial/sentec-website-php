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
        $cloudinaryUrl = env('CLOUDINARY_URL') ?: getenv('CLOUDINARY_URL');
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

        $cloudName = env('CLOUDINARY_CLOUD_NAME') ?: getenv('CLOUDINARY_CLOUD_NAME');
        $apiKey    = env('CLOUDINARY_API_KEY') ?: getenv('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET') ?: getenv('CLOUDINARY_API_SECRET');

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

        $apiUrl = "https://api.cloudinary.com/v1_1/" . rawurlencode($config['cloud_name']) . "/image/upload";

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
     * Store image in Cloudinary if configured, otherwise convert to WebP and save locally.
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

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $result['error'] = 'Upload failed or no file provided.';
            return $result;
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $result['error'] = 'No valid upload found.';
            return $result;
        }

        $info = @getimagesize($file['tmp_name']);
        if (!$info || empty($info['mime'])) {
            $result['error'] = 'Unsupported or unreadable image file.';
            return $result;
        }

        $mime = strtolower($info['mime']);
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            $result['error'] = 'Unsupported image format.';
            return $result;
        }

        // =========================================================
        // 1. CLOUDINARY UPLOAD (PRIORITIZED IF CONFIGURED)
        // =========================================================
        $cloudinaryConfig = get_cloudinary_config();
        if ($cloudinaryConfig) {
            // Determine a clean folder in Cloudinary
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
            }

            $cloudinaryUrl = upload_to_cloudinary($file['tmp_name'], $folder);
            if ($cloudinaryUrl) {
                $result['success']   = true;
                $result['path']      = $cloudinaryUrl;
                $result['converted'] = true;
                return $result;
            }
            // If Cloudinary failed, log and gracefully fall back to local disk storage
            $result['warning'] = 'Cloudinary upload unreachable; saved to local disk.';
        }

        // =========================================================
        // 2. LOCAL DISK STORAGE (FALLBACK OR DEFAULT)
        // =========================================================
        $destinationDir = rtrim(str_replace('\\', '/', $destinationDir), '/') . '/';
        $publicPrefix   = $publicPrefix !== '' ? (rtrim(str_replace('\\', '/', $publicPrefix), '/') . '/') : '';

        if (!is_dir($destinationDir)) {
            if (!mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
                $result['error'] = 'Unable to prepare upload directory.';
                return $result;
            }
        }

        $hasWebp = function_exists('imagewebp');

        if ($mime === 'image/webp') {
            $filename = uniqid('img_', true) . '.webp';
            $targetPath = $destinationDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $result['success']   = true;
                $result['converted'] = true;
                $result['path']      = $publicPrefix . $filename;
                return $result;
            }

            $result['error'] = 'Failed to store WebP image.';
            return $result;
        }

        if (!$hasWebp) {
            $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
            if ($ext === '') {
                $ext = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/gif'  => 'gif',
                ][$mime] ?? 'img';
            }

            $filename = uniqid('img_', true) . '.' . $ext;
            $targetPath = $destinationDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $result['success'] = true;
                $result['path']    = $publicPrefix . $filename;
                $result['warning'] = 'WebP conversion unavailable; stored original format.';
                return $result;
            }

            $result['error'] = 'Failed to store uploaded image.';
            return $result;
        }

        $resource = null;
        switch ($mime) {
            case 'image/jpeg':
                if (function_exists('imagecreatefromjpeg')) {
                    $resource = @imagecreatefromjpeg($file['tmp_name']);
                }
                break;
            case 'image/png':
                if (function_exists('imagecreatefrompng')) {
                    $resource = @imagecreatefrompng($file['tmp_name']);
                }
                break;
            case 'image/gif':
                if (function_exists('imagecreatefromgif')) {
                    $resource = @imagecreatefromgif($file['tmp_name']);
                }
                break;
        }

        if (!$resource) {
            $result['error'] = 'Failed to read uploaded image.';
            return $result;
        }

        if ($mime === 'image/png' || $mime === 'image/gif') {
            if (function_exists('imagepalettetotruecolor')) {
                @imagepalettetotruecolor($resource);
            }
            imagealphablending($resource, true);
            imagesavealpha($resource, true);
        }

        $filename = uniqid('img_', true) . '.webp';
        $targetPath = $destinationDir . $filename;

        if (!imagewebp($resource, $targetPath, $quality)) {
            imagedestroy($resource);

            $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
            if ($ext === '') {
                $ext = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/gif'  => 'gif',
                ][$mime] ?? 'img';
            }

            $fallbackPath = $destinationDir . uniqid('img_', true) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $fallbackPath)) {
                $result['success'] = true;
                $result['path']    = $publicPrefix . basename($fallbackPath);
                $result['warning'] = 'WebP conversion failed; stored original format.';
                return $result;
            }

            $result['error'] = 'Failed to convert and store image.';
            return $result;
        }

        imagedestroy($resource);
        if (isset($file['tmp_name']) && is_file($file['tmp_name'])) {
            @unlink($file['tmp_name']);
        }

        $result['success']   = true;
        $result['converted'] = true;
        $result['path']      = $publicPrefix . $filename;
        return $result;
    }
}
