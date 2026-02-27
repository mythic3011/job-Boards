<?php

use App\Models\Application;
use App\Services\ApplicationQueryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Applications');

new class extends Component
{
    use WithPagination;

    public ?string $jobIdcode = null;
    public string $search = '';
    public string $statusFilter = '';
    public string $sort = 'latest';

    public function mount(?string $jobIdcode = null): void
    {
        $this->jobIdcode = $jobIdcode ?? request()->query('jobIdcode');
    }

    private const ALLOWED_STATUSES = ['', 'pending', 'approved', 'rejected'];
    private const ALLOWED_SORTS    = ['latest', 'oldest'];

    public function updatedSearch(): void { $this->resetPage(); }

    public function updatedStatusFilter(): void
    {
        if (!in_array($this->statusFilter, self::ALLOWED_STATUSES, true)) {
            $this->statusFilter = '';
        }
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        if (!in_array($this->sort, self::ALLOWED_SORTS, true)) {
            $this->sort = 'latest';
        }
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function with(ApplicationQueryService $applicationQueryService): array
    {
        $user = Auth::user();
        $isCompany = $user->isCompany();

        // Build query directly for filtering/search/pagination
        if ($isCompany) {
            $query = Application::forCompanyJobs($user->id)
                ->with(['jobPosting.companyUser', 'applicantUser']);

            if ($this->jobIdcode) {
                $job = \App\Models\JobPosting::byIdcode($this->jobIdcode)
                    ->byCompany($user->id)
                    ->firstOrFail();
                $query->forJob($job->id);
            }
        } else {
            $query = Application::byApplicant($user->id)
                ->with(['jobPosting.companyUser']);
        }

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search, $isCompany) {
                $q->whereHas('jobPosting', fn($j) => $j->where('title', 'ilike', '%' . $search . '%'));
                if ($isCompany) {
                    $q->orWhereHas('applicantUser', fn($u) => $u->where('nickname', 'ilike', '%' . $search . '%'));
                }
            });
        }

        if ($this->statusFilter && in_array($this->statusFilter, self::ALLOWED_STATUSES, true)) {
            $query->where('status', $this->statusFilter);
        }

        match (in_array($this->sort, self::ALLOWED_SORTS, true) ? $this->sort : 'latest') {
            'oldest' => $query->oldest(),
            default  => $query->latest(),
        };

        return [
            'applications' => $query->paginate(10),
            'isCompany'    => $isCompany,
        ];
    }
}; ?>

