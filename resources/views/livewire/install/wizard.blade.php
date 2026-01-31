<div class="min-h-screen bg-gradient-to-br from-indigo-50 to-blue-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-600 rounded-2xl mb-4 shadow-lg">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Setup Your Job Board</h1>
            <p class="text-gray-600">Let's get everything configured in just a few steps</p>
        </div>

        <!-- Step Indicators -->
        <div class="flex justify-between mb-8 max-w-md mx-auto">
            @foreach([1 => 'Account', 2 => 'System', 3 => 'Security', 4 => 'Review'] as $step => $label)
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full border-2 flex items-center justify-center font-semibold text-sm transition-all duration-200 
                        @if($currentStep > $step) bg-green-500 text-white border-green-500
                        @elseif($currentStep === $step) bg-indigo-600 text-white border-indigo-600 ring-4 ring-indigo-200
                        @else bg-white text-gray-400 border-gray-300
                        @endif">
                        @if($currentStep > $step)
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        @else
                            {{ $step }}
                        @endif
                    </div>
                    <span class="text-xs mt-2 font-medium 
                        @if($currentStep === $step) text-indigo-600
                        @elseif($currentStep > $step) text-green-600
                        @else text-gray-400
                        @endif">{{ $label }}</span>
                </div>
            @endforeach
        </div>

        <!-- Error Message -->
        @if($error)
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                {{ $error }}
            </div>
        @endif

        <!-- Step Content -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
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
    </div>
</div>
