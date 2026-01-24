<?php

namespace App\Services;

use App\Models\Application;
use App\Models\JobPosting;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApplicationService
{
    public function __construct(
        private readonly CvFileService $cvFileService,
        private readonly Guard $auth,
        private readonly Request $request
    ) {
    }

    /**
     * Check if user has already applied for a job.
     */
    public function hasExistingApplication(JobPosting $job, ?int $userId = null): bool
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

    /**
     * Log application submission for audit purposes.
     */
    private function logApplicationSubmission(Application $application, JobPosting $job): void
    {
        Log::info('Application submitted', [
            'user_id' => $this->auth->id(),
            'application_id' => $application->id,
            'application_idcode' => $application->idcode,
            'job_id' => $job->id,
            'job_idcode' => $job->idcode,
            'ip' => $this->request->ip(),
        ]);
    }
}
