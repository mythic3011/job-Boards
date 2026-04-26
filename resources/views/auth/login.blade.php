<x-layouts.base :title="'Login'" :show-header="false">
    <x-auth.shell
        title="Sign in to your account"
        subtitle="Use your username or email to continue with your secured workspace."
        max-width="max-w-3xl"
    >
        <x-slot:icon>
            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
            </svg>
        </x-slot:icon>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(260px,0.75fr)] lg:items-start">
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

            <x-ui.card tone="subtle" padding="p-6">
                <x-ui.section-label class="mb-2">Need Help?</x-ui.section-label>
                <h2 class="theme-text-strong text-lg font-semibold">Sign-In Quick Notes</h2>
                <ul class="theme-text-muted mt-4 space-y-3 text-sm leading-6">
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-[var(--app-accent-strong)]"></span>
                        <span>Use your username or account email.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-[var(--app-accent-strong)]"></span>
                        <span>Use “Remember me” only on a trusted device.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-[var(--app-accent-strong)]"></span>
                        <span>If access fails, reset password before trying repeated sign-ins.</span>
                    </li>
                </ul>
                <div class="mt-5">
                    <a href="{{ route('password.request') }}" class="theme-link text-sm font-medium">
                        Open password recovery
                    </a>
                </div>
            </x-ui.card>
        </div>

        <x-slot:footer>
            Don't have an account?
            <a href="{{ route('register') }}" class="theme-link font-medium">
                Sign up now
            </a>
        </x-slot:footer>
    </x-auth.shell>
</x-layouts.base>
