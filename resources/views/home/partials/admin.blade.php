<div class="space-y-8">
    <section class="rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 px-6 py-8 text-white shadow-xl shadow-slate-900/10 sm:px-8">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.25fr)_minmax(300px,0.95fr)] lg:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-200/80">Admin Workspace</p>
                <h1 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Admin Control Room</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                    Home stays lightweight here: use it as the handoff surface into the operational dashboard and the admin modules that carry real platform workload.
                </p>
                @if($primaryAdminLink)
                    <div class="mt-6 flex flex-wrap gap-3">
                        <x-ui.button href="{{ $primaryAdminLink['href'] }}" size="lg">{{ $primaryAdminLink['label'] }}</x-ui.button>
                        @if($secondaryAdminLink)
                            <x-ui.button href="{{ $secondaryAdminLink['href'] }}" variant="outline" size="lg">{{ $secondaryAdminLink['label'] }}</x-ui.button>
                        @endif
                    </div>
                @else
                    <div class="mt-6 rounded-2xl border border-white/12 bg-white/5 px-4 py-4">
                        <p class="text-sm font-semibold">No admin modules are assigned yet</p>
                        <p class="mt-2 text-sm leading-6 text-slate-300">Ask another administrator to grant the admin scopes you need before using protected operational surfaces.</p>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 px-5 py-5 backdrop-blur-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Security Continuity</p>
                <p class="mt-3 text-lg font-semibold">Use `home` as a quick launch point, not as a duplicate of the full operational snapshot.</p>
                <p class="mt-3 text-sm leading-6 text-slate-300">Deep telemetry, risk review, and queue detail stay inside the dedicated admin dashboard and its linked modules.</p>
            </div>
        </div>
    </section>

    <section>
        <div class="mb-4">
            <x-ui.section-label class="mb-2">Entry Points</x-ui.section-label>
            <h2 class="theme-text-strong text-2xl font-semibold">Operational Surfaces</h2>
            <p class="theme-text-muted mt-1 text-sm">Fast access to the admin modules you are most likely to reach from the global home route.</p>
        </div>

        @if(! empty($remainingAdminLinks))
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach($remainingAdminLinks as $link)
                    <a href="{{ $link['href'] }}" class="block">
                        <x-ui.card padding="p-5" hover="true" class="h-full">
                            <p class="theme-text-strong text-base font-semibold">{{ $link['label'] }}</p>
                            <p class="theme-text-muted mt-2 text-sm leading-6">{{ $link['description'] }}</p>
                        </x-ui.card>
                    </a>
                @endforeach
            </div>
        @elseif($primaryAdminLink)
            <div class="theme-panel-subtle rounded-3xl border px-5 py-5">
                <p class="theme-text-strong text-sm font-semibold">Primary launch surfaces are already pinned above.</p>
                <p class="theme-text-muted mt-2 text-sm leading-6">This admin scope currently exposes a narrow set of protected modules, so the hero actions stay as the canonical handoff.</p>
            </div>
        @else
            <div class="theme-panel-subtle rounded-3xl border px-5 py-5">
                <p class="theme-text-strong text-sm font-semibold">Admin access is currently unscoped.</p>
                <p class="theme-text-muted mt-2 text-sm leading-6">Protected admin links stay hidden here until your account is granted at least one admin surface permission.</p>
            </div>
        @endif
    </section>
</div>
