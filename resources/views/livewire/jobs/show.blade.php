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
        {{-- Breadcrumb --}}
        <nav class="mb-4 flex items-center gap-2 text-sm text-gray-500">
            <a href="{{ route('jobs.index') }}" class="hover:text-indigo-600 transition-colors">Jobs</a>
            <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-gray-900 font-medium">{{ $job->title }}</span>
        </nav>

        <x-ui.card padding="p-8">
            {{-- Header section --}}
            <div class="border-b border-gray-200 pb-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-3">{{ $job->title }}</h1>

                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                    {{-- Company info --}}
                    <div class="flex items-center gap-2">
                        <div class="flex items-center justify-center w-8 h-8 rounded-md bg-indigo-50 border border-indigo-100 text-indigo-700 font-semibold text-xs uppercase">
                            {{ substr($job->companyUser->nickname ?? '?', 0, 2) }}
                        </div>
                        <span class="font-medium text-gray-900">{{ $job->companyUser->nickname }}</span>
                    </div>

                    <span class="text-gray-300">•</span>

                    {{-- Posted date --}}
                    <div class="flex items-center gap-1.5">
                        <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>Posted {{ $job->created_at->diffForHumans() }}</span>
                    </div>

                    @if($job->salary)
                        <span class="text-gray-300">•</span>
                        <div class="flex items-center gap-1.5 text-emerald-700 font-semibold">
                            <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <span>{{ $job->salary }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Job details --}}
            <div class="space-y-6">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-3 flex items-center gap-2">
                        <svg style="width:20px;height:20px" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="text-indigo-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Requirements
                    </h2>
                    <div class="prose max-w-none text-gray-700 leading-relaxed pl-7">
                        {!! nl2br(e($job->requirement)) !!}
                    </div>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-3 flex items-center gap-2">
                        <svg style="width:20px;height:20px" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="text-indigo-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                        </svg>
                        Duties
                    </h2>
                    <div class="prose max-w-none text-gray-700 leading-relaxed pl-7">
                        {!! nl2br(e($job->duty)) !!}
                    </div>
                </div>
            </div>

            {{-- Action buttons --}}
            <div class="mt-8 pt-6 border-t border-gray-200">
                <div class="flex flex-wrap gap-3">
                    @if($canApply)
                        <x-ui.button href="{{ route('applications.create', $job->idcode) }}" variant="primary" size="lg">
                            <svg style="width:18px;height:18px;margin-right:6px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Apply for this Job
                        </x-ui.button>
                    @endif

                    @if($isOwner)
                        <x-ui.button href="{{ route('my.applications.index', ['jobIdcode' => $job->idcode]) }}" variant="secondary" size="lg">
                            <svg style="width:18px;height:18px;margin-right:6px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            View Applications
                        </x-ui.button>
                    @endif

                    <x-ui.button href="{{ route('jobs.index') }}" variant="outline" size="lg">
                        <svg style="width:18px;height:18px;margin-right:6px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        Back to Listings
                    </x-ui.button>
                </div>
            </div>
        </x-ui.card>
    @endif
</div>
