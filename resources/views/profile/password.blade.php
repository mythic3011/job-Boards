@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
<div class="max-w-2xl mx-auto">
    <nav class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('profile.show') }}" class="hover:text-indigo-600 transition-colors">Profile</a>
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="text-gray-900 font-medium">Change Password</span>
    </nav>

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Change Password</h1>
        <p class="text-gray-600 mt-1">Update your password to keep your account secure</p>
    </div>

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

    <form action="{{ route('profile.password.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card>
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Password Security</h2>

            @if($two_factor_enabled)
                <x-ui.alert type="info" class="mb-6">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-400 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <p class="font-medium">Two-Factor Authentication Required</p>
                            <p class="text-sm mt-1">Since you have two-factor authentication enabled, you'll need to provide your authentication code to change your password.</p>
                        </div>
                    </div>
                </x-ui.alert>
            @endif

            <div class="space-y-6">
                <!-- Current Password -->
                <div>
                    <x-ui.input 
                        label="Current Password" 
                        name="current_password" 
                        type="password"
                        required
                        autocomplete="current-password"
                        help="Enter your current password to verify your identity"
                    />
                </div>

                <!-- New Password -->
                <div>
                    <x-ui.input 
                        label="New Password" 
                        name="password" 
                        type="password"
                        required
                        autocomplete="new-password"
                        help="Must be at least 12 characters with uppercase, lowercase, numbers, and symbols"
                    />
                </div>

                <!-- Confirm New Password -->
                <div>
                    <x-ui.input 
                        label="Confirm New Password" 
                        name="password_confirmation" 
                        type="password"
                        required
                        autocomplete="new-password"
                        help="Re-enter your new password to confirm"
                    />
                </div>

                @if($two_factor_enabled)
                    <!-- Two-Factor Code -->
                    <div>
                        <x-ui.input 
                            label="Two-Factor Authentication Code" 
                            name="two_factor_code" 
                            maxlength="6"
                            pattern="[0-9]{6}"
                            placeholder="000000"
                            autocomplete="one-time-code"
                            required
                            help="Enter the 6-digit code from your authenticator app"
                        />
                    </div>
                @endif
            </div>

            <!-- Password Requirements -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900 mb-2">Password Requirements</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• At least 12 characters long</li>
                    <li>• Contains uppercase and lowercase letters</li>
                    <li>• Contains at least one number</li>
                    <li>• Contains at least one special character (@$!%*?&)</li>
                    <li>• Not found in common password breaches</li>
                </ul>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                <a href="{{ route('profile.show') }}" 
                   class="text-gray-600 hover:text-gray-800 font-medium">
                    Cancel
                </a>
                
                <x-ui.button type="submit" variant="primary">
                    Update Password
                </x-ui.button>
            </div>
        </x-ui.card>
    </form>
</div>
@endsection