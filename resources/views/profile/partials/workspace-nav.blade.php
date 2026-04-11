@props([
    'active' => 'show',
    'twoFactorEnabled' => false,
])

@php
    $items = [
        [
            'key' => 'show',
            'label' => 'Overview',
            'href' => route('profile.show'),
            'enabled' => true,
        ],
        [
            'key' => 'edit',
            'label' => 'Edit Profile',
            'href' => route('profile.edit'),
            'enabled' => true,
        ],
        [
            'key' => 'password',
            'label' => 'Change Password',
            'href' => $twoFactorEnabled ? route('profile.password') : null,
            'enabled' => $twoFactorEnabled,
        ],
        [
            'key' => 'two-factor',
            'label' => 'Security',
            'href' => route('profile.two-factor'),
            'enabled' => true,
        ],
    ];
@endphp

<div class="mb-6 rounded-xl border border-gray-200 bg-white p-1.5 shadow-sm">
    <div class="flex flex-wrap gap-1.5">
        @foreach($items as $item)
            @php
                $isActive = $active === $item['key'];
                $baseClasses = 'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors';
                $stateClasses = $isActive
                    ? 'bg-indigo-600 text-white shadow-sm'
                    : ($item['enabled']
                        ? 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                        : 'text-gray-400');
            @endphp

            @if($item['enabled'] && $item['href'])
                <a href="{{ $item['href'] }}" class="{{ $baseClasses }} {{ $stateClasses }}">
                    <span>{{ $item['label'] }}</span>
                    @if($item['key'] === 'password' && $twoFactorEnabled)
                        <span class="rounded-full {{ $isActive ? 'bg-white/20 text-white' : 'bg-indigo-50 text-indigo-700' }} px-2 py-0.5 text-[11px] font-semibold">2FA</span>
                    @endif
                </a>
            @else
                <div class="{{ $baseClasses }} {{ $stateClasses }}" aria-disabled="true" title="Enable two-factor authentication first">
                    <span>{{ $item['label'] }}</span>
                    <span class="rounded-full bg-yellow-50 px-2 py-0.5 text-[11px] font-semibold text-yellow-700">Locked</span>
                </div>
            @endif
        @endforeach
    </div>
</div>
