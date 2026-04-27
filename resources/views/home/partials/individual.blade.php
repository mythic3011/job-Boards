@php
    $user = auth()->user();
@endphp

<div class="space-y-8">
    <div class="theme-hero-surface rounded-3xl border px-6 py-7 sm:px-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="theme-hero-eyebrow text-xs font-semibold uppercase tracking-[0.18em]">Candidate Dashboard</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight">Career Dashboard</h1>
                <p class="theme-text-muted mt-3 text-sm leading-6">
                    Track where decisions are stalling and move into profile tasks from here.
                </p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <x-ui.button href="{{ route('my.applications.index') }}">My Applications</x-ui.button>
                    <x-ui.button href="{{ route('jobs.index') }}" variant="outline">Browse Roles</x-ui.button>
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

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(300px,0.95fr)]">
        <div class="space-y-6">
            <section>
                <x-ui.section-label class="mb-4">Application Pipeline</x-ui.section-label>
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach($applicationPipeline as $item)
                        <x-ui.card tone="subtle" padding="p-5">
                            <p class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">{{ $item['label'] }}</p>
                            <p class="theme-text-strong mt-3 text-3xl font-semibold">{{ number_format($item['value']) }}</p>
                            <p class="theme-text-muted mt-2 text-sm">{{ $item['description'] }}</p>
                        </x-ui.card>
                    @endforeach
                </div>
            </section>

            <section>
                <div class="mb-4 flex items-center justify-between gap-4">
                    <x-ui.section-label>Recent Applications</x-ui.section-label>
                    <x-ui.button href="{{ route('my.applications.index') }}" variant="outline" size="sm">View all</x-ui.button>
                </div>

                @if($recentApplications->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($recentApplications as $application)
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
                                        <p class="theme-text-strong truncate text-sm font-semibold">{{ $application->jobPosting?->title }}</p>
                                        <p class="theme-text-muted mt-0.5 truncate text-xs">{{ $application->jobPosting?->companyUser?->nickname }}</p>
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
                        title="No applications yet"
                        message="Browse jobs and apply to start tracking your pipeline here."
                    >
                        <x-slot:icon><x-heroicon-o-document-text class="h-10 w-10" /></x-slot:icon>
                        <x-slot:action>
                            <x-ui.button href="{{ route('jobs.index') }}">Browse jobs</x-ui.button>
                        </x-slot:action>
                    </x-ui.empty-state>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            <section>
                <x-ui.section-label class="mb-4">Account</x-ui.section-label>
                <x-ui.card padding="p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="theme-text-strong truncate text-sm font-semibold">{{ $user?->nickname }}</p>
                            <p class="theme-text-muted mt-0.5 truncate text-xs">{{ $user?->email }}</p>
                        </div>
                        @if($twoFactorEnabled)
                            <span class="theme-alert-success inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold">2FA on</span>
                        @else
                            <span class="theme-alert-warning inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold">2FA off</span>
                        @endif
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-ui.button href="{{ route('profile.show') }}" variant="outline" size="sm">Profile</x-ui.button>
                        <x-ui.button href="{{ route('profile.two-factor') }}" size="sm">Security</x-ui.button>
                    </div>
                </x-ui.card>
            </section>

            <section>
                <x-ui.section-label class="mb-4">Fresh Roles</x-ui.section-label>
                <div class="space-y-2">
                    @foreach($recommendedJobs as $job)
                        <a href="{{ route('jobs.show', $job->idcode) }}" class="block">
                            <x-ui.card tone="subtle" padding="p-4" hover="true">
                                <p class="theme-text-strong truncate text-sm font-semibold">{{ $job->title }}</p>
                                <div class="mt-2 flex items-center justify-between gap-3 text-xs">
                                    <span class="theme-text-muted truncate">{{ $job->companyUser?->nickname }}</span>
                                    <span class="theme-text-muted shrink-0">{{ $job->created_at->diffForHumans() }}</span>
                                </div>
                            </x-ui.card>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</div>
