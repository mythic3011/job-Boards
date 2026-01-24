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
</head>
<body class="min-h-screen bg-gray-50" style="display: block !important; visibility: visible !important; opacity: 1 !important;">
    <div id="app"></div>
</body>
</html>