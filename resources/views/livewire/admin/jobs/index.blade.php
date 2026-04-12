<?php

use App\Models\JobPosting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DashboardService;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Admin - Jobs');

new class extends Component
{
    use WithPagination;

    private const PAGE_SIZE = 15;
    public string $search = '';
    public string $companyFilter = '';
    public string $sort = 'latest';
    public int $visibleCount = self::PAGE_SIZE;

    private const ALLOWED_SORTS = ['latest', 'oldest'];

    public function updatedSearch(): void { $this->resetInfinitePagination(); }
    public function updatedCompanyFilter(): void { $this->resetInfinitePagination(); }

    public function updatedSort(): void
    {
        if (!in_array($this->sort, self::ALLOWED_SORTS, true)) {
            $this->sort = 'latest';
        }
        $this->resetInfinitePagination();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->companyFilter = '';
        $this->sort = 'latest';
        $this->resetInfinitePagination();
    }

    public function deleteJob(string $jobId, DashboardService $dashboardService, AuditLogger $auditLogger): void
    {
        $this->authorize('admin.jobs.moderate');

        $job = JobPosting::findOrFail($jobId);

        $auditLogger->logBusinessEvent(
            eventType: 'admin.job.deleted',
            request: request(),
            targetType: 'job',
            targetIdcode: $job->idcode,
            meta: [
                'job_title' => $job->title,
                'company_user_id' => $job->company_user_id,
            ]
        );

        $job->delete();

        $dashboardService->clearCache();
        $this->dispatch('close-delete-modal');

        session()->flash('message', 'Job deleted successfully.');
    }

    public function with(): array
    {
        $query = JobPosting::with('companyUser');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'ilike', '%' . $this->search . '%')
                  ->orWhere('requirement', 'ilike', '%' . $this->search . '%');
            });
        }

        if ($this->companyFilter) {
            $query->where('company_user_id', $this->companyFilter);
        }

        match (in_array($this->sort, self::ALLOWED_SORTS, true) ? $this->sort : 'latest') {
            'oldest' => $query->oldest(),
            default  => $query->latest(),
        };

        $companies = User::where('user_type', 'company')
            ->orderBy('nickname')
            ->get(['id', 'nickname']);

        return [
            'jobs'      => $query->paginate($this->visibleCount),
            'companies' => $companies,
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

<div x-data="{ showDeleteModal: false, pendingDeleteId: '' }">
    <!-- Page Header -->
    <div class="theme-panel mb-8 flex items-center justify-between rounded-3xl border px-6 py-6">
        <div>
            <h1 class="theme-text-strong text-2xl font-bold">Job Postings Management</h1>
            <p class="theme-text-muted mt-1 text-sm">Manage and moderate all job postings on the platform</p>
        </div>
    </div>

    @if(session('message'))
        <x-ui.alert type="success" class="mb-6" dismissible>
            {{ session('message') }}
        </x-ui.alert>
    @endif

    <!-- Search + filters -->
    <div class="mb-6 flex flex-wrap gap-3">
        <div class="relative flex-1 min-w-48">
            <div class="theme-input-shell flex items-center gap-3 rounded-lg border px-4 py-2.5 transition-all duration-150 focus-within:border-[var(--app-accent)]">
                <svg class="theme-text-muted shrink-0" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by title or requirements…"
                    class="theme-input flex-1 min-w-0 border-0 bg-transparent text-sm outline-none placeholder:text-[var(--app-text-muted)]"
                    autocomplete="off"
                />
                @if($search)
                    <button wire:click="$set('search', '')" class="theme-text-muted shrink-0 rounded-full p-0.5 transition-colors cursor-pointer hover:bg-[var(--app-panel-subtle-bg)] hover:text-[var(--app-text-strong)]" aria-label="Clear search">
                        <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        <select wire:model.live="companyFilter" class="theme-input-shell theme-text-strong shrink-0 rounded-lg border px-3 py-2.5 text-sm outline-none transition-all duration-150 cursor-pointer focus:border-[var(--app-accent)]">
            <option value="">All companies</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}">{{ $company->nickname }}</option>
            @endforeach
        </select>

        <select wire:model.live="sort" class="theme-input-shell theme-text-strong shrink-0 rounded-lg border px-3 py-2.5 text-sm outline-none transition-all duration-150 cursor-pointer focus:border-[var(--app-accent)]">
            <option value="latest">Newest first</option>
            <option value="oldest">Oldest first</option>
        </select>

        @if($search || $companyFilter || $sort !== 'latest')
            <button wire:click="clearFilters" class="theme-button theme-button-outline shrink-0 rounded-lg border px-3 py-2.5 text-sm transition-colors cursor-pointer">
                Clear all
            </button>
        @endif
    </div>

    <!-- Jobs Table -->
    <div class="theme-table-shell overflow-hidden rounded-2xl border">

        <!-- Table Toolbar -->
        <div class="theme-table-head theme-table-divider flex items-center justify-between border-b px-6 py-4">
            <h2 class="theme-text-muted text-sm font-semibold uppercase tracking-wider">All Job Listings</h2>
            <span class="theme-pill px-3 py-0.5 text-xs font-semibold rounded-full">
                {{ $jobs->total() }} {{ \Illuminate\Support\Str::plural('job', $jobs->total()) }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="theme-table-head theme-table-divider border-b">
                        <th class="theme-text-muted px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider">Title</th>
                        <th class="theme-text-muted px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider">Company</th>
                        <th class="theme-text-muted px-6 py-3.5 text-center text-xs font-semibold uppercase tracking-wider">Applications</th>
                        <th class="theme-text-muted px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider">Created</th>
                        <th class="theme-text-muted px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="theme-table-divider divide-y">
                    @forelse($jobs as $job)
                        <tr class="group transition-colors duration-150 hover:bg-[var(--app-panel-subtle-bg)]/70">
                            <td class="px-6 py-4">
                                <div class="theme-text-strong text-sm font-semibold transition-colors group-hover:text-[var(--app-link-accent)]">{{ $job->title }}</div>
                                @if($job->salary)
                                    <div class="theme-text-muted mt-0.5 text-xs">{{ $job->salary }}</div>
                                @else
                                    <div class="theme-text-muted mt-0.5 text-xs opacity-80">No salary stated</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="theme-text-strong text-sm">{{ $job->companyUser->nickname }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @php $appCount = $job->applications()->count(); @endphp
                                <a href="{{ route('admin.applications.index', ['companyFilter' => $job->company_user_id]) }}"
                                   class="inline-flex min-w-[2rem] items-center justify-center rounded-full px-2 py-0.5 text-xs font-bold transition-colors cursor-pointer {{ $appCount > 0 ? 'theme-pill hover:brightness-105' : 'theme-panel-subtle theme-text-muted border' }}">
                                    {{ $appCount }}
                                </a>
                            </td>
                            <td class="theme-text-muted px-6 py-4 whitespace-nowrap text-sm">
                                {{ $job->created_at->diffForHumans() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('jobs.show', $job->idcode) }}"
                                       class="theme-button theme-button-outline inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-medium transition-all duration-150 cursor-pointer">
                                        View
                                    </a>
                                    <a href="{{ route('admin.jobs.edit', $job->idcode) }}"
                                       class="theme-alert-info inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold transition-all duration-150 cursor-pointer hover:brightness-105">
                                        Edit
                                    </a>
                                    <button
                                        @click="pendingDeleteId = '{{ $job->id }}'; showDeleteModal = true"
                                        class="theme-alert-error inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold transition-all duration-150 cursor-pointer hover:brightness-105"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="theme-empty-icon rounded-full p-4">
                                        <svg class="theme-text-muted h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p class="theme-text-muted text-sm font-semibold">No job postings found</p>
                                    @if($search || $companyFilter)
                                        <p class="theme-text-muted text-xs opacity-80">Try adjusting your search or filters</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-ui.infinite-scroll-pagination
            :paginator="$jobs"
            action="loadMore"
            label="job"
            key-name="admin-jobs"
        />
    </div>

    <!-- Delete Confirmation Modal -->
    <div
        x-show="showDeleteModal"
        x-cloak
        style="display: none;"
        @close-delete-modal.window="showDeleteModal = false"
        x-on:keydown.escape.window="showDeleteModal = false"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            class="theme-modal-surface w-full max-w-md rounded-2xl"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.outside="showDeleteModal = false"
        >
            <div class="px-6 py-6">
                <div class="flex items-start gap-4">
                    <div class="theme-alert-error shrink-0 rounded-full border p-3">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="theme-text-strong text-lg font-semibold">Delete Job Posting</h3>
                        <p class="theme-text-muted mt-1.5 text-sm">
                            Are you sure you want to delete this job posting? This action <span class="theme-text-strong font-semibold">cannot be undone</span>.
                        </p>
                        <div class="theme-alert-error mt-3 flex items-start gap-2 rounded-xl border px-4 py-3">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01" />
                            </svg>
                            <p class="text-xs font-medium">All related applications will be permanently removed.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="theme-panel-subtle theme-table-divider flex justify-end gap-3 rounded-b-2xl border-t px-6 py-4">
                <button
                    type="button"
                    x-on:click="showDeleteModal = false"
                    class="theme-button theme-button-outline rounded-lg border px-4 py-2 text-sm font-medium transition-colors cursor-pointer"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="$wire.deleteJob(pendingDeleteId)"
                    wire:loading.attr="disabled"
                    wire:target="deleteJob"
                    class="theme-button theme-button-danger rounded-lg border px-4 py-2 text-sm font-semibold transition-colors disabled:opacity-60 cursor-pointer"
                >
                    <span wire:loading.remove wire:target="deleteJob">Delete Job</span>
                    <span wire:loading wire:target="deleteJob">Deleting…</span>
                </button>
            </div>
        </div>
    </div>
</div>
