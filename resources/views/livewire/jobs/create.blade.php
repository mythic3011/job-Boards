<?php

use App\Services\JobService;
use Illuminate\Support\Facades\Auth;
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
        if (!Auth::check() || !Auth::user()->isCompany()) {
            abort(403, 'Only company users can create job postings.');
        }
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
    <nav class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('jobs.index') }}" class="hover:text-indigo-600 transition-colors">Jobs</a>
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="text-gray-900 font-medium">Post a Job</span>
    </nav>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Post a Job</h1>
        <p class="text-sm text-gray-500 mt-1">Fill in the details below to publish your job listing.</p>
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

            <x-ui.textarea
                label="Job Duties"
                name="duty"
                wire:model="duty"
                rows="6"
                required
            />

            <div>
                <x-ui.form-label>Salary (Optional, HK$)</x-ui.form-label>
                <div class="mt-1 flex items-center gap-3">
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute inset-y-0 left-5 flex items-center text-sm text-gray-400">$</span>
                        <input
                            type="number"
                            wire:model="salary_from"
                            placeholder="From"
                            min="0"
                            max="99999999"
                            step="1"
                            class="block w-full rounded-lg border-0 py-1.5 pl-9 pr-3 text-gray-900 text-right shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                        >
                    </div>
                    <span class="shrink-0 text-sm text-gray-400">to</span>
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute inset-y-0 left-5 flex items-center text-sm text-gray-400">$</span>
                        <input
                            type="number"
                            wire:model="salary_to"
                            placeholder="To (optional)"
                            min="0"
                            max="99999999"
                            step="1"
                            class="block w-full rounded-lg border-0 py-1.5 pl-9 pr-3 text-gray-900 text-right shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                        >
                    </div>
                </div>
                <p class="mt-1.5 text-xs text-gray-400">Leave blank if not specified. "To" is optional for a range.</p>
                <x-ui.form-error name="salary_from" />
                <x-ui.form-error name="salary_to" />
            </div>

            <div class="flex gap-4 pt-2">
                <x-ui.button type="submit" variant="primary" size="lg">
                    Publish Job Posting
                </x-ui.button>
                <x-ui.button href="{{ route('jobs.index') }}" variant="outline" size="lg">
                    Cancel
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
