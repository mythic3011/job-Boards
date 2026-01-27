@extends('layouts.app')

@section('title', 'Two-Factor Authentication')

@section('content')
<div class="max-w-md mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-center">Two-Factor Authentication</h1>

    <x-ui.card>
        <div class="mb-6">
            <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-indigo-100">
                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <p class="text-center text-gray-600">
                Please confirm access to your account by entering the authentication code provided by your authenticator application.
            </p>
        </div>

        <form method="POST" action="{{ route('two-factor.login') }}" x-data="{ useRecovery: false }">
            @csrf

            <!-- Authentication Code -->
            <div x-show="!useRecovery" x-cloak>
                <x-ui.input
                    label="Authentication Code"
                    id="code"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    autofocus
                    autocomplete="one-time-code"
                    placeholder="000000"
                    :value="old('code')"
                />
                @error('code')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                
                <p class="mt-2 text-sm text-gray-600">
                    Enter the 6-digit code from your authenticator app.
                </p>
            </div>

            <!-- Recovery Code -->
            <div x-show="useRecovery" x-cloak>
                <x-ui.input
                    label="Recovery Code"
                    id="recovery_code"
                    name="recovery_code"
                    type="text"
                    autocomplete="off"
                    placeholder="XXXXX-XXXXX"
                    :value="old('recovery_code')"
                />
                @error('recovery_code')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                
                <p class="mt-2 text-sm text-gray-600">
                    Enter one of your recovery codes.
                </p>
            </div>

            <!-- Toggle Recovery Code -->
            <div class="mt-4">
                <button
                    type="button"
                    @click="useRecovery = !useRecovery"
                    class="text-sm text-indigo-600 hover:text-indigo-800 underline"
                >
                    <span x-show="!useRecovery">Use a recovery code instead</span>
                    <span x-show="useRecovery">Use authentication code</span>
                </button>
            </div>

            <!-- Trust Device Option -->
            <div class="mt-6 flex items-start">
                <div class="flex items-center h-5">
                    <input
                        id="trust_device"
                        name="trust_device"
                        type="checkbox"
                        value="1"
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                    >
                </div>
                <div class="ml-3 text-sm">
                    <label for="trust_device" class="font-medium text-gray-700">
                        Trust this device for 30 days
                    </label>
                    <p class="text-gray-500">
                        You won't be asked for a code on this device for 30 days.
                    </p>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="mt-6">
                <x-ui.button type="submit" variant="primary" class="w-full">
                    Verify and Continue
                </x-ui.button>
            </div>

            <!-- Cancel -->
            <div class="mt-4 text-center">
                <a href="{{ route('logout') }}" 
                   onclick="event.preventDefault(); $('#logout-form').submit();"
                   class="text-sm text-gray-600 hover:text-gray-800">
                    Cancel and log out
                </a>
            </div>
        </form>

        <!-- Logout Form (hidden) -->
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
            @csrf
        </form>
    </x-ui.card>

    <!-- Help Section -->
    <div class="mt-6 text-center">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-sm font-medium text-blue-900 mb-2">
                Having trouble?
            </h3>
            <p class="text-sm text-blue-700">
                If you've lost access to your authenticator app, use a recovery code. 
                If you don't have your recovery codes, please contact support.
            </p>
        </div>
    </div>
</div>

<style>
[x-cloak] { display: none !important; }
</style>
@endsection
