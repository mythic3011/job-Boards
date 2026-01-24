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
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    @if($help)
        <p class="text-sm text-gray-500 mb-2">{{ $help }}</p>
    @endif

    <div class="mt-1">
        <label for="{{ $name }}" class="cursor-pointer">
            <div class="flex items-center justify-center px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-400 hover:bg-indigo-50 transition-colors">
                <div class="text-center">
                    <x-heroicon-o-document-arrow-up class="mx-auto h-8 w-8 text-gray-400" />
                    <span class="mt-2 block text-sm font-medium text-gray-700">
                        Choose file
                    </span>
                    @if($maxSize)
                        <span class="mt-1 block text-xs text-gray-500">
                            Max size: {{ $maxSize }}
                        </span>
                    @endif
                </div>
            </div>
            <input
                type="file"
                id="{{ $name }}"
                name="{{ $name }}"
                accept="{{ $accept }}"
                {{ $required ? 'required' : '' }}
                class="hidden"
                {{ $attributes }}
            >
        </label>
    </div>

    @if($attributes->has('wire:model'))
        <div wire:loading.remove wire:target="{{ $attributes->get('wire:model') }}" class="mt-2">
            <div x-data x-show="$wire.{{ str_replace(['wire:model=', '"', "'"], '', $attributes->get('wire:model')) }}" class="flex items-center gap-2 text-sm text-gray-600">
                <x-heroicon-o-document class="h-5 w-5" />
                <span x-text="$wire.{{ str_replace(['wire:model=', '"', "'"], '', $attributes->get('wire:model')) }}?.getClientOriginalName()"></span>
            </div>
        </div>
        <div wire:loading wire:target="{{ $attributes->get('wire:model') }}" class="mt-2 text-sm text-gray-500">
            <div class="flex items-center gap-2">
                <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" />
                <span>Uploading...</span>
            </div>
        </div>
    @endif

    @if($error)
        <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
