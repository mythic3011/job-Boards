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

        // Mark decision message as read when applicant views it
        if ($application->applicant_user_id === $user->id
            && $application->decision_message
            && !$application->decision_message_read_at) {
            $application->update(['decision_message_read_at' => now()]);
        }

        title('Application for ' . $application->jobPosting->title);

        return [
            'application' => $application,
            'isJobOwner' => $isJobOwner,
        ];
    }

}; ?>

<div class="max-w-4xl mx-auto" x-data="{ showApprove: false, showReject: false }">

    {{-- Breadcrumb --}}
    <nav class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('my.applications.index') }}" class="hover:text-indigo-600 transition-colors">Applications</a>
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="text-gray-900 font-medium truncate">{{ $application->jobPosting->title }}</span>
    </nav>

    {{-- Page header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-900">Application Details</h1>
        <div class="flex items-center gap-2">
            @if($isJobOwner)
                @if($application->status->value === 'pending')
                    <button type="button"
                        x-on:click="showApprove = true"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-green-700 cursor-pointer">
                        <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        Approve
                    </button>
                    <button type="button"
                        x-on:click="showReject = true"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-red-700 cursor-pointer">
                        <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        Reject
                    </button>
                @endif
            @endif
            <a href="{{ route('my.applications.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 cursor-pointer">
                <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back
            </a>
        </div>
    </div>

    {{-- Approve modal --}}
    @if($isJobOwner)
        <div
            x-show="showApprove"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-sm"
            x-on:keydown.escape.window="showApprove = false"
        >
            <div
                class="w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-black/5"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.outside="showApprove = false"
            >
                <div class="flex items-start gap-4 px-6 pt-6 pb-4">
                    <div class="shrink-0 rounded-full bg-green-100 p-3">
                        <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Approve Application</h2>
                        <p class="mt-1 text-sm text-gray-500">Optionally leave a message for the applicant.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('applications.approve', $application->idcode) }}">
                    @csrf
                    <div class="px-6 pb-4">
                        <label for="decision_message_approve" class="block text-sm font-medium text-gray-700 mb-1.5">Message (Optional)</label>
                        <textarea
                            id="decision_message_approve"
                            name="decision_message"
                            rows="4"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none transition-all"
                            placeholder="e.g., We are excited to move forward with your application…"
                        ></textarea>
                    </div>
                    <div class="flex justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50/70 px-6 py-4">
                        <button type="button" x-on:click="showApprove = false"
                            class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                            class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-green-700 cursor-pointer">
                            Confirm Approve
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Reject modal --}}
        <div
            x-show="showReject"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-sm"
            x-on:keydown.escape.window="showReject = false"
        >
            <div
                class="w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-black/5"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.outside="showReject = false"
            >
                <div class="flex items-start gap-4 px-6 pt-6 pb-4">
                    <div class="shrink-0 rounded-full bg-red-100 p-3">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Reject Application</h2>
                        <p class="mt-1 text-sm text-gray-500">Optionally leave a message for the applicant.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('applications.reject', $application->idcode) }}">
                    @csrf
                    <div class="px-6 pb-4">
                        <label for="decision_message_reject" class="block text-sm font-medium text-gray-700 mb-1.5">Message (Optional)</label>
                        <textarea
                            id="decision_message_reject"
                            name="decision_message"
                            rows="4"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none transition-all"
                            placeholder="e.g., We decided to proceed with other candidates…"
                        ></textarea>
                    </div>
                    <div class="flex justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50/70 px-6 py-4">
                        <button type="button" x-on:click="showReject = false"
                            class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-red-700 cursor-pointer">
                            Confirm Reject
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <x-ui.card padding="p-8">
        {{-- Job title + status --}}
        <div class="border-b border-gray-200 pb-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-3">
                <a href="{{ route('jobs.show', $application->jobPosting->idcode) }}" class="hover:text-indigo-700 transition-colors">
                    {{ $application->jobPosting->title }}
                </a>
            </h2>
            @php
                $statusValue  = $application->status->value;
                $statusLabel  = match($statusValue) {
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    default    => 'Pending review',
                };
                $statusClasses = match($statusValue) {
                    'approved' => 'bg-green-50 text-green-700 border-green-200',
                    'rejected' => 'bg-red-50 text-red-700 border-red-200',
                    default    => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                };
                $dotClass = match($statusValue) {
                    'approved' => 'bg-green-500',
                    'rejected' => 'bg-red-500',
                    default    => 'bg-yellow-500',
                };
            @endphp
            <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500">
                <span class="flex items-center gap-1.5">
                    <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    Applied {{ $application->created_at->format('F j, Y') }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $statusClasses }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
                    {{ $statusLabel }}
                </span>
            </div>
        </div>

        <div class="space-y-6">
            {{-- Applicant --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-3">Applicant</h3>
                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl border border-gray-200">
                    @if($application->applicantUser->profile_image_path)
                        <img src="{{ route('images.profile', ['path' => \App\Services\ProfileImageService::encodePath($application->applicantUser->profile_image_path)]) }}"
                             alt="{{ $application->applicantUser->nickname }}"
                             class="w-14 h-14 rounded-full object-cover border-2 border-gray-200 bg-white shrink-0">
                    @else
                        <div class="w-14 h-14 rounded-full bg-indigo-100 flex items-center justify-center border-2 border-indigo-200 shrink-0">
                            <span class="text-xl font-bold text-indigo-600">{{ strtoupper(substr($application->applicantUser->nickname, 0, 1)) }}</span>
                        </div>
                    @endif
                    <div>
                        <p class="font-semibold text-gray-900">{{ $application->applicantUser->nickname }}</p>
                        @if($isJobOwner)
                            <p class="text-sm text-gray-500 mt-0.5">{{ $application->applicantUser->email }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Cover message --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-3">Cover Message</h3>
                <div class="text-gray-700 bg-gray-50 p-4 rounded-xl border border-gray-200 text-sm leading-relaxed">
                    @if($application->message)
                        {!! nl2br(e($application->message)) !!}
                    @else
                        <span class="text-gray-400">No message provided.</span>
                    @endif
                </div>
            </div>

            {{-- Company decision message --}}
            @if($application->decision_message)
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-3">Company Message</h3>
                    <div class="text-gray-700 bg-indigo-50 p-4 rounded-xl border border-indigo-100 text-sm leading-relaxed">
                        {!! nl2br(e($application->decision_message)) !!}
                    </div>
                </div>
            @endif

            {{-- CV --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-3">Resume / CV</h3>
                <div class="flex items-center justify-between bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-gray-200 shrink-0">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $application->cv_original_name }}</p>
                            @if($application->cv_size_bytes)
                                <p class="text-xs text-gray-500 mt-0.5">{{ number_format($application->cv_size_bytes / 1024, 2) }} KB</p>
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
