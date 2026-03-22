<?php

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $login_id = '';
    public string $nickname = '';
    public string $email = '';
    public string $user_type = 'individual';
    public string $password = '';
    public string $password_confirmation = '';
    public $profile_image = null;

    public function register()
    {
        $this->validate([
            'login_id' => 'required|string|max:255|unique:users,login_id',
            'nickname' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'user_type' => 'required|in:company,individual',
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
                'not_regex:/^(password|123456|admin|user|test|p@ssw0rd)/i',
            ],
            'profile_image' => 'nullable|image|max:2048',
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
};
?>

<div>
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
                    autocomplete="username"
                    required
                    class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all @error('login_id') border-red-300 @enderror"
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
                    autocomplete="name"
                    required
                    class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all @error('nickname') border-red-300 @enderror"
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
                    class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all @error('email') border-red-300 @enderror"
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
                class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all @error('user_type') border-red-300 @enderror"
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
                    class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all @error('password') border-red-300 @enderror"
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
                    wire:model="password_confirmation"
                    autocomplete="new-password"
                    required
                    class="block w-full px-3 py-2.5 border rounded-lg shadow-sm text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all @error('password_confirmation') border-red-300 @enderror"
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
                class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors cursor-pointer"
            >
                Create account
            </button>
        </div>
    </form>
</div>