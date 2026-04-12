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
    $fallbackId = $inputId . '-fallback-avatar';
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
            <div class="relative w-32 h-32 rounded-full overflow-hidden border-4 {{ $hasError ? 'border-red-300' : 'border-[var(--app-panel-border)]' }} bg-gradient-to-br from-indigo-100 to-sky-100 shadow-lg"
                 id="{{ $inputId }}-container">
                <div class="relative w-full h-full" id="{{ $inputId }}-current-avatar">
                    @if($hasCurrentImage)
                        <img src="{{ $currentImage }}"
                             alt="Profile Image"
                             class="w-full h-full object-cover hidden transition-all duration-300 group-hover:scale-110"
                             id="{{ $inputId }}-current-image"
                             data-avatar-image
                             data-avatar-input-id="{{ $inputId }}"
                             data-avatar-fallback-id="{{ $fallbackId }}">
                    @endif

                    <div class="absolute inset-0 w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-400 to-sky-500"
                         id="{{ $fallbackId }}">
                        <span class="text-4xl font-bold text-white">{{ $userInitial }}</span>
                    </div>
                </div>
            </div>
            
            <label for="{{ $inputId }}" 
                   class="absolute inset-0 w-32 h-32 rounded-full cursor-pointer {{ $disabled ? 'cursor-not-allowed' : '' }}">
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-colors duration-300 rounded-full flex items-center justify-center">
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
                data-avatar-input
                data-has-current="{{ $currentImage ? '1' : '0' }}"
                {{ $attributes->except(['class']) }}
            >
        </div>
        
        <div class="text-center">
            <p class="theme-text-strong mb-1 text-sm font-medium">
                {{ $disabled ? 'Upload disabled' : 'Click to update your photo' }}
            </p>
            <p class="theme-text-muted text-xs">
                JPG, PNG, WebP or GIF, up to 2MB
            </p>
        </div>
        
        <div class="flex space-x-3 opacity-0 transition-opacity duration-300" id="{{ $inputId }}-actions">
            <button type="button"
                    data-avatar-action="confirm"
                    data-avatar-target="{{ $inputId }}"
                    class="theme-button theme-button-primary rounded-lg px-4 py-2 text-sm font-medium shadow-sm">
                Save Photo
            </button>
            <button type="button"
                    data-avatar-action="cancel"
                    data-avatar-target="{{ $inputId }}"
                    class="theme-button theme-button-outline rounded-lg px-4 py-2 text-sm font-medium">
                Cancel
            </button>
        </div>

        @if($currentImage)
            <button type="button"
                    data-avatar-action="remove"
                    data-avatar-target="{{ $inputId }}"
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
