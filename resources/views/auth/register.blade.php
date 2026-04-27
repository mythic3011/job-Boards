@php
    use App\Models\Setting;

    $registrationsOpen = Setting::getBool('registrations_open', true);
    $selectedUserType = old('user_type', 'individual');
@endphp

<x-layouts.base :title="'Register'" :show-header="false">
    <x-auth.shell
        title="Create your account"
        subtitle="Pick the account type that matches how you will use the platform."
        max-width="max-w-3xl"
    >
        <x-slot:icon>
            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 0 1 8 0ZM3 20a6 6 0 0 1 12 0v1H3v-1Z" />
            </svg>
        </x-slot:icon>

        @if(!$registrationsOpen)
            <x-ui.card padding="p-8 sm:p-10">
                <div class="space-y-6 text-center">
                    <div class="theme-auth-emblem mx-auto flex h-20 w-20 items-center justify-center rounded-full">
                        <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm10-10V7a4 4 0 1 0-8 0v4h8Z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="theme-text-strong text-xl font-bold">Registrations Temporarily Closed</h2>
                        <p class="theme-text-muted mt-2 text-sm leading-6">
                            We are not accepting new registrations right now. Existing accounts can still sign in and continue working.
                        </p>
                    </div>
                    <div class="flex justify-center">
                        <x-ui.button href="{{ route('login') }}" variant="primary">
                            Sign In to Continue
                        </x-ui.button>
                    </div>
                </div>
            </x-ui.card>
        @else
            <x-ui.card padding="p-8">
                <form action="{{ route('register.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <div class="theme-panel-subtle rounded-xl border p-4">
                        <p class="theme-text-muted text-sm leading-6">
                            Complete the required fields first. Profile image and two-factor setup are optional and can be configured now or later.
                        </p>
                    </div>

                        @csrf
                        <x-honeypot />

                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-ui.input
                                id="username"
                                name="login_id"
                                label="Username"
                                type="text"
                                autocomplete="username"
                                placeholder="Choose a unique username"
                                :required="true"
                                :value="old('login_id')"
                            />

                            <x-ui.input
                                id="nickname"
                                name="nickname"
                                label="Display Name"
                                type="text"
                                autocomplete="name"
                                placeholder="Your display name"
                                :required="true"
                                :value="old('nickname')"
                            />
                        </div>

                        <x-ui.input
                            id="email"
                            name="email"
                            label="Email Address"
                            type="email"
                            autocomplete="email"
                            placeholder="you@example.com"
                            :required="true"
                            :value="old('email')"
                        />

                        <div class="space-y-3">
                            <div>
                                <h2 class="theme-text-strong text-lg font-semibold">Choose Your Workspace</h2>
                                <x-ui.form-help class="mt-1">This decides which dashboard and workflow entry points you will land in after sign-up.</x-ui.form-help>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="relative">
                                    <input
                                        id="user_type_individual"
                                        type="radio"
                                        name="user_type"
                                        value="individual"
                                        class="peer sr-only"
                                        {{ $selectedUserType === 'individual' ? 'checked' : '' }}
                                    >
                                    <span class="pointer-events-none absolute right-4 top-4 hidden rounded-full border border-[var(--app-accent-soft-border)] bg-[var(--app-accent-soft-bg)] px-2 py-0.5 text-[11px] font-semibold text-[var(--app-accent-soft-fg)] peer-checked:inline-flex">
                                        Selected
                                    </span>
                                    <label
                                        for="user_type_individual"
                                        data-workspace-option
                                        class="theme-panel-subtle block cursor-pointer rounded-xl border p-5 transition-all hover:border-[var(--app-accent-soft-border)] hover:bg-[var(--app-panel-bg)] peer-checked:border-[var(--app-accent-strong)] peer-checked:bg-[var(--app-panel-bg)] peer-checked:shadow-sm peer-focus-visible:outline-none peer-focus-visible:ring-2 peer-focus-visible:ring-[var(--app-focus-ring)]"
                                    >
                                        <span class="flex items-start justify-between gap-4">
                                            <span class="block">
                                                <span class="theme-text-strong block text-base font-semibold">Individual Workspace</span>
                                                <span class="theme-text-muted mt-2 block text-sm leading-6">For candidates who want to browse jobs, submit applications, and manage profile/security from one workspace.</span>
                                            </span>
                                        </span>
                                    </label>
                                </div>

                                <div class="relative">
                                    <input
                                        id="user_type_company"
                                        type="radio"
                                        name="user_type"
                                        value="company"
                                        class="peer sr-only"
                                        {{ $selectedUserType === 'company' ? 'checked' : '' }}
                                    >
                                    <span class="pointer-events-none absolute right-4 top-4 hidden rounded-full border border-[var(--app-accent-soft-border)] bg-[var(--app-accent-soft-bg)] px-2 py-0.5 text-[11px] font-semibold text-[var(--app-accent-soft-fg)] peer-checked:inline-flex">
                                        Selected
                                    </span>
                                    <label
                                        for="user_type_company"
                                        data-workspace-option
                                        class="theme-panel-subtle block cursor-pointer rounded-xl border p-5 transition-all hover:border-[var(--app-accent-soft-border)] hover:bg-[var(--app-panel-bg)] peer-checked:border-[var(--app-accent-strong)] peer-checked:bg-[var(--app-panel-bg)] peer-checked:shadow-sm peer-focus-visible:outline-none peer-focus-visible:ring-2 peer-focus-visible:ring-[var(--app-focus-ring)]"
                                    >
                                        <span class="flex items-start justify-between gap-4">
                                            <span class="block">
                                                <span class="theme-text-strong block text-base font-semibold">Company Workspace</span>
                                                <span class="theme-text-muted mt-2 block text-sm leading-6">For employers who need to publish listings, review applicants, and keep the hiring queue moving.</span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <x-ui.form-error name="user_type" />
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-ui.input
                                id="password"
                                name="password"
                                label="Password"
                                type="password"
                                autocomplete="new-password"
                                :required="true"
                                help="At least 12 characters with mixed case, letters, numbers, and symbols."
                            />

                            <x-ui.input
                                id="password_confirmation"
                                name="password_confirmation"
                                label="Confirm Password"
                                type="password"
                                autocomplete="new-password"
                                :required="true"
                            />
                        </div>

                        <details class="theme-panel-subtle rounded-xl border">
                            <summary class="theme-text-strong cursor-pointer px-4 py-3 text-sm font-semibold">
                                Optional profile & security setup
                            </summary>
                            <div class="space-y-4 border-t border-[var(--app-panel-border)] px-4 py-4">
                                <div class="space-y-3">
                                    <div>
                                        <x-ui.form-label for="profile_image">Profile Image</x-ui.form-label>
                                        <x-ui.form-help class="mt-1">Optional. JPG, PNG, or GIF up to 2MB.</x-ui.form-help>
                                    </div>
                                    <div class="theme-panel-subtle rounded-xl border p-4">
                                        <input
                                            id="profile_image"
                                            name="profile_image"
                                            type="file"
                                            accept="image/*"
                                            class="theme-text-muted block w-full cursor-pointer text-sm file:mr-4 file:rounded-full file:border-0 file:bg-[var(--app-panel-bg)] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[var(--app-accent-strong)] hover:file:bg-[var(--app-panel-border)]"
                                        >
                                        <div class="mt-4 flex items-center gap-4">
                                            <img id="profile_image_preview" src="" alt="Profile Image Preview" class="hidden h-20 w-20 rounded-2xl object-cover" />
                                            <div class="theme-text-muted text-sm leading-6">
                                                Add an avatar now if you want your profile to look complete immediately.
                                            </div>
                                        </div>
                                    </div>
                                    <x-ui.form-error name="profile_image" />
                                </div>

                                <div class="rounded-xl border border-[var(--app-panel-border)] bg-[var(--app-panel-bg)] px-4 py-4">
                                    <label for="enable_2fa" class="flex cursor-pointer items-start gap-3">
                                        <input
                                            id="enable_2fa"
                                            name="enable_2fa"
                                            type="checkbox"
                                            value="1"
                                            class="mt-0.5 h-4 w-4 rounded border-[var(--app-panel-border)] text-[var(--app-accent-strong)] focus:ring-[var(--app-accent-soft)]"
                                        >
                                        <div>
                                            <span class="theme-text-strong block text-sm font-semibold">Enable two-factor authentication</span>
                                            <span class="theme-text-muted mt-1 block text-sm leading-6">Recommended for stronger sign-in protection. You can also enable it later from profile security.</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </details>

                        <x-ui.button type="submit" variant="primary" class="w-full justify-center">
                            Create account
                        </x-ui.button>
                </form>
            </x-ui.card>
        @endif

        <x-slot:footer>
            Already have an account?
            <a href="{{ route('login') }}" class="theme-link font-medium">
                Sign in
            </a>
        </x-slot:footer>
    </x-auth.shell>
</x-layouts.base>
