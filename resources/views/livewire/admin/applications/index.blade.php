<?php

use App\Models\Application;
use App\Models\JobPosting;
use App\Services\AdminCompanyOptionsService;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Admin - Applications');

new class extends Component
{
    use WithPagination;

    private const PAGE_SIZE = 15;
    public string $search = '';
    public string $statusFilter = '';
    public string $companyFilter = '';
    public string $jobIdcode = '';
    public string $sort = 'latest';
    public int $visibleCount = self::PAGE_SIZE;

    private const ALLOWED_STATUSES = ['', 'pending', 'approved', 'rejected'];
    private const ALLOWED_SORTS    = ['latest', 'oldest'];

    public function mount(?string $jobIdcode = null): void
    {
        $this->jobIdcode = (string) ($jobIdcode ?? request()->query('jobIdcode', ''));
    }

    public function updatedSearch(): void { $this->resetInfinitePagination(); }
    public function updatedCompanyFilter(): void { $this->resetInfinitePagination(); }

    public function updatedStatusFilter(): void
    {
        if (!in_array($this->statusFilter, self::ALLOWED_STATUSES, true)) {
            $this->statusFilter = '';
        }
        $this->resetInfinitePagination();
    }

    public function updatedSort(): void
    {
        if (!in_array($this->sort, self::ALLOWED_SORTS, true)) {
            $this->sort = 'latest';
        }
        $this->resetInfinitePagination();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetInfinitePagination();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->companyFilter = '';
        $this->sort = 'latest';
        $this->resetInfinitePagination();
    }

    public function clearJobScope(): void
    {
        $this->jobIdcode = '';
        $this->resetInfinitePagination();
    }

    public function with(AdminCompanyOptionsService $companyOptionsService): array
    {
        $query = Application::with(['jobPosting.companyUser', 'applicantUser'])->latest();
        $jobFilterTitle = null;

        if ($this->jobIdcode !== '') {
            $jobFilter = JobPosting::query()
                ->where('idcode', $this->jobIdcode)
                ->first(['id', 'title']);

            if ($jobFilter) {
                $query->where('job_id', $jobFilter->id);
                $jobFilterTitle = $jobFilter->title;
            }
        }

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('jobPosting', fn($j) => $j->where('title', 'ilike', '%' . $search . '%'))
                  ->orWhereHas('applicantUser', fn($u) => $u->where('nickname', 'ilike', '%' . $search . '%'));
            });
        }

        if ($this->statusFilter && in_array($this->statusFilter, self::ALLOWED_STATUSES, true)) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->companyFilter) {
            $query->whereHas('jobPosting', fn($j) => $j->where('company_user_id', $this->companyFilter));
        }

        match (in_array($this->sort, self::ALLOWED_SORTS, true) ? $this->sort : 'latest') {
            'oldest' => $query->reorder()->oldest(),
            default  => $query,
        };

        $companies = $companyOptionsService->getCompanyOptions();

        $applicationStats = Application::query()
            ->selectRaw('COUNT(*) as total_applications')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_applications")
            ->selectRaw("SUM(CASE WHEN cv_original_name IS NOT NULL THEN 1 ELSE 0 END) as cv_attached")
            ->first();

        return [
            'applications' => $query->paginate($this->visibleCount),
            'companies'    => $companies,
            'jobFilterTitle' => $jobFilterTitle,
            'stats'        => [
                'total_applications' => (int) ($applicationStats?->total_applications ?? 0),
                'pending_applications' => (int) ($applicationStats?->pending_applications ?? 0),
                'approved_applications' => (int) ($applicationStats?->approved_applications ?? 0),
                'cv_attached' => (int) ($applicationStats?->cv_attached ?? 0),
            ],
        ];
    }

    public function loadMore(): void
    {
        $this->visibleCount += self::PAGE_SIZE;
    }

    private function resetInfinitePagination(): void
    {
        $this->visibleCount = self::PAGE_SIZE;
        $this->resetPage();
    }
}; ?>

@php
    $activeFilterCount = collect([$search, $statusFilter, $companyFilter, $sort !== 'latest' ? $sort : null])
        ->filter(fn ($value) => filled($value))
        ->count();
@endphp

