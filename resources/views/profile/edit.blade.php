@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
<div class="max-w-2xl mx-auto">
    <nav class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('profile.show') }}" class="hover:text-indigo-600 transition-colors">Profile</a>
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="text-gray-900 font-medium">Edit Profile</span>
    </nav>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Edit Profile</h1>
        <p class="text-gray-600 mt-1">Update your profile information and settings</p>
    </div>

    @if(session('success'))
        <x-ui.alert type="success" class="mb-6">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    @if($errors->any())
        <x-ui.alert type="error" class="mb-6">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card>
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Profile Information</h2>

            <div class="mb-6">
                <x-ui.file-input
                    label="Profile Image"
                    name="profile_image"
                    accept="image/*"
                    :current-image="$profile_image_url"
                    :user-name="$user['nickname']"
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ui.input
                    label="Display Name"
                    name="nickname"
                    value="{{ old('nickname', $user['nickname']) }}"
                    autocomplete="name"
                    required
                    help="This is how your name will appear to others"
                />
                <x-ui.input
                    label="Email Address"
                    name="email"
                    type="email"
                    value="{{ old('email', $user['email']) }}"
                    autocomplete="email"
                    required
                    help="Used for login and notifications"
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6 pt-6 border-t border-gray-200">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Login ID</label>
                    <p class="text-gray-900 text-sm bg-gray-50 px-3 py-2 rounded-lg border border-gray-200">{{ $user['login_id'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">Login ID cannot be changed</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Type</label>
                    <p class="text-gray-900 text-sm bg-gray-50 px-3 py-2 rounded-lg border border-gray-200">{{ auth()->user()->getUserTypeLabel() }}</p>
                    <p class="text-xs text-gray-500 mt-1">Account type cannot be changed</p>
                </div>
            </div>

            <div class="flex items-center justify-between pt-6 border-t border-gray-200 mt-6">
                <a href="{{ route('profile.show') }}" class="text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">
                    Cancel
                </a>
                <x-ui.button type="submit" variant="primary">
                    Update Profile
                </x-ui.button>
            </div>
        </x-ui.card>
    </form>

    @if($has_profile_image)
        <form id="delete-image-form" action="{{ route('profile.image.delete') }}" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif
</div>
@endsection
