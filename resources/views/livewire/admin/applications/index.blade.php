<?php

use App\Models\Application;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Admin - Applications');

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $companyFilter = '';
    public string $sort = 'latest';

    private const ALLOWED_STATUSES = ['', 'pending', 'approved', 'rejected'];
    private const ALLOWED_SORTS    = ['latest', 'oldest'];

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedCompanyFilter(): void { $this->resetPage(); }

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

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->companyFilter = '';
        $this->sort = 'latest';
        $this->resetPage();
    }

    public function with(): array
    {
        $query = Application::with(['jobPosting.companyUser', 'applicantUser'])->latest();

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

        $companies = User::where('user_type', 'company')
            ->orderBy('nickname')
            ->get(['id', 'nickname']);

        return [
            'applications' => $query->paginate(15),
            'companies'    => $companies,
        ];
    }
}; ?>

<div>
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Applications Management</h1>
            <p class="mt-1 text-sm text-gray-500">Review and manage all submitted applications</p>
        </div>
    </div>

    {{-- Search + filter bar --}}
    <div class="mb-6 flex flex-wrap gap-3">
        {{-- Search --}}
        <div class="relative flex-1 min-w-48">
            <div class="flex items-center gap-3 rounded-lg border border-gray-300 bg-white px-4 py-2.5 shadow-sm transition-all duration-150 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-100">
                <svg style="width:18px;height:18px;flex-shrink:0;color:#9ca3af" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    type="text"
                    wire:model.live.debounce.250ms="search"
                    placeholder="Search by job title or applicant…"
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

        <select wire:model.live="companyFilter" class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all duration-150 cursor-pointer">
            <option value="">All companies</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}">{{ $company->nickname }}</option>
            @endforeach
        </select>

        <select wire:model.live="statusFilter" class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all duration-150 cursor-pointer">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        </select>

        <select wire:model.live="sort" class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all duration-150 cursor-pointer">
            <option value="latest">Newest first</option>
            <option value="oldest">Oldest first</option>
        </select>

        @if($search || $statusFilter || $companyFilter || $sort !== 'latest')
            <button wire:click="clearFilters" class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-500 shadow-sm transition-colors hover:bg-gray-50 hover:text-gray-700 cursor-pointer">
                Clear all
            </button>
        @endif
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

        <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50/70 px-6 py-4">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-600">All Applications</h2>
            <span class="rounded-full border border-indigo-100 bg-indigo-50 px-3 py-0.5 text-xs font-semibold text-indigo-600">
                {{ $applications->total() }} {{ \Illuminate\Support\Str::plural('application', $applications->total()) }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/40">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Job</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Applicant</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">CV</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Submitted</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($applications as $application)
                        @php
                            $statusValue = $application->status->value;
                            $statusLabel = match($statusValue) {
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                default    => 'Pending',
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
                        <tr class="group transition-colors duration-150 hover:bg-slate-50/60">
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900 transition-colors group-hover:text-indigo-700">
                                    {{ $application->jobPosting->title }}
                                </div>
                                <div class="mt-0.5 text-xs text-gray-500">{{ $application->jobPosting->companyUser->nickname }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                {{ $application->applicantUser->nickname }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $statusClasses }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses }}"></span>
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($application->cv_original_name)
                                    <span class="flex items-center gap-1.5">
                                        <svg style="width:14px;height:14px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        {{ $application->cv_original_name }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $application->created_at->diffForHumans() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.applications.show', $application->idcode) }}"
                                       class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 transition-all duration-150 hover:border-gray-300 hover:bg-gray-50 cursor-pointer">
                                        View
                                    </a>
                                    @if($application->cv_original_name)
                                        <a href="{{ route('applications.download-cv', $application->idcode) }}"
                                           class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition-all duration-150 hover:border-indigo-300 hover:bg-indigo-100 cursor-pointer">
                                            Download CV
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="rounded-full bg-gray-100 p-4">
                                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-500">No applications found</p>
                                    @if($search || $statusFilter || $companyFilter)
                                        <p class="text-xs text-gray-400">Try adjusting your search or filters</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-6 py-4">
            {{ $applications->links() }}
        </div>
    </div>
</div>
