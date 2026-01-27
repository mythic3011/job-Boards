@props([
    'src' => null,
    'name' => null,
    'size' => 'md',
    'class' => '',
])

@php
    $sizes = [
        'xs' => 'w-6 h-6 text-xs',
        'sm' => 'w-8 h-8 text-sm',
        'md' => 'w-16 h-16 text-lg',
        'lg' => 'w-20 h-20 text-xl',
        'xl' => 'w-24 h-24 text-2xl',
        '2xl' => 'w-32 h-32 text-4xl',
    ];
    
    $sizeClasses = $sizes[$size] ?? $sizes['md'];
    $initial = $name ? strtoupper(substr($name, 0, 1)) : '?';
@endphp

<div class="relative {{ $sizeClasses }} rounded-full overflow-hidden {{ $class }}">
    @if($src)
        <!-- Profile Image -->
        <img src="{{ $src }}" 
             alt="{{ $name ? $name . '\'s avatar' : 'Avatar' }}" 
             class="w-full h-full object-cover">
    @else
        <!-- Default Avatar with Initial -->
        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-500 to-purple-600">
            <span class="font-bold text-white">{{ $initial }}</span>
        </div>
    @endif
</div>