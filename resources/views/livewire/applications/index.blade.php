<?php

use App\Services\ApplicationQueryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Applications');

new class extends Component
{
    public ?string $jobIdcode = null;

    public function mount(?string $jobIdcode = null): void
    {
        $this->jobIdcode = $jobIdcode ?? request()->query('jobIdcode');
    }

    public function with(ApplicationQueryService $applicationQueryService): array
    {
        $user = Auth::user();
        $applications = $applicationQueryService->getApplicationsForUser($user, $this->jobIdcode);

        return [
            'applications' => $applications,
            'isCompany' => $user->isCompany(),
        ];
    }
}; ?>

<div>
    <h1 class="text-3xl font-bold mb-6">
        @if($isCompany)
            @if($jobIdcode)
                Applications for Job
            @else
                All Applications
            @endif
        @else
            My Applications
        @endif
    </h1>

    <div class="space-y-4">
        @forelse($applications as $application)
            <x-ui.card>
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        @if($isCompany)
                            <h2 class="text-xl font-semibold">
                                Application from {{ $application->applicantUser->nickname }}
                            </h2>
                            <p class="text-gray-600 mt-1">
                                For: <a href="{{ route('jobs.show', $application->jobPosting->idcode) }}" class="text-indigo-600 hover:text-indigo-800">
                                    {{ $application->jobPosting->title }}
                                </a>
                            </p>
                        @else
                            <h2 class="text-xl font-semibold">
                                <a href="{{ route('jobs.show', $application->jobPosting->idcode) }}" class="text-indigo-600 hover:text-indigo-800">
                                    {{ $application->jobPosting->title }}
                                </a>
                            </h2>
                        @endif

                        @if($application->message)
                            <p class="text-gray-700 mt-3">{{ Str::limit($application->message, 200) }}</p>
                        @endif

                        <div class="mt-4 text-sm text-gray-500">
                            <p>CV: {{ $application->cv_original_name ?? 'N/A' }}</p>
                            @if($application->cv_size_bytes)
                                <p>Size: {{ number_format($application->cv_size_bytes / 1024, 2) }} KB</p>
                            @endif
                            <p>Submitted: {{ $application->created_at->diffForHumans() }}</p>
                        </div>
                    </div>

                    <div class="ml-4 flex flex-col space-y-2">
                        <x-ui.button href="{{ route('applications.show', $application->idcode) }}" variant="secondary">
                            View Details
                        </x-ui.button>
                        <x-ui.button href="{{ route('applications.download-cv', $application->idcode) }}" variant="primary">
                            Download CV
                        </x-ui.button>
                    </div>
                </div>
            </x-ui.card>
        @empty
            <x-ui.empty-state message="No applications found." />
        @endforelse
    </div>
</div>
