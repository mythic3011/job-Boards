<?php

namespace App\Http\Controllers;

use App\Services\ProfileImageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    /**
     * Serve a profile image.
     */
    public function showProfileImage(string $path)
    {
        return $this->serveFile(
            disk: 'private',
            encodedPath: $path,
            allowedPrefixes: ['profile-images/'],
            cacheControl: 'public, max-age=3600',
            enforceImageMimeAllowlist: true,
            notFoundLogContext: ['user_id' => Auth::id()],
        );
    }

    /**
     * Serve a public image (for future use).
     */
    public function showPublicImage(string $path)
    {
        return $this->serveFile(
            disk: 'public',
            encodedPath: $path,
            allowedPrefixes: ['public-images/', 'uploads/'],
            cacheControl: 'public, max-age=86400',
            enforceImageMimeAllowlist: false,
        );
    }

    private function serveFile(
        string $disk,
        string $encodedPath,
        array $allowedPrefixes,
        string $cacheControl,
        bool $enforceImageMimeAllowlist,
        array $notFoundLogContext = [],
    ) {
        try {
            $normalizedPath = $this->normalizeEncodedPath($encodedPath);

            $isAllowed = false;
            foreach ($allowedPrefixes as $allowedPrefix) {
                if (str_starts_with($normalizedPath, $allowedPrefix)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (! $isAllowed) {
                Log::warning('Unauthorized image access attempt', [
                    'path' => $encodedPath,
                    'normalized_path' => $normalizedPath,
                    'disk' => $disk,
                    'ip' => request()->ip(),
                ]);
                abort(404);
            }

            if (! Storage::disk($disk)->exists($normalizedPath)) {
                Log::info('Image not found', [
                    'path' => $normalizedPath,
                    'disk' => $disk,
                    ...$notFoundLogContext,
                ]);
                abort(404);
            }

            $file = Storage::disk($disk)->get($normalizedPath);
            $mimeType = Storage::disk($disk)->mimeType($normalizedPath) ?: 'application/octet-stream';
            $lastModified = Storage::disk($disk)->lastModified($normalizedPath);

            if ($enforceImageMimeAllowlist && ! in_array($mimeType, ProfileImageService::ALLOWED_MIME_TYPES, true)) {
                Log::warning('Invalid MIME type for image response', [
                    'path' => $normalizedPath,
                    'disk' => $disk,
                    'mime_type' => $mimeType,
                    'user_id' => Auth::id(),
                ]);
                abort(404);
            }

            return response($file, 200)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', $cacheControl)
                ->header('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified) . ' GMT')
                ->header('ETag', md5($file))
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (\Throwable $e) {
            Log::error('Image serving failed', [
                'path' => $encodedPath,
                'error' => $e->getMessage(),
                'disk' => $disk,
                'user_id' => Auth::id(),
                'ip' => request()->ip(),
            ]);

            abort(404);
        }
    }

    private function normalizeEncodedPath(string $encodedPath): string
    {
        $base64 = strtr($encodedPath, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $decodedPath = base64_decode($base64, true);
        if ($decodedPath === false || $decodedPath === '') {
            throw new \InvalidArgumentException('Invalid encoded image path.');
        }

        if (str_contains($decodedPath, "\0")) {
            throw new \InvalidArgumentException('Image path contains null bytes.');
        }

        $normalizedPath = str_replace('\\', '/', $decodedPath);
        $normalizedPath = preg_replace('#/+#', '/', $normalizedPath) ?? $normalizedPath;

        if ($normalizedPath === '' || str_starts_with($normalizedPath, '/')) {
            throw new \InvalidArgumentException('Image path must be relative.');
        }

        foreach (explode('/', $normalizedPath) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Image path traversal detected.');
            }
        }

        return $normalizedPath;
    }
}
