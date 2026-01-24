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

    #[Validate('nullable|string|max:255')]
    public ?string $salary = null;

    public function mount(): void
    {
        if (!Auth::check() || !Auth::user()->isCompany()) {
            abort(403, 'Only company users can create job postings.');
        }
    }

    public function create(JobService $jobService): mixed
    {
        $this->validate();

        $job = $jobService->createJob([
            'title' => $this->title,
            'requirement' => $this->requirement,
            'duty' => $this->duty,
            'salary' => $this->salary,
        ]);

        session()->flash('message', 'Job posting created successfully!');

        return redirect()->route('jobs.show', $job->idcode);
    }
}; ?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold mb-6">Create Job</h1>

    <x-ui.card padding="p-8">
        <form wire:submit="create" class="space-y-6">
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
                label="Salary (Optional)"
                name="salary"
                wire:model="salary"
                placeholder="e.g., $50,000 - $70,000"
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
