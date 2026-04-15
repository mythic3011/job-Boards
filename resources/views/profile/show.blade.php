@extends('layouts.app')

@section('title', 'Profile')

@section('content')
<div class="max-w-4xl mx-auto">
    <nav class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="/" class="hover:text-indigo-600 transition-colors">Home</a>
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="text-gray-900 font-medium">Profile</span>
    </nav>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Profile</h1>
        <p class="text-gray-600 mt-1">Manage your account settings and preferences</p>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Profile Information --}}
        <div class="lg:col-span-2">
            <x-ui.card>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-900">Profile Information</h2>
                    <a href="{{ route('profile.edit') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition-colors">
                        Edit Profile
                    </a>
                </div>

                <div class="space-y-6">
                    <div class="flex items-center gap-4">
                        <div class="shrink-0">
                            <x-ui.avatar
                                :src="$profile_image_url"
                                :name="$user['nickname']"
                                size="md"
                                class="border-2 border-gray-200"
                            />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ $user['nickname'] }}</h3>
                            <p class="text-sm text-gray-500">{{ $user['user_type_label'] }} Account</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-100">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Login ID</label>
                            <p class="text-sm text-gray-900">{{ $user['login_id'] }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Email</label>
                            <p class="text-sm text-gray-900">{{ $user['email'] }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Account Type</label>
                            <p class="text-sm text-gray-900">{{ $user['user_type_label'] }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Member Since</label>
                            <p class="text-sm text-gray-900">{{ \Carbon\Carbon::parse($user['created_at'])->format('F j, Y') }}</p>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Security --}}
            <x-ui.card>
                <h3 class="text-base font-semibold text-gray-900 mb-4">Security</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Two-Factor Auth</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $two_factor_enabled ? 'Enabled' : 'Not enabled' }}
                            </p>
                        </div>
                        @if($two_factor_enabled)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 border border-green-200 px-2.5 py-0.5 text-xs font-medium text-green-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                Enabled
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-yellow-50 border border-yellow-200 px-2.5 py-0.5 text-xs font-medium text-yellow-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span>
                                Disabled
                            </span>
                        @endif
                    </div>
                    <a href="{{ route('profile.two-factor') }}"
                       class="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition-colors">
                        Manage 2FA &rarr;
                    </a>
                </div>
            </x-ui.card>

            {{-- Quick Actions --}}
            <x-ui.card>
                <h3 class="text-base font-semibold text-gray-900 mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    <a href="{{ route('profile.edit') }}"
                       class="group flex items-center gap-3 w-full px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-700 transition-all duration-150 cursor-pointer">
                        <svg class="w-4 h-4 shrink-0 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit Profile
                    </a>

                    @if($two_factor_enabled)
                        <a href="{{ route('profile.password') }}"
                           class="group flex items-center gap-3 w-full px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-700 transition-all duration-150 cursor-pointer">
                            <svg class="w-4 h-4 shrink-0 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                            Change Password
                        </a>
                    @else
                        <div class="flex items-center gap-3 w-full px-4 py-3 text-sm font-medium text-gray-400 bg-gray-50 border border-gray-200 rounded-lg cursor-not-allowed"
                             title="Enable two-factor authentication to change your password.">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                            Change Password
                            <span class="ml-auto text-xs text-gray-400">Requires 2FA</span>
                        </div>
                    @endif

                    <a href="{{ route('profile.two-factor') }}"
                       class="group flex items-center gap-3 w-full px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-700 transition-all duration-150 cursor-pointer">
                        <svg class="w-4 h-4 shrink-0 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Security Settings
                    </a>
                </div>
            </x-ui.card>
        </div>
    </div>
</div>
@endsection
