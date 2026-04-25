<div class="theme-auth-shell py-12" data-install-livewire-root>
    <div class="max-w-xl mx-auto">
        {{-- Header --}}
        <div class="text-center mb-8">
            <div class="theme-auth-emblem inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-4 shadow-md">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <h1 class="theme-text-strong text-2xl font-bold">Setup Your Job Board</h1>
            <p class="theme-text-muted text-sm mt-1">Complete the steps below to get started</p>
        </div>

        {{-- Step Indicators --}}
        <div class="flex items-center justify-center mb-8">
            @foreach([1 => 'Account', 2 => 'System', 3 => 'Security', 4 => 'Review'] as $step => $label)
                <div class="flex items-center" wire:key="step-indicator-{{ $step }}">
                    <div class="flex flex-col items-center">
                        <div
                            class="theme-install-step-indicator w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold"
                            data-state="{{ $currentStep > $step ? 'completed' : ($currentStep === $step ? 'active' : 'upcoming') }}"
                        >
                            @if($currentStep > $step)
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            @else
                                {{ $step }}
                            @endif
                        </div>
                        <span
                            class="theme-install-step-label text-xs mt-1.5 font-medium"
                            data-state="{{ $currentStep > $step ? 'completed' : ($currentStep === $step ? 'active' : 'upcoming') }}"
                        >
                            {{ $label }}
                        </span>
                    </div>
                    @if($step < 4)
                        <div
                            class="theme-install-step-connector w-12 h-px mx-1 mb-5"
                            data-completed="{{ $currentStep > $step ? 'true' : 'false' }}"
                        >
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Error Message --}}
        @if($error)
            <div class="theme-alert theme-alert-error mb-4 flex items-start gap-3 px-4 py-3 rounded-2xl text-sm border">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ $error }}
            </div>
        @endif

        {{-- Step Content --}}
        <div class="theme-panel rounded-[2rem] border p-8" wire:key="step-content-{{ $currentStep }}">
            @if($currentStep === 1)
                @include('livewire.install.steps.account')
            @elseif($currentStep === 2)
                @include('livewire.install.steps.system')
            @elseif($currentStep === 3)
                @include('livewire.install.steps.security')
            @elseif($currentStep === 4)
                @include('livewire.install.steps.review')
            @endif
        </div>

        <p class="theme-text-muted text-center text-xs mt-6">Step {{ $currentStep }} of 4</p>
    </div>
</div>
