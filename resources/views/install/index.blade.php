@php
    $title = 'Installation - Jobs Board';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>

    <!-- Force HTTPS for assets in production -->
    @if(config('app.env') === 'production' && !request()->secure())
        <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    @endif

    @php
        $manifestPath = public_path('build/manifest.json');
        $cssFile = null;
        $jsFile = null;

        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $cssFile = $manifest['resources/css/app.css']['file'] ?? null;
            $jsFile = $manifest['resources/js/app.js']['file'] ?? null;
        }
    @endphp

    @if($cssFile)
        <link rel="preload" as="style" href="{{ asset('build/' . $cssFile) }}">
        <link rel="stylesheet" href="{{ asset('build/' . $cssFile) }}" crossorigin="anonymous">
    @else
        @vite(['resources/css/app.css'])
    @endif

    @if($jsFile)
        <link rel="modulepreload" as="script" href="{{ asset('build/' . $jsFile) }}">
        <script type="module" src="{{ asset('build/' . $jsFile) }}" crossorigin="anonymous"></script>
    @else
        @vite(['resources/js/app.js'])
    @endif

    @livewireStyles
    @livewireScriptConfig
</head>
<body class="min-h-screen bg-gray-50" style="display: block !important; visibility: visible !important; opacity: 1 !important;">
    <div class="flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl w-full space-y-8">
            <div class="text-center">
                <h1 class="text-4xl font-bold text-gray-900">Installation Wizard</h1>
                <p class="mt-2 text-gray-600">Setup your Jobs Board</p>
            </div>

            <div class="flex justify-center space-x-4 mb-8">
                <div class="text-center">
                    <div class="w-10 h-10 rounded-full mx-auto mb-2 bg-indigo-600 text-white flex items-center justify-center">1</div>
                    <div class="text-sm text-indigo-600">Checks</div>
                </div>
                <div class="text-center">
                    <div class="w-10 h-10 rounded-full mx-auto mb-2 bg-gray-300 text-gray-600 flex items-center justify-center">2</div>
                    <div class="text-sm text-gray-600">Admin</div>
                </div>
                <div class="text-center">
                    <div class="w-10 h-10 rounded-full mx-auto mb-2 bg-gray-300 text-gray-600 flex items-center justify-center">3</div>
                    <div class="text-sm text-gray-600">Complete</div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-8">
                <h2 class="text-2xl font-bold mb-6">System Checks</h2>

                <div class="space-y-4 mb-6">
                    <div class="flex justify-between p-4 rounded bg-green-50 border border-green-200">
                        <span class="font-medium">Database Connection</span><span class="text-xl">✓</span>
                    </div>
                    <div class="flex justify-between p-4 rounded bg-green-50 border border-green-200">
                        <span class="font-medium">Storage Permissions</span><span class="text-xl">✓</span>
                    </div>
                    <div class="flex justify-between p-4 rounded bg-green-50 border border-green-200">
                        <span class="font-medium">Cache Configuration</span><span class="text-xl">✓</span>
                    </div>
                </div>

                <button class="w-full bg-indigo-600 text-white py-3 rounded hover:bg-indigo-700 transition-colors">
                    Continue
                </button>
            </div>
        </div>
    </div>
    @livewireScripts
</body>
</html>