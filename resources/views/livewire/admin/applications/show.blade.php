<?php

use App\Models\Application;
use App\Services\AuditLogger;
use Livewire\Volt\Component;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Admin - Application Detail');

new class extends Component
{
    public string $idcode;

    public function mount(string $idcode): void
    {
        $this->authorize('admin.applications.view');
        $this->idcode = $idcode;
    }

    public function with(): array
    {
        $application = Application::byIdcode($this->idcode)
            ->with(['jobPosting.companyUser', 'applicantUser'])
            ->firstOrFail();

        title('Admin – Application for ' . $application->jobPosting->title);

        return ['application' => $application];
    }
}; ?>

<div class="max-w-4xl mx-auto">

    {{-- Breadcrumb --}}
    <nav class="theme-text-muted mb-4 flex items-center gap-2 text-sm">
        <a href="{{ route('admin.applications.index') }}" class="theme-link transition-colors">Applications</a>
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="theme-text-strong truncate font-medium">{{ $application->jobPosting->title }}</span>
    </nav>

    {{-- Page header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="theme-text-strong text-2xl font-bold">Application Details</h1>
        <a href="{{ route('admin.applications.index') }}"
           class="theme-button theme-button-outline inline-flex items-center gap-1.5 rounded-lg border px-4 py-2 text-sm font-medium cursor-pointer">
            <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            Back to Applications
        </a>
    </div>

    <x-ui.card padding="p-8">
        {{-- Job title + status --}}
        <div class="theme-table-divider mb-6 border-b pb-6">
            <h2 class="theme-text-strong mb-3 text-2xl font-bold">
                <a href="{{ route('jobs.show', $application->jobPosting->idcode) }}" class="theme-link transition-colors">
                    {{ $application->jobPosting->title }}
                </a>
            </h2>
            @php
                $statusValue   = $application->status->value;
                $statusLabel   = match($statusValue) {
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
            {{-- Company --}}
            <div>
                <h3 class="theme-text-muted mb-3 text-sm font-semibold uppercase tracking-wider">Company</h3>
                <div class="theme-panel-subtle flex items-center gap-3 rounded-xl border p-4">
                    <div class="theme-icon-tile-accent flex h-10 w-10 shrink-0 items-center justify-center rounded-full border">
                        <span class="text-sm font-bold">{{ strtoupper(substr($application->jobPosting->companyUser->nickname, 0, 1)) }}</span>
                    </div>
                    <div>
                        <p class="theme-text-strong font-semibold">{{ $application->jobPosting->companyUser->nickname }}</p>
                        <p class="theme-text-muted mt-0.5 text-sm">{{ $application->jobPosting->companyUser->email }}</p>
                    </div>
                </div>
            </div>

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
                        <p class="theme-text-muted mt-0.5 text-sm">{{ $application->applicantUser->email }}</p>
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
                        <span class="opacity-80">No message provided.</span>
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
                @if($application->cv_original_name)
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
                        <x-ui.button href="{{ route('applications.download-cv', $application->idcode) }}" variant="primary" size="sm">
                            Download
                        </x-ui.button>
                    </div>
                @else
                    <div class="theme-panel-subtle theme-text-muted rounded-xl border p-4 text-sm">No CV attached.</div>
                @endif
            </div>
        </div>
    </x-ui.card>
</div>
