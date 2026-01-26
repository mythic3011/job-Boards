{{--
    Simplified asset loading using Laravel's built-in Vite directive.
    The @vite directive automatically handles:
    - Development mode (when dev server is running)
    - Production mode (when assets are built)
    - Proper asset paths and integrity hashes
--}}
@vite(['resources/css/app.css', 'resources/js/app.js'])
