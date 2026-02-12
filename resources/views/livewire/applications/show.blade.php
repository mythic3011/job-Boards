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

        // Scope query to authorized applications before fetching
        $query = Application::byIdcode($this->idcode)
            ->with(['jobPosting', 'applicantUser'])
            ->where(function ($q) use ($user) {
                // User must be the applicant OR the company owner of the job
                $q->where('applicant_user_id', $user->id)
                  ->orWhereHas('jobPosting', function ($jobQuery) use ($user) {
                      $jobQuery->where('company_user_id', $user->id);
                  });
            });

        $application = $query->firstOrFail();

        $isJobOwner = $application->jobPosting->company_user_id === $user->id;

        if ($application->applicant_user_id === $user->id) {
            \Illuminate\Support\Facades\Cache::forget('application_new_message_' . $application->id);
        }

        title('Application for ' . $application->jobPosting->title);

        return [
            'application' => $application,
            'isJobOwner' => $isJobOwner,
        ];
    }

}; ?>

<div class="max-w-4xl mx-auto" x-data="{ showApprove: false, showReject: false }">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-3xl font-bold">Application Details</h1>
        <div class="flex items-center gap-3">
            @if($isJobOwner)
                <x-ui.button type="button" variant="primary" :disabled="$application->status === 'approved'" x-on:click="showApprove = true">
                    Accept
                </x-ui.button>
                <x-ui.button type="button" variant="danger" x-on:click="showReject = true">
                    Reject
                </x-ui.button>
            @endif
            <x-ui.button href="{{ route('my.applications.index') }}" variant="secondary">
                Back to List
            </x-ui.button>
        </div>
    </div>

    @if($isJobOwner)
        <div x-show="showApprove" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="border-b px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Approve Application</h2>
                    <p class="text-sm text-gray-500">Optional: leave a message for the applicant.</p>
                </div>
                <form method="POST" action="{{ route('applications.approve', $application->idcode) }}">
                    @csrf
                    <div class="px-6 py-4">
                        <label for="decision_message_approve" class="block text-sm font-medium text-gray-700 mb-2">Message (Optional)</label>
                        <textarea
                            id="decision_message_approve"
                            name="decision_message"
                            rows="4"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="e.g., We are excited to move forward with your application..."
                        ></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-3 border-t px-6 py-4">
                        <x-ui.button type="button" variant="outline" x-on:click="showApprove = false">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary">Confirm Approve</x-ui.button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showReject" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="border-b px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Reject Application</h2>
                    <p class="text-sm text-gray-500">Optional: leave a message for the applicant.</p>
                </div>
                <form method="POST" action="{{ route('applications.reject', $application->idcode) }}">
                    @csrf
                    <div class="px-6 py-4">
                        <label for="decision_message_reject" class="block text-sm font-medium text-gray-700 mb-2">Message (Optional)</label>
                        <textarea
                            id="decision_message_reject"
                            name="decision_message"
                            rows="4"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="e.g., We decided to proceed with other candidates..."
                        ></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-3 border-t px-6 py-4">
                        <x-ui.button type="button" variant="outline" x-on:click="showReject = false">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="danger">Confirm Reject</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <x-ui.card padding="p-8">
        <div class="border-b border-gray-200 pb-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                {{ $application->jobPosting->title }}
            </h2>
            @php
                $statusLabel = $application->status === 'approved'
                    ? 'Approved'
                    : ($application->status === 'rejected'
                        ? 'Rejected'
                        : 'Applied, pending approval');
                $statusClasses = $application->status === 'approved'
                    ? 'bg-green-100 text-green-800 border-green-200'
                    : ($application->status === 'rejected'
                        ? 'bg-red-100 text-red-800 border-red-200'
                        : 'bg-yellow-100 text-yellow-800 border-yellow-200');
                $dotClass = $application->status === 'approved'
                    ? 'bg-green-600'
                    : ($application->status === 'rejected'
                        ? 'bg-red-600'
                        : 'bg-yellow-600');
            @endphp
            <div class="flex flex-wrap items-center gap-3 text-gray-500">
                <p>Applied on {{ $application->created_at->format('F j, Y, g:i a') }}</p>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $statusClasses }}">
                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $dotClass }}"></span>
                    {{ $statusLabel }}
                </span>
            </div>
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

            @if($application->decision_message)
                <div>
                    <h3 class="text-lg font-medium text-gray-900">Company Message</h3>
                    <div class="mt-2 text-gray-700 bg-indigo-50 p-4 rounded-md border border-indigo-100">
                        {!! nl2br(e($application->decision_message)) !!}
                    </div>
                </div>
            @endif

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