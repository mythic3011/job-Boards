@props([
    'active' => 'show',
    'twoFactorEnabled' => false,
    'registrationPending' => false,
])

@php
    $items = $registrationPending
        ? [
            [
                'key' => 'two-factor',
                'label' => 'Finish Activation',
                'href' => route('profile.two-factor'),
                'enabled' => true,
            ],
        ]
        : [
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

<div class="theme-panel mb-6 rounded-xl border p-1.5 shadow-sm">
    <div class="flex flex-wrap gap-1.5">
        @foreach($items as $item)
            @php
                $isActive = $active === $item['key'];
                $baseClasses = 'theme-text-strong inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors';
                $stateClasses = $isActive
                    ? 'theme-link-active-chip shadow-sm'
                    : ($item['enabled']
                        ? 'theme-text-muted hover:bg-[var(--app-panel-subtle-bg)] hover:text-[var(--app-text-strong)]'
                        : 'theme-text-muted opacity-60');
            @endphp

            @if($item['enabled'] && $item['href'])
                <a href="{{ $item['href'] }}" class="{{ $baseClasses }} {{ $stateClasses }}">
                    <span>{{ $item['label'] }}</span>
                    @if($item['key'] === 'password' && $twoFactorEnabled)
                        <span class="theme-pill rounded-full px-2 py-0.5 text-[11px] font-semibold">2FA</span>
                    @endif
                </a>
            @else
                <div class="{{ $baseClasses }} {{ $stateClasses }}" aria-disabled="true" title="Enable two-factor authentication first">
                    <span>{{ $item['label'] }}</span>
                    <span class="theme-alert-warning rounded-full border px-2 py-0.5 text-[11px] font-semibold">Locked</span>
                </div>
            @endif
        @endforeach
    </div>
</div>
