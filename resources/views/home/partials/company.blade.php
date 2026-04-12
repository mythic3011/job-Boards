@php
    $user = auth()->user();
@endphp

<div class="space-y-8">
    <section class="rounded-[2rem] border border-[var(--app-panel-border)] bg-[radial-gradient(circle_at_top_right,_rgba(16,185,129,0.14),_transparent_38%),linear-gradient(135deg,var(--app-panel-bg),var(--app-panel-subtle-bg))] px-6 py-8 shadow-sm sm:px-8">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.95fr)] lg:items-end">
            <div>
                <p class="theme-text-muted text-xs font-semibold uppercase tracking-[0.22em]">Company Workspace</p>
                <h1 class="theme-text-strong mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Hiring Dashboard</h1>
                <p class="theme-text-muted mt-3 max-w-2xl text-sm leading-6 sm:text-base">
                    Keep active listings visible, pull candidate decisions out of backlog, and route straight into the surfaces that keep the hiring funnel moving.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <x-ui.button href="{{ route('jobs.create') }}" size="lg">Post a Job</x-ui.button>
                    <x-ui.button href="{{ route('my.applications.index') }}" variant="outline" size="lg">Review Applications</x-ui.button>
                </div>
            </div>

            <div class="grid gap-3">
                @foreach($summaryCards as $card)
                    <div class="theme-panel rounded-2xl border px-4 py-4">
                        <p class="theme-text-muted text-xs uppercase tracking-[0.16em]">{{ $card['label'] }}</p>
                        <p class="theme-text-strong mt-3 text-3xl font-semibold">{{ $card['value'] }}</p>
                        <p class="theme-text-muted mt-2 text-sm">{{ $card['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.95fr)]">
        <div class="space-y-6">
            <section>
                <div class="mb-4 flex items-end justify-between gap-4">
                    <div>
                        <x-ui.section-label class="mb-2">Queue</x-ui.section-label>
                        <h2 class="theme-text-strong text-2xl font-semibold">Response Queue</h2>
                        <p class="theme-text-muted mt-1 text-sm">The latest candidates waiting for attention across your listings.</p>
                    </div>
                    <x-ui.button href="{{ route('my.applications.index') }}" variant="outline" size="sm">Open queue</x-ui.button>
                </div>

                @if($responseQueue->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($responseQueue as $application)
                            @php
                                $statusTone = match($application->status->value) {
                                    'approved' => 'border-green-200 bg-green-50 text-green-700',
                                    'rejected' => 'border-red-200 bg-red-50 text-red-700',
                                    default => 'border-amber-200 bg-amber-50 text-amber-700',
                                };
                            @endphp
                            <x-ui.card padding="p-5">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="theme-text-strong text-lg font-semibold">{{ $application->applicantUser?->nickname }}</p>
                                        <p class="theme-text-muted mt-1 text-sm">{{ $application->jobPosting?->title }}</p>
                                        <p class="theme-text-muted mt-3 text-sm leading-6">{{ \Illuminate\Support\Str::limit($application->message ?? 'No message attached.', 150) }}</p>
                                    </div>
                                    <div class="flex flex-col items-start gap-2 sm:items-end">
                                        <span class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] {{ $statusTone }}">
                                            {{ $application->status->value }}
                                        </span>
                                        <span class="theme-text-muted text-xs">{{ $application->created_at->diffForHumans() }}</span>
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
                        <x-slot:icon>
                            <x-heroicon-o-users class="h-10 w-10" />
                        </x-slot:icon>
                    </x-ui.empty-state>
                @endif
            </section>

            <section>
                <div class="mb-4">
                    <x-ui.section-label class="mb-2">Listings</x-ui.section-label>
                    <h2 class="theme-text-strong text-2xl font-semibold">Active Listings</h2>
                    <p class="theme-text-muted mt-1 text-sm">The roles currently representing your company in the marketplace.</p>
                </div>

                @if($activeListings->isNotEmpty())
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach($activeListings as $job)
                            <a href="{{ route('jobs.show', $job->idcode) }}" class="block">
                                <x-ui.card tone="subtle" padding="p-5" hover="true">
                                    <p class="theme-text-strong text-base font-semibold">{{ $job->title }}</p>
                                    <p class="theme-text-muted mt-1 text-sm">{{ $user?->nickname }}</p>
                                    <div class="mt-4 flex items-center justify-between gap-3 text-xs">
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
                        message="Publish your first role to start collecting applicants and building the response queue."
                    >
                        <x-slot:icon>
                            <x-heroicon-o-briefcase class="h-10 w-10" />
                        </x-slot:icon>
                        <x-slot:action>
                            <x-ui.button href="{{ route('jobs.create') }}">Create a listing</x-ui.button>
                        </x-slot:action>
                    </x-ui.empty-state>
                @endif
            </section>
        </div>

        <section>
            <div class="mb-4">
                <x-ui.section-label class="mb-2">Ops</x-ui.section-label>
                <h2 class="theme-text-strong text-2xl font-semibold">Operational Checklist</h2>
                <p class="theme-text-muted mt-1 text-sm">The three surfaces that keep hiring throughput and account posture under control.</p>
            </div>

            <div class="space-y-3">
                @foreach($operationalChecklist as $item)
                    <a href="{{ $item['href'] }}" class="block">
                        <x-ui.card padding="p-5" hover="true">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="theme-text-strong text-base font-semibold">{{ $item['title'] }}</p>
                                    <p class="theme-text-muted mt-2 text-sm leading-6">{{ $item['description'] }}</p>
                                </div>
                                <span class="theme-link text-sm font-medium">{{ $item['action'] }}</span>
                            </div>
                        </x-ui.card>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</div>
