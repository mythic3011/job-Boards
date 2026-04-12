@extends('layouts.app')

@section('title', 'Profile')

@section('content')
@php
    $createdAt = \Carbon\Carbon::parse($user['created_at']);
    $memberSince = $createdAt->format('F j, Y');
    $membershipAge = $createdAt->diffForHumans();
    $twoFactorStatusLabel = $two_factor_enabled ? 'Enabled' : 'Not enabled';
    $twoFactorStatusTone = $two_factor_enabled
        ? 'theme-alert-success'
        : 'theme-alert-warning';
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

    <div class="mb-6">
        <h1 class="theme-text-strong text-3xl font-bold tracking-tight">Workspace Overview</h1>
        <p class="theme-text-muted mt-1 max-w-2xl text-sm leading-6">
            Review your account identity, security readiness, and the fastest path back into editing and protected settings.
        </p>
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

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.4fr_0.9fr]">
        <div class="space-y-6">
            <div class="theme-hero-surface rounded-3xl border px-6 py-7 sm:px-8" data-profile-overview-hero>
                <div class="min-w-0" data-profile-overview-identity>
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                        <x-ui.avatar
                            :src="$profile_image_url"
                            :name="$user['nickname']"
                            size="xl"
                            class="border-2 border-[var(--app-panel-border)] shadow-sm"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="theme-hero-eyebrow text-xs font-semibold uppercase tracking-[0.18em]">Profile Workspace</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2.5">
                                <h2 class="min-w-0 text-2xl font-semibold tracking-tight sm:text-3xl">{{ $user['nickname'] }}</h2>
                                <span class="theme-pill inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em]">
                                    {{ $user['user_type_label'] }}
                                </span>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] {{ $twoFactorStatusTone }}">
                                    2FA {{ $twoFactorStatusLabel }}
                                </span>
                            </div>
                            <p class="theme-text-muted mt-3 break-all text-sm leading-6">{{ $user['email'] }}</p>
                            <p class="theme-text-muted mt-4 max-w-2xl text-sm leading-6">
                                This page is the account/settings surface. Use the workspace tabs below to update identity details, review login truth, and move into protected security tasks when needed.
                            </p>
                            <div class="theme-hero-card mt-5 inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm">
                                <span class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">Member since</span>
                                <span class="theme-text-strong font-medium">{{ $memberSince }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                        <dd class="theme-text-strong mt-2 break-all text-base font-semibold" data-profile-identity-login-id>{{ $user['login_id'] }}</dd>
                    </div>
                    <div class="theme-panel-subtle rounded-2xl border p-4">
                        <dt class="theme-text-muted text-xs font-semibold uppercase tracking-[0.14em]">Account Type</dt>
                        <dd class="theme-text-strong mt-2 text-base font-semibold">{{ $user['user_type_label'] }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>

        <div class="space-y-6 xl:sticky xl:top-24 xl:self-start">
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
        </div>
    </div>
</div>
@endsection
