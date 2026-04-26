<?php

use App\Services\JobService;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Create Job');

new class extends Component
{
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

    private function normalizeInput(): void
    {
        $this->title = trim($this->title);
        $this->requirement = trim($this->requirement);
        $this->duty = trim($this->duty);
    }

    public function mount(): void
    {
        $this->authorize('create', \App\Models\JobPosting::class);
    }

    public function create(JobService $jobService): mixed
    {
        $this->normalizeInput();
        $this->validate();

        if ($this->salary_to && $this->salary_from && $this->salary_to <= $this->salary_from) {
            $this->addError('salary_to', 'The upper salary must be greater than the lower salary.');
            return null;
        }

        $job = $jobService->createJob([
            'title' => $this->title,
            'requirement' => $this->requirement,
            'duty' => $this->duty,
            'salary_from' => $this->salary_from,
            'salary_to' => $this->salary_to,
        ]);

        session()->flash('message', 'Job posting created successfully!');

        return redirect()->route('jobs.index');
    }
}; ?>

<div class="max-w-4xl mx-auto">
    {{-- Breadcrumb --}}
    <nav class="theme-text-muted mb-4 flex items-center gap-2 text-sm">
        <a href="{{ route('jobs.index') }}" class="theme-link transition-colors">Jobs</a>
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="theme-text-strong font-medium">Post a Job</span>
    </nav>

    <div class="mb-6">
        <h1 class="theme-text-strong text-2xl font-bold">Post a Job</h1>
        <p class="theme-text-muted mt-1 text-sm">Fill in the details below to publish your job listing.</p>
    </div>

    <x-ui.card padding="p-8">
        <form
            method="POST"
            action="{{ route('jobs.store') }}"
            wire:submit.prevent="create"
            class="space-y-6"
        >
            @csrf
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
            <p class="theme-text-muted -mt-4 text-xs">Use this field for entry criteria (who qualifies): must-have skills, certifications, and minimum experience.</p>

            <x-ui.textarea
                label="Job Duties"
                name="duty"
                wire:model="duty"
                rows="6"
                required
            />
            <p class="theme-text-muted -mt-4 text-xs">Use this field for scope of work (what they do): day-to-day responsibilities, ownership areas, and expected outcomes.</p>

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

            <div class="flex gap-4 pt-2">
                <x-ui.button
                    type="submit"
                    variant="primary"
                    size="lg"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-60 cursor-not-allowed"
                    wire:target="create"
                >
                    <span wire:loading.remove wire:target="create">Publish Job Posting</span>
                    <span wire:loading wire:target="create" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 12 6.477 12 12h-4z"></path>
                        </svg>
                        Publishing...
                    </span>
                </x-ui.button>
                <x-ui.button href="{{ route('jobs.index') }}" variant="outline" size="lg">
                    Cancel
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
