<x-layouts.base :title="'Reset Password'" :show-header="false">
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <x-layouts.flash-messages />
            
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Set new password
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Enter your new password below.
                </p>
            </div>

            <form class="mt-8 space-y-6" action="{{ route('password.update') }}" method="POST">
                @csrf

                <input type="hidden" name="token" value="{{ $request->route('token') }}">

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
                            value="{{ old('email', $request->email) }}"
                            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('email') border-red-300 @enderror"
                        >
                    </div>
                    @error('email')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        New password
                    </label>
                    <div class="mt-1">
                        <input 
                            id="password" 
                            name="password" 
                            type="password" 
                            autocomplete="new-password" 
                            required 
                            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('password') border-red-300 @enderror"
                        >
                    </div>
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                        Confirm password
                    </label>
                    <div class="mt-1">
                        <input 
                            id="password_confirmation" 
                            name="password_confirmation" 
                            type="password" 
                            autocomplete="new-password" 
                            required 
                            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        >
                    </div>
                </div>

                <!-- Two-Factor Authentication Code (if required) -->
                <div>
                    <label for="two_factor_code" class="block text-sm font-medium text-gray-700">
                        Two-Factor Authentication Code
                    </label>
                    <div class="mt-1">
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
                            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('two_factor_code') border-red-300 @enderror"
                        >
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Required if your account has two-factor authentication enabled
                    </p>
                    @error('two_factor_code')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Requirements -->
                <div class="bg-gray-50 rounded-md p-3">
                    <h3 class="text-sm font-medium text-gray-900 mb-2">Password Requirements</h3>
                    <ul class="text-xs text-gray-600 space-y-1">
                        <li>• At least 12 characters long</li>
                        <li>• Contains uppercase and lowercase letters</li>
                        <li>• Contains at least one number</li>
                        <li>• Contains at least one special character</li>
                        <li>• Not found in common password breaches</li>
                    </ul>
                </div>

                <div>
                    <button 
                        type="submit" 
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        Reset password
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.base>
