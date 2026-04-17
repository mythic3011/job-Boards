@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
<div class="max-w-5xl mx-auto">
    <nav class="theme-text-muted mb-4 flex items-center gap-2 text-sm">
        <a href="{{ route('profile.show') }}" class="theme-link transition-colors">Profile</a>
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="theme-text-strong font-medium">Edit Profile</span>
    </nav>

    <div class="mb-6">
        <h1 class="theme-text-strong text-2xl font-bold">Edit Profile</h1>
        <p class="theme-text-muted mt-1">Update your profile information and settings</p>
    </div>

    @include('profile.partials.workspace-nav', [
        'active' => 'edit',
        'twoFactorEnabled' => $two_factor_enabled,
    ])

    @if(session('success'))
        <x-ui.alert type="success" class="mb-6">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    @if($errors->any())
        <x-ui.alert type="error" class="mb-6">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                <x-ui.card>
                    <div class="mb-6 flex items-start justify-between gap-4">
                        <div>
                            <h2 class="theme-text-strong text-xl font-semibold">Profile Information</h2>
                            <p class="theme-text-muted mt-1 text-sm">Keep your public-facing details accurate. Changes apply after validation.</p>
                        </div>
                        <span class="theme-pill inline-flex items-center rounded-full px-3 py-1 text-xs font-medium">
                            Visible on your account
                        </span>
                    </div>

                    <div class="mb-6">
                        <x-ui.file-input
                            label="Profile Image"
                            name="profile_image"
                            accept="image/*"
                            :current-image="$profile_image_url"
                            :user-name="$user['nickname']"
                            help="Use a clear square photo. Missing or invalid files fall back to your initials."
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <x-ui.input
                            label="Display Name"
                            name="nickname"
                            value="{{ old('nickname', $user['nickname']) }}"
                            autocomplete="name"
                            required
                            help="This is how your name will appear to others"
                        />
                        <x-ui.input
                            label="Email Address"
                            name="email"
                            type="email"
                            value="{{ old('email', $user['email']) }}"
                            autocomplete="email"
                            required
                            help="Used for login and account notifications"
                        />
                    </div>

                    <div class="theme-table-divider mt-6 grid grid-cols-1 gap-6 border-t pt-6 md:grid-cols-2">
                        <div>
                            <label class="theme-text-strong mb-1 block text-sm font-medium">Login ID</label>
                            <p class="theme-panel-subtle theme-text-strong rounded-lg border px-3 py-2 text-sm">{{ $user['login_id'] }}</p>
                            <p class="theme-text-muted mt-1 text-xs">Login ID stays fixed for audit and sign-in consistency.</p>
                        </div>
                        <div>
                            <label class="theme-text-strong mb-1 block text-sm font-medium">Account Type</label>
                            <p class="theme-panel-subtle theme-text-strong rounded-lg border px-3 py-2 text-sm">{{ auth()->user()->getUserTypeLabel() }}</p>
                            <p class="theme-text-muted mt-1 text-xs">Account type is managed by your current registration path.</p>
                        </div>
                    </div>

                    <div class="theme-table-divider mt-6 flex items-center justify-between border-t pt-6">
                        <a href="{{ route('profile.show') }}" class="theme-link text-sm font-medium transition-colors">
                            Back to Profile
                        </a>
                        <x-ui.button type="submit" variant="primary">
                            Save Profile Changes
                        </x-ui.button>
                    </div>
                </x-ui.card>
            </form>
        </div>

        <div class="space-y-6">
            <x-ui.card>
                <h2 class="theme-text-strong text-lg font-semibold">Account Summary</h2>
                <div class="mt-4 flex items-center gap-3">
                    <x-ui.avatar
                        :src="$profile_image_url"
                        :name="$user['nickname']"
                        size="sm"
                        class="border border-[var(--app-panel-border)]"
                    />
                    <div>
                        <p class="theme-text-strong text-sm font-semibold">{{ $user['nickname'] }}</p>
                        <p class="theme-text-muted text-xs">{{ $user['user_type_label'] }} account</p>
                    </div>
                </div>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <dt class="theme-text-muted">Email</dt>
                        <dd class="theme-text-strong break-all text-right font-medium">{{ $user['email'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="theme-text-muted">Login ID</dt>
                        <dd class="theme-text-strong font-medium">{{ $user['login_id'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="theme-text-muted">2FA</dt>
                        <dd class="{{ $two_factor_enabled ? 'theme-signal-success' : 'theme-signal-warning' }} font-medium">
                            {{ $two_factor_enabled ? 'Enabled' : 'Not enabled' }}
                        </dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card>
                <h2 class="theme-text-strong text-lg font-semibold">Security Shortcuts</h2>
                <div class="mt-4 space-y-3">
                    <a href="{{ route('profile.two-factor') }}" class="theme-panel-subtle flex items-start justify-between rounded-xl border px-4 py-3 text-sm transition-colors hover:bg-[var(--app-panel-bg)]">
                        <div>
                            <p class="theme-text-strong font-medium">Security Settings</p>
                            <p class="theme-text-muted mt-1 text-xs">Review two-factor status and recovery access.</p>
                        </div>
                        <span class="theme-link">Open</span>
                    </a>

                    @if($two_factor_enabled)
                        <a href="{{ route('profile.password') }}" class="theme-panel-subtle flex items-start justify-between rounded-xl border px-4 py-3 text-sm transition-colors hover:bg-[var(--app-panel-bg)]">
                            <div>
                                <p class="theme-text-strong font-medium">Change Password</p>
                                <p class="theme-text-muted mt-1 text-xs">Update your password without leaving the secured workflow.</p>
                            </div>
                            <span class="theme-link">Open</span>
                        </a>
                    @else
                        <div class="theme-alert-warning rounded-xl border px-4 py-3 text-sm">
                            <p class="font-medium">Change Password is locked</p>
                            <p class="mt-1 text-xs">Enable two-factor authentication first to access the password change flow.</p>
                        </div>
                    @endif
                </div>
            </x-ui.card>
        </div>
    </div>

    @if($has_profile_image)
        <form id="delete-image-form" action="{{ route('profile.image.delete') }}" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif
</div>
@endsection
