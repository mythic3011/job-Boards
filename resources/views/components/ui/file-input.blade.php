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
    $hasCurrentImage = !empty($currentImage);
@endphp

<div class="space-y-4">
    @if($label)
        <x-ui.form-label :for="$inputId" :required="$required">
            {{ $label }}
        </x-ui.form-label>
    @endif

    <div class="flex flex-col items-center space-y-4">
        <div class="relative group">
            <div class="relative w-32 h-32 rounded-full overflow-hidden border-4 {{ $hasError ? 'border-red-300' : 'border-gray-200' }} bg-indigo-100 shadow-lg"
                 id="{{ $inputId }}-container">
                @if($hasCurrentImage)
                    <img src="{{ $currentImage }}" 
                         alt="Profile Image" 
                         class="w-full h-full object-cover transition-all duration-300 group-hover:scale-110"
                         id="{{ $inputId }}-current-avatar">
                @else
                    <!-- Default Avatar with Initial -->
                    <div class="w-full h-full flex items-center justify-center bg-indigo-100"
                         id="{{ $inputId }}-current-avatar">
                        <span class="text-4xl font-bold text-white">{{ $userInitial }}</span>
                    </div>
                @endif
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
                data-has-current="{{ $currentImage ? '1' : '0' }}"
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
                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
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

    if (!file) return;

    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    if (!allowedTypes.includes(file.type.toLowerCase())) {
        showToast('Please select a valid image file (JPG, PNG, WebP, or GIF)', 'error');
        input.value = '';
        return;
    }

    if (file.size > 2097152) {
        showToast('File size must be less than 2MB', 'error');
        input.value = '';
        return;
    }

    showAvatarPreview(file, input);
}

function showAvatarPreview(file, input) {
    const reader = new FileReader();
    reader.onload = e => {
        const container = document.getElementById(`${input.id}-container`);
        const current = document.getElementById(`${input.id}-current-avatar`);
        const actions = document.getElementById(`${input.id}-actions`);

        if (container && current) {
            let preview = document.getElementById(`${input.id}-preview-avatar`);
            if (!preview) {
                preview = document.createElement('img');
                preview.id = `${input.id}-preview-avatar`;
                preview.alt = 'Preview';
                preview.className = 'w-full h-full object-cover absolute inset-0 rounded-full';
                container.appendChild(preview);
            }

            preview.src = e.target.result;
            preview.style.cssText = 'width: 100% !important; height: 100% !important; object-fit: cover !important; position: absolute !important; inset: 0 !important; opacity: 1 !important; visibility: visible !important; display: block !important; z-index: 10; border: none !important; border-radius: 50% !important;';
            container.style.cssText = 'background: transparent !important; border: none !important; box-shadow: 0 0 0 2px #e5e7eb;';
            current.style.opacity = '0.3';
            if (actions) {
                actions.classList.remove('opacity-0');
                actions.classList.add('opacity-100');
            }
            pendingChange = {inputId: input.id, file, dataUrl: e.target.result};
            showToast('Photo ready to save', 'info');
        }
    };

    reader.onerror = () => {
        showToast('Error reading file. Please try again.', 'error');
        input.value = '';
    };

    reader.readAsDataURL(file);
}

function confirmAvatarChange(inputId) {
    if (!pendingChange || pendingChange.inputId !== inputId) return showToast('No changes to save', 'warning');
    
    const $preview = document.getElementById(`${inputId}-preview-avatar`);
    const $current = document.getElementById(`${inputId}-current-avatar`);
    const $actions = document.getElementById(`${inputId}-actions`);
    const $success = document.getElementById(`${inputId}-success-indicator`);
    
    if ($preview && $current) {
        // Keep the preview visible as the main display
        $preview.style.zIndex = '20';
        $current.style.opacity = '1';
    }
    
    if ($actions) {
        $actions.classList.add('opacity-0');
        $actions.classList.remove('opacity-100');
    }
    
    if ($success) {
        $success.classList.remove('hidden');
        $success.classList.add('flex');
    }
    
    pendingChange = null;
    showToast('Profile photo updated! Remember to save your changes.', 'success');
}

function cancelAvatarChange(inputId) {
    const $input = document.getElementById(inputId);
    const $preview = document.getElementById(`${inputId}-preview-avatar`);
    const $current = document.getElementById(`${inputId}-current-avatar`);
    const $actions = document.getElementById(`${inputId}-actions`);
    
    if ($input) $input.value = '';
    if ($preview) {
        $preview.remove();
    }
    if ($current) $current.style.opacity = '1';
    if ($actions) {
        $actions.classList.add('opacity-0');
        $actions.classList.remove('opacity-100');
    }
    
    pendingChange = null;
    showToast('Changes cancelled', 'info');
}

function resetAvatarState(input) {
    const $preview = document.getElementById(`${input.id}-preview-avatar`);
    const $current = document.getElementById(`${input.id}-current-avatar`);
    const $actions = document.getElementById(`${input.id}-actions`);
    const $success = document.getElementById(`${input.id}-success-indicator`);
    
    if ($preview) {
        $preview.remove();
    }
    if ($current) $current.style.opacity = '1';
    if ($actions) {
        $actions.classList.add('opacity-0');
        $actions.classList.remove('opacity-100');
    }
    if ($success) {
        $success.classList.add('hidden');
        $success.classList.remove('flex');
    }
}

function removeCurrentAvatar(inputId) {
    if (!confirm('Are you sure you want to remove your profile photo?')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/profile/image';
    form.style.display = 'none';
    const methodInput = document.createElement('input');
    methodInput.type = 'hidden';
    methodInput.name = '_method';
    methodInput.value = 'DELETE';
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    const tokenInput = document.createElement('input');
    tokenInput.type = 'hidden';
    tokenInput.name = '_token';
    tokenInput.value = tokenMeta ? tokenMeta.getAttribute('content') : '';
    form.appendChild(methodInput);
    form.appendChild(tokenInput);
    document.body.appendChild(form);
    showToast('Removing profile photo...', 'info');
    form.submit();
}

// Legacy compatibility
const handleFileSelect = handleAvatarSelect, clearFilePreview = cancelAvatarChange, changeImage = inputId => document.getElementById(inputId)?.click();

function showToast(message, type = 'info') {
    if (window.toast) {
        window.toast.show(message, type);
    } else {
        console.log(`Toast (${type}): ${message}`);
    }
}
</script>