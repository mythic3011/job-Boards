@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="max-w-md mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-center">Login</h1>

    @if(session('warning'))
        <x-ui.alert type="warning" class="mb-6">
            {{ session('warning') }}
        </x-ui.alert>
    @endif

    <x-ui.card>
        <form method="POST" action="{{ route('login') }}" class="space-y-6">
            @csrf

            <div>
                <x-ui.input
                    label="Username or Email"
                    id="login_id"
                    name="login_id"
                    type="text"
                    value="{{ old('login_id') }}"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Enter your username or email"
                />
                @error('login_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-ui.input
                    label="Password"
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                />
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center">
                <input
                    id="remember"
                    name="remember"
                    type="checkbox"
                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                >
                <label for="remember" class="ml-2 block text-sm text-gray-900">
                    Remember me
                </label>
            </div>

            <div class="flex items-center justify-between">
                <x-ui.button type="submit" variant="primary" class="w-full">
                    Login
                </x-ui.button>
            </div>

            <div class="text-center">
                <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Forgot your password?
                </a>
            </div>

            <div class="text-center text-sm text-gray-600">
                Don't have an account?
                <a href="{{ route('register') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                    Register here
                </a>
            </div>
        </form>
    </x-ui.card>
</div>
@endsection
