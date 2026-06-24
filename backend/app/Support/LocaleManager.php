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
