<?php

use App\Models\Application;
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
        $user = auth()->user();

        $application = Application::byIdcode($this->idcode)
            ->with(['jobPosting', 'applicantUser'])
            ->firstOrFail();

        // Authorization check: User must be the applicant OR the company owner of the job
        $isApplicant = $user->id === $application->applicant_user_id;
        $isJobOwner = $application->jobPosting->company_user_id === $user->id;

        if (!$isApplicant && !$isJobOwner) {
            abort(403, 'Unauthorized access to application details.');
        }

        title('Application for ' . $application->jobPosting->title);

        return [
            'application' => $application,
            'isJobOwner' => $isJobOwner,
        ];
    }
}; ?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-3xl font-bold">Application Details</h1>
        <x-ui.button href="{{ route('my.applications.index') }}" variant="secondary">
            Back to List
        </x-ui.button>
    </div>

    <x-ui.card padding="p-8">
        <div class="border-b border-gray-200 pb-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                {{ $application->jobPosting->title }}
            </h2>
            <p class="text-gray-500">
                Applied on {{ $application->created_at->format('F j, Y, g:i a') }}
            </p>
        </div>

        <div class="space-y-6">
            <div>
                <h3 class="text-lg font-medium text-gray-900 mb-3">Applicant</h3>
                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    @if($application->applicantUser->profile_image_path)
                         <img src="{{ route('images.profile', ['path' => \App\Services\ProfileImageService::encodePath($application->applicantUser->profile_image_path)]) }}"
                              alt="{{ $application->applicantUser->nickname }}"
                              class="w-16 h-16 rounded-full object-cover border-2 border-gray-200 bg-white">
                    @else
                        <div class="w-16 h-16 rounded-full bg-indigo-100 flex items-center justify-center border-2 border-indigo-200 shrink-0">
                            <span class="text-xl font-bold text-indigo-600">{{ strtoupper(substr($application->applicantUser->nickname, 0, 1)) }}</span>
                        </div>
                    @endif
                    <div>
                        <p class="text-gray-900 font-bold text-lg">{{ $application->applicantUser->nickname }}</p>
                        @if($isJobOwner)
                            <p class="text-gray-500">{{ $application->applicantUser->email }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-medium text-gray-900">Cover Message</h3>
                <div class="mt-2 text-gray-700 bg-gray-50 p-4 rounded-md">
                    @if($application->message)
                        {!! nl2br(e($application->message)) !!}
                    @else
                        <span class="text-gray-400 italic">No message provided.</span>
                    @endif
                </div>
            </div>

            <div>
                <h3 class="text-lg font-medium text-gray-900">Resume / CV</h3>
                <div class="mt-2 flex items-center justify-between bg-gray-50 p-4 rounded-md border border-gray-200">
                    <div class="flex items-center">
                        <svg class="h-8 w-8 text-gray-400 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $application->cv_original_name }}</p>
                            @if($application->cv_size_bytes)
                                <p class="text-xs text-gray-500">{{ number_format($application->cv_size_bytes / 1024, 2) }} KB</p>
                            @endif
                        </div>
                    </div>
                    <x-ui.button href="{{ route('applications.download-cv', $application->idcode) }}" variant="primary" size="sm">
                        Download
                    </x-ui.button>
                </div>
            </div>
        </div>
    </x-ui.card>
</div>