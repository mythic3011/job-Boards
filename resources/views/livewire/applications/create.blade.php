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

    public function mount(string $jobIdcode, ApplicationService $applicationService): void
    {
        $this->jobIdcode = $jobIdcode;

        if (!Auth::check() || !Auth::user()->isIndividual()) {
            abort(403, 'Only individual users can submit applications.');
        }

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

    public function submit(ApplicationService $applicationService, ProfileImageService $profileImageService): mixed
    {
        $this->validate();

        $oldProfileImagePath = null;
        if ($this->profile_image) {
            try {
                $user = Auth::user();
                $oldProfileImagePath = $user->profile_image_path;
                $path = $profileImageService->storeImage($this->profile_image);
                $user->update(['profile_image_path' => $path]);
            } catch (\InvalidArgumentException $e) {
                $this->addError('profile_image', $e->getMessage());
                return null;
            }
        }

        // OWASP A01: Job lookup is public
        $job = JobPosting::byIdcode($this->jobIdcode)->firstOrFail();

        // OWASP A01: Scope to current user
        if ($applicationService->hasExistingApplication($job)) {
            $this->addError('cv_file', 'You have already applied for this job.');
            return null;
        }

        try {
            $applicationService->createApplication($job, [
                'message' => $this->message,
                'cv_file' => $this->cv_file,
            ]);

            // Delete old profile image only after successful application creation
            if ($oldProfileImagePath) {
                $profileImageService->deleteImage($oldProfileImagePath);
            }

            session()->flash('message', 'Application submitted successfully!');

            return redirect()->route('jobs.show', $this->jobIdcode);
        } catch (\InvalidArgumentException $e) {
            // Rollback profile image update if application creation fails
            if ($this->profile_image && $oldProfileImagePath) {
                $user = Auth::user();
                $profileImageService->deleteImage($user->profile_image_path);
                $user->update(['profile_image_path' => $oldProfileImagePath]);
            }
            $this->addError('cv_file', $e->getMessage());
            return null;
        }
    }
}; ?>
<div>
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Apply for Job</h1>

        <x-ui.card padding="p-8">
            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    Please fix the highlighted fields and try again.
                </div>
            @endif

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
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-6 md:p-7 min-h-[320px]">
                            <div class="flex flex-col items-center gap-4 text-center">
                                <x-ui.avatar
                                    :src="$profile_image ? $profile_image->temporaryUrl() : $profileImageUrl"
                                    :name="$userName"
                                    size="2xl"
                                    class="border-2 border-gray-200"
                                />
                                <label for="profile_image" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition-colors cursor-pointer">
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
                                <p class="text-xs text-gray-500">JPG, PNG, WebP or GIF, up to 2MB</p>
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
                            help="Accepted formats: PDF, DOC, DOCX"
                            maxSize="5MB"
                            required
                        />
                    </div>
                </div>

                <div class="flex gap-4">
                    <x-ui.button type="submit" variant="primary" size="lg">
                        Submit Application
                    </x-ui.button>
                    <x-ui.button href="{{ route('jobs.show', $jobIdcode) }}" variant="outline" size="lg">
                        Cancel
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>