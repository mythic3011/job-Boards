@extends('layouts.app')

@section('title', 'Profile')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Profile</h1>
        <p class="text-gray-600 mt-1">Manage your account settings and preferences</p>
    </div>

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
        <!-- Profile Information -->
        <div class="lg:col-span-2">
            <x-ui.card>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-900">Profile Information</h2>
                    <a href="{{ route('profile.edit') }}" class="text-blue-600 hover:text-blue-800 font-medium">
                        Edit Profile
                    </a>
                </div>

                <div class="space-y-6">
                    <!-- Profile Image -->
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <x-ui.avatar 
                                :src="$profile_image_url"
                                :name="$user['nickname']"
                                size="md"
                                class="border-2 border-gray-200"
                            />
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ $user['nickname'] }}</h3>
                            <p class="text-sm text-gray-500">{{ ucfirst($user['user_type']) }} Account</p>
                        </div>
                    </div>

                    <!-- Basic Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Login ID</label>
                            <p class="text-gray-900">{{ $user['login_id'] }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <p class="text-gray-900">{{ $user['email'] }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Account Type</label>
                            <p class="text-gray-900">{{ ucfirst($user['user_type']) }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Member Since</label>
                            <p class="text-gray-900">{{ \Carbon\Carbon::parse($user['created_at'])->format('F j, Y') }}</p>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <!-- Security Settings -->
        <div class="space-y-6">
            <!-- Security Overview -->
            <x-ui.card>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Security</h3>
                
                <div class="space-y-4">
                    <!-- 2FA  section block -->
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-900">2F Auth</p>
                            <p class="text-sm text-gray-500">
                                @if($two_factor_enabled)
                                    Enabled
                                @else
                                    Not enabled
                                @endif
                            </p>
                        </div>
                        <div>
                            @if($two_factor_enabled)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                    Enabled
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">
                                    Disabled
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="pt-2">
                        <a href="{{ route('profile.two-factor') }}" 
                           class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            Manage 2FA →
                        </a>
                    </div>
                </div>
            </x-ui.card>

            <!-- Quick Actions -->
            <x-ui.card>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                
                <div class="space-y-3">
                    <a href="{{ route('profile.edit') }}" 
                       class="block w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md">
                        Edit Profile Information
                    </a>
                    @if($two_factor_enabled)
                        <a href="{{ route('profile.password') }}" 
                           class="block w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md">
                            Change Password
                        </a>
                    @else
                        <span
                            class="block w-full text-left px-3 py-2 text-sm text-gray-400 rounded-md cursor-not-allowed"
                            aria-disabled="true"
                            title="Enable two-factor authentication to change your password."
                        >
                            Change Password
                        </span>
                    @endif
                    <a href="{{ route('profile.two-factor') }}" 
                       class="block w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md">
                        Security Settings
                    </a>
                </div>
            </x-ui.card>
        </div>
    </div>
</div>
@endsection