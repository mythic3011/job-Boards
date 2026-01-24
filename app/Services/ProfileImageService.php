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
     */
    public function storeImage(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid()->toString() . '.' . $extension;

        return $file->storeAs(self::STORAGE_PATH, $filename, self::STORAGE_DISK);
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
}