<div class="space-y-8">
    <div class="theme-hero-surface rounded-3xl border px-6 py-7 sm:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="theme-hero-eyebrow text-xs font-semibold uppercase tracking-[0.18em]">Admin Applications</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight">Application queue</h1>
                <p class="theme-text-muted mt-3 text-sm leading-6">
                    Search submitted applications, triage review posture, and move from applicant context to detail review without losing the queue.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3 lg:min-w-[420px]">
                <div class="theme-hero-card rounded-2xl border px-4 py-4">
                    <p class="theme-text-muted text-xs uppercase tracking-[0.16em]">Total Applications</p>
                    <p class="mt-3 text-3xl font-semibold">{{ number_format($stats['total_applications']) }}</p>
                    <p class="theme-text-muted mt-2 text-sm">All submissions currently in the review system.</p>
                </div>
                <div class="theme-hero-card rounded-2xl border px-4 py-4">
                    <p class="theme-text-muted text-xs uppercase tracking-[0.16em]">Pending Review</p>
                    <p class="mt-3 text-3xl font-semibold">{{ number_format($stats['pending_applications']) }}</p>
                    <p class="theme-text-muted mt-2 text-sm">Applicants still waiting for a review decision.</p>
                </div>
                <div class="theme-hero-card rounded-2xl border px-4 py-4">
                    <p class="theme-text-muted text-xs uppercase tracking-[0.16em]">CV Attached</p>
                    <p class="mt-3 text-3xl font-semibold">{{ number_format($stats['cv_attached']) }}</p>
                    <p class="theme-text-muted mt-2 text-sm">Applications with a downloadable resume on file.</p>
                </div>
                <div class="theme-hero-card rounded-2xl border px-4 py-4">
                    <p class="theme-text-muted text-xs uppercase tracking-[0.16em]">Approved</p>
                    <p class="mt-3 text-3xl font-semibold">{{ number_format($stats['approved_applications']) }}</p>
                    <p class="theme-text-muted mt-2 text-sm">Applications already accepted by the company side.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.8fr)_minmax(320px,1fr)]">
        <div class="space-y-6">
            <div class="theme-panel rounded-2xl border p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <x-ui.section-label class="mb-2">Review Filters</x-ui.section-label>
                        <p class="theme-text-muted text-sm">Search submitted applications by job title, applicant, status, or company.</p>
                        @if($jobFilterTitle)
                            <div class="mt-2 inline-flex items-center gap-2 text-xs">
                                <span class="theme-pill inline-flex items-center rounded-full border px-2.5 py-1 font-semibold">
                                    Applications for: {{ $jobFilterTitle }}
                                </span>
                                <button
                                    type="button"
                                    wire:click="clearJobScope"
                                    class="theme-link text-xs font-semibold cursor-pointer"
                                >
                                    Clear job scope
                                </button>
                            </div>
                        @endif
                    </div>
                    <div class="theme-pill inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold">
                        <span>{{ $applications->total() }} {{ \Illuminate\Support\Str::plural('application', $applications->total()) }}</span>
                        @if($activeFilterCount > 0)
                            <span class="theme-panel rounded-full border px-2 py-0.5 text-[11px] font-semibold">
                                {{ $activeFilterCount }} filter{{ $activeFilterCount > 1 ? 's' : '' }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <div class="relative min-w-64 flex-1">
                        <div class="theme-input-shell flex items-center gap-3 rounded-lg border px-4 py-2.5 transition-all duration-150">
                            <svg class="theme-text-muted h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            <input
                                type="text"
                                wire:model.live.debounce.250ms="search"
                                placeholder="Search submitted applications"
                                class="theme-input flex-1 min-w-0 border-0 bg-transparent px-0 py-0 text-sm shadow-none outline-none"
                                autocomplete="off"
                            />
                            @if($search)
                                <button wire:click="clearSearch" class="theme-text-muted shrink-0 rounded-full p-0.5 transition-colors cursor-pointer hover:bg-[var(--app-panel-subtle-bg)] hover:text-[var(--app-text-strong)]" aria-label="Clear search">
                                    <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    <select wire:model.live="companyFilter" class="theme-input shrink-0 rounded-lg border px-3 py-2.5 text-sm shadow-sm outline-none transition-all duration-150 cursor-pointer">
                        <option value="">All companies</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->nickname }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="statusFilter" class="theme-input shrink-0 rounded-lg border px-3 py-2.5 text-sm shadow-sm outline-none transition-all duration-150 cursor-pointer">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>

                    <select wire:model.live="sort" class="theme-input shrink-0 rounded-lg border px-3 py-2.5 text-sm shadow-sm outline-none transition-all duration-150 cursor-pointer">
                        <option value="latest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                    </select>

                    @if($search || $statusFilter || $companyFilter || $sort !== 'latest')
                        <button wire:click="clearFilters" class="theme-input shrink-0 rounded-lg border px-3 py-2.5 text-sm shadow-sm transition-colors hover:bg-[var(--app-panel-subtle-bg)] cursor-pointer">
                            Clear all
                        </button>
                    @endif
                </div>
            </div>

            <div class="theme-table-shell rounded-2xl border shadow-sm">
                <div class="theme-table-head flex items-center justify-between border-b px-6 py-4">
                    <h2 class="theme-text-muted text-sm font-semibold uppercase tracking-wider">Application Review Queue</h2>
                    <span class="theme-panel rounded-full border px-3 py-0.5 text-xs font-semibold">
                        {{ $applications->count() }} on this page
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[760px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[31%]">
                            <col class="w-[28%]">
                            <col class="w-[25%]">
                            <col class="w-[16%]">
                        </colgroup>
                <thead>
                    <tr class="theme-table-head theme-table-divider border-b">
                        <th class="theme-text-muted px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider">Application</th>
                        <th class="theme-text-muted px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider">Applicant</th>
                        <th class="theme-text-muted px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider">Review & Timeline</th>
                        <th class="theme-text-muted px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="--tw-divide-opacity: 1; border-color: var(--app-panel-border);">
                    @forelse($applications as $application)
                        @php
                            $statusValue = $application->status->value;
                            $statusLabel = match($statusValue) {
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                default    => 'Pending',
                            };
                            $statusClasses = match($statusValue) {
                                'approved' => 'theme-alert-success',
                                'rejected' => 'theme-alert-error',
                                default    => 'theme-alert-warning',
                            };
                            $dotClasses = match($statusValue) {
                                'approved' => 'bg-[var(--app-success-fg)]',
                                'rejected' => 'bg-[var(--app-danger-fg)]',
                                default    => 'bg-[var(--app-warning-fg)]',
                            };
                        @endphp
                        <tr class="group transition-colors duration-150 hover:bg-[var(--app-panel-subtle-bg)]">
                            <td class="px-6 py-4">
                                <div class="theme-text-strong text-sm font-semibold transition-colors group-hover:text-[var(--app-link-accent)]">
                                    {{ $application->jobPosting->title }}
                                </div>
                                <div class="theme-panel-subtle theme-text-strong mt-1 inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium">
                                    {{ $application->jobPosting->companyUser->nickname }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar
                                        :src="$application->applicantUser->profile_image_path ? app(\App\Services\ProfileImageService::class)->getImageUrl($application->applicantUser->profile_image_path) : null"
                                        :name="$application->applicantUser->nickname"
                                        size="sm"
                                        class="shrink-0 border border-[var(--app-panel-border)]"
                                    />
                                    <div class="min-w-0">
                                        <div class="theme-text-strong truncate text-sm font-semibold">{{ $application->applicantUser->nickname }}</div>
                                        <div class="theme-text-muted truncate text-sm">{{ $application->applicantUser->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-2.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $statusClasses }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses }}"></span>
                                        {{ $statusLabel }}
                                    </span>
                                    @if($application->cv_original_name)
                                        <span class="theme-pill inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium">
                                            <svg style="width:14px;height:14px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                            CV attached
                                        </span>
                                    @else
                                        <span class="theme-panel-subtle theme-text-muted inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium">
                                            No CV
                                        </span>
                                    @endif
                                    <div class="theme-text-muted text-xs">
                                        <div class="theme-text-strong font-medium">{{ $application->created_at->diffForHumans() }}</div>
                                        <div class="mt-1">{{ $application->created_at->format('M j, Y') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col items-end gap-2">
                                    <a href="{{ route('admin.applications.show', $application->idcode) }}"
                                       class="theme-button theme-button-primary inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors duration-150 cursor-pointer">
                                        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z" />
                                        </svg>
                                        Open review
                                    </a>
                                    @if($application->cv_original_name)
                                        <a href="{{ route('applications.download-cv', $application->idcode) }}"
                                           class="theme-link inline-flex items-center gap-1.5 text-xs font-semibold transition-colors duration-150 cursor-pointer">
                                            <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                            CV file
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="theme-icon-tile rounded-full p-4">
                                        <svg class="theme-text-muted h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p class="theme-text-strong text-sm font-semibold">No applications found</p>
                                    @if($search || $statusFilter || $companyFilter)
                                        <p class="theme-text-muted text-xs">Try adjusting your search or filters</p>
                                    @else
                                        <p class="theme-text-muted text-xs">The review queue is currently empty. New submissions will appear here.</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

                <x-ui.infinite-scroll-pagination
                    :paginator="$applications"
                    action="loadMore"
                    label="application"
                    key-name="admin-applications"
                />
            </div>
        </div>

        <div class="space-y-6">
            <div>
                <div class="mb-4">
                    <x-ui.section-label class="mb-2">Review posture</x-ui.section-label>
                    <p class="theme-text-muted text-sm">Queue health signals before you open an application detail view.</p>
                </div>
                <x-ui.card class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="theme-panel-subtle rounded-2xl border px-4 py-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="theme-text-strong text-sm font-medium">Pending Review</p>
                            <p class="text-2xl font-semibold {{ $stats['pending_applications'] > 0 ? 'theme-signal-warning' : 'theme-text-strong' }}">{{ number_format($stats['pending_applications']) }}</p>
                        </div>
                        <p class="theme-text-muted mt-2 text-sm">Applications still waiting for first-pass review or a company-side decision.</p>
                    </div>

                    <div class="theme-panel-subtle rounded-2xl border px-4 py-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="theme-text-strong text-sm font-medium">CV Attached</p>
                            <p class="theme-signal-info text-2xl font-semibold">{{ number_format($stats['cv_attached']) }}</p>
                        </div>
                        <p class="theme-text-muted mt-2 text-sm">Submissions with a downloadable resume available to operators and reviewers.</p>
                    </div>

                    <div class="theme-panel-subtle rounded-2xl border px-4 py-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="theme-text-strong text-sm font-medium">Approved</p>
                            <p class="theme-signal-success text-2xl font-semibold">{{ number_format($stats['approved_applications']) }}</p>
                        </div>
                        <p class="theme-text-muted mt-2 text-sm">Applications that already cleared review and moved forward with the employer.</p>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>
</div>
