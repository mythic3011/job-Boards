@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'accept' => null,
    'help' => null,
    'maxSize' => null,
    'error' => null,
    'disabled' => false,
    'showPreview' => true,
    'currentImage' => null,
    'userName' => null,
])

@php
    $inputId = $name ?? 'file-' . uniqid();
    $previewId = $inputId . '-preview';
    $hasError = $error || (isset($errors) && $errors->has($name));
    $helpId = $help ? "{$inputId}-help" : null;
    $errorId = $hasError ? "{$inputId}-error" : null;
    
    $ariaDescribedBy = collect([$helpId, $errorId])->filter()->implode(' ');
    $userInitial = $userName ? strtoupper(substr($userName, 0, 1)) : 'U';
@endphp

<div class="space-y-4">
    @if($label)
        <x-ui.form-label :for="$inputId" :required="$required">
            {{ $label }}
        </x-ui.form-label>
    @endif

    <div class="flex flex-col items-center space-y-4">
        <div class="relative group">
            <div class="relative w-32 h-32 rounded-full overflow-hidden border-4 {{ $hasError ? 'border-red-300' : 'border-gray-200' }} bg-gradient-to-br from-blue-400 to-purple-500 shadow-lg">
                @if($currentImage)
                    <img src="{{ $currentImage }}" 
                         alt="Profile Image" 
                         class="w-full h-full object-cover transition-all duration-300 group-hover:scale-110"
                         id="{{ $inputId }}-current-avatar">
                @else
                    <!-- Default Avatar with Initial -->
                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-500 to-purple-600">
                        <span class="text-4xl font-bold text-white">{{ $userInitial }}</span>
                    </div>
                @endif
                
                <img class="w-full h-full object-cover absolute inset-0 opacity-0 transition-opacity duration-300" 
                     alt="Preview" 
                     id="{{ $inputId }}-preview-avatar">
            </div>
            
            <label for="{{ $inputId }}" 
                   class="absolute inset-0 w-32 h-32 rounded-full cursor-pointer {{ $disabled ? 'cursor-not-allowed' : '' }}">
                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition-all duration-300 rounded-full flex items-center justify-center">
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform group-hover:scale-110">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                </div>
                
                <!-- Error Indicator -->
                @if($hasError)
                    <div class="absolute -top-2 -right-2 w-8 h-8 bg-red-500 rounded-full flex items-center justify-center border-2 border-white shadow-lg">
                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                    </div>
                @endif
                
                <div class="absolute -top-2 -right-2 w-8 h-8 bg-green-500 rounded-full items-center justify-center border-2 border-white shadow-lg hidden" 
                     id="{{ $inputId }}-success-indicator">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                </div>
            </label>
            
            <input
                type="file"
                id="{{ $inputId }}"
                name="{{ $name }}"
                accept="{{ $accept }}"
                {{ $required ? 'required' : '' }}
                {{ $disabled ? 'disabled' : '' }}
                aria-invalid="{{ $hasError ? 'true' : 'false' }}"
                @if($ariaDescribedBy) aria-describedby="{{ $ariaDescribedBy }}" @endif
                class="sr-only"
                onchange="handleAvatarSelect(this, '{{ $previewId }}')"
                {{ $attributes->except(['class']) }}
            >
        </div>
        
        <div class="text-center">
            <p class="text-sm font-medium text-gray-900 mb-1">
                {{ $disabled ? 'Upload disabled' : 'Click to update your photo' }}
            </p>
            <p class="text-xs text-gray-500">
                JPG, PNG, WebP or GIF, up to 2MB
            </p>
        </div>
        
        <div class="flex space-x-3 opacity-0 transition-opacity duration-300" id="{{ $inputId }}-actions">
            <button type="button" 
                    onclick="confirmAvatarChange('{{ $inputId }}')"
                    class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
                Save Photo
            </button>
            <button type="button" 
                    onclick="cancelAvatarChange('{{ $inputId }}')"
                    class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">
                Cancel
            </button>
        </div>
        
        @if($currentImage)
            <button type="button" 
                    onclick="removeCurrentAvatar('{{ $inputId }}')"
                    class="text-sm text-red-600 hover:text-red-800 font-medium transition-colors">
                Remove Photo
            </button>
        @endif
    </div>

    @error($name)
        <div class="text-center">
            <p class="text-sm text-red-600 flex items-center justify-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                </svg>
                {{ $message }}
            </p>
        </div>
    @enderror

    @if($help && !$hasError)
        <div class="text-center">
            <x-ui.form-help id="{{ $helpId }}">{{ $help }}</x-ui.form-help>
        </div>
    @endif
