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

    #[Validate('nullable|string|max:255|regex:/^(?!\s+$)[0-9\s\-,]*$/')]
    public ?string $salary = null;

    private function normalizeInput(): void
    {
        $this->title = trim($this->title);
        $this->requirement = trim($this->requirement);
        $this->duty = trim($this->duty);
        $this->salary = $this->salary !== null ? trim($this->salary) : null;

        if ($this->salary === '') {
            $this->salary = null;
        }
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

        $job = $jobService->createJob([
            'title' => $this->title,
            'requirement' => $this->requirement,
            'duty' => $this->duty,
            'salary' => $this->salary,
        ]);

        session()->flash('message', 'Job posting created successfully!');

        return redirect()->route('jobs.index');
    }
}; ?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold mb-6">Create Job</h1>

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

            <x-ui.input
                label="Salary (Optional, HK$)"
                name="salary"
                wire:model="salary"
                placeholder="e.g., 50000 - 70000"
                inputmode="numeric"
                pattern="[0-9\s\-,]*"
                title="Please enter only numbers, spaces, hyphens, and commas"
                oninput="if (/[^0-9\s\-,]/.test(this.value)) { alert('Only numbers, spaces, hyphens, and commas are allowed'); this.value = this.value.replace(/[^0-9\s\-,]/g, ''); }"
            />

            <div class="flex gap-4">
                <x-ui.button type="submit" variant="primary" size="lg">
                    Create Job Posting
                </x-ui.button>
                <x-ui.button href="{{ route('jobs.index') }}" variant="outline" size="lg">
                    Cancel
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
