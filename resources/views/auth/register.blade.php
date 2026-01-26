<?php

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component
{
    #[Validate('required|string|max:255|unique:users,login_id')]
    public string $login_id = '';

    #[Validate('required|string|max:255')]
    public string $nickname = '';

    #[Validate('required|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('required|in:company,individual')]
    public string $user_type = 'individual';

    #[Validate('required|string', as: 'password')]
    public string $password = '';

    #[Validate('required|string|same:password', as: 'password confirmation')]
    public string $password_confirmation = '';

    #[Validate('nullable|image|max:2048')]
    public $profile_image = null;

    public function register()
    {
        $this->validate([
            'password' => ['required', 'string', Password::defaults()],
        ]);

        $createUser = new CreateNewUser();
        $user = $createUser->create([
            'login_id' => $this->login_id,
            'nickname' => $this->nickname,
            'email' => $this->email,
            'user_type' => $this->user_type,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
            'profile_image' => $this->profile_image,
        ]);

        auth()->login($user);

        return redirect()->intended('/');
    }
}; ?>

<x-layouts.base :title="'Register'" :show-header="false">
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
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
                <form wire:submit="register" class="space-y-6">
                    <div>
                        <label for="login_id" class="block text-sm font-medium text-gray-700">
                            Username
                        </label>
                        <div class="mt-1">
                            <input
                                id="login_id"
                                name="login_id"
                                type="text"
                                wire:model="login_id"
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
                                wire:model="nickname"
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
                                wire:model="email"
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
                            wire:model="user_type"
                            required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('user_type') border-red-300 @enderror"
                        >
                            <option value="individual">Individual (Job Seeker)</option>
                            <option value="company">Company (Employer)</option>
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
                                wire:model="password"
                                autocomplete="new-password"
                                required
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('password') border-red-300 @enderror"
                            >
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Must be at least 8 characters with mixed case and numbers.
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
                                wire:model="password_confirmation"
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
                        <x-ui.image-upload
                            label="Profile Image (Optional)"
                            name="profile_image"
                            wire:model="profile_image"
                            accept="image/*"
                            maxSize="2MB"
                            help="JPG, PNG, GIF up to 2MB"
                            :preview="true"
                        />
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
