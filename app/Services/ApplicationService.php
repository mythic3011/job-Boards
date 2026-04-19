<?php

namespace App\Services;

use App\Enums\ApplicationDecisionOutcome;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\JobPosting;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplicationService
{
    public function __construct(
        private readonly CvFileService $cvFileService,
        private readonly Guard $auth,
        private readonly Request $request,
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * Check if user has already applied for a job.
     */
    public function hasExistingApplication(JobPosting $job, ?string $userId = null): bool
    {
        $userId = $userId ?? $this->auth->id();

        return Application::forJob($job->id)
            ->byApplicant($userId)
            ->exists();
    }

    /**
     * Create a new application.
     *
     * @param  array{message?: string|null, cv_file: \Illuminate\Http\UploadedFile}  $data
     */
    public function createApplication(JobPosting $job, array $data): Application
    {
        // Validate file
        $validation = $this->cvFileService->validateFile($data['cv_file']);
        if (!$validation['valid']) {
            throw new \InvalidArgumentException($validation['error']);
        }

        // Store file
        $fileData = $this->cvFileService->storeFile($data['cv_file']);
        $metadata = $this->cvFileService->getFileMetadata($data['cv_file']);

        // Create application
        $application = Application::create([
            'job_id' => $job->id,
            'applicant_user_id' => $this->auth->id(),
            'message' => $data['message'] ?? null,
            'cv_file_path' => $fileData['path'],
            'cv_original_name' => $metadata['original_name'],
            'cv_mime' => $metadata['mime'],
            'cv_size_bytes' => $metadata['size_bytes'],
            'cv_sha256' => $fileData['sha256'],
        ]);

        $this->logApplicationSubmission($application, $job);

        return $application;
    }

    public function approve(Application $application, ?string $decisionMessage): ApplicationDecisionOutcome
    {
        return $this->transitionDecision($application, ApplicationStatus::APPROVED, $decisionMessage);
    }

    public function reject(Application $application, ?string $decisionMessage): ApplicationDecisionOutcome
    {
        return $this->transitionDecision($application, ApplicationStatus::REJECTED, $decisionMessage);
    }

    /**
     * Log application submission for audit purposes.
     */
    private function logApplicationSubmission(Application $application, JobPosting $job): void
    {
        $user = $this->auth->user();

        $this->auditLogger->logBusinessEvent(
            eventType: 'application.submitted',
            request: $this->request,
            targetType: 'application',
            targetIdcode: $application->idcode,
            meta: [
                'application_id' => $application->id,
                'application_idcode' => $application->idcode,
                'job_id' => $job->id,
                'job_idcode' => $job->idcode,
                'applicant_user_id' => $application->applicant_user_id,
                'actor_user_type' => $user?->user_type,
                'actor_roles' => $user ? $user->roles()->pluck('name')->values()->all() : [],
                'cv_mime' => $application->cv_mime,
                'cv_size_bytes' => $application->cv_size_bytes,
            ]
        );

        Log::info('Application submitted', [
            'user_id' => $this->auth->id(),
            'application_id' => $application->id,
            'application_idcode' => $application->idcode,
            'job_id' => $job->id,
            'job_idcode' => $job->idcode,
            'ip' => $this->request->ip(),
        ]);
    }

    /**
     * Status change and success audit logging are intentionally atomic here.
     * This assumes AuditLogger::logBusinessEvent() persists synchronously
     * inside the current transaction. If audit delivery becomes queued,
     * event-driven, async, or otherwise out-of-transaction, redesign this
     * consistency model in the same slice.
     */
    private function transitionDecision(
        Application $application,
        ApplicationStatus $targetStatus,
        ?string $decisionMessage
    ): ApplicationDecisionOutcome {
        return DB::transaction(function () use ($application, $targetStatus, $decisionMessage): ApplicationDecisionOutcome {
            $lockedApplication = Application::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $lockedApplication->status;
            if ($currentStatus === $targetStatus) {
                return ApplicationDecisionOutcome::NOOP_ALREADY_TARGET;
            }

            try {
                $lockedApplication->status = $targetStatus;
            } catch (\InvalidArgumentException) {
                return ApplicationDecisionOutcome::INVALID_TRANSITION;
            }

            $lockedApplication->decision_message = $decisionMessage;
            $lockedApplication->decision_message_read_at = null;
            $lockedApplication->save();

            $this->auditLogger->logBusinessEvent(
                eventType: 'application.status_changed',
                request: $this->request,
                targetType: 'application',
                targetIdcode: $lockedApplication->idcode,
                meta: [
                    'application_id' => $lockedApplication->id,
                    'application_idcode' => $lockedApplication->idcode,
                    'old_status' => $currentStatus->value,
                    'new_status' => $targetStatus->value,
                    'changed_by_user_id' => $this->auth->id(),
                    'job_id' => $lockedApplication->job_id,
                    'applicant_user_id' => $lockedApplication->applicant_user_id,
                ],
            );

            return ApplicationDecisionOutcome::UPDATED;
        });
    }
}
