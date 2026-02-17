@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Edit Profile</h1>
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

    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6" onsubmit="debugFormSubmission(event)">
        @csrf
        @method('PUT')

        <x-ui.card>
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Profile Information</h2>

            <!-- Profile Image -->
            <div class="mb-6">
                <x-ui.file-input 
                    label="Profile Image"
                    name="profile_image" 
                    accept="image/*"
                    :current-image="$profile_image_url"
                    :user-name="$user['nickname']"
                />
            </div>

            <!-- Basic Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-ui.input 
                        label="Display Name" 
                        name="nickname" 
                        value="{{ old('nickname', $user['nickname']) }}"
                        required
                        help="This is how your name will appear to others"
                    />
                </div>

                <div>
                    <x-ui.input 
                        label="Email Address" 
                        name="email" 
                        type="email"
                        value="{{ old('email', $user['email']) }}"
                        required
                        help="Used for login and notifications"
                    />
                </div>
            </div>

            <!-- Read-only Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6 pt-6 border-t border-gray-200">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Login ID</label>
                    <p class="text-gray-900 text-sm bg-gray-50 px-3 py-2 rounded-md">{{ $user['login_id'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">Login ID cannot be changed</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Type</label>
                    <p class="text-gray-900 text-sm bg-gray-50 px-3 py-2 rounded-md">{{ auth()->user()->getUserTypeLabel() }}</p>
                    <p class="text-xs text-gray-500 mt-1">Account type cannot be changed</p>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                <a href="{{ route('profile.show') }}" 
                   class="text-gray-600 hover:text-gray-800 font-medium">
                    Cancel
                </a>
                
                <x-ui.button type="submit" variant="primary">
                    Update Profile
                </x-ui.button>
            </div>
        </x-ui.card>
    </form>
</div>

@if($has_profile_image)
<!-- Delete Profile Image Form -->
<form id="delete-image-form" action="{{ route('profile.image.delete') }}" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>

<script>
// jQuery-based profile functions with minimal code
const deleteProfileImage = () => confirm('Are you sure you want to remove your profile image?') && $('#delete-image-form').submit();

const debugFormSubmission = (event) => {
    const form = event.target, formData = new FormData(form);
    console.log('Form submission debug:', { action: form.action, method: form.method, enctype: form.enctype });
    for (let [key, value] of formData.entries()) console.log(`${key}:`, value instanceof File ? { name: value.name, size: value.size, type: value.type } : value);
    const profileImageInput = $(form).find('input[name="profile_image"]')[0];
    if (profileImageInput?.files.length > 0) { console.log('Profile image file found:', profileImageInput.files[0]); showToast('Uploading profile image...', 'info'); } else console.log('No profile image file selected');
    return true;
};

const showToast = (message, type = 'info') => window.toast ? window.toast.show(message, type) : (console.log(`Toast (${type}): ${message}`), typeof $ !== 'undefined' && createSimpleToast(message, type));

const createSimpleToast = (message, type) => {
    const colors = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500' };
    const toast = $(`<div class="fixed top-4 right-4 ${colors[type] || colors.info} text-white px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300 opacity-0 translate-x-full"><div class="flex items-center gap-2"><span>${message}</span><button class="ml-2 text-white hover:text-gray-200 font-bold" onclick="$(this).closest('div').fadeOut()">&times;</button></div></div>`);
    $('body').append(toast);
    setTimeout(() => toast.removeClass('opacity-0 translate-x-full'), 10);
    setTimeout(() => toast.fadeOut(300, function() { $(this).remove(); }), 5000);
};
</script>
@else
<script>
const debugFormSubmission = (event) => {
    const form = event.target, formData = new FormData(form);
    console.log('Form submission debug:', { action: form.action, method: form.method, enctype: form.enctype });
    for (let [key, value] of formData.entries()) console.log(`${key}:`, value instanceof File ? { name: value.name, size: value.size, type: value.type } : value);
    const profileImageInput = $(form).find('input[name="profile_image"]')[0];
    profileImageInput?.files.length > 0 ? console.log('Profile image file found:', profileImageInput.files[0]) : console.log('No profile image file selected');
    return true;
};
</script>
@endif
@endsection