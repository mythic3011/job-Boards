<?php

use App\Models\Application;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

use function Livewire\Volt\{layout, title};

layout('layouts.app');

new class extends Component
{
    public string $idcode;
    #[Url]
    public ?string $jobIdcode = null;

    public function mount(string $idcode)
    {
        $this->idcode = $idcode;
    }

    public function with(): array
    {
        $user = auth()->user();
        $requestedJobIdcode = request()->query('jobIdcode');
        $scopedJobIdcode = is_string($requestedJobIdcode) && $requestedJobIdcode !== ''
            ? $requestedJobIdcode
            : $this->jobIdcode;

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
            'scopedJobIdcode' => $scopedJobIdcode,
            'backRoute' => $scopedJobIdcode
                ? route('my.applications.index', ['jobIdcode' => $scopedJobIdcode])
                : route('my.applications.index'),
        ];
    }

}; ?>

<div class="max-w-4xl mx-auto" x-data="{ showApprove: false, showReject: false, lastActiveEl: null }">
    {{-- Breadcrumb --}}
    <nav class="theme-text-muted mb-4 flex items-center gap-2 text-sm">
        <a href="{{ $backRoute }}" class="theme-link transition-colors">Applications</a>
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="theme-text-strong truncate font-medium">{{ $application->jobPosting->title }}</span>
    </nav>

    {{-- Page header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="theme-text-strong text-2xl font-bold">Application Details</h1>
        <div class="flex items-center gap-2">
            @if($isJobOwner)
                @if($application->status->value === 'pending')
                    <button type="button"
                        x-on:click="lastActiveEl = $event.currentTarget; showApprove = true"
                        class="theme-button theme-button-primary inline-flex items-center gap-1.5 rounded-lg border px-4 py-2 text-sm font-semibold cursor-pointer">
                        <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        Approve
                    </button>
                    <button type="button"
                        x-on:click="lastActiveEl = $event.currentTarget; showReject = true"
                        class="theme-button theme-button-danger inline-flex items-center gap-1.5 rounded-lg border px-4 py-2 text-sm font-semibold cursor-pointer">
                        <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        Reject
                    </button>
                @endif
            @endif
            <a href="{{ $backRoute }}"
               class="theme-button theme-button-outline inline-flex items-center gap-1.5 rounded-lg border px-4 py-2 text-sm font-medium cursor-pointer">
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
            class="theme-overlay-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
            x-on:keydown.escape.window="showApprove = false; lastActiveEl?.focus()"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="approve-application-title"
                tabindex="-1"
                x-ref="approveDialog"
                x-effect="if (showApprove) { $nextTick(() => $refs.approveDialog?.focus()) }"
                class="theme-modal-surface w-full max-w-md rounded-2xl"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.outside="showApprove = false; lastActiveEl?.focus()"
            >
                <div class="flex items-start gap-4 px-6 pt-6 pb-4">
                    <div class="theme-alert-success shrink-0 rounded-full border p-3">
                        <svg class="theme-signal-success h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <div>
                        <h2 id="approve-application-title" class="theme-text-strong text-lg font-semibold">Approve Application</h2>
                        <p class="theme-text-muted mt-1 text-sm">Optionally leave a message for the applicant.</p>
                        <p class="theme-text-muted mt-2 text-xs">
                            You are approving <span class="theme-text-strong font-medium">{{ $application->applicantUser->nickname }}</span> for
                            <span class="theme-text-strong font-medium">{{ $application->jobPosting->title }}</span>.
                            Your message will be visible to the applicant in their application timeline.
                        </p>
                    </div>
                </div>
                <form method="POST" action="{{ route('applications.approve', $application->idcode) }}">
                    @csrf
                    @if($scopedJobIdcode)
                        <input type="hidden" name="job_idcode" value="{{ $scopedJobIdcode }}">
                    @endif
                    <div class="px-6 pb-4">
                        <label for="decision_message_approve" class="theme-text-strong mb-1.5 block text-sm font-medium">Message (Optional)</label>
                        <textarea
                            id="decision_message_approve"
                            name="decision_message"
                            rows="4"
                            class="theme-input w-full rounded-lg border px-3 py-2 text-sm outline-none transition-all focus:ring-2 focus:ring-[var(--app-focus-ring)]"
                            placeholder="e.g., We are excited to move forward with your application…"
                        ></textarea>
                    </div>
                    <div class="theme-panel-subtle theme-table-divider flex justify-end gap-3 rounded-b-2xl border-t px-6 py-4">
                        <button type="button" x-on:click="showApprove = false; lastActiveEl?.focus()"
                            class="theme-button theme-button-outline rounded-lg border px-4 py-2 text-sm font-medium cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                            class="theme-button theme-button-primary rounded-lg border px-4 py-2 text-sm font-semibold cursor-pointer">
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
            class="theme-overlay-backdrop fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
            x-on:keydown.escape.window="showReject = false; lastActiveEl?.focus()"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="reject-application-title"
                tabindex="-1"
                x-ref="rejectDialog"
                x-effect="if (showReject) { $nextTick(() => $refs.rejectDialog?.focus()) }"
                class="theme-modal-surface w-full max-w-md rounded-2xl"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.outside="showReject = false; lastActiveEl?.focus()"
            >
                <div class="flex items-start gap-4 px-6 pt-6 pb-4">
                    <div class="theme-alert-error shrink-0 rounded-full border p-3">
                        <svg class="theme-signal-danger h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <div>
                        <h2 id="reject-application-title" class="theme-text-strong text-lg font-semibold">Reject Application</h2>
                        <p class="theme-text-muted mt-1 text-sm">Optionally leave a message for the applicant.</p>
                        <p class="theme-text-muted mt-2 text-xs">
                            You are rejecting <span class="theme-text-strong font-medium">{{ $application->applicantUser->nickname }}</span> for
                            <span class="theme-text-strong font-medium">{{ $application->jobPosting->title }}</span>.
                            Your message will be visible to the applicant in their application timeline.
                        </p>
                    </div>
                </div>
                <form method="POST" action="{{ route('applications.reject', $application->idcode) }}">
                    @csrf
                    @if($scopedJobIdcode)
                        <input type="hidden" name="job_idcode" value="{{ $scopedJobIdcode }}">
                    @endif
                    <div class="px-6 pb-4">
                        <label for="decision_message_reject" class="theme-text-strong mb-1.5 block text-sm font-medium">Message (Optional)</label>
                        <textarea
                            id="decision_message_reject"
                            name="decision_message"
                            rows="4"
                            class="theme-input w-full rounded-lg border px-3 py-2 text-sm outline-none transition-all focus:ring-2 focus:ring-[var(--app-focus-ring)]"
                            placeholder="e.g., We decided to proceed with other candidates…"
                        ></textarea>
                    </div>
                    <div class="theme-panel-subtle theme-table-divider flex justify-end gap-3 rounded-b-2xl border-t px-6 py-4">
                        <button type="button" x-on:click="showReject = false; lastActiveEl?.focus()"
                            class="theme-button theme-button-outline rounded-lg border px-4 py-2 text-sm font-medium cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                            class="theme-button theme-button-danger rounded-lg border px-4 py-2 text-sm font-semibold cursor-pointer">
                            Confirm Reject
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <x-ui.card padding="p-8">
        {{-- Job title + status --}}
        <div class="theme-table-divider mb-6 border-b pb-6">
            <h2 class="theme-text-strong mb-3 text-2xl font-bold">
                <a href="{{ route('jobs.show', $application->jobPosting->idcode) }}" class="theme-link transition-colors">
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
                    'approved' => 'theme-alert-success border',
                    'rejected' => 'theme-alert-error border',
                    default    => 'theme-alert-warning border',
                };
                $dotClass = match($statusValue) {
                    'approved' => 'theme-dot-success',
                    'rejected' => 'theme-dot-danger',
                    default    => 'theme-dot-warning',
                };
            @endphp
            <div class="theme-text-muted flex flex-wrap items-center gap-3 text-sm">
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
                <h3 class="theme-text-muted mb-3 text-sm font-semibold uppercase tracking-wider">Applicant</h3>
                <div class="theme-panel-subtle flex items-center gap-4 rounded-xl border p-4">
                    <x-ui.avatar
                        :src="$application->applicantUser->profile_image_path ? app(\App\Services\ProfileImageService::class)->getImageUrl($application->applicantUser->profile_image_path) : null"
                        :name="$application->applicantUser->nickname"
                        size="md"
                        class="h-14 w-14 shrink-0 border-2 border-[var(--app-panel-border)] bg-[var(--app-panel-bg)]"
                    />
                    <div>
                        <p class="theme-text-strong font-semibold">{{ $application->applicantUser->nickname }}</p>
                        @if($isJobOwner)
                            <p class="theme-text-muted mt-0.5 text-sm">{{ $application->applicantUser->email }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Cover message --}}
            <div>
                <h3 class="theme-text-muted mb-3 text-sm font-semibold uppercase tracking-wider">Cover Message</h3>
                <div class="theme-panel-subtle theme-text-muted rounded-xl border p-4 text-sm leading-relaxed">
                    @if($application->message)
                        {!! nl2br(e($application->message)) !!}
                    @else
                        <span class="theme-text-muted opacity-80">No message provided.</span>
                    @endif
                </div>
            </div>

            {{-- Company decision message --}}
            @if($application->decision_message)
                <div>
                    <h3 class="theme-text-muted mb-3 text-sm font-semibold uppercase tracking-wider">Company Message</h3>
                    <div class="theme-alert-info rounded-xl border p-4 text-sm leading-relaxed">
                        {!! nl2br(e($application->decision_message)) !!}
                    </div>
                </div>
            @endif

            {{-- CV --}}
            <div>
                <h3 class="theme-text-muted mb-3 text-sm font-semibold uppercase tracking-wider">Resume / CV</h3>
                <div class="theme-panel-subtle flex items-center justify-between rounded-xl border p-4">
                    <div class="flex items-center gap-3">
                        <div class="theme-panel flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border">
                            <svg class="theme-text-muted h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                        <div>
                            <p class="theme-text-strong text-sm font-medium">{{ $application->cv_original_name }}</p>
                            @if($application->cv_size_bytes)
                                <p class="theme-text-muted mt-0.5 text-xs">{{ number_format($application->cv_size_bytes / 1024, 2) }} KB</p>
                            @endif
                        </div>
                    </div>
                    @can('downloadCv', $application)
                        <x-ui.button href="{{ route('applications.download-cv', $application->idcode) }}" variant="primary" size="sm">
                            Download
                        </x-ui.button>
                    @else
                        <span class="theme-text-muted inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold">
                            CV restricted
                        </span>
                    @endcan
                </div>
            </div>
        </div>
    </x-ui.card>
</div>
