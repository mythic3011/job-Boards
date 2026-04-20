<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CvFileService
{
    /**
     * Allowed file extensions for CV uploads.
     */
    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx'];

    /**
     * Allowed MIME types for CV uploads.
     */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * Maximum file size in bytes (5MB).
     */
    public const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /**
     * Storage disk for CV files.
     */
    private const STORAGE_DISK = 'private';

    /**
     * Storage path for CV files.
     */
    private const STORAGE_PATH = 'cvs';

    /**
     * Validate CV file according to OWASP File Upload Cheat Sheet guidelines.
     *
     * @return array{valid: bool, error?: string}
     */
    public function validateFile(UploadedFile $file): array
    {
        $originalName = $file->getClientOriginalName();
        $mime = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        // 1. Whitelist validation - allowed extensions
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return [
                'valid' => false,
                'error' => 'Invalid file extension. Only PDF, DOC, and DOCX files are allowed.',
            ];
        }

        // 2. Double-extension detection - allow benign multi-dot filenames, but
        // reject names where the segment before the final allowed extension is
        // itself a dangerous executable/code extension (for example:
        // "resume.php.pdf" or "resume.phtml.docx").
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        if (str_contains($basename, '.')) {
            $penultimate = strtolower(substr($basename, strrpos($basename, '.') + 1));
            $dangerous = ['php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm', 'cgi', 'pl'];

            if (in_array($penultimate, $dangerous, true)) {
                return [
                    'valid' => false,
                    'error' => 'Invalid filename. Double extensions are not allowed.',
                ];
            }
        }

        // 3. Validate MIME type (don't trust Content-Type header alone)
        if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            return [
                'valid' => false,
                'error' => 'Invalid file type. Only PDF, DOC, and DOCX files are allowed.',
            ];
        }

        // 4. Size validation
        $size = $file->getSize();
        if ($size > self::MAX_FILE_SIZE) {
            return [
                'valid' => false,
                'error' => 'File size exceeds maximum allowed size of 5MB.',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Store CV file with system-generated filename (OWASP: prevent path traversal).
     *
     * @return array{path: string, filename: string, sha256: string}
     */
    public function storeFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs(self::STORAGE_PATH, $filename, self::STORAGE_DISK);

        $content = Storage::disk(self::STORAGE_DISK)->get($path);
        $sha256 = hash('sha256', $content);

        return [
            'path' => $path,
            'filename' => $filename,
            'sha256' => $sha256,
        ];
    }

    /**
     * Get file metadata for storage.
     *
     * @return array{original_name: string, mime: string, size_bytes: int}
     */
    public function getFileMetadata(UploadedFile $file): array
    {
        return [
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ];
    }
}
