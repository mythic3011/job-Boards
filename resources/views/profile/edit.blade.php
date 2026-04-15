@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
<div class="max-w-5xl mx-auto">
    <nav class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('profile.show') }}" class="hover:text-indigo-600 transition-colors">Profile</a>
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="text-gray-900 font-medium">Edit Profile</span>
    </nav>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Edit Profile</h1>
        <p class="text-gray-600 mt-1">Update your profile information and settings</p>
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
                            <h2 class="text-xl font-semibold text-gray-900">Profile Information</h2>
                            <p class="mt-1 text-sm text-gray-500">Keep your public-facing details accurate. Changes apply after validation.</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">
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

                    <div class="mt-6 grid grid-cols-1 gap-6 border-t border-gray-200 pt-6 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Login ID</label>
                            <p class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ $user['login_id'] }}</p>
                            <p class="mt-1 text-xs text-gray-500">Login ID stays fixed for audit and sign-in consistency.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Account Type</label>
                            <p class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900">{{ auth()->user()->getUserTypeLabel() }}</p>
                            <p class="mt-1 text-xs text-gray-500">Account type is managed by your current registration path.</p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-6">
                        <a href="{{ route('profile.show') }}" class="text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">
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
                <h2 class="text-lg font-semibold text-gray-900">Account Summary</h2>
                <div class="mt-4 flex items-center gap-3">
                    <x-ui.avatar
                        :src="$profile_image_url"
                        :name="$user['nickname']"
                        size="sm"
                        class="border border-gray-200"
                    />
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $user['nickname'] }}</p>
                        <p class="text-xs text-gray-500">{{ $user['user_type_label'] }} account</p>
                    </div>
                </div>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-gray-500">Email</dt>
                        <dd class="text-right font-medium text-gray-900 break-all">{{ $user['email'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-gray-500">Login ID</dt>
                        <dd class="font-medium text-gray-900">{{ $user['login_id'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-gray-500">2FA</dt>
                        <dd class="{{ $two_factor_enabled ? 'text-green-700' : 'text-yellow-700' }} font-medium">
                            {{ $two_factor_enabled ? 'Enabled' : 'Not enabled' }}
                        </dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card>
                <h2 class="text-lg font-semibold text-gray-900">Security Shortcuts</h2>
                <div class="mt-4 space-y-3">
                    <a href="{{ route('profile.two-factor') }}" class="flex items-start justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm transition-colors hover:border-indigo-200 hover:bg-indigo-50">
                        <div>
                            <p class="font-medium text-gray-900">Security Settings</p>
                            <p class="mt-1 text-xs text-gray-500">Review two-factor status and recovery access.</p>
                        </div>
                        <span class="text-indigo-600">Open</span>
                    </a>

                    @if($two_factor_enabled)
                        <a href="{{ route('profile.password') }}" class="flex items-start justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm transition-colors hover:border-indigo-200 hover:bg-indigo-50">
                            <div>
                                <p class="font-medium text-gray-900">Change Password</p>
                                <p class="mt-1 text-xs text-gray-500">Update your password without leaving the secured workflow.</p>
                            </div>
                            <span class="text-indigo-600">Open</span>
                        </a>
                    @else
                        <div class="rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
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
