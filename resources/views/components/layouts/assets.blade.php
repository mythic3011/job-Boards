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
