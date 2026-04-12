@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
<div class="max-w-5xl mx-auto">
    <nav class="theme-text-muted mb-4 flex items-center gap-2 text-sm">
        <a href="{{ route('profile.show') }}" class="theme-link transition-colors">Profile</a>
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="theme-text-strong font-medium">Change Password</span>
    </nav>

    <div class="mb-6">
        <h1 class="theme-text-strong text-3xl font-bold">Change Password</h1>
        <p class="theme-text-muted mt-1">Update your password to keep your account secure</p>
    </div>

    @include('profile.partials.workspace-nav', [
        'active' => 'password',
        'twoFactorEnabled' => $two_factor_enabled,
    ])

    @if(session('success'))
        <x-ui.alert type="success" class="mb-6">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    @if($errors->updatePassword->any())
        <x-ui.alert type="error" class="mb-6">
            <ul class="list-disc list-inside">
                @foreach($errors->updatePassword->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form action="{{ route('profile.password.update') }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <x-ui.card>
                    <div class="mb-6 flex items-start justify-between gap-4">
                        <div>
                            <h2 class="theme-text-strong text-xl font-semibold">Password Security</h2>
                            <p class="theme-text-muted mt-1 text-sm">This flow requires your current password and a fresh 2FA code before the update is accepted.</p>
                        </div>
                        <span class="theme-alert-success inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium">
                            2FA protected
                        </span>
                    </div>

                    @if($two_factor_enabled)
                        <x-ui.alert type="info" class="mb-6">
                            <div class="flex items-start">
                                <svg class="theme-signal-info mt-0.5 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <p class="font-medium">Two-Factor Authentication Required</p>
                                    <p class="text-sm mt-1">Keep your authenticator app nearby. You will need a valid 6-digit code to finish the password change.</p>
                                </div>
                            </div>
                        </x-ui.alert>
                    @endif

                    <div class="space-y-6">
                        <x-ui.input 
                            label="Current Password" 
                            name="current_password" 
                            type="password"
                            required
                            autocomplete="current-password"
                            help="Enter your current password to verify your identity"
                        />

                        <x-ui.input 
                            label="New Password" 
                            name="password" 
                            type="password"
                            required
                            autocomplete="new-password"
                            help="Use a unique password that you do not reuse anywhere else"
                        />

                        <x-ui.input 
                            label="Confirm New Password" 
                            name="password_confirmation" 
                            type="password"
                            required
                            autocomplete="new-password"
                            help="Re-enter your new password exactly as typed above"
                        />

                        @if($two_factor_enabled)
                            <x-ui.input 
                                label="Two-Factor Authentication Code" 
                                name="two_factor_code" 
                                maxlength="6"
                                pattern="[0-9]{6}"
                                placeholder="000000"
                                autocomplete="one-time-code"
                                required
                                help="Enter the latest 6-digit code from your authenticator app"
                            />
                        @endif
                    </div>

                    <div class="theme-table-divider mt-6 flex items-center justify-between border-t pt-6">
                        <a href="{{ route('profile.show') }}" 
                           class="theme-link font-medium">
                            Back to Profile
                        </a>
                        
                        <x-ui.button type="submit" variant="primary">
                            Update Password
                        </x-ui.button>
                    </div>
                </x-ui.card>
            </form>
        </div>

        <div class="space-y-6">
            <x-ui.card>
                <h2 class="theme-text-strong text-lg font-semibold">Security Status</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <dt class="theme-text-muted">Account</dt>
                        <dd class="theme-text-strong text-right font-medium">{{ $user['nickname'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="theme-text-muted">Login ID</dt>
                        <dd class="theme-text-strong font-medium">{{ $user['login_id'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="theme-text-muted">Two-factor</dt>
                        <dd class="theme-signal-success font-medium">Enabled</dd>
                    </div>
                </dl>

                <div class="theme-alert-info mt-4 rounded-xl border px-4 py-3 text-sm">
                    Password changes are audited and require both your current password and a valid authenticator code.
                </div>
            </x-ui.card>

            <x-ui.card>
                <h2 class="theme-text-strong text-lg font-semibold">Password Checklist</h2>
                <ul class="theme-panel-subtle theme-text-muted mt-4 space-y-3 rounded-xl border px-4 py-4 text-sm">
                    <li class="flex items-start gap-3">
                        <span class="theme-dot-success mt-1 h-2 w-2 rounded-full"></span>
                        <span>Use at least 12 characters.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="theme-dot-success mt-1 h-2 w-2 rounded-full"></span>
                        <span>Mix uppercase, lowercase, numbers, and symbols.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="theme-dot-success mt-1 h-2 w-2 rounded-full"></span>
                        <span>Avoid reusing passwords from email, banking, or work accounts.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="theme-dot-success mt-1 h-2 w-2 rounded-full"></span>
                        <span>Make sure your authenticator app is accessible before submitting.</span>
                    </li>
                </ul>
            </x-ui.card>
        </div>
    </div>
</div>
@endsection
