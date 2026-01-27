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
                <form action="{{ route('register.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    
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
                        </div>
                        <p class="mt-1 text-xs text-gray-500">JPG, PNG, GIF up to 2MB</p>
                        @error('profile_image')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
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
            </div>

            <div class="text-center text-sm text-gray-600">
                Already have an account?
                <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                    Sign in now
                </a>
            </div>
        </div>
    </div>
</x-layouts.base>