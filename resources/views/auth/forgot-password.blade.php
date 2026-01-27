<x-layouts.base :title="'Forgot Password'" :show-header="false">
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <x-layouts.flash-messages />
            
            <div>
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-indigo-100">
                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Reset your password
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    For security, password reset requires two-factor authentication verification.
                </p>
            </div>

            <div class="bg-white shadow-md rounded-lg p-6" x-data="{ useRecovery: false }">
                <form class="space-y-6" action="{{ route('password.email') }}" method="POST">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">
                            Email address
                        </label>
                        <div class="mt-1">
                            <input
                                id="email"
                                name="email"
                                type="email"
                                autocomplete="email"
                                required
                                value="{{ old('email') }}"
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('email') border-red-300 @enderror"
                            >
                        </div>
                        @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- 2FA Code or Recovery Code -->
                    <div>
                        <div x-show="!useRecovery">
                            <label for="code" class="block text-sm font-medium text-gray-700">
                                Two-Factor Authentication Code
                            </label>
                            <div class="mt-1">
                                <input
                                    id="code"
                                    name="code"
                                    type="text"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    maxlength="6"
                                    autocomplete="one-time-code"
                                    placeholder="000000"
                                    value="{{ old('code') }}"
                                    class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('code') border-red-300 @enderror"
                                >
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                Enter the 6-digit code from your authenticator app
                            </p>
                            @error('code')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-show="useRecovery" x-cloak>
                            <label for="recovery_code" class="block text-sm font-medium text-gray-700">
                                Recovery Code
                            </label>
                            <div class="mt-1">
                                <input
                                    id="recovery_code"
                                    name="recovery_code"
                                    type="text"
                                    autocomplete="off"
                                    placeholder="XXXXX-XXXXX"
                                    value="{{ old('recovery_code') }}"
                                    class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('recovery_code') border-red-300 @enderror"
                                >
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                Enter one of your recovery codes
                            </p>
                            @error('recovery_code')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-2">
                            <button
                                type="button"
                                @click="useRecovery = !useRecovery"
                                class="text-sm text-indigo-600 hover:text-indigo-800 underline"
                            >
                                <span x-show="!useRecovery">Use a recovery code instead</span>
                                <span x-show="useRecovery" x-cloak>Use authentication code</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <button
                            type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                        >
                            Verify and Send Reset Link
                        </button>
                    </div>

                    <div class="text-center">
                        <a href="{{ route('login') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            Back to login
                        </a>
                    </div>
                </form>
            </div>

            <!-- Help Section -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="text-sm font-medium text-blue-900 mb-2">
                    Why do I need 2FA?
                </h3>
                <p class="text-sm text-blue-700">
                    Password reset requires two-factor authentication to ensure account security.
                    If you've lost access to both your authenticator app and recovery codes, please contact support.
                </p>
            </div>
        </div>
    </div>

    <style>
    [x-cloak] { display: none !important; }
    </style>
</x-layouts.base>
