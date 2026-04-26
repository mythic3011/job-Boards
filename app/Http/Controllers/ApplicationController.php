<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationDecisionOutcome;
use App\Exceptions\ProfileImageStoreException;
use App\Models\Application;
use App\Models\JobPosting;
use App\Services\ApplicationService;
use App\Services\AuditLogger;
use App\Services\ProfileImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Download CV file with authorization check.
     */
    public function downloadCv(string $idcode)
    {
        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        $application = $this->findAuthorizedApplication($idcode, $user);

        // Additional policy check (defense in depth)
        if (! $user->can('downloadCv', $application)) {
            $this->logApplicationAuthorizationDenied(
                application: $application,
                user: $user,
                policy: 'downloadCv',
                eventType: 'audit.application.download_cv.denied',
                request: request(),
            );
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
        } elseif ($user->isAdmin()) {
            // Allow lookup for admins so policy + audit can produce an explicit denied event.
            // Authorization remains enforced by the subsequent policy check.
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
        if (! Storage::disk('private')->exists($application->cv_file_path)) {
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
    private function logApplicationAuthorizationDenied(
        Application $application,
        $user,
        string $policy,
        string $eventType,
        Request $request,
    ): void {
        $this->auditLogger->logRequestEvent(
            eventType: $eventType,
            request: $request,
            statusCode: 403,
            targetType: 'application',
            targetIdcode: $application->idcode,
            meta: [
                'policy' => $policy,
            ],
            actorUserId: $user?->id,
            actorType: $user ? 'user' : 'guest',
        );

        Log::warning('Unauthorized CV download attempt', [
            'user_id' => $user?->id,
            'application_idcode' => $application->idcode,
            'policy' => $policy,
            'ip' => $request->ip(),
        ]);
    }

    /**
     * Log CV download for audit purposes.
     */
    private function logDownload(Application $application, $user): void
    {
        // Log download (audit log for admin downloads)
        if ($user->isAdmin()) {
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
        $disk = Storage::disk('private');
        $stream = $disk->readStream($application->cv_file_path);

        if ($stream === false) {
            Log::error('CV file stream could not be opened', [
                'application_id' => $application->id,
                'file_path' => $application->cv_file_path,
            ]);

            abort(404, 'CV file not found.');
        }

        // Sanitize filename for Content-Disposition header (prevent header injection)
        $filename = $this->sanitizeFilename($application);
        $contentLength = $disk->size($application->cv_file_path);

        return response()->streamDownload(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $filename, [
            'Content-Type' => $application->cv_mime ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff', // Prevent MIME sniffing attacks
            'Content-Length' => (string) $contentLength,
        ]);
    }

    /**
     * Sanitize filename for safe use in Content-Disposition header.
     */
    private function sanitizeFilename(Application $application): string
    {
        $filename = $application->cv_original_name ?? 'cv.'.pathinfo($application->cv_file_path, PATHINFO_EXTENSION);
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

        if (! $user || ! $user->isIndividual()) {
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
            \DB::transaction(function () use ($user, $validated, $applicationService, $profileImageService, $job) {
                $oldProfileImagePath = $user->profile_image_path;
                $newImagePath = null;

                // Store new profile image if provided
                if (! empty($validated['profile_image'])) {
                    try {
                        $newImagePath = $profileImageService->storeImage($validated['profile_image']);
                    } catch (\InvalidArgumentException $e) {
                        throw new ProfileImageStoreException($e->getMessage(), 0, $e);
                    }
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
        } catch (ProfileImageStoreException $e) {
            return back()->withErrors(['profile_image' => $e->getMessage()]);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['cv_file' => $e->getMessage()]);
        }
    }

    /**
     * Approve an application (company owner only).
     */
    public function approve(Request $request, string $idcode, ApplicationService $applicationService)
    {
        $application = Application::byIdcode($idcode)->firstOrFail();
        if (! $request->user()?->can('approve', $application)) {
            $this->logApplicationAuthorizationDenied(
                application: $application,
                user: $request->user(),
                policy: 'approve',
                eventType: 'audit.application.approve.denied',
                request: $request,
            );
            abort(403, 'You are not authorized to approve this application.');
        }

        $validated = $request->validate([
            'decision_message' => ['nullable', 'string', 'max:2000'],
            'job_idcode' => ['nullable', 'string'],
        ]);

        $scopedParams = $request->filled('job_idcode')
            ? ['jobIdcode' => $request->string('job_idcode')->toString()]
            : [];

        return match ($applicationService->approve($application, $validated['decision_message'] ?? null)) {
            ApplicationDecisionOutcome::UPDATED => redirect()
                ->route('my.applications.index', $scopedParams)
                ->with('success', 'Application approved successfully.'),
            ApplicationDecisionOutcome::NOOP_ALREADY_TARGET => back()
                ->with('info', 'This application has already been approved.'),
            ApplicationDecisionOutcome::INVALID_TRANSITION => back()
                ->withErrors(['application' => 'This application could not be moved to the approved state.']),
        };
    }

    /**
     * Reject an application (company owner only).
     */
    public function reject(Request $request, string $idcode, ApplicationService $applicationService)
    {
        $application = Application::byIdcode($idcode)->firstOrFail();
        if (! $request->user()?->can('reject', $application)) {
            $this->logApplicationAuthorizationDenied(
                application: $application,
                user: $request->user(),
                policy: 'reject',
                eventType: 'audit.application.reject.denied',
                request: $request,
            );
            abort(403, 'You are not authorized to reject this application.');
        }

        $validated = $request->validate([
            'decision_message' => ['nullable', 'string', 'max:2000'],
            'job_idcode' => ['nullable', 'string'],
        ]);

        $scopedParams = $request->filled('job_idcode')
            ? ['jobIdcode' => $request->string('job_idcode')->toString()]
            : [];

        return match ($applicationService->reject($application, $validated['decision_message'] ?? null)) {
            ApplicationDecisionOutcome::UPDATED => redirect()
                ->route('my.applications.index', $scopedParams)
                ->with('success', 'Application rejected successfully.'),
            ApplicationDecisionOutcome::NOOP_ALREADY_TARGET => back()
                ->with('info', 'This application has already been rejected.'),
            ApplicationDecisionOutcome::INVALID_TRANSITION => back()
                ->withErrors(['application' => 'This application could not be moved to the rejected state.']),
        };
    }
}
