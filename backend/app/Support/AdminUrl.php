<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class AdminUrl
{
    public static function domain(): string
    {
        return (string) config('app.admin_domain', 'admin.kztusdt.kz');
    }

    public static function base(): string
    {
        return rtrim((string) config('app.admin_url', 'https://admin.kztusdt.kz'), '/');
    }

    public static function isAdminHost(?Request $request = null): bool
    {
        $request ??= request();

        if ($request === null) {
            return false;
        }

        return strtolower($request->getHost()) === strtolower(self::domain());
    }

    public static function path(string $path = ''): string
    {
        $path = trim($path, '/');

        return $path === '' ? '/admin' : '/admin/'.$path;
    }

    public static function to(string $path = ''): string
    {
        return self::base().self::path($path);
    }
}
