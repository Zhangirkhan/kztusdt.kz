@php
    $seo = \App\Support\SeoPresenter::forBlade(request());
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <script>
            (function () {
                if (!('serviceWorker' in navigator)) {
                    return;
                }

                navigator.serviceWorker.getRegistrations().then(function (registrations) {
                    registrations.forEach(function (registration) {
                        var url = (registration.active && registration.active.scriptURL) || '';

                        // Remove the legacy Workbox precache SW that caused stale
                        // assets, but keep the push SW served at /sw.js.
                        if (url.indexOf('/build/') !== -1 || url.indexOf('workbox') !== -1) {
                            registration.unregister();
                        }
                    });
                });

                if ('caches' in window) {
                    caches.keys().then(function (keys) {
                        keys.forEach(function (key) {
                            if (/workbox|precache|pages-vite/i.test(key)) {
                                caches.delete(key);
                            }
                        });
                    });
                }
            })();
        </script>
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#0b0f14">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="manifest" href="/build/manifest.webmanifest">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="/icons/icon-32.png">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">

        @if (! empty($seo['description']))
            <meta name="description" content="{{ $seo['description'] }}">
        @endif
        <meta name="robots" content="{{ $seo['robots'] }}">
        @if (! empty($seo['canonical']))
            <link rel="canonical" href="{{ $seo['canonical'] }}">
        @endif
        @if (! empty($seo['ogTitle']))
            <meta property="og:title" content="{{ $seo['ogTitle'] }}">
            <meta property="og:description" content="{{ $seo['ogDescription'] }}">
            <meta property="og:url" content="{{ $seo['ogUrl'] }}">
            <meta property="og:type" content="{{ $seo['ogType'] }}">
            <meta property="og:site_name" content="{{ $seo['ogSiteName'] }}">
            @if (! empty($seo['ogImage']))
                <meta property="og:image" content="{{ $seo['ogImage'] }}">
            @endif
            <meta name="twitter:card" content="{{ $seo['twitterCard'] }}">
            <meta name="twitter:title" content="{{ $seo['ogTitle'] }}">
            <meta name="twitter:description" content="{{ $seo['ogDescription'] }}">
        @endif
        @if (! empty($seo['jsonLd']))
            <script type="application/ld+json">{!! json_encode($seo['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
        @endif

        <title inertia>{{ $seo['title'] ?? config('company.name', config('app.name')) }}</title>

        @routes(\App\Support\AdminNavPresenter::ziggyGroup(auth()->user()))
        @vite(['resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        <noscript>
            <div style="max-width:420px;margin:2rem auto;padding:1.5rem;font-family:sans-serif;color:#e8eaed;background:#151c26;border-radius:1rem;">
                <p style="font-size:1.125rem;font-weight:700;margin:0 0 .75rem;">kztusdt.kz</p>
                <p style="margin:0;color:#9aa4b2;">Для работы сервиса нужен JavaScript. Включите его в настройках браузера и обновите страницу.</p>
            </div>
        </noscript>
        @inertia
    </body>
</html>
