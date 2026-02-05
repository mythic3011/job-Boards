<?php

namespace App\Http\Controllers;

use App\Services\ProfileImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ImageController extends Controller
{
    /**
     * Serve a profile image.
     */
    public function showProfileImage(string $path)
    {
        try {
            $decodedPath = ProfileImageService::decodePath($path);

            // Security check: ensure the path is within profile-images directory
            if (!str_starts_with($decodedPath, 'profile-images/')) {
                Log::warning('Unauthorized profile image access attempt', [
                    'path' => $path,
                    'decoded_path' => $decodedPath,
                    'user_id' => Auth::id(),
                    'ip' => request()->ip(),
                ]);
                abort(404);
            }

            // Check if file exists
            if (!Storage::disk('private')->exists($decodedPath)) {
                Log::info('Profile image not found', [
                    'path' => $decodedPath,
                    'user_id' => Auth::id(),
                ]);
                abort(404);
            }

            // Get file content and MIME type
            $file = Storage::disk('private')->get($decodedPath);
            $mimeType = Storage::disk('private')->mimeType($decodedPath);
            $lastModified = Storage::disk('private')->lastModified($decodedPath);

            // Validate MIME type for security
            $allowedMimeTypes = [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif'
            ];

            if (!in_array($mimeType, $allowedMimeTypes)) {
                Log::warning('Invalid MIME type for profile image', [
                    'path' => $decodedPath,
                    'mime_type' => $mimeType,
                    'user_id' => Auth::id(),
                ]);
                abort(404);
            }

            return response($file, 200)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=3600')
                ->header('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified) . ' GMT')
                ->header('ETag', md5($file));

        } catch (\Exception $e) {
            Log::error('Profile image serving failed', [
                'path' => $path,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'ip' => request()->ip(),
            ]);

            abort(404);
        }
    }

    /**
     * Serve a public image (for future use).
     */
    public function showPublicImage(string $path)
    {
        try {
            $decodedPath = ProfileImageService::decodePath($path);

            // Security check: ensure the path is within allowed directories
            $allowedPaths = ['public-images/', 'uploads/'];
            $isAllowed = false;

            foreach ($allowedPaths as $allowedPath) {
                if (str_starts_with($decodedPath, $allowedPath)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                Log::warning('Unauthorized public image access attempt', [
                    'path' => $path,
                    'decoded_path' => $decodedPath,
                    'ip' => request()->ip(),
                ]);
                abort(404);
            }

            // Check if file exists
            if (!Storage::disk('public')->exists($decodedPath)) {
                abort(404);
            }

            // Get file content and MIME type
            $file = Storage::disk('public')->get($decodedPath);
            $mimeType = Storage::disk('public')->mimeType($decodedPath);
            $lastModified = Storage::disk('public')->lastModified($decodedPath);

            return response($file, 200)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=86400') // 24 hours for public images
                ->header('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified) . ' GMT')
                ->header('ETag', md5($file));

        } catch (\Exception $e) {
            Log::error('Public image serving failed', [
                'path' => $path,
                'error' => $e->getMessage(),
                'ip' => request()->ip(),
            ]);

            abort(404);
        }
    }
}
