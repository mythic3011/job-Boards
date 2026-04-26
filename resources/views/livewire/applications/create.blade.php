<?php

use App\Models\JobPosting;
use App\Services\ApplicationService;
use App\Services\CvFileService;
use App\Services\ProfileImageService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Apply');

new class extends Component
{
    use WithFileUploads;

    public string $jobIdcode;

    #[Validate('nullable|string')]
    public ?string $message = null;

    #[Validate('nullable|image|max:2048|mimetypes:image/jpeg,image/png,image/webp,image/gif')]
    public $profile_image = null;

    #[Validate('required|file|max:5120|mimes:pdf,doc,docx|mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document')]
    public $cv_file = null;

    public ?string $profileImageNotice = null;

    public function mount(string $jobIdcode, ApplicationService $applicationService): void
    {
        $this->jobIdcode = $jobIdcode;

        $this->authorize('create', \App\Models\Application::class);

        // OWASP A01: Job lookup is public (no scoping needed)
        $job = JobPosting::byIdcode($jobIdcode)->firstOrFail();

        // OWASP A01: Scope application check to current user
        if ($applicationService->hasExistingApplication($job)) {
            session()->flash('error', 'You have already applied for this job.');
            redirect()->route('jobs.show', $jobIdcode);
        }
    }

    public function with(ProfileImageService $profileImageService): array
    {
        $user = Auth::user();

        return [
            'profileImageUrl' => $user?->profile_image_path
                ? $profileImageService->getImageUrl($user->profile_image_path)
                : null,
            'userName' => $user?->nickname,
        ];
    }

    public function updatedProfileImage(): void
    {
        $this->profileImageNotice = null;
        $this->validateOnly('profile_image');
        $this->profileImageNotice = 'Photo ready. It will be saved when you submit.';
        $this->dispatch('profile-image-ready');
    }

    public function submit(ApplicationService $applicationService, ProfileImageService $profileImageService): mixed
    {
        if (!$this->cv_file) {
            $this->addError('cv_file', 'Please upload your CV/Resume before submitting.');
            return null;
        }

        $this->validate();

        // OWASP A01: Job lookup is public
        $job = JobPosting::byIdcode($this->jobIdcode)->firstOrFail();

        // OWASP A01: Scope to current user
        if ($applicationService->hasExistingApplication($job)) {
            $this->addError('cv_file', 'You have already applied for this job.');
            return null;
        }

        try {
            \DB::transaction(function () use ($applicationService, $profileImageService, $job) {
                $user = Auth::user();
                $oldProfileImagePath = $user->profile_image_path;
                $newImagePath = null;

                // Store new profile image if provided
                if ($this->profile_image) {
                    try {
                        $newImagePath = $profileImageService->storeImage($this->profile_image);
                    } catch (\InvalidArgumentException $e) {
                        throw new \App\Exceptions\ProfileImageStoreException($e->getMessage(), 0, $e);
                    }
                    $user->update(['profile_image_path' => $newImagePath]);
                }

                try {
                    // Create application
                    $applicationService->createApplication($job, [
                        'message' => $this->message,
                        'cv_file' => $this->cv_file,
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

            $successMessage = 'Application submitted successfully!';
            if ($this->profile_image) {
                $successMessage .= ' Profile photo updated successfully.';
            }

            session()->flash('message', $successMessage);

            return redirect()->route('jobs.show', $this->jobIdcode);
        } catch (\App\Exceptions\ProfileImageStoreException $e) {
            $this->addError('profile_image', $e->getMessage());
            return null;
        } catch (\InvalidArgumentException $e) {
            $this->addError('cv_file', $e->getMessage());
            return null;
        }
    }
}; ?>
<div x-on:profile-image-ready.window="window.toast && window.toast.info('Photo ready - will be saved on submit')">
    <div class="max-w-4xl mx-auto">
        {{-- Breadcrumb --}}
        <nav class="theme-text-muted mb-4 flex items-center gap-2 text-sm">
            <a href="{{ route('jobs.index') }}" class="theme-link transition-colors">Jobs</a>
            <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="theme-text-strong font-medium">Apply</span>
        </nav>

        <div class="mb-6">
            <h1 class="theme-text-strong text-2xl font-bold">Apply for Job</h1>
            <p class="theme-text-muted mt-1 text-sm">Fill in the details below to submit your application.</p>
        </div>

        <x-ui.card padding="p-8">
            <form wire:submit.prevent="submit" method="POST" action="{{ route('applications.store', $jobIdcode) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <x-ui.textarea
                    label="Cover Message (Optional)"
                    name="message"
                    wire:model="message"
                    rows="6"
                    placeholder="Tell the employer why you're a good fit for this position..."
                />

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="space-y-3">
                        <x-ui.form-label for="profile_image">Update Profile Photo</x-ui.form-label>
                        <p class="theme-text-muted text-xs">Optional. If you upload a photo here, your account avatar is updated across profile and application views after submit.</p>
                        <div class="theme-panel-subtle min-h-[320px] rounded-2xl border p-6 md:p-7">
                            <div class="flex flex-col items-center gap-4 text-center">
                                <x-ui.avatar
                                    :src="$profile_image ? $profile_image->temporaryUrl() : $profileImageUrl"
                                    :name="$userName"
                                    size="2xl"
                                    class="border-2 border-[var(--app-panel-border)] bg-[var(--app-panel-bg)]"
                                />
                                <label for="profile_image" class="theme-button theme-button-primary inline-flex cursor-pointer items-center justify-center rounded-lg border px-5 py-2.5 text-sm font-medium">
                                    Upload photo
                                </label>
                                <input
                                    id="profile_image"
                                    name="profile_image"
                                    type="file"
                                    wire:model="profile_image"
                                    accept="image/*"
                                    class="sr-only"
                                >
                                <p class="theme-text-muted text-xs">JPG, PNG, WebP or GIF, up to 2MB</p>
                                @if($profileImageNotice)
                                    <x-ui.alert type="info" class="mt-2">
                                        {{ $profileImageNotice }}
                                    </x-ui.alert>
                                @endif
                                <x-ui.form-error name="profile_image" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <x-ui.file-upload
                            label="CV/Resume"
                            name="cv_file"
                            wire:model="cv_file"
                            accept=".pdf,.doc,.docx"
                            help="Accepted formats: PDF, DOC, DOCX. Uploading a CV does not change your profile photo."
                            maxSize="5MB"
                            required
                        />
                        <x-ui.form-error name="cv_file" />
                    </div>
                </div>

                <div class="flex gap-4">
                    <x-ui.button
                        type="submit"
                        variant="primary"
                        size="lg"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-60 cursor-not-allowed"
                        wire:target="submit"
                    >
                        <span wire:loading.remove wire:target="submit">Submit Application</span>
                        <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 12 6.477 12 12h-4z"></path>
                            </svg>
                            Submitting...
                        </span>
                    </x-ui.button>
                    <x-ui.button href="{{ route('jobs.show', $jobIdcode) }}" variant="outline" size="lg">
                        Cancel
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
