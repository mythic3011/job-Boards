@extends('layouts.app')

@section('title', 'Profile')

@section('content')
@php
    $createdAt = \Carbon\Carbon::parse($user['created_at']);
    $memberSince = $createdAt->format('F j, Y');
    $membershipAge = $createdAt->diffForHumans();
    $twoFactorStatusLabel = $two_factor_enabled ? 'Enabled' : 'Not enabled';
    $twoFactorStatusTone = $two_factor_enabled
        ? 'bg-green-50 text-green-700 border-green-200'
        : 'bg-yellow-50 text-yellow-700 border-yellow-200';
    $securitySummary = $two_factor_enabled
        ? 'Your account recovery flow is unlocked and password changes stay inside the secured workspace.'
        : 'Enable two-factor authentication to unlock the protected password workflow and stronger recovery coverage.';
@endphp

<div class="mx-auto max-w-5xl">
    <nav class="theme-text-muted mb-4 flex items-center gap-2 text-sm">
        <a href="/" class="theme-link transition-colors">Home</a>
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="theme-text-strong font-medium">Profile</span>
    </nav>

    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="theme-text-strong text-3xl font-bold tracking-tight">Workspace Overview</h1>
            <p class="theme-text-muted mt-1 max-w-2xl text-sm leading-6">
                Review your account identity, security readiness, and the fastest path back into editing and protected settings.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="theme-pill inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold">
                {{ $user['user_type_label'] }} account
            </span>
            <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $twoFactorStatusTone }}">
                2FA {{ $twoFactorStatusLabel }}
            </span>
        </div>
    </div>

    @include('profile.partials.workspace-nav', [
        'active' => 'show',
        'twoFactorEnabled' => $two_factor_enabled,
    ])

    @if($errors->any())
        <x-ui.alert type="error" class="mb-6">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.35fr_0.95fr]">
        <div class="space-y-6">
            <x-ui.card padding="p-8">
                <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <x-ui.avatar
                            :src="$profile_image_url"
                            :name="$user['nickname']"
                            size="lg"
                            class="border-2 border-[var(--app-panel-border)] shadow-sm"
                        />
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="theme-text-strong truncate text-2xl font-semibold">{{ $user['nickname'] }}</h2>
                                <span class="theme-pill inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em]">
                                    {{ $user['user_type_label'] }}
                                </span>
                            </div>
                            <p class="theme-text-muted mt-1 text-sm">{{ $user['email'] }}</p>
                            <p class="theme-text-muted mt-3 max-w-xl text-sm leading-6">
                                This is your account control center. Use it to confirm identity details, review membership status, and jump into security-sensitive tasks.
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 md:justify-end">
                        <x-ui.button href="{{ route('profile.edit') }}" variant="primary">
                            Edit Profile
                        </x-ui.button>
                        <x-ui.button href="{{ route('profile.two-factor') }}" variant="outline">
                            Security Settings
                        </x-ui.button>
                    </div>
                </div>

                <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="theme-panel-subtle rounded-2xl border p-4">
                        <p class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">Login ID</p>
                        <p class="theme-text-strong mt-2 text-sm font-semibold">{{ $user['login_id'] }}</p>
                        <p class="theme-text-muted mt-2 text-xs">Stable identifier used for sign-in and audit continuity.</p>
                    </div>
                    <div class="theme-panel-subtle rounded-2xl border p-4">
                        <p class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">Member Since</p>
                        <p class="theme-text-strong mt-2 text-sm font-semibold">{{ $memberSince }}</p>
                        <p class="theme-text-muted mt-2 text-xs">Account created {{ $membershipAge }}.</p>
                    </div>
                    <div class="theme-panel-subtle rounded-2xl border p-4">
                        <p class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">Account Role</p>
                        <p class="theme-text-strong mt-2 text-sm font-semibold">{{ $user['user_type_label'] }}</p>
                        <p class="theme-text-muted mt-2 text-xs">Determines navigation, permissions, and workflow entry points.</p>
                    </div>
                    <div class="theme-panel-subtle rounded-2xl border p-4">
                        <p class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">Security Status</p>
                        <p class="mt-2 inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $twoFactorStatusTone }}">
                            {{ $twoFactorStatusLabel }}
                        </p>
                        <p class="theme-text-muted mt-2 text-xs">Password change access is gated behind confirmed 2FA.</p>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card padding="p-8">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="theme-text-strong text-xl font-semibold">Identity Snapshot</h2>
                        <p class="theme-text-muted mt-1 text-sm">The core account values used across workspace, auth, and recovery surfaces.</p>
                    </div>
                    <span class="theme-pill rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em]">
                        Read-only truth
                    </span>
                </div>

                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="theme-panel-subtle rounded-2xl border p-4">
                        <dt class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">Display Name</dt>
                        <dd class="theme-text-strong mt-2 text-base font-semibold">{{ $user['nickname'] }}</dd>
                    </div>
                    <div class="theme-panel-subtle rounded-2xl border p-4">
                        <dt class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">Email Address</dt>
                        <dd class="theme-text-strong mt-2 break-all text-base font-semibold">{{ $user['email'] }}</dd>
                    </div>
                    <div class="theme-panel-subtle rounded-2xl border p-4">
                        <dt class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">Login ID</dt>
                        <dd class="theme-text-strong mt-2 text-base font-semibold">{{ $user['login_id'] }}</dd>
                    </div>
                    <div class="theme-panel-subtle rounded-2xl border p-4">
                        <dt class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">Account Type</dt>
                        <dd class="theme-text-strong mt-2 text-base font-semibold">{{ $user['user_type_label'] }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card padding="p-7">
                <h2 class="theme-text-strong text-lg font-semibold">Security Posture</h2>
                <p class="theme-text-muted mt-2 text-sm leading-6">{{ $securitySummary }}</p>

                <div class="mt-5 space-y-4">
                    <div class="theme-panel-subtle rounded-2xl border p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="theme-text-strong text-sm font-semibold">Two-Factor Authentication</p>
                                <p class="theme-text-muted mt-1 text-xs">{{ $twoFactorStatusLabel }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $twoFactorStatusTone }}">
                                {{ $twoFactorStatusLabel }}
                            </span>
                        </div>
                    </div>

                    <div class="theme-panel-subtle rounded-2xl border p-4">
                        <p class="theme-text-strong text-sm font-semibold">Protected Password Flow</p>
                        <p class="theme-text-muted mt-1 text-xs">
                            {{ $two_factor_enabled ? 'Available now through the secured password workflow.' : 'Locked until two-factor authentication is enabled and confirmed.' }}
                        </p>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-3">
                    <x-ui.button href="{{ route('profile.two-factor') }}" variant="primary" class="w-full justify-center">
                        Manage 2FA
                    </x-ui.button>

                    @if($two_factor_enabled)
                        <x-ui.button href="{{ route('profile.password') }}" variant="outline" class="w-full justify-center">
                            Change Password
                        </x-ui.button>
                    @else
                        <div class="theme-alert theme-alert-warning rounded-2xl border px-4 py-3 text-sm">
                            Enable two-factor authentication first to unlock password changes.
                        </div>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card padding="p-7">
                <h2 class="theme-text-strong text-lg font-semibold">Recommended Next Steps</h2>
                <div class="mt-4 space-y-3">
                    <a href="{{ route('profile.edit') }}" class="theme-panel-subtle group flex items-start justify-between gap-4 rounded-2xl border p-4 transition-colors hover:bg-[var(--app-panel-bg)]">
                        <div>
                            <p class="theme-text-strong text-sm font-semibold">Refresh your public profile</p>
                            <p class="theme-text-muted mt-1 text-xs">Update display name, avatar, and account-facing details.</p>
                        </div>
                        <span class="theme-link text-sm font-medium">Open</span>
                    </a>

                    <a href="{{ route('profile.two-factor') }}" class="theme-panel-subtle group flex items-start justify-between gap-4 rounded-2xl border p-4 transition-colors hover:bg-[var(--app-panel-bg)]">
                        <div>
                            <p class="theme-text-strong text-sm font-semibold">Review security settings</p>
                            <p class="theme-text-muted mt-1 text-xs">Check authenticator state and recovery readiness.</p>
                        </div>
                        <span class="theme-link text-sm font-medium">Open</span>
                    </a>

                    @if($two_factor_enabled)
                        <a href="{{ route('profile.password') }}" class="theme-panel-subtle group flex items-start justify-between gap-4 rounded-2xl border p-4 transition-colors hover:bg-[var(--app-panel-bg)]">
                            <div>
                                <p class="theme-text-strong text-sm font-semibold">Rotate your password</p>
                                <p class="theme-text-muted mt-1 text-xs">Use the protected password workflow to refresh your credentials.</p>
                            </div>
                            <span class="theme-link text-sm font-medium">Open</span>
                        </a>
                    @else
                        <div class="theme-panel-subtle rounded-2xl border p-4">
                            <p class="theme-text-strong text-sm font-semibold">Unlock protected password changes</p>
                            <p class="theme-text-muted mt-1 text-xs">Enable 2FA first so the password workflow becomes available.</p>
                        </div>
                    @endif
                </div>
            </x-ui.card>
        </div>
    </div>
</div>
@endsection
