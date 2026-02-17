<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\JobPosting;
use App\Services\ApplicationService;
use App\Services\AuditLogger;
use App\Services\ProfileImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * Download CV file with authorization check.
     */
    public function downloadCv(string $idcode)
    {
        $user = auth()->user();

        if (!$user) {
            abort(401);
        }

        $application = $this->findAuthorizedApplication($idcode, $user);

        // Additional policy check (defense in depth)
        if (!$user->can('downloadCv', $application)) {
            $this->logUnauthorizedAttempt($idcode, $user);
            abort(403, 'You are not authorized to download this CV.');
        }

        $this->verifyFileExists($application);

        $this->logDownload($application, $user);

        return $this->streamCvFile($application);
    }

    /**
     * Find application with proper authorization scoping.
     */
    private function findAuthorizedApplication(string $idcode, $user): Application
    {
        // OWASP A01: Object-level authorization - scope query to owner
        // Don't find() then check - scope in query itself
        $query = Application::byIdcode($idcode);

        if ($user->isCompany()) {
            // Company can only download CVs for their own jobs
            $query->forCompanyJobs($user->id);
        } elseif ($user->isIndividual()) {
            // Individual can only download their own CVs
            $query->byApplicant($user->id);
        } else {
            // No access for other user types
            $query->whereRaw('1 = 0');
        }

        return $query->firstOrFail();
    }

    /**
     * Verify CV file exists on disk.
     */
    private function verifyFileExists(Application $application): void
    {
        if (!Storage::disk('private')->exists($application->cv_file_path)) {
            Log::error('CV file not found', [
                'application_id' => $application->id,
                'file_path' => $application->cv_file_path,
            ]);

            abort(404, 'CV file not found.');
        }
    }

    /**
     * Log unauthorized download attempt.
     */
    private function logUnauthorizedAttempt(string $idcode, $user): void
    {
        Log::warning('Unauthorized CV download attempt', [
            'user_id' => $user?->id,
            'application_idcode' => $idcode,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * Log CV download for audit purposes.
     */
    private function logDownload(Application $application, $user): void
    {
        // Log download (audit log for admin downloads)
        if ($user->hasRole('admin')) {
            $this->auditLogger->logBusinessEvent(
                eventType: 'cv_download',
                request: request(),
                targetType: 'application',
                targetIdcode: $application->idcode,
                meta: [
                    'job_idcode' => $application->jobPosting->idcode,
                    'applicant_idcode' => $application->applicantUser->idcode ?? null,
                    'cv_file_size' => $application->cv_size_bytes,
                    'cv_mime' => $application->cv_mime,
                ]
            );
        }

        // Also log to regular log
        Log::info('CV downloaded', [
            'user_id' => $user->id,
            'application_id' => $application->id,
            'application_idcode' => $application->idcode,
            'job_idcode' => $application->jobPosting->idcode,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Stream CV file with proper security headers.
     */
    private function streamCvFile(Application $application)
    {
        // OWASP File Upload: Force download (never inline) to prevent content-type attacks
        // Always use attachment disposition, never inline
        $file = Storage::disk('private')->get($application->cv_file_path);

        // Sanitize filename for Content-Disposition header (prevent header injection)
        $filename = $this->sanitizeFilename($application);

        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $filename, [
            'Content-Type' => $application->cv_mime ?? 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"', // Force attachment, never inline
            'X-Content-Type-Options' => 'nosniff', // Prevent MIME sniffing attacks
            'Content-Length' => strlen($file),
        ]);
    }

    /**
     * Sanitize filename for safe use in Content-Disposition header.
     */
    private function sanitizeFilename(Application $application): string
    {
        $filename = $application->cv_original_name ?? 'cv.' . pathinfo($application->cv_file_path, PATHINFO_EXTENSION);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        return substr($filename, 0, 255);
    }

    /**
     * Store a new application (POST fallback for non-Livewire submissions).
     */
    public function store(
        Request $request,
        string $jobIdcode,
        ApplicationService $applicationService,
        ProfileImageService $profileImageService
    ) {
        $user = $request->user();

        if (!$user || !$user->isIndividual()) {
            abort(403, 'Only individual users can submit applications.');
        }

        $this->authorize('create', Application::class);

        $validated = $request->validate([
            'message' => ['nullable', 'string'],
            'profile_image' => ['nullable', 'image', 'max:2048', 'mimetypes:image/jpeg,image/png,image/webp,image/gif'],
            'cv_file' => ['required', 'file', 'max:5120', 'mimes:pdf,doc,docx', 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ]);

        $job = JobPosting::byIdcode($jobIdcode)->firstOrFail();

        if ($applicationService->hasExistingApplication($job, $user->id)) {
            return back()->withErrors(['cv_file' => 'You have already applied for this job.']);
        }

        try {
            \DB::transaction(function () use ($user, $validated, $applicationService, $profileImageService, $job, $jobIdcode) {
                $oldProfileImagePath = $user->profile_image_path;
                $newImagePath = null;

                // Store new profile image if provided
                if (!empty($validated['profile_image'])) {
                    $newImagePath = $profileImageService->storeImage($validated['profile_image']);
                    $user->update(['profile_image_path' => $newImagePath]);
                }

                try {
                    // Create application
                    $applicationService->createApplication($job, [
                        'message' => $validated['message'] ?? null,
                        'cv_file' => $validated['cv_file'],
                    ]);

                    // Delete old profile image only after successful application creation
                    if ($oldProfileImagePath && $newImagePath) {
                        $profileImageService->deleteImage($oldProfileImagePath);
                    }
                } catch (\Exception $e) {
                    // Clean up new image file if application creation fails
                    if ($newImagePath) {
                        $profileImageService->deleteImage($newImagePath);
                    }
                    throw $e;
                }
            });

            return redirect()
                ->route('jobs.show', $jobIdcode)
                ->with('message', 'Application submitted successfully!');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['cv_file' => $e->getMessage()]);
        }
    }

    /**
     * Approve an application (company owner only).
     */
    public function approve(Request $request, string $idcode, AuditLogger $auditLogger)
    {
        $application = Application::byIdcode($idcode)->firstOrFail();
        $this->authorize('approve', $application);

        if ($application->status === 'approved') {
            return back()->with('info', 'This application has already been approved.');
        }

        $validated = $request->validate([
            'decision_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldStatus = $application->status->value;

        $application->update([
            'status' => 'approved',
            'decision_message' => $validated['decision_message'] ?? null,
            'decision_message_read_at' => null,
        ]);

        $auditLogger->log('application.status_changed', [
            'application_id' => $application->id,
            'application_idcode' => $application->idcode,
            'old_status' => $oldStatus,
            'new_status' => 'approved',
            'changed_by_user_id' => auth()->id(),
            'job_id' => $application->job_id,
            'applicant_user_id' => $application->applicant_user_id,
        ]);

        return redirect()
            ->route('my.applications.index')
            ->with('success', 'Application approved successfully.');
    }

    /**
     * Reject an application (company owner only).
     */
    public function reject(Request $request, string $idcode, AuditLogger $auditLogger)
    {
        $application = Application::byIdcode($idcode)->firstOrFail();
        $this->authorize('reject', $application);

        $validated = $request->validate([
            'decision_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldStatus = $application->status->value;

        $application->update([
            'status' => 'rejected',
            'decision_message' => $validated['decision_message'] ?? null,
            'decision_message_read_at' => null,
        ]);

        $auditLogger->log('application.status_changed', [
            'application_id' => $application->id,
            'application_idcode' => $application->idcode,
            'old_status' => $oldStatus,
            'new_status' => 'rejected',
            'changed_by_user_id' => auth()->id(),
            'job_id' => $application->job_id,
            'applicant_user_id' => $application->applicant_user_id,
        ]);

        return redirect()
            ->route('my.applications.index')
            ->with('success', 'Application rejected successfully.');
    }
}
