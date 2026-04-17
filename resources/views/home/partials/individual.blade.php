@php
    $user = auth()->user();
    $securityTone = $twoFactorEnabled
        ? 'border-green-200 bg-green-50 text-green-700'
        : 'border-amber-200 bg-amber-50 text-amber-700';
@endphp

<div class="space-y-8">
    <section class="rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 px-6 py-8 text-white shadow-xl shadow-slate-900/10 sm:px-8">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.95fr)] lg:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-indigo-200/80">Candidate Workspace</p>
                <h1 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Career Dashboard</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                    Keep your application momentum visible, spot where decisions are stalling, and route directly into profile and security tasks without leaving the workspace.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <x-ui.button href="{{ route('my.applications.index') }}" size="lg">Open My Applications</x-ui.button>
                    <x-ui.button href="{{ route('jobs.index') }}" variant="outline" size="lg">Browse New Roles</x-ui.button>
                </div>
            </div>

            <div class="grid gap-3">
                @foreach($summaryCards as $card)
                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4 backdrop-blur-sm">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-400">{{ $card['label'] }}</p>
                        <p class="mt-3 text-3xl font-semibold">{{ $card['value'] }}</p>
                        <p class="mt-2 text-sm text-slate-300">{{ $card['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.95fr)]">
        <div class="space-y-6">
            <section>
                <div class="mb-4">
                    <x-ui.section-label class="mb-2">Pipeline</x-ui.section-label>
                    <h2 class="theme-text-strong text-2xl font-semibold">Application Pipeline</h2>
                    <p class="theme-text-muted mt-1 text-sm">The current state of everything you have already sent into the market.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    @foreach($applicationPipeline as $item)
                        <x-ui.card tone="subtle" padding="p-5">
                            <p class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">{{ $item['label'] }}</p>
                            <p class="theme-text-strong mt-3 text-3xl font-semibold">{{ number_format($item['value']) }}</p>
                            <p class="theme-text-muted mt-2 text-sm leading-6">{{ $item['description'] }}</p>
                        </x-ui.card>
                    @endforeach
                </div>
            </section>

            <section>
                <div class="mb-4 flex items-end justify-between gap-4">
                    <div>
                        <x-ui.section-label class="mb-2">Recent Work</x-ui.section-label>
                        <h2 class="theme-text-strong text-2xl font-semibold">Recent Applications</h2>
                        <p class="theme-text-muted mt-1 text-sm">The latest decisions and in-flight roles connected to your account.</p>
                    </div>
                    <x-ui.button href="{{ route('my.applications.index') }}" variant="outline" size="sm">View all</x-ui.button>
                </div>

                @if($recentApplications->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($recentApplications as $application)
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
                                        <p class="theme-text-strong text-lg font-semibold">{{ $application->jobPosting?->title }}</p>
                                        <p class="theme-text-muted mt-1 text-sm">{{ $application->jobPosting?->companyUser?->nickname }}</p>
                                        @if($application->message)
                                            <p class="theme-text-muted mt-3 text-sm leading-6">{{ \Illuminate\Support\Str::limit($application->message, 150) }}</p>
                                        @endif
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
                        title="No applications yet"
                        message="Start with a fresh role so the dashboard can track your pipeline and decisions."
                    >
                        <x-slot:icon>
                            <x-heroicon-o-document-text class="h-10 w-10" />
                        </x-slot:icon>
                        <x-slot:action>
                            <x-ui.button href="{{ route('jobs.index') }}">Browse jobs</x-ui.button>
                        </x-slot:action>
                    </x-ui.empty-state>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            <section>
                <div class="mb-4">
                    <x-ui.section-label class="mb-2">Account</x-ui.section-label>
                    <h2 class="theme-text-strong text-2xl font-semibold">Security Checkpoint</h2>
                    <p class="theme-text-muted mt-1 text-sm">Keep profile identity and recovery posture aligned with the application workflow.</p>
                </div>

                <x-ui.card padding="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="theme-text-strong text-lg font-semibold">{{ $user?->nickname }}</p>
                            <p class="theme-text-muted mt-1 text-sm">{{ $user?->email }}</p>
                        </div>
                        <span class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] {{ $securityTone }}">
                            {{ $twoFactorEnabled ? '2FA ready' : '2FA recommended' }}
                        </span>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div class="theme-panel-subtle rounded-2xl border p-4">
                            <p class="theme-text-strong text-sm font-semibold">Protected password flow</p>
                            <p class="theme-text-muted mt-1 text-sm leading-6">
                                {{ $twoFactorEnabled ? 'Unlocked. You can move through password management inside the secured profile workspace.' : 'Still gated. Confirm two-factor authentication first to unlock password changes.' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <x-ui.button href="{{ route('profile.show') }}" variant="outline">Profile workspace</x-ui.button>
                            <x-ui.button href="{{ route('profile.two-factor') }}">Security settings</x-ui.button>
                        </div>
                    </div>
                </x-ui.card>
            </section>

            <section>
                <div class="mb-4">
                    <x-ui.section-label class="mb-2">Fresh Roles</x-ui.section-label>
                    <h2 class="theme-text-strong text-2xl font-semibold">Roles Worth Reviewing</h2>
                    <p class="theme-text-muted mt-1 text-sm">New opportunities to feed back into your pipeline.</p>
                </div>

                <div class="space-y-3">
                    @foreach($recommendedJobs as $job)
                        <a href="{{ route('jobs.show', $job->idcode) }}" class="block">
                            <x-ui.card tone="subtle" padding="p-5" hover="true">
                                <p class="theme-text-strong text-base font-semibold">{{ $job->title }}</p>
                                <p class="theme-text-muted mt-1 text-sm">{{ $job->companyUser?->nickname }}</p>
                                <div class="mt-3 flex items-center justify-between gap-3 text-xs">
                                    <span class="theme-text-strong font-semibold">{{ $job->salary ?? 'Compensation on request' }}</span>
                                    <span class="theme-text-muted">{{ $job->created_at->diffForHumans() }}</span>
                                </div>
                            </x-ui.card>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</div>
