@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'accept' => 'image/*',
    'help' => null,
    'maxSize' => '2MB',
    'preview' => false,
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

    <div class="mt-1 flex items-center gap-4">
        <label for="{{ $name }}" class="cursor-pointer flex-shrink-0">
            <div class="flex items-center justify-center px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-400 hover:bg-indigo-50 transition-colors">
                <div class="text-center">
                    <x-heroicon-o-photo class="mx-auto h-8 w-8 text-gray-400" />
                    <span class="mt-2 block text-sm font-medium text-gray-700">
                        Choose image
                    </span>
                    <span class="mt-1 block text-xs text-gray-500">
                        Max size: {{ $maxSize }}
                    </span>
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

        @if($preview && $attributes->has('wire:model'))
            <div class="flex-1">
                <div wire:loading.remove wire:target="{{ $attributes->get('wire:model') }}">
                    <template x-if="$wire.{{ str_replace(['wire:model=', '"', "'"], '', $attributes->get('wire:model')) }}">
                        <img 
                            :src="$wire.{{ str_replace(['wire:model=', '"', "'"], '', $attributes->get('wire:model')) }}?.temporaryUrl()" 
                            alt="Preview" 
                            class="h-20 w-20 rounded-lg object-cover border border-gray-300"
                        >
                    </template>
                </div>
                <div wire:loading wire:target="{{ $attributes->get('wire:model') }}" class="text-sm text-gray-500">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" />
                        <span>Uploading...</span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <x-ui.form-error :name="$name" :message="$error" />
</div>
