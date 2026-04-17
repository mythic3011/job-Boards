@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'accept' => null,
    'help' => null,
    'maxSize' => null,
    'error' => null,
])

<div>
    @if($label)
        <x-ui.form-label :for="$name" :required="$required" class="mb-1">
            {{ $label }}
        </x-ui.form-label>
    @endif

    @if($help)
        <x-ui.form-help class="mb-2">{{ $help }}</x-ui.form-help>
    @endif

    @php
        $isLivewireUpload = $attributes->has('wire:model')
            || $attributes->has('wire:model.live')
            || $attributes->has('wire:model.defer')
            || $attributes->has('wire:model.lazy');
    @endphp

    <div class="mt-1 flex items-center gap-4" x-data="{ fileName: '' }">
        <label for="{{ $name }}" class="cursor-pointer shrink-0 w-full">
            <div class="theme-panel-subtle flex w-full flex-col items-center justify-center rounded-2xl border-2 border-dashed px-4 py-6 transition-colors hover:border-[var(--app-accent-soft-border)] hover:bg-[var(--app-panel-bg)]">
                <div class="text-center">
                    <svg class="theme-text-muted mx-auto h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                    <span class="theme-text-strong mt-2 block text-sm font-medium">
                        {{ $attributes->has('wire:model') ? 'Upload file' : 'Choose file' }}
                    </span>
                    @if($maxSize)
                        <span class="theme-text-muted mt-1 block text-xs">
                            Max size: {{ $maxSize }}
                        </span>
                    @endif
                </div>

                <div class="theme-text-muted mt-2 text-xs">
                     <span x-text="fileName || 'No file selected'"></span>
                </div>
            </div>

            <input
                type="file"
                id="{{ $name }}"
                name="{{ $name }}"
                accept="{{ $accept }}"
                {{ $required && !$isLivewireUpload ? 'required' : '' }}
                class="hidden"
                @change="fileName = $event.target.files[0] ? $event.target.files[0].name : ''"
                {{ $attributes }}
            >
        </label>
    </div>

    @if($error || ($errors->has($name)))
        <x-ui.form-error :error="$error ?? $errors->first($name)" class="mt-2" />
    @endif
</div>
