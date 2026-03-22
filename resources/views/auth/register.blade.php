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
                <h2 class="mt-6 text-center text-3xl font-bold text-gray-900">
                    Create your account
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Already have an account?
                    <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                        Sign in
                    </a>
                </p>
            </div>

            <div class="bg-white shadow-sm rounded-xl border border-gray-200 p-8">
                @if(!$registrationsOpen)
                    <div class="text-center space-y-6">
                        <div class="flex items-center justify-center">
                            <div class="flex items-center justify-center w-20 h-20 rounded-full bg-amber-50 ring-4 ring-amber-100">
                                <svg class="w-10 h-10 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">
                                Registrations Temporarily Closed
                            </h3>
                            <p class="text-gray-500 text-sm leading-relaxed max-w-sm mx-auto">
                                We're currently not accepting new registrations. This is a temporary measure, and we'll be opening up again soon.
                            </p>
                        </div>
                        <div class="border-t border-gray-100 pt-6">
                            <p class="text-sm text-gray-500 mb-3">Already have an account?</p>
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                </svg>
                                Sign In to Continue
                            </a>
                        </div>
                    </div>
                @else
                    <form action="{{ route('register.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                        @csrf
                        <x-honeypot />

                        <div>
                            <label for="login_id" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input
                                id="login_id"
                                name="login_id"
                                type="text"
                                value="{{ old('login_id') }}"
                                autocomplete="username"
                                required
                                class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all {{ $errors->has('login_id') ? 'border-red-300' : 'border-gray-300' }}"
                                placeholder="Choose a unique username"
                            >
                            @error('login_id')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="nickname" class="block text-sm font-medium text-gray-700 mb-1">Display Name</label>
                            <input
                                id="nickname"
                                name="nickname"
                                type="text"
                                value="{{ old('nickname') }}"
                                autocomplete="name"
                                required
                                class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all {{ $errors->has('nickname') ? 'border-red-300' : 'border-gray-300' }}"
                                placeholder="Your display name"
                            >
                            @error('nickname')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                autocomplete="email"
                                required
                                class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all {{ $errors->has('email') ? 'border-red-300' : 'border-gray-300' }}"
                                placeholder="you@example.com"
                            >
                            @error('email')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="user_type" class="block text-sm font-medium text-gray-700 mb-1">Account Type</label>
                            <select
                                id="user_type"
                                name="user_type"
                                required
                                class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all cursor-pointer {{ $errors->has('user_type') ? 'border-red-300' : 'border-gray-300' }}"
                            >
                                <option value="individual" {{ old('user_type') == 'individual' ? 'selected' : '' }}>Individual (Job Seeker)</option>
                                <option value="company" {{ old('user_type') == 'company' ? 'selected' : '' }}>Company (Employer)</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Choose Individual if you're looking for jobs, or Company if you're hiring.</p>
                            @error('user_type')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all {{ $errors->has('password') ? 'border-red-300' : 'border-gray-300' }}"
                            >
                            <p class="mt-1 text-xs text-gray-500">At least 12 characters with mixed case, letters, numbers, and symbols.</p>
                            @error('password')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all {{ $errors->has('password_confirmation') ? 'border-red-300' : 'border-gray-300' }}"
                            >
                            @error('password_confirmation')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="profile_image" class="block text-sm font-medium text-gray-700 mb-1">Profile Image (Optional)</label>
                            <input
                                id="profile_image"
                                name="profile_image"
                                type="file"
                                accept="image/*"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                            >
                            <img id="profile_image_preview" src="" alt="Profile Image Preview" class="mt-4 w-24 h-24 rounded-full object-cover hidden" />
                            <p class="mt-1 text-xs text-gray-500">JPG, PNG, GIF up to 2MB</p>
                            @error('profile_image')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="border-t border-gray-100 pt-5">
                            <div class="flex items-start gap-3">
                                <input
                                    id="enable_2fa"
                                    name="enable_2fa"
                                    type="checkbox"
                                    value="1"
                                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                >
                                <div>
                                    <label for="enable_2fa" class="block text-sm font-medium text-gray-700 cursor-pointer">
                                        Enable Two-Factor Authentication
                                    </label>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Recommended — adds an extra layer of security. You can also enable this later in security settings.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors cursor-pointer"
                        >
                            Create account
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-layouts.base>
