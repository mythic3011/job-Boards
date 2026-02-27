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
    <nav class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('admin.applications.index') }}" class="hover:text-indigo-600 transition-colors">Applications</a>
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="text-gray-900 font-medium truncate">{{ $application->jobPosting->title }}</span>
    </nav>

    {{-- Page header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-900">Application Details</h1>
        <a href="{{ route('admin.applications.index') }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 cursor-pointer">
            <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            Back to Applications
        </a>
    </div>

    <x-ui.card padding="p-8">
        {{-- Job title + status --}}
        <div class="border-b border-gray-200 pb-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-3">
                <a href="{{ route('jobs.show', $application->jobPosting->idcode) }}" class="hover:text-indigo-700 transition-colors">
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
            {{-- Company --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-3">Company</h3>
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center border-2 border-blue-200 shrink-0">
                        <span class="text-sm font-bold text-blue-600">{{ strtoupper(substr($application->jobPosting->companyUser->nickname, 0, 1)) }}</span>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">{{ $application->jobPosting->companyUser->nickname }}</p>
                        <p class="text-sm text-gray-500 mt-0.5">{{ $application->jobPosting->companyUser->email }}</p>
                    </div>
                </div>
            </div>

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
                        <p class="text-sm text-gray-500 mt-0.5">{{ $application->applicantUser->email }}</p>
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
                @if($application->cv_original_name)
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
                @else
                    <div class="p-4 bg-gray-50 rounded-xl border border-gray-200 text-sm text-gray-400">No CV attached.</div>
                @endif
            </div>
        </div>
    </x-ui.card>
</div>
