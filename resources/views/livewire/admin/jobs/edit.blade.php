<?php

use App\Models\JobPosting;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Admin - Edit Job');

new class extends Component
{
    public string $idcode;
    public JobPosting $job;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('required|string')]
    public string $requirement = '';

    #[Validate('required|string')]
    public string $duty = '';

    #[Validate('nullable|integer|min:0|max:99999999')]
    public ?int $salary_from = null;

    #[Validate('nullable|integer|min:0|max:99999999')]
    public ?int $salary_to = null;

    public function updatedSalaryFrom(mixed $value): void
    {
        $this->salary_from = $value !== '' && $value !== null ? (int) $value : null;
    }

    public function updatedSalaryTo(mixed $value): void
    {
        $this->salary_to = $value !== '' && $value !== null ? (int) $value : null;
    }

    public function mount(string $idcode): void
    {
        $this->authorize('admin.jobs.moderate');

        $this->idcode = $idcode;
        $this->job = JobPosting::byIdcode($idcode)->firstOrFail();

        $this->title = $this->job->title;
        $this->requirement = $this->job->requirement;
        $this->duty = $this->job->duty;
        $this->salary_from = $this->job->salary_from;
        $this->salary_to = $this->job->salary_to;
    }

    private function normalizeInput(): void
    {
        $this->title = trim($this->title);
        $this->requirement = trim($this->requirement);
        $this->duty = trim($this->duty);
    }

    public function update(AuditLogger $auditLogger): mixed
    {
        $this->authorize('admin.jobs.moderate');

        $this->normalizeInput();

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->dispatch('validation-failed', message: 'Please correct the highlighted fields before saving.');
            $this->setErrorBag($e->validator->errors());
            return null;
        }

        if ($this->salary_to && $this->salary_from && $this->salary_to <= $this->salary_from) {
            $this->addError('salary_to', 'The upper salary must be greater than the lower salary.');
            return null;
        }

        $this->job->update([
            'title' => $this->title,
            'requirement' => $this->requirement,
            'duty' => $this->duty,
            'salary_from' => $this->salary_from,
            'salary_to' => $this->salary_to,
        ]);

        $auditLogger->logBusinessEvent(
            eventType: 'admin.job.updated',
            request: request(),
            targetType: 'job',
            targetIdcode: $this->job->idcode,
            meta: [
                'job_title' => $this->title,
                'company_user_id' => $this->job->company_user_id,
            ]
        );

        session()->flash('message', 'Job updated successfully.');

        return redirect()->route('admin.jobs.index');
    }
}; ?>

<div class="max-w-4xl mx-auto" x-data="{ validationAlert: '' }" x-on:validation-failed.window="validationAlert = $event.detail.message">

    <!-- Validation Failed Alert -->
    <div
        x-show="validationAlert"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="theme-alert-error mb-6 flex items-start gap-3 rounded-xl border px-5 py-4 shadow-sm"
        role="alert"
    >
        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <div class="flex-1">
            <p class="text-sm font-semibold">Validation Error</p>
            <p class="mt-0.5 text-sm" x-text="validationAlert"></p>
        </div>
        <button type="button" @click="validationAlert = ''" class="shrink-0 rounded transition-colors hover:opacity-80 focus:outline-none" aria-label="Dismiss">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="theme-text-strong text-3xl font-bold">Edit Job Posting</h1>
            <p class="theme-text-muted mt-1 text-sm">Refine the listing content without dropping the current hiring context.</p>
        </div>
        <x-ui.button href="{{ route('admin.jobs.index') }}" variant="outline">
            Back to Jobs
        </x-ui.button>
    </div>

    @if(session('message'))
        <x-ui.alert type="success" class="mb-6">
            {{ session('message') }}
        </x-ui.alert>
    @endif

    @if($errors->any())
        <x-ui.alert type="error" class="mb-6">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <x-ui.card padding="p-8">
        <form wire:submit.prevent="update" class="space-y-6">
            <x-ui.input
                label="Job Title"
                name="title"
                wire:model="title"
                required
            />

            <x-ui.textarea
                label="Job Requirements"
                name="requirement"
                wire:model="requirement"
                rows="6"
                required
            />
            <p class="theme-text-muted -mt-4 text-xs">List must-have qualifications, certifications, and experience thresholds.</p>

            <x-ui.textarea
                label="Job Duties"
                name="duty"
                wire:model="duty"
                rows="6"
                required
            />
            <p class="theme-text-muted -mt-4 text-xs">Describe day-to-day responsibilities and expected outcomes in this role.</p>

            <div>
                <x-ui.form-label>Salary (Optional, HK$)</x-ui.form-label>
                <div class="theme-panel-subtle mt-1 rounded-2xl border p-4">
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1">
                            <span class="theme-text-muted pointer-events-none absolute inset-y-0 left-5 flex items-center text-sm">$</span>
                            <input
                                type="number"
                                wire:model="salary_from"
                                placeholder="From"
                                min="0"
                                max="99999999"
                                step="1"
                                class="theme-input block w-full rounded-lg border-0 py-1.5 pl-9 pr-3 text-right shadow-sm ring-1 ring-inset ring-[var(--app-input-border)] placeholder:text-[var(--app-text-muted)] focus:ring-2 focus:ring-inset focus:ring-[var(--app-accent)] sm:text-sm sm:leading-6"
                            >
                        </div>
                        <span class="theme-text-muted shrink-0 text-sm">to</span>
                        <div class="relative flex-1">
                            <span class="theme-text-muted pointer-events-none absolute inset-y-0 left-5 flex items-center text-sm">$</span>
                            <input
                                type="number"
                                wire:model="salary_to"
                                placeholder="To (optional)"
                                min="0"
                                max="99999999"
                                step="1"
                                class="theme-input block w-full rounded-lg border-0 py-1.5 pl-9 pr-3 text-right shadow-sm ring-1 ring-inset ring-[var(--app-input-border)] placeholder:text-[var(--app-text-muted)] focus:ring-2 focus:ring-inset focus:ring-[var(--app-accent)] sm:text-sm sm:leading-6"
                            >
                        </div>
                    </div>
                    <p class="theme-text-muted mt-3 text-xs">Leave blank if not specified. "To" is optional for a range.</p>
                </div>
                <x-ui.form-error name="salary_from" />
                <x-ui.form-error name="salary_to" />
            </div>

            <div class="flex gap-4">
                <x-ui.button type="submit" variant="primary" size="lg">
                    Save Changes
                </x-ui.button>
                <x-ui.button href="{{ route('admin.jobs.index') }}" variant="outline" size="lg">
                    Cancel
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
