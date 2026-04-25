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
            <label for="login_id" class="theme-text-strong block text-sm font-medium">
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
                    class="theme-input block w-full rounded-lg border px-3 py-2.5 text-sm transition-shadow @error('login_id') theme-input-error @enderror"
                    placeholder="Choose a unique username"
                >
            </div>
            @error('login_id')
                <p class="theme-error-text mt-2 text-sm">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="nickname" class="theme-text-strong block text-sm font-medium">
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
                    class="theme-input block w-full rounded-lg border px-3 py-2.5 text-sm transition-shadow @error('nickname') theme-input-error @enderror"
                    placeholder="Your display name"
                >
            </div>
            @error('nickname')
                <p class="theme-error-text mt-2 text-sm">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="theme-text-strong block text-sm font-medium">
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
                    class="theme-input block w-full rounded-lg border px-3 py-2.5 text-sm transition-shadow @error('email') theme-input-error @enderror"
                    placeholder="you@example.com"
                >
            </div>
            @error('email')
                <p class="theme-error-text mt-2 text-sm">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="user_type" class="theme-text-strong block text-sm font-medium">
                Account Type
            </label>
            <select
                id="user_type"
                name="user_type"
                wire:model="user_type"
                required
                class="theme-input block w-full rounded-lg border px-3 py-2.5 text-sm transition-shadow @error('user_type') theme-input-error @enderror"
            >
                <option value="individual">Individual (Job Seeker)</option>
                <option value="company">Company (Employer)</option>
            </select>
            <p class="theme-text-muted mt-1 text-xs">
                Choose Individual if you're looking for jobs, or Company if you're hiring.
            </p>
            @error('user_type')
                <p class="theme-error-text mt-2 text-sm">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="theme-text-strong block text-sm font-medium">
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
                    class="theme-input block w-full rounded-lg border px-3 py-2.5 text-sm transition-shadow @error('password') theme-input-error @enderror"
                >
            </div>
            <p class="theme-text-muted mt-1 text-xs">
                Must be at least 12 characters with mixed case, letters, numbers, and symbols.
            </p>
            @error('password')
                <p class="theme-error-text mt-2 text-sm">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="theme-text-strong block text-sm font-medium">
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
                    class="theme-input block w-full rounded-lg border px-3 py-2.5 text-sm transition-shadow @error('password_confirmation') theme-input-error @enderror"
                >
            </div>
            @error('password_confirmation')
                <p class="theme-error-text mt-2 text-sm">{{ $message }}</p>
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
                class="theme-button theme-button-primary flex w-full items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold"
            >
                Create account
            </button>
        </div>
    </form>
</div>
