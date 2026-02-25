@php
    use App\Models\Setting;
    $registrationsOpen = Setting::getBool('registrations_open', true);
@endphp

<x-layouts.base :title="'Register'" :show-header="false">
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <x-layouts.flash-messages />
            <div>
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-indigo-100">
                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Create your account
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Already have an account?
                    <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                        Sign in
                    </a>
                </p>
            </div>

            <div class="bg-white shadow-md rounded-lg p-8">
                @if(!$registrationsOpen)
                    {{-- Enhanced Registration Closed Notice --}}
                    <div class="text-center space-y-6">
                        {{-- Icon Section --}}
                        <div class="flex items-center justify-center">
                            <div class="relative">                                
                                 {{-- Main Icon --}}
                                <div class="relative flex items-center justify-center w-24 h-24 rounded-full bg-gradient-to-br from-amber-50 to-orange-100 ring-4 ring-amber-100 shadow-lg">
                                    <svg class="w-12 h-12 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        {{-- Message Header --}}
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3 tracking-tight" style="text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
                                Registrations Temporarily Closed
                            </h3>
                            <p class="text-gray-600 text-sm leading-relaxed max-w-sm mx-auto" style="text-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);">
                                We're currently not accepting new registrations. This is a temporary measure, and we'll be opening up again soon.
                            </p>
                        </div>

                        {{-- Bottom Message --}}
                        <div class="border-t border-gray-100 pt-8 mt-4">
                            <div class="bg-gray-50 rounded-xl p-5 mx-auto max-w-xs border border-gray-100 shadow-sm">
                                <p class="text-sm text-gray-500 font-medium mb-3">
                                    Already have an account?
                                </p>
                                
                                <a 
                                    href="{{ route('login') }}" 
                                    class="w-full inline-flex items-center justify-center px-6 py-3 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 active:bg-indigo-800 transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                    </svg>
                                    Sign In to Continue
                                </a>
                            </div>
                        </div>
                    </div>
                @else
                    <form action="{{ route('register.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                        @csrf
                        <x-honeypot />
                    
                    <div>
                        <label for="login_id" class="block text-sm font-medium text-gray-700">
                            Username
                        </label>
                        <div class="mt-1">
                            <input
                                id="login_id"
                                name="login_id"
                                type="text"
                                value="{{ old('login_id') }}"
                                required
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('login_id') border-red-300 @enderror"
                                placeholder="Choose a unique username"
                            >
                        </div>
                        @error('login_id')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="nickname" class="block text-sm font-medium text-gray-700">
                            Display Name
                        </label>
                        <div class="mt-1">
                            <input
                                id="nickname"
                                name="nickname"
                                type="text"
                                value="{{ old('nickname') }}"
                                required
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('nickname') border-red-300 @enderror"
                                placeholder="Your display name"
                            >
                        </div>
                        @error('nickname')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">
                            Email address
                        </label>
                        <div class="mt-1">
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                autocomplete="email"
                                required
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('email') border-red-300 @enderror"
                                placeholder="you@example.com"
                            >
                        </div>
                        @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="user_type" class="block text-sm font-medium text-gray-700">
                            Account Type
                        </label>
                        <select
                            id="user_type"
                            name="user_type"
                            required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('user_type') border-red-300 @enderror"
                        >
                            <option value="individual" {{ old('user_type') == 'individual' ? 'selected' : '' }}>Individual (Job Seeker)</option>
                            <option value="company" {{ old('user_type') == 'company' ? 'selected' : '' }}>Company (Employer)</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            Choose Individual if you're looking for jobs, or Company if you're hiring.
                        </p>
                        @error('user_type')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password
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
                        <p class="mt-1 text-xs text-gray-500">
                            Must be at least 12 characters with mixed case, letters, numbers, and symbols.
                        </p>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                            Confirm Password
                        </label>
                        <div class="mt-1">
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('password_confirmation') border-red-300 @enderror"
                            >
                        </div>
                        @error('password_confirmation')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="profile_image" class="block text-sm font-medium text-gray-700">
                            Profile Image (Optional)
                        </label>
                        <div class="mt-1">
                            <input
                                id="profile_image"
                                name="profile_image"
                                type="file"
                                accept="image/*"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            >
                            <img id="profile_image_preview" src="" alt="Profile Image Preview" class="mt-4 w-32 h-32 rounded-full object-cover" style="display: none;" />
                        </div>
                        <p class="mt-1 text-xs text-gray-500">JPG, PNG, GIF up to 2MB</p>
                        @error('profile_image')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="border-t pt-6">
                        <div class="flex items-center">
                            <input
                                id="enable_2fa"
                                name="enable_2fa"
                                type="checkbox"
                                value="1"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                            <label for="enable_2fa" class="ml-3 block text-sm font-medium text-gray-700">
                                Enable Two-Factor Authentication
                            </label>
                        </div>
                        <p class="mt-2 text-xs text-gray-600 ml-7">
                            <strong>Recommended:</strong> Add an extra layer of security to your account with two-factor authentication.
                            <br><span class="text-gray-600">You can enable or disable 2FA later in your security settings.</span>
                        </p>
                    </div>

                        <div>
                            <button
                                type="submit"
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                            >
                                Create account
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            <!-- <div class="text-center text-sm text-gray-600">
                Already have an account?
                <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                    Sign in now
                </a>
            </div> -->
        </div>
    </div>
</x-layouts.base>