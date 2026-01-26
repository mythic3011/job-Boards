@php
    // Check if Vite dev server is running by looking for the hot file
    $isViteDevServer = file_exists(public_path('hot'));
    
    // Check if production build exists
    $manifestPath = public_path('build/.vite/manifest.json');
    $legacyManifestPath = public_path('build/manifest.json');
    $hasProductionBuild = file_exists($manifestPath) || file_exists($legacyManifestPath);
@endphp

@if($isViteDevServer)
    {{-- Development mode: Use Vite dev server --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@elseif($hasProductionBuild)
    {{-- Production mode: Use built assets --}}
    @php
        // Try new manifest path first, fallback to legacy
        $manifestFile = file_exists($manifestPath) ? $manifestPath : $legacyManifestPath;
        $manifest = json_decode(file_get_contents($manifestFile), true);
        
        // Get the CSS and JS files from manifest
        $cssFile = $manifest['resources/css/app.css']['file'] ?? null;
        $jsFile = $manifest['resources/js/app.js']['file'] ?? null;
    @endphp
    
    @if($cssFile)
        <link rel="preload" as="style" href="{{ asset('build/' . $cssFile) }}">
        <link rel="stylesheet" href="{{ asset('build/' . $cssFile) }}" crossorigin="anonymous">
    @endif
    
    @if($jsFile)
        <link rel="modulepreload" as="script" href="{{ asset('build/' . $jsFile) }}">
        <script type="module" src="{{ asset('build/' . $jsFile) }}" crossorigin="anonymous"></script>
    @endif
@else
    {{-- Fallback: Try Vite directive (will work if dev server starts later) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endif
