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
        <label for="{{ $name }}" class="cursor-pointer shrink-0">
            <div class="theme-panel-subtle flex items-center justify-center rounded-lg border-2 border-dashed px-4 py-3 transition-colors hover:border-[var(--app-accent-soft-border)] hover:bg-[var(--app-accent-soft-bg)]">
                <div class="text-center">
                    <x-heroicon-o-photo class="theme-text-muted mx-auto h-8 w-8" />
                    <span class="theme-text-strong mt-2 block text-sm font-medium">
                        Choose image
                    </span>
                    <span class="theme-text-muted mt-1 block text-xs">
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
                            class="h-20 w-20 rounded-lg border border-[var(--app-panel-border)] object-cover"
                        >
                    </template>
                </div>
                <div wire:loading wire:target="{{ $attributes->get('wire:model') }}" class="theme-text-muted text-sm">
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
