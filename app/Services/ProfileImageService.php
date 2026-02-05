<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileImageService
{
    /**
     * Allowed MIME types for profile images.
     */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /**
     * Maximum file size in bytes (2MB).
     */
    public const MAX_FILE_SIZE = 2 * 1024 * 1024;

    /**
     * Storage disk for profile images.
     */
    private const STORAGE_DISK = 'private';

    /**
     * Storage path for profile images.
     */
    private const STORAGE_PATH = 'profile-images';

    /**
     * Store profile image with system-generated filename.
     *
     * @return string The stored file path
     * @throws \InvalidArgumentException If the file is not a valid image
     */
    public function storeImage(UploadedFile $file): string
    {
        // Validate file before storing
        $this->validateImageFile($file);

        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid()->toString() . '.' . $extension;

        return $file->storeAs(self::STORAGE_PATH, $filename, self::STORAGE_DISK);
    }

    /**
     * Validate uploaded image file for security.
     *
     * @throws \InvalidArgumentException If validation fails
     */
    private function validateImageFile(UploadedFile $file): void
    {
        // Check MIME type
        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            throw new \InvalidArgumentException('We couldn\'t use this file. It may not be a valid image or could contain unsafe content. Try another JPG, PNG, WebP or GIF under 2MB.');
        }

        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('We couldn\'t use this file. It may not be a valid image or could contain unsafe content. Try another JPG, PNG, WebP or GIF under 2MB.');
        }

        // Validate image using getimagesize for additional security
        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('We couldn\'t use this file. It may not be a valid image or could contain unsafe content. Try another JPG, PNG, WebP or GIF under 2MB.');
        }

        // Validate image dimensions (reasonable limits)
        [$width, $height] = $imageInfo;
        if ($width > 4000 || $height > 4000) {
            throw new \InvalidArgumentException('We couldn\'t use this file. It may not be a valid image or could contain unsafe content. Try another JPG, PNG, WebP or GIF under 2MB.');
        }

        if ($width < 10 || $height < 10) {
            throw new \InvalidArgumentException('We couldn\'t use this file. It may not be a valid image or could contain unsafe content. Try another JPG, PNG, WebP or GIF under 2MB.');
        }

        // Validate MIME type matches actual image type
        $allowedImageTypes = [
            IMAGETYPE_JPEG => 'image/jpeg',
            IMAGETYPE_PNG => 'image/png',
            IMAGETYPE_WEBP => 'image/webp',
            IMAGETYPE_GIF => 'image/gif',
        ];

        if (!isset($allowedImageTypes[$imageInfo[2]])) {
            throw new \InvalidArgumentException('We couldn\'t use this file. It may not be a valid image or could contain unsafe content. Try another JPG, PNG, WebP or GIF under 2MB.');
        }

        $detectedMimeType = $allowedImageTypes[$imageInfo[2]];
        if ($file->getMimeType() !== $detectedMimeType) {
            throw new \InvalidArgumentException('We couldn\'t use this file. It may not be a valid image or could contain unsafe content. Try another JPG, PNG, WebP or GIF under 2MB.');
        }

        // Additional security: Check for embedded PHP code or suspicious content
        $this->scanForMaliciousContent($file);
    }

    /**
     * Scan file for potentially malicious content.
     *
     * @throws \InvalidArgumentException If malicious content is detected
     */
    private function scanForMaliciousContent(UploadedFile $file): void
    {
        $content = file_get_contents($file->getPathname());

        // Only check for obvious PHP execution tags (be less strict)
        $phpPatterns = [
            '/<\?php/i',
            '/<\?=/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
        ];

        foreach ($phpPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new \InvalidArgumentException('We couldn\'t use this file. It may not be a valid image or could contain unsafe content. Try another JPG, PNG, WebP or GIF under 2MB.');
            }
        }
    }

    /**
     * Delete profile image if it exists.
     */
    public function deleteImage(?string $path): void
    {
        if ($path && Storage::disk(self::STORAGE_DISK)->exists($path)) {
            Storage::disk(self::STORAGE_DISK)->delete($path);
        }
    }

    /**
     * Get the URL for a profile image.
     */
    public function getImageUrl(string $path): string
    {
        // For private storage, we use a dedicated image controller route
        return route('images.profile', ['path' => self::encodePath($path)]);
    }

    /**
     * Encode a storage path for URL-safe transport.
     */
    public static function encodePath(string $path): string
    {
        return rtrim(strtr(base64_encode($path), '+/', '-_'), '=');
    }

    /**
     * Decode a URL-safe encoded storage path.
     */
    public static function decodePath(string $encodedPath): string
    {
        $base64 = strtr($encodedPath, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($base64, true) ?: '';
    }
}
