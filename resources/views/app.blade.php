<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark', 'h-full'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'fann') }}</title>
        
        <link rel="favicon" href="/icons/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png"/>
        <link rel="manifest" href="/site.webmanifest">


        <meta name="theme-color" content="#0ea5e9">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="fann">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue", "resources/css/app.css"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased min-h-screen">
        @inertia
    </body>
</html>