<div>
    {{-- Page header --}}
    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                @if($isCompany)
                    {{ $jobIdcode ? 'Applications for Job' : 'All Applications' }}
                @else
                    My Applications
                @endif
            </h1>
            <p class="text-sm text-gray-500 mt-1">{{ $applications->total() }} {{ Str::plural('application', $applications->total()) }}</p>
        </div>
    </div>

    {{-- Search + filter bar --}}
    <div class="flex flex-wrap gap-3 mb-6">
        {{-- Search --}}
        <div class="relative flex-1 min-w-48">
            <div class="flex items-center gap-3 rounded-lg border border-gray-300 bg-white px-4 py-2.5 shadow-sm transition-all duration-150 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-100">
                <svg style="width:18px;height:18px;flex-shrink:0;color:#9ca3af" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    type="text"
                    wire:model.live.debounce.250ms="search"
                    placeholder="{{ $isCompany ? 'Search by job title or applicant…' : 'Search by job title…' }}"
                    class="flex-1 bg-transparent text-sm text-gray-800 placeholder-gray-400 outline-none min-w-0"
                    autocomplete="off"
                />
                @if($search)
                    <button wire:click="clearSearch" class="shrink-0 rounded-full p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors cursor-pointer" aria-label="Clear search">
                        <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- Status filter --}}
        <select wire:model.live="statusFilter" class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all duration-150 cursor-pointer">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        </select>

        {{-- Sort --}}
        <select wire:model.live="sort" class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all duration-150 cursor-pointer">
            <option value="latest">Newest first</option>
            <option value="oldest">Oldest first</option>
        </select>
    </div>

    {{-- Active search label --}}
    @if($search)
        <div class="flex items-center gap-2 mb-4">
            <span class="text-sm text-gray-500">Results for</span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 border border-indigo-100 px-3 py-0.5 text-sm font-medium text-indigo-700">
                "{{ $search }}"
                <button wire:click="clearSearch" class="hover:text-indigo-900 transition-colors cursor-pointer">
                    <svg style="width:12px;height:12px" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
        </div>
    @endif

    {{-- Application list --}}
    <div class="space-y-3">
        @forelse($applications as $application)
            @php
                $statusValue = $application->status->value;
                $statusLabel = match($statusValue) {
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    default    => 'Pending review',
                };
                $statusClasses = match($statusValue) {
                    'approved' => 'bg-green-50 text-green-700 border-green-200',
                    'rejected' => 'bg-red-50 text-red-700 border-red-200',
                    default    => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                };
                $dotClasses = match($statusValue) {
                    'approved' => 'bg-green-500',
                    'rejected' => 'bg-red-500',
                    default    => 'bg-yellow-500',
                };
            @endphp

            <div class="group rounded-lg border border-gray-200 bg-white px-5 py-4 shadow-sm hover:border-indigo-200 hover:shadow-md transition-all duration-150">
                <div class="flex items-start gap-4">

                    {{-- Avatar / company initials --}}
                    <div class="flex shrink-0 items-center justify-center rounded-md border border-gray-100 bg-gray-50 font-bold text-gray-600 uppercase transition-colors group-hover:border-indigo-100 group-hover:bg-indigo-50 group-hover:text-indigo-700" style="width:48px;height:48px;font-size:13px">
                        @if($isCompany)
                            {{ Str::substr($application->applicantUser->nickname ?? '?', 0, 2) }}
                        @else
                            {{ Str::substr($application->jobPosting->companyUser?->nickname ?? '?', 0, 2) }}
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        {{-- Title row --}}
                        <div class="flex flex-wrap items-center gap-2">
                            @if($isCompany)
                                <h2 class="text-base font-semibold text-gray-900">
                                    {{ $application->applicantUser->nickname }}
                                </h2>
                                <span class="text-sm text-gray-400">&middot;</span>
                                <a href="{{ route('jobs.show', $application->jobPosting->idcode) }}" class="text-sm text-indigo-600 hover:text-indigo-800 hover:underline underline-offset-2 transition-colors">
                                    {{ $application->jobPosting->title }}
                                </a>
                            @else
                                <h2 class="text-base font-semibold leading-snug">
                                    <a href="{{ route('jobs.show', $application->jobPosting->idcode) }}" class="text-gray-900 hover:text-indigo-700 transition-colors">
                                        {{ $application->jobPosting->title }}
                                    </a>
                                </h2>
                                <span class="text-sm text-gray-500">{{ $application->jobPosting->companyUser?->nickname }}</span>
                            @endif

                            {{-- New message badge --}}
                            @if(!$isCompany && Cache::has('application_new_message_' . $application->id))
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-xs font-medium text-blue-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                    New message
                                </span>
                            @endif
                        </div>

                        {{-- Cover message snippet --}}
                        @if($application->message)
                            <p class="mt-1.5 text-sm text-gray-600 leading-relaxed line-clamp-2">{{ $application->message }}</p>
                        @endif

                        {{-- Footer meta --}}
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-400">
                                {{-- Status badge --}}
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $statusClasses }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses }}"></span>
                                    {{ $statusLabel }}
                                </span>

                                {{-- CV --}}
                                @if($application->cv_original_name)
                                    <span class="flex items-center gap-1">
                                        <svg style="width:13px;height:13px" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        {{ $application->cv_original_name }}
                                    </span>
                                @endif

                                {{-- Time --}}
                                <span class="flex items-center gap-1">
                                    <svg style="width:13px;height:13px" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    {{ $application->created_at->diffForHumans() }}
                                </span>
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-2">
                                <a href="{{ route('applications.show', $application->idcode) }}"
                                   class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 transition-all duration-150 hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer">
                                    View details &rarr;
                                </a>
                                @if($application->cv_original_name)
                                    <a href="{{ route('applications.download-cv', $application->idcode) }}"
                                       class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition-all duration-150 hover:border-indigo-300 hover:bg-indigo-100 cursor-pointer">
                                        Download CV
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <x-ui.empty-state
                title="{{ $search || $statusFilter ? 'No matching applications' : ($isCompany ? 'No applications yet' : 'You have not applied to any jobs yet') }}"
                message="{{ $search || $statusFilter ? 'Try adjusting your search or filters.' : ($isCompany ? 'Applications will appear here once candidates apply.' : 'Browse open positions and submit your first application.') }}"
            >
                @if($search || $statusFilter)
                    <x-slot:action>
                        <x-ui.button wire:click="$set('search', ''); $set('statusFilter', '')" variant="outline">
                            Clear filters
                        </x-ui.button>
                    </x-slot:action>
                @elseif(!$isCompany)
                    <x-slot:action>
                        <x-ui.button href="{{ route('jobs.index') }}" variant="primary">
                            Browse jobs
                        </x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $applications->links() }}
    </div>
</div>