</div>

<script>
let pendingChange = null;

function handleAvatarSelect(input) {
    const file = input.files[0];
    if (!file) return resetAvatarState(input);
    
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    if (!allowedTypes.includes(file.type.toLowerCase())) return showToast('Please select a valid image file (JPG, PNG, WebP, or GIF)', 'error'), input.value = '', void 0;
    if (file.size > 2097152) return showToast('File size must be less than 2MB', 'error'), input.value = '', void 0;
    
    console.log('Avatar selected:', {name: file.name.replace(/[\/\\<>:"|?*]/g, '_').substring(0, 100), size: file.size, type: file.type});
    showAvatarPreview(file, input);
}

function showAvatarPreview(file, input) {
    const reader = new FileReader();
    reader.onload = e => {
        const $preview = $(`#${input.id}-preview-avatar`), $current = $(`#${input.id}-current-avatar`), $actions = $(`#${input.id}-actions`), $success = $(`#${input.id}-success-indicator`);
        
        $preview.one('load', () => {
            pendingChange = {inputId: input.id, file, dataUrl: e.target.result};
            $preview.css('opacity', 1), $current.css('opacity', 0.3), $actions.removeClass('opacity-0').addClass('opacity-100'), $success.addClass('hidden').removeClass('flex');
            showToast('Photo ready to save', 'info');
        }).one('error', () => (showToast('Failed to load image preview. Please try a different image.', 'error'), input.value = '', resetAvatarState(input))).attr('src', e.target.result);
    };
    reader.onerror = () => (showToast('Error reading file. Please try again.', 'error'), input.value = '', resetAvatarState(input));
    reader.readAsDataURL(file);
}

function confirmAvatarChange(inputId) {
    if (!pendingChange || pendingChange.inputId !== inputId) return showToast('No changes to save', 'warning');
    
    const $preview = $(`#${inputId}-preview-avatar`), $current = $(`#${inputId}-current-avatar`), $actions = $(`#${inputId}-actions`), $success = $(`#${inputId}-success-indicator`);
    $current.attr('src', pendingChange.dataUrl).css('opacity', 1), $preview.css('opacity', 0), $actions.addClass('opacity-0').removeClass('opacity-100'), $success.removeClass('hidden').addClass('flex');
    pendingChange = null, showToast('Profile photo updated! Remember to save your changes.', 'success');
}

function cancelAvatarChange(inputId) {
    $(`#${inputId}`).val(''), resetAvatarState($(`#${inputId}`)[0]), pendingChange = null, showToast('Changes cancelled', 'info');
}

function resetAvatarState(input) {
    const $preview = $(`#${input.id}-preview-avatar`), $current = $(`#${input.id}-current-avatar`), $actions = $(`#${input.id}-actions`), $success = $(`#${input.id}-success-indicator`);
    $preview.css('opacity', 0).attr('src', ''), $current.css('opacity', 1), $actions.addClass('opacity-0').removeClass('opacity-100'), $success.addClass('hidden').removeClass('flex');
}

function removeCurrentAvatar(inputId) {
    if (!confirm('Are you sure you want to remove your profile photo?')) return;
    const $form = $('<form method="POST" action="/profile/image" style="display:none">').append($('<input type="hidden" name="_method" value="DELETE">')).append($('<input type="hidden" name="_token">').val($('meta[name="csrf-token"]').attr('content')));
    $('body').append($form), showToast('Removing profile photo...', 'info'), $form.submit();
}

// Legacy compatibility
const handleFileSelect = handleAvatarSelect, clearFilePreview = cancelAvatarChange, changeImage = inputId => $(`#${inputId}`).click();

function showToast(message, type = 'info') {
    window.toast ? window.toast.show(message, type) : console.log(`Toast (${type}): ${message}`) || ($ && createSimpleToast(message, type));
}

function createSimpleToast(message, type) {
    const colors = {success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500'};
    const $toast = $(`<div class="fixed top-4 right-4 ${colors[type] || colors.info} text-white px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300 opacity-0 translate-x-full"><div class="flex items-center gap-2"><span>${message}</span><button class="ml-2 text-white hover:text-gray-200 font-bold" onclick="$(this).closest('div').fadeOut()">&times;</button></div></div>`);
    $('body').append($toast), setTimeout(() => $toast.removeClass('opacity-0 translate-x-full'), 10), setTimeout(() => $toast.fadeOut(300, function() { $(this).remove(); }), 5000);
}
</script>