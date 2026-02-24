<?php

use App\Models\JobPosting;
use Livewire\Volt\Component;

use function Livewire\Volt\{layout, title};

layout('layouts.app');

new class extends Component
{
    public string $idcode;

    public function mount(string $idcode)
    {
        $this->idcode = $idcode;
    }

    public function with(): array
    {
        // OWASP A01: Job viewing is public, but we still validate it exists
        // No scoping needed here as jobs are public
        $job = JobPosting::byIdcode($this->idcode)->firstOrFail();

        title($job->title);

        $user = auth()->user();
        return [
            'job' => $job,
            'canApply' => $user && $user->isIndividual(),
            'isOwner' => $user && $user->isCompany() && $job->company_user_id === $user->id,
        ];
    }
}; ?>

<div class="max-w-4xl mx-auto">
    @if(isset($job))
        <x-ui.card padding="p-8">
            <h1 class="text-3xl font-bold mb-4">{{ $job->title }}</h1>

            @if($job->salary)
                <p class="text-emerald-700 font-medium text-lg mb-4">Salary: HKD {{ $job->salary }}</p>
            @endif

            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-2">Job Requirements</h2>
                <div class="prose max-w-none text-gray-800">
                    {!! nl2br(e($job->requirement)) !!}
                </div>
            </div>

            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-2">Job Duties</h2>
                <div class="prose max-w-none text-gray-800">
                    {!! nl2br(e($job->duty)) !!}
                </div>
            </div>

            <div class="mt-8 flex gap-4">
                @if($canApply)
                    <x-ui.button href="{{ route('applications.create', $job->idcode) }}" variant="primary" size="lg">
                        Apply for this Job
                    </x-ui.button>
                @endif

                @if($isOwner)
                    <x-ui.button href="{{ route('my.applications.index', ['jobIdcode' => $job->idcode]) }}" variant="secondary" size="lg">
                        View Applications
                    </x-ui.button>
                @endif

                <x-ui.button href="{{ route('jobs.index') }}" variant="outline" size="lg">
                    Back to Listings
                </x-ui.button>
            </div>
        </x-ui.card>
    @endif
</div>
