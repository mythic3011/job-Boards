<x-layouts.base :title="'Login'" :show-header="false">
    <x-auth.shell
        title="Sign in to your account"
        subtitle="Use your username or email to continue with your secured workspace."
        max-width="max-w-5xl"
    >
        <x-slot:icon>
            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
            </svg>
        </x-slot:icon>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(300px,0.9fr)] lg:items-start">
            <x-ui.card padding="p-8">
                <form class="space-y-6" action="{{ route('login') }}" method="POST">
                    @csrf
                    <x-honeypot />

                    <x-ui.input
                        id="login_id"
                        name="login_id"
                        label="Username or Email"
                        type="text"
                        autocomplete="username"
                        placeholder="Enter your username or email"
                        :required="true"
                        :value="old('login_id')"
                        autofocus
                    />

                    <x-ui.input
                        id="password"
                        name="password"
                        label="Password"
                        type="password"
                        autocomplete="current-password"
                        :required="true"
                    />

                    <div class="flex items-center justify-between gap-4">
                        <label for="remember" class="flex cursor-pointer items-center gap-2">
                            <input
                                id="remember"
                                name="remember"
                                type="checkbox"
                                class="h-4 w-4 rounded border-[var(--app-panel-border)] text-[var(--app-accent-strong)] focus:ring-[var(--app-accent-soft)]"
                            >
                            <span class="theme-text-muted text-sm">Remember me on this device</span>
                        </label>
                        <a href="{{ route('password.request') }}" class="theme-link text-sm font-medium">
                            Forgot password?
                        </a>
                    </div>

                    <x-ui.button type="submit" variant="primary" class="w-full justify-center">
                        Sign in
                    </x-ui.button>
                </form>
            </x-ui.card>

            <div class="space-y-4">
                <x-ui.card tone="subtle" padding="p-6">
                    <x-ui.section-label class="mb-2">Access</x-ui.section-label>
                    <h2 class="theme-text-strong text-xl font-semibold">Workspace Access</h2>
                    <div class="mt-4 space-y-3">
                        <div class="rounded-2xl border border-[var(--app-panel-border)] bg-[var(--app-panel-bg)] px-4 py-4">
                            <p class="theme-text-strong text-sm font-semibold">Candidates</p>
                            <p class="theme-text-muted mt-1 text-sm leading-6">Resume your application pipeline, profile workspace, and security settings from one place.</p>
                        </div>
                        <div class="rounded-2xl border border-[var(--app-panel-border)] bg-[var(--app-panel-bg)] px-4 py-4">
                            <p class="theme-text-strong text-sm font-semibold">Companies</p>
                            <p class="theme-text-muted mt-1 text-sm leading-6">Get back to listings, inbound applicants, and hiring tasks without jumping between screens.</p>
                        </div>
                        <div class="rounded-2xl border border-[var(--app-panel-border)] bg-[var(--app-panel-bg)] px-4 py-4">
                            <p class="theme-text-strong text-sm font-semibold">Admins</p>
                            <p class="theme-text-muted mt-1 text-sm leading-6">Use the same entry point, then move directly into operational and audit surfaces.</p>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card padding="p-6">
                    <x-ui.section-label class="mb-2">Security</x-ui.section-label>
                    <h2 class="theme-text-strong text-xl font-semibold">Security Notes</h2>
                    <ul class="theme-text-muted mt-4 space-y-3 text-sm leading-6">
                        <li class="flex gap-3">
                            <span class="mt-1 h-2 w-2 rounded-full bg-[var(--app-accent-strong)]"></span>
                            <span>Password recovery and protected changes stay inside the same secured auth workflow.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-1 h-2 w-2 rounded-full bg-[var(--app-accent-strong)]"></span>
                            <span>Use “Remember me” only on devices you control.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-1 h-2 w-2 rounded-full bg-[var(--app-accent-strong)]"></span>
                            <span>Two-factor protected accounts keep sensitive recovery and password flows locked to verified sessions.</span>
                        </li>
                    </ul>
                </x-ui.card>
            </div>
        </div>

        <x-slot:footer>
            Don't have an account?
            <a href="{{ route('register') }}" class="theme-link font-medium">
                Sign up now
            </a>
        </x-slot:footer>
    </x-auth.shell>
</x-layouts.base>
