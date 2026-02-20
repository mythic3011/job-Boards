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
                            <p class="text-sm text-gray-500">{{ $user['user_type_label'] }} Account</p>
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
                            <p class="text-gray-900">{{ $user['user_type_label'] }}</p>
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
                
                <div class="space-y-2">
                    <a href="{{ route('profile.edit') }}" 
                       class="group block w-full text-left px-4 py-3 text-sm font-medium text-gray-800 bg-gradient-to-r from-gray-50 to-gray-100 hover:from-blue-50 hover:to-indigo-50 border border-gray-200 hover:border-blue-300 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-600 group-hover:text-blue-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span class="group-hover:text-blue-700 transition-colors italic">Edit Profile Information</span>
                        </span>
                    </a>
                    @if($two_factor_enabled)
                        <a href="{{ route('profile.password') }}" 
                           class="group block w-full text-left px-4 py-3 text-sm font-medium text-gray-800 bg-gradient-to-r from-gray-50 to-gray-100 hover:from-purple-50 hover:to-pink-50 border border-gray-200 hover:border-purple-300 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-600 group-hover:text-purple-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                                <span class="group-hover:text-purple-700 transition-colors italic">Change Password</span>
                            </span>
                        </a>
                    @else
                        <span
                            class="block w-full text-left px-4 py-3 text-sm font-medium text-gray-400 bg-gray-50 border border-gray-200 rounded-lg cursor-not-allowed"
                            aria-disabled="true"
                            title="Enable two-factor authentication to change your password."
                        >
                            Change Password
                        </span>
                    @endif
                          <a href="{{ route('admin.settings.index') }}" 
                       class="group block w-full text-left px-4 py-3 text-sm font-medium text-gray-800 bg-gradient-to-r from-gray-50 to-gray-100 hover:from-green-50 hover:to-emerald-50 border border-gray-200 hover:border-green-300 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-600 group-hover:text-green-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span class="group-hover:text-green-700 transition-colors italic">Security Settings</span>
                        </span>
                    </a>
                </div>
            </x-ui.card>
        </div>
    </div>
</div>
@endsection