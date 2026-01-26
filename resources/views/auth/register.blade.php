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

@extends('layouts.app')

@section('title', 'Register')

@section('content')
<div class="max-w-md mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold">Create account</h1>
        <p class="mt-1 text-sm text-gray-600">Register as an individual or a company.</p>
    </div>

    <form wire:submit="register" class="rounded-lg border bg-white p-8 shadow-sm space-y-6">
        <x-ui.input
            label="Username"
            name="login_id"
            wire:model="login_id"
            placeholder="Choose a unique username"
            required
        />

        <x-ui.input
            label="Nickname"
            name="nickname"
            wire:model="nickname"
            required
        />

        <x-ui.input
            label="Email"
            name="email"
            type="email"
            wire:model="email"
            required
        />

        <div>
            <label for="user_type" class="block text-sm font-medium text-gray-700 mb-1">
                Account Type
            </label>
            <select 
                id="user_type"
                wire:model="user_type" 
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500"
                required
            >
                <option value="individual">Individual</option>
                <option value="company">Company</option>
            </select>
            @error('user_type') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <x-ui.input
            label="Password"
            name="password"
            type="password"
            wire:model="password"
            required
        />

        <x-ui.input
            label="Confirm Password"
            name="password_confirmation"
            type="password"
            wire:model="password_confirmation"
            required
        />

        <x-ui.image-upload
            label="Profile Image (Optional)"
            name="profile_image"
            wire:model="profile_image"
            accept="image/*"
            maxSize="2MB"
            help="Accepted formats: JPG, PNG, GIF (Max: 2MB)"
            :preview="true"
        />

        <button 
            type="submit" 
            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
        >
            Register
        </button>

        <p class="text-center text-sm text-gray-600">
            Already have an account?
            <a href="{{ route('login') }}" class="font-medium text-indigo-700 hover:text-indigo-900">Login</a>
        </p>
    </form>
</div>

@endsection
