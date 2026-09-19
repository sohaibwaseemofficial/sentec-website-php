<?php
if (!function_exists('save_image_as_webp')) {
    /**
     * Convert an uploaded image to WebP and store it on disk.
     * Returns an array with success status, stored path, and optional warnings.
     */
    function save_image_as_webp(array $file, string $destinationDir, string $publicPrefix = '', int $quality = 82): array
    {
        $result = [
            'success' => false,
            'path' => null,
            'error' => null,
            'warning' => null,
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

        if (!is_dir($destinationDir)) {
            if (!mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
                $result['error'] = 'Unable to prepare upload directory.';
                return $result;
            }
        }

        $destinationDir = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

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

        $hasWebp = function_exists('imagewebp');

        if ($mime === 'image/webp') {
            $filename = uniqid('img_', true) . '.webp';
            $targetPath = $destinationDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $result['success'] = true;
                $result['converted'] = true;
                $result['path'] = $publicPrefix . $filename;
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
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                ][$mime] ?? 'img';
            }

            $filename = uniqid('img_', true) . '.' . $ext;
            $targetPath = $destinationDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $result['success'] = true;
                $result['path'] = $publicPrefix . $filename;
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
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                ][$mime] ?? 'img';
            }

            $fallbackPath = $destinationDir . uniqid('img_', true) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $fallbackPath)) {
                $result['success'] = true;
                $result['path'] = $publicPrefix . basename($fallbackPath);
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

        $result['success'] = true;
        $result['converted'] = true;
        $result['path'] = $publicPrefix . $filename;
        return $result;
    }
}
