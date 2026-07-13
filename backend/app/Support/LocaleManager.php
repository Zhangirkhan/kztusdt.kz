<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

final class LocaleManager
{
    public static function supported(): array
    {
        /** @var list<string> $supported */
        $supported = config('locales.supported', ['ru', 'kk', 'en']);

        return $supported;
    }

    public static function default(): string
    {
        return (string) config('locales.default', 'ru');
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::supported(), true);
    }

    public static function normalize(?string $locale): ?string
    {
        if ($locale === null || $locale === '') {
            return null;
        }

        $locale = strtolower(str_replace('_', '-', $locale));

        if (str_starts_with($locale, 'kk')) {
            return 'kk';
        }

        if (str_starts_with($locale, 'ru')) {
            return 'ru';
        }

        if (str_starts_with($locale, 'en')) {
            return 'en';
        }

        return null;
    }

    public static function resolve(Request $request): string
    {
        $routeLocale = self::normalize($request->route('locale'));

        if ($routeLocale !== null && self::isSupported($routeLocale)) {
            return $routeLocale;
        }

        $pathLocale = self::normalize(explode('/', trim($request->path(), '/'))[0] ?? null);

        if ($pathLocale !== null && self::isSupported($pathLocale)) {
            return $pathLocale;
        }

        $user = $request->user();

        if ($user instanceof User && is_string($user->locale) && self::isSupported($user->locale)) {
            return $user->locale;
        }

        $cookie = self::normalize($request->cookie((string) config('locales.cookie', 'app_locale')));

        if ($cookie !== null) {
            return $cookie;
        }

        $preferred = $request->getPreferredLanguage(self::supported());

        if (is_string($preferred) && self::isSupported($preferred)) {
            return $preferred;
        }

        return self::default();
    }

    public static function localizedPath(string $locale, string $path): string
    {
        $locale = self::isSupported($locale) ? $locale : self::default();
        $path = '/'.ltrim($path, '/');

        if ($path === '/') {
            return '/'.$locale;
        }

        $segments = explode('/', trim($path, '/'));

        if (($segments[0] ?? '') !== '' && self::isSupported($segments[0])) {
            $segments[0] = $locale;

            return '/'.implode('/', $segments);
        }

        return '/'.$locale.$path;
    }

    public static function localizedUrl(string $locale, ?string $url = null): string
    {
        $target = $url ?? url()->previous();
        $parts = parse_url($target);

        $path = (string) ($parts['path'] ?? '');

        if (self::isAdminPath($path) || self::isAdminHostUrl($target)) {
            return self::sameHostUrl($target, self::normalizeAdminPath($path));
        }

        $localizedPath = self::localizedPath($locale, $path !== '' ? $path : '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        if ($target !== '' && str_contains($target, '://')) {
            $scheme = (string) ($parts['scheme'] ?? request()->getScheme());
            $host = (string) ($parts['host'] ?? request()->getHost());
            $port = isset($parts['port']) ? ':'.$parts['port'] : '';

            return sprintf('%s://%s%s%s%s', $scheme, $host, $port, $localizedPath, $query.$fragment);
        }

        return $localizedPath.$query.$fragment;
    }

    public static function redirectAfterLocaleChange(Request $request, string $locale): string
    {
        $referer = (string) ($request->headers->get('referer') ?: url()->previous());
        $path = (string) (parse_url($referer, PHP_URL_PATH) ?? '/');

        if (self::isAdminPath($path) || AdminUrl::isAdminHost($request) || self::isAdminHostUrl($referer)) {
            return self::sameHostUrl($referer, self::normalizeAdminPath($path));
        }

        return self::localizedUrl($locale, $referer);
    }

    public static function isAdminPath(string $path): bool
    {
        $normalized = '/'.trim($path, '/');

        return $normalized === '/admin' || str_starts_with($normalized, '/admin/');
    }

    private static function normalizeAdminPath(string $path): string
    {
        $segments = explode('/', trim($path, '/'));

        if (($segments[0] ?? '') !== '' && self::isSupported($segments[0])) {
            array_shift($segments);
        }

        $normalized = '/'.implode('/', $segments);

        if ($normalized === '/' || $normalized === '') {
            return '/admin';
        }

        return $normalized;
    }

    private static function isAdminHostUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && strtolower($host) === strtolower(AdminUrl::domain());
    }

    private static function sameHostUrl(string $baseUrl, string $path): string
    {
        $parts = parse_url($baseUrl);
        $scheme = (string) ($parts['scheme'] ?? request()->getScheme());
        $host = (string) ($parts['host'] ?? request()->getHost());
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        return sprintf('%s://%s%s%s%s%s', $scheme, $host, $port, $path, $query, $fragment);
    }

    public static function apply(string $locale): void
    {
        App::setLocale(self::isSupported($locale) ? $locale : self::default());
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public static function options(): array
    {
        $labels = config('locales.labels', []);

        return array_map(
            fn (string $code): array => [
                'code' => $code,
                'label' => (string) ($labels[$code] ?? strtoupper($code)),
            ],
            self::supported(),
        );
    }

    public static function remember(string $locale): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make(
            name: (string) config('locales.cookie', 'app_locale'),
            value: $locale,
            minutes: 60 * 24 * 365,
            path: '/',
            secure: request()->isSecure(),
            httpOnly: false,
            raw: false,
            sameSite: 'lax',
        );
    }
}
