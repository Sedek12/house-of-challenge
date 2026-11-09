<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <meta name="description" content="OCD, Les Opportunités, C'est Dehors : un concept de Bovann. Présentez vos produits en direct sur le TikTok de House of Challenge pour vendre et gagner de l'argent. Réservez et payez votre passage en ligne.">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">
        <meta name="theme-color" content="#1F1F1F">
        <meta name="color-scheme" content="light">

        @php
            $loaderLogo = \App\Services\Settings::get('brand_logo_url') ?: '/images/logo-stacked.webp';
            $loaderColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) \App\Services\Settings::get('brand_color')) ? \App\Services\Settings::get('brand_color') : '#EE6400';
        @endphp
        <style>
            /* Écran de chargement : logo au centre, cercle qui tourne autour. Défini ici pour s'afficher dès le premier octet. */
            #app-loader { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: #fff; transition: opacity .35s ease, visibility .35s ease; }
            #app-loader.is-hidden { opacity: 0; visibility: hidden; pointer-events: none; }
            .app-loader__box { position: relative; display: flex; align-items: center; justify-content: center; width: 176px; height: 176px; }
            .app-loader__ring { position: absolute; inset: 0; box-sizing: border-box; border-radius: 50%; border: 6px solid {{ $loaderColor }}26; border-top-color: {{ $loaderColor }}; animation: app-loader-spin 1s linear infinite; }
            .app-loader__logo { width: 104px; height: auto; animation: app-loader-pulse 1.6s ease-in-out infinite; }
            .app-loader__sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
            @keyframes app-loader-spin { to { transform: rotate(360deg); } }
            @keyframes app-loader-pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(.94); } }
            @media (prefers-reduced-motion: reduce) { .app-loader__ring { animation-duration: 3s; } .app-loader__logo { animation: none; } }
            @media print { #app-loader { display: none; } }
        </style>
        <noscript><style>#app-loader { display: none; }</style></noscript>
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        <div id="app-loader" role="status" aria-live="polite">
            <div class="app-loader__box">
                <div class="app-loader__ring"></div>
                <img class="app-loader__logo" src="{{ $loaderLogo }}" alt="OCD Live" width="722" height="349">
            </div>
            <span class="app-loader__sr">Chargement en cours…</span>
        </div>
        @inertia
    </body>
</html>
