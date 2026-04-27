@php
    $user = auth()->user();
@endphp

<div class="space-y-8">
    <div class="theme-hero-surface rounded-3xl border px-6 py-7 sm:px-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="theme-hero-eyebrow text-xs font-semibold uppercase tracking-[0.18em]">Company Dashboard</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight">Hiring Dashboard</h1>
                <p class="theme-text-muted mt-3 text-sm leading-6">
                    Review active listings and respond to candidates without leaving the queue.
                </p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <x-ui.button href="{{ route('jobs.create') }}">Post a Job</x-ui.button>
                    <x-ui.button href="{{ route('my.applications.index') }}" variant="outline">Review Applications</x-ui.button>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($summaryCards as $card)
                    <div class="theme-pill inline-flex flex-col items-center rounded-2xl border px-4 py-2.5 text-center">
                        <span class="theme-text-strong text-xl font-semibold">{{ $card['value'] }}</span>
                        <span class="theme-text-muted text-xs">{{ $card['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(300px,0.95fr)]">
        <div class="space-y-6">
            <section>
                <div class="mb-4 flex items-center justify-between gap-4">
                    <x-ui.section-label>Response Queue</x-ui.section-label>
                    <x-ui.button href="{{ route('my.applications.index') }}" variant="outline" size="sm">Open queue</x-ui.button>
                </div>

                @if($responseQueue->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($responseQueue as $application)
                            @php
                                $statusTone = match($application->status->value) {
                                    'approved' => 'theme-alert-success',
                                    'rejected' => 'theme-alert-error',
                                    default => 'theme-alert-warning',
                                };
                            @endphp
                            <x-ui.card padding="p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="theme-text-strong truncate text-sm font-semibold">{{ $application->applicantUser?->nickname }}</p>
                                        <p class="theme-text-muted mt-0.5 truncate text-xs">{{ $application->jobPosting?->title }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-3">
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $statusTone }}">{{ ucfirst($application->status->value) }}</span>
                                        <span class="theme-text-muted whitespace-nowrap text-xs">{{ $application->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </x-ui.card>
                        @endforeach
                    </div>
                @else
                    <x-ui.empty-state
                        title="No candidates in queue"
                        message="Applications from candidates will show up here once your listings start receiving responses."
                    >
                        <x-slot:icon><x-heroicon-o-users class="h-10 w-10" /></x-slot:icon>
                    </x-ui.empty-state>
                @endif
            </section>

            <section>
                <x-ui.section-label class="mb-4">Active Listings</x-ui.section-label>
                @if($activeListings->isNotEmpty())
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach($activeListings as $job)
                            <a href="{{ route('jobs.show', $job->idcode) }}" class="block">
                                <x-ui.card tone="subtle" padding="p-4" hover="true">
                                    <p class="theme-text-strong truncate text-sm font-semibold">{{ $job->title }}</p>
                                    <div class="mt-3 flex items-center justify-between gap-3 text-xs">
                                        <span class="theme-text-strong font-semibold">{{ number_format($job->applications_count) }} applicants</span>
                                        <span class="theme-text-muted">{{ $job->created_at->diffForHumans() }}</span>
                                    </div>
                                </x-ui.card>
                            </a>
                        @endforeach
                    </div>
                @else
                    <x-ui.empty-state
                        title="No active listings yet"
                        message="Publish your first role to start collecting applicants."
                    >
                        <x-slot:icon><x-heroicon-o-briefcase class="h-10 w-10" /></x-slot:icon>
                        <x-slot:action>
                            <x-ui.button href="{{ route('jobs.create') }}">Create a listing</x-ui.button>
                        </x-slot:action>
                    </x-ui.empty-state>
                @endif
            </section>
        </div>

        <section>
            <x-ui.section-label class="mb-4">Quick Actions</x-ui.section-label>
            <div class="space-y-2">
                @foreach($operationalChecklist as $item)
                    <a href="{{ $item['href'] }}" class="block">
                        <x-ui.card padding="p-4" hover="true">
                            <div class="flex items-center justify-between gap-4">
                                <p class="theme-text-strong text-sm font-semibold">{{ $item['title'] }}</p>
                                <span class="theme-link shrink-0 text-xs font-medium">{{ $item['action'] }}</span>
                            </div>
                        </x-ui.card>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</div>
