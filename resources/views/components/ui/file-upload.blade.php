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

    <div class="mt-1 flex items-center gap-4" x-data="{ fileName: '' }">
        <label for="{{ $name }}" class="cursor-pointer flex-shrink-0 w-full">
            <div class="flex flex-col items-center justify-center px-4 py-6 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-400 hover:bg-indigo-50 transition-colors w-full">
                <div class="text-center">
                    <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                    <span class="mt-2 block text-sm font-medium text-gray-700">
                        {{ $attributes->has('wire:model') ? 'Upload file' : 'Choose file' }}
                    </span>
                    @if($maxSize)
                        <span class="mt-1 block text-xs text-gray-500">
                            Max size: {{ $maxSize }}
                        </span>
                    @endif
                </div>

                <div class="mt-2 text-xs text-gray-500">
                     <span x-text="fileName || 'No file selected'"></span>
                </div>
            </div>

            <input
                type="file"
                id="{{ $name }}"
                name="{{ $name }}"
                accept="{{ $accept }}"
                {{ $required ? 'required' : '' }}
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