@php
    $locale = app()->getLocale();
    $direction = \App\Http\Middleware\SetLocale::direction($locale);
@endphp
<!DOCTYPE html>
{{-- translate="no" + the google meta: the site has its own language switcher, and a browser
     machine-translating it again garbles the real translations (and brand names). --}}
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $direction }}" translate="no">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="google" content="notranslate">

        <title inertia>{{ app(\App\Services\BrandingService::class)->summary()['title'] }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&family=jetbrains-mono:400,500,600&display=swap" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
