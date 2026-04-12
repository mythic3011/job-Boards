<div class="space-y-10">
    <section class="overflow-hidden rounded-[2rem] border border-[var(--app-panel-border)] bg-[radial-gradient(circle_at_top_left,_rgba(99,102,241,0.16),_transparent_40%),linear-gradient(135deg,var(--app-panel-bg),var(--app-panel-subtle-bg))] px-6 py-10 shadow-sm sm:px-10">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1.3fr)_minmax(300px,0.9fr)] lg:items-end">
            <div class="max-w-3xl">
                <p class="theme-text-muted text-xs font-semibold uppercase tracking-[0.24em]">Jobs Board</p>
                <h1 class="theme-text-strong mt-4 text-4xl font-semibold tracking-tight sm:text-5xl">Find Your Dream Job</h1>
                <p class="theme-text-muted mt-4 max-w-2xl text-base leading-7 sm:text-lg">
                    Discover credible roles, move applications through a calmer workflow, and keep profile and security posture in one workspace.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <x-ui.button href="{{ route('jobs.index') }}" size="lg">Browse Jobs</x-ui.button>
                    @guest
                        <x-ui.button href="{{ route('register') }}" variant="outline" size="lg">Get Started</x-ui.button>
                    @else
                        <x-ui.button href="{{ route('home') }}" variant="outline" size="lg">Open Dashboard</x-ui.button>
                    @endguest
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-1">
                <x-ui.card tone="subtle" padding="p-5">
                    <p class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">For Candidates</p>
                    <p class="theme-text-strong mt-3 text-lg font-semibold">Track every application without losing momentum.</p>
                </x-ui.card>
                <x-ui.card tone="subtle" padding="p-5">
                    <p class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">For Companies</p>
                    <p class="theme-text-strong mt-3 text-lg font-semibold">Keep listings, applicants, and response cadence in one queue.</p>
                </x-ui.card>
                <x-ui.card tone="subtle" padding="p-5">
                    <p class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">Security-first</p>
                    <p class="theme-text-strong mt-3 text-lg font-semibold">Authentication and profile flows stay inside the same hardened shell.</p>
                </x-ui.card>
            </div>
        </div>
    </section>

    <section>
        <div class="mb-5 flex items-end justify-between gap-4">
            <div>
                <x-ui.section-label class="mb-2">Marketplace Pulse</x-ui.section-label>
                <h2 class="theme-text-strong text-2xl font-semibold">Recent Job Postings</h2>
                <p class="theme-text-muted mt-1 text-sm">Fresh roles landing in the board right now.</p>
            </div>
            <x-ui.button href="{{ route('jobs.index') }}" variant="outline" size="sm">View all jobs</x-ui.button>
        </div>

        @if($recentJobs->isNotEmpty())
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($recentJobs as $job)
                    <a href="{{ route('jobs.show', $job->idcode) }}" class="group block">
                        <x-ui.card hover="true" padding="p-6" class="h-full">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="theme-text-strong text-lg font-semibold leading-6 group-hover:text-[var(--app-accent-strong)]">{{ $job->title }}</p>
                                    <p class="theme-text-muted mt-1 text-sm">{{ $job->companyUser?->nickname }}</p>
                                </div>
                                <span class="theme-pill rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em]">Fresh</span>
                            </div>
                            <p class="theme-text-muted mt-4 text-sm leading-6">{{ \Illuminate\Support\Str::limit($job->requirement, 135) }}</p>
                            <div class="mt-5 flex items-center justify-between gap-3 text-xs">
                                <span class="theme-text-strong font-semibold">{{ $job->salary ?? 'Compensation on request' }}</span>
                                <span class="theme-text-muted">{{ $job->created_at->diffForHumans() }}</span>
                            </div>
                        </x-ui.card>
                    </a>
                @endforeach
            </div>
        @else
            <x-ui.empty-state
                title="No recent listings yet"
                message="Once companies publish roles, the latest postings will appear here."
            >
                <x-slot:icon>
                    <x-heroicon-o-briefcase class="h-10 w-10" />
                </x-slot:icon>
            </x-ui.empty-state>
        @endif
    </section>
</div>
