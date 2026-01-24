<?php

use App\Models\JobPosting;
use App\Services\ApplicationService;
use App\Services\CvFileService;
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

    public function submit(ApplicationService $applicationService): mixed
    {
        $this->validate();

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

            session()->flash('message', 'Application submitted successfully!');

            return redirect()->route('jobs.show', $this->jobIdcode);
        } catch (\InvalidArgumentException $e) {
            $this->addError('cv_file', $e->getMessage());
            return null;
        }
    }
}; ?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold mb-6">Apply for Job</h1>

    <x-ui.card padding="p-8">
        <form wire:submit="submit" class="space-y-6">
            <x-ui.textarea
                label="Cover Message (Optional)"
                name="message"
                wire:model="message"
                rows="6"
                placeholder="Tell the employer why you're a good fit for this position..."
            />

            <x-ui.file-input
                label="CV/Resume"
                name="cv_file"
                wire:model="cv_file"
                accept=".pdf,.doc,.docx"
                help="Accepted formats: PDF, DOC, DOCX"
                maxSize="5MB"
                required
            />

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
