@php
    $seo = \App\Support\SeoPresenter::forBlade(request());
    $isAdminSurface = \App\Support\AdminUrl::isAdminHost(request());
    $assetVersion = is_readable(public_path('build/manifest.json'))
        ? (string) filemtime(public_path('build/manifest.json'))
        : '0';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-admin-surface="{{ $isAdminSurface ? 'true' : 'false' }}">
    <head>
        <meta charset="utf-8">
        <script>
            (function () {
                var isAdminHost = {{ \App\Support\AdminUrl::isAdminHost(request()) ? 'true' : 'false' }};
                if (!isAdminHost && window.location.pathname.indexOf('/admin') === 0) {
                    window.location.replace(@json(\App\Support\AdminUrl::base()) + window.location.pathname + window.location.search + window.location.hash);
                }
            })();
        </script>
        <script>
            (function () {
                if (!('serviceWorker' in navigator)) {
                    return;
                }

                var isAdmin = {{ $isAdminSurface ? 'true' : 'false' }};
                var purgeKey = isAdmin ? 'kztusdt_legacy_cache_purged_admin_v10' : 'kztusdt_legacy_cache_purged_client_v10';

                try {
                    // Drop flaky ETag probe keys that caused reload loops / white screen.
                    localStorage.removeItem(isAdmin ? 'kztusdt_admin_build_etag' : 'kztusdt_client_build_etag');
                    sessionStorage.removeItem(isAdmin ? 'kztusdt_admin_build_reload' : 'kztusdt_client_build_reload');
                } catch (error) {}

                if (localStorage.getItem(purgeKey) === '1') {
                    return;
                }

                navigator.serviceWorker.getRegistrations().then(function (registrations) {
                    registrations.forEach(function (registration) {
                        var url = (registration.active && registration.active.scriptURL) || '';

                        // Drop only legacy Workbox / hashed build SWs. Keep /sw.js for PWA.
                        if (
                            url.indexOf('/build/') !== -1
                            || url.indexOf('workbox') !== -1
                            || url.endsWith('/build/sw.js')
                        ) {
                            registration.unregister();
                        }
                    });
                });

                if ('caches' in window) {
                    caches.keys().then(function (keys) {
                        keys.forEach(function (key) {
                            caches.delete(key);
                        });
                    });
                }

                localStorage.setItem(purgeKey, '1');
            })();
        </script>
        <script>
            (function () {
                var version = @json($assetVersion);
                var isAdmin = {{ $isAdminSurface ? 'true' : 'false' }};
                var key = isAdmin ? 'kztusdt_admin_asset_v' : 'kztusdt_client_asset_v';
                var reloadKey = isAdmin ? 'kztusdt_admin_force_reload' : 'kztusdt_client_force_reload';
                var previous = localStorage.getItem(key);

                // One-shot reload after deploy; never loop on oscillating headers.
                if (previous && previous !== version && sessionStorage.getItem(reloadKey) !== '1') {
                    localStorage.setItem(key, version);
                    sessionStorage.setItem(reloadKey, '1');
                    window.location.reload();
                    return;
                }

                localStorage.setItem(key, version);
                sessionStorage.removeItem(reloadKey);
            })();
        </script>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
        <script>
            (function () {
                var isAdmin = {{ $isAdminSurface ? 'true' : 'false' }};
                var key = 'kztusdt.theme';

                if (isAdmin) {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.dataset.theme = 'light';
                    return;
                }

                var stored = localStorage.getItem(key);
                var dark = stored === 'dark';
                if (dark) {
                    document.documentElement.classList.add('dark');
                }
                document.documentElement.dataset.theme = dark ? 'dark' : 'light';
                window.__kztusdtClientDark = dark;
            })();
        </script>
        <meta name="theme-color" content="{{ $isAdminSurface ? '#001529' : '#ffffff' }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="{{ $isAdminSurface ? 'black' : 'black-translucent' }}">
        <meta name="apple-mobile-web-app-title" content="{{ $isAdminSurface ? 'Admin kztusdt' : 'KZTUSDT' }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="manifest" href="/manifest.webmanifest">
        @if ($isAdminSurface)
            <link rel="icon" href="/icons/admin/favicon.ico" sizes="any">
            <link rel="icon" type="image/png" sizes="32x32" href="/icons/admin/icon-32.png">
            <link rel="apple-touch-icon" href="/icons/admin/icon-192.png">
        @else
            <link rel="icon" data-theme-favicon="ico" href="/favicon.ico" sizes="any">
            <link rel="icon" data-theme-favicon="png" type="image/png" sizes="32x32" href="/icons/icon-32.png">
            <link rel="apple-touch-icon" href="/icons/icon-192.png">
            <script>
                (function () {
                    if (!window.__kztusdtClientDark) {
                        return;
                    }
                    var ico = document.querySelector('link[rel="icon"][data-theme-favicon="ico"]');
                    var png = document.querySelector('link[rel="icon"][data-theme-favicon="png"]');
                    if (ico) ico.setAttribute('href', '/favicon-dark.ico');
                    if (png) png.setAttribute('href', '/icons/icon-32-dark.png');
                })();
            </script>
        @endif

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
        <div id="app-boot-splash" style="position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:{{ $isAdminSurface ? '#001529' : '#eef1f6' }};color:{{ $isAdminSurface ? '#ffffff' : '#0f172a' }};font-family:Manrope,ui-sans-serif,system-ui,sans-serif;">
            <div style="text-align:center;padding:24px;">
                <p style="margin:0 0 8px;font-size:18px;font-weight:700;">{{ $isAdminSurface ? 'Admin kztusdt' : 'KZTUSDT' }}</p>
                <p style="margin:0;font-size:14px;color:{{ $isAdminSurface ? '#94a3b8' : '#64748b' }};">Загрузка…</p>
            </div>
        </div>
        <noscript>
            <div style="max-width:420px;margin:2rem auto;padding:1.5rem;font-family:sans-serif;color:#e8eaed;background:#151c26;border-radius:1rem;">
                <p style="font-size:1.125rem;font-weight:700;margin:0 0 .75rem;">kztusdt.kz</p>
                <p style="margin:0;color:#9aa4b2;">Для работы сервиса нужен JavaScript. Включите его в настройках браузера и обновите страницу.</p>
            </div>
        </noscript>
        @inertia
        <script>
            (function () {
                var isAdmin = document.documentElement.dataset.adminSurface === 'true';
                var recoveryKey = isAdmin ? 'kztusdt_admin_boot_recovery' : 'kztusdt_client_boot_recovery';
                var bootTimeoutMs = 30000;

                function hardReload() {
                    var jobs = [];

                    try {
                        localStorage.removeItem('kztusdt_admin_asset_v');
                        localStorage.removeItem('kztusdt_client_asset_v');
                        localStorage.removeItem('kztusdt_admin_build_etag');
                        localStorage.removeItem('kztusdt_client_build_etag');
                        // Keep purge + recovery keys: clearing them re-triggers cache wipe
                        // and allows infinite reload loops (_=timestamp in the URL).
                    } catch (error) {}

                    if ('serviceWorker' in navigator) {
                        jobs.push(
                            navigator.serviceWorker.getRegistrations().then(function (registrations) {
                                return Promise.all(registrations.map(function (registration) {
                                    return registration.unregister();
                                }));
                            })
                        );
                    }

                    if ('caches' in window) {
                        jobs.push(
                            caches.keys().then(function (keys) {
                                return Promise.all(keys.map(function (key) {
                                    return caches.delete(key);
                                }));
                            })
                        );
                    }

                    Promise.all(jobs).finally(function () {
                        var url = new URL(window.location.href);
                        url.searchParams.set('_', String(Date.now()));
                        window.location.replace(url.toString());
                    });
                }

                function showBootFailure() {
                    var splash = document.getElementById('app-boot-splash');

                    if (!splash || splash.dataset.failed === '1') {
                        return;
                    }

                    splash.dataset.failed = '1';
                    splash.innerHTML = ''
                        + '<div style="text-align:center;padding:24px;max-width:360px;">'
                        + '<p style="margin:0 0 8px;font-size:18px;font-weight:700;">' + (isAdmin ? 'Admin kztusdt' : 'KZTUSDT') + '</p>'
                        + '<p style="margin:0 0 16px;font-size:14px;line-height:1.5;color:' + (isAdmin ? '#94a3b8' : '#64748b') + ';">'
                        + 'Не удалось загрузить приложение. Обычно помогает обновление страницы.'
                        + '</p>'
                        + '<button type="button" id="app-boot-reload" style="border:0;border-radius:12px;padding:12px 18px;font-size:14px;font-weight:700;cursor:pointer;background:#2563eb;color:#fff;">'
                        + 'Обновить'
                        + '</button>'
                        + '</div>';

                    var button = document.getElementById('app-boot-reload');

                    if (button) {
                        button.addEventListener('click', hardReload);
                    }
                }

                function tryAutoRecovery() {
                    try {
                        if (sessionStorage.getItem(recoveryKey) === '1') {
                            return false;
                        }

                        sessionStorage.setItem(recoveryKey, '1');
                    } catch (error) {
                        return false;
                    }

                    hardReload();

                    return true;
                }

                window.addEventListener('error', function (event) {
                    var target = event.target;

                    if (!target || (target.tagName !== 'SCRIPT' && target.tagName !== 'LINK')) {
                        return;
                    }

                    var href = target.src || target.href || '';
                    // Only recover on failed app bundles — not favicon/manifest/etc.
                    if (href.indexOf('/build/') === -1) {
                        return;
                    }

                    if (!tryAutoRecovery()) {
                        showBootFailure();
                    }
                }, true);

                window.setTimeout(function () {
                    var splash = document.getElementById('app-boot-splash');

                    if (!splash || splash.dataset.failed === '1') {
                        return;
                    }

                    if (!tryAutoRecovery()) {
                        showBootFailure();
                    }
                }, bootTimeoutMs);
            })();
        </script>
    </body>
</html>
