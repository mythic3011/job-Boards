<x-layouts.base title="System Maintenance">
    <div class="flex min-h-[75vh] items-center justify-center px-4 py-12">
        <div class="w-full max-w-lg">
            <div class="mb-8 text-center">
                <div class="theme-alert-error inline-flex h-20 w-20 items-center justify-center rounded-full border shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
            </div>

            <div class="theme-panel overflow-hidden rounded-[2rem] border">
                <div class="theme-alert-error border-b px-6 py-7 sm:px-8">
                    <h1 class="theme-text-strong text-2xl font-bold">System Maintenance</h1>
                    <p class="theme-text-muted mt-2 text-sm">We&rsquo;re temporarily offline while operational updates are in progress.</p>
                </div>

                <div class="space-y-6 px-6 py-8 sm:px-8">
                    <p class="theme-text-muted text-center leading-relaxed">
                        We&rsquo;re currently performing scheduled maintenance to improve platform stability and security. Please check back shortly.
                    </p>

                    <div class="flex justify-center">
                        <div class="flex gap-1">
                            <div class="h-2.5 w-2.5 rounded-full bg-[var(--app-danger-fg)] animate-bounce" style="animation-delay: 0s;"></div>
                            <div class="h-2.5 w-2.5 rounded-full bg-[var(--app-danger-fg)] animate-bounce" style="animation-delay: 0.2s;"></div>
                            <div class="h-2.5 w-2.5 rounded-full bg-[var(--app-danger-fg)] animate-bounce" style="animation-delay: 0.4s;"></div>
                        </div>
                    </div>

                    <div class="theme-alert-info rounded-2xl border p-4">
                        <div class="flex gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="theme-text-strong text-sm font-medium">What&rsquo;s happening?</p>
                                <p class="theme-text-muted mt-1 text-sm">
                                    Backups, dependency updates, and system checks are running. The platform will return once those tasks complete safely.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('home') }}" class="theme-button theme-button-primary inline-flex flex-1 items-center justify-center rounded-lg border px-4 py-2.5 text-sm font-medium">
                            Return to Home
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="flex-1">
                            @csrf
                            <button type="submit" class="theme-button theme-button-outline inline-flex w-full items-center justify-center rounded-lg border px-4 py-2.5 text-sm font-medium cursor-pointer">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <p class="theme-text-muted mt-6 text-center text-sm">Estimated completion time: Less than an hour</p>
        </div>
    </div>
</x-layouts.base>
