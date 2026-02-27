<x-layouts.base :title="'Reset Password'" :show-header="false">
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <x-layouts.flash-messages />

            @php
                $token = $request->route('token');
                $adminInitiated = \Illuminate\Support\Facades\Cache::has('admin_reset:' . $token);
            @endphp

            <div>
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-indigo-100">
                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>
                <h2 class="mt-6 text-center text-3xl font-bold text-gray-900">
                    Set new password
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Enter your new password below.
                </p>
            </div>

            <div class="bg-white shadow-sm rounded-xl border border-gray-200 p-8">
                <form class="space-y-5" action="{{ route('password.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autocomplete="email"
                            required
                            value="{{ old('email', $request->email) }}"
                            class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all {{ $errors->has('email') ? 'border-red-300' : 'border-gray-300' }}"
                        >
                        @error('email')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all {{ $errors->has('password') ? 'border-red-300' : 'border-gray-300' }}"
                        >
                        @error('password')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm password</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all border-gray-300"
                        >
                    </div>

                    @unless($adminInitiated)
                        <div>
                            <label for="two_factor_code" class="block text-sm font-medium text-gray-700 mb-1">Two-Factor Authentication Code</label>
                            <input
                                id="two_factor_code"
                                name="two_factor_code"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="6"
                                autocomplete="one-time-code"
                                placeholder="000000"
                                value="{{ old('two_factor_code') }}"
                                class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all {{ $errors->has('two_factor_code') ? 'border-red-300' : 'border-gray-300' }}"
                            >
                            <p class="mt-1 text-xs text-gray-500">Required if your account has two-factor authentication enabled</p>
                            @error('two_factor_code')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endunless

                    <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Password Requirements</h3>
                        <ul class="text-xs text-gray-600 space-y-1">
                            <li>• At least 12 characters long</li>
                            <li>• Contains uppercase and lowercase letters</li>
                            <li>• Contains at least one number</li>
                            <li>• Contains at least one special character</li>
                            <li>• Not found in common password breaches</li>
                        </ul>
                    </div>

                    <button
                        type="submit"
                        class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors cursor-pointer"
                    >
                        Reset password
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.base>
