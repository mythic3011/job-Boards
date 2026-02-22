<div class="min-h-screen bg-gray-50 py-12 px-4">
    <div class="max-w-xl mx-auto">
        {{-- Header --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-600 rounded-2xl mb-4 shadow-md">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Setup Your Job Board</h1>
            <p class="text-sm text-gray-500 mt-1">Complete the steps below to get started</p>
        </div>

        {{-- Step Indicators --}}
        <div class="flex items-center justify-center mb-8">
            @foreach([1 => 'Account', 2 => 'System', 3 => 'Security', 4 => 'Review'] as $step => $label)
                <div class="flex items-center" wire:key="step-indicator-{{ $step }}">
                    <div class="flex flex-col items-center">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold transition-all
                            {{ $currentStep > $step ? 'bg-green-500 text-white' : '' }}
                            {{ $currentStep === $step ? 'bg-indigo-600 text-white ring-4 ring-indigo-100' : '' }}
                            {{ $currentStep < $step ? 'bg-white text-gray-400 border-2 border-gray-200' : '' }}">
                            @if($currentStep > $step)
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            @else
                                {{ $step }}
                            @endif
                        </div>
                        <span class="text-xs mt-1.5 font-medium
                            {{ $currentStep === $step ? 'text-indigo-600' : '' }}
                            {{ $currentStep > $step ? 'text-green-600' : '' }}
                            {{ $currentStep < $step ? 'text-gray-400' : '' }}">
                            {{ $label }}
                        </span>
                    </div>
                    @if($step < 4)
                        <div class="w-12 h-px mx-1 mb-5
                            {{ $currentStep > $step ? 'bg-green-400' : 'bg-gray-200' }}">
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Error Message --}}
        @if($error)
            <div class="mb-4 flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ $error }}
            </div>
        @endif

        {{-- Step Content --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8" wire:key="step-content-{{ $currentStep }}">
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

        <p class="text-center text-xs text-gray-400 mt-6">Step {{ $currentStep }} of 4</p>
    </div>
</div>
