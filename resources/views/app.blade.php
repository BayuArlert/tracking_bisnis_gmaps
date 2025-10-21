<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        <!-- Suppress browser extension errors early -->
        <script>
            // Early error suppression for browser extensions
            (function() {
                window.addEventListener('error', function(event) {
                    var filename = event.filename || '';
                    var message = event.message || '';
                    
                    // Suppress extension errors
                    if (filename.includes('content.bundle') || 
                        filename.includes('content.js') ||
                        filename.includes('extension://') ||
                        message.includes('parentElement')) {
                        event.preventDefault();
                        event.stopPropagation();
                        return false;
                    }
                }, true);
            })();
        </script>

        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
