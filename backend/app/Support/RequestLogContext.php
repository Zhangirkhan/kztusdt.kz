<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RequestLogContext
{
    private static ?string $requestId = null;

    public static function id(): string
    {
        return self::$requestId ??= (string) Str::uuid();
    }

    public static function reset(?string $requestId = null): void
    {
        self::$requestId = $requestId;
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromRequest(Request $request): array
    {
        return [
            'request_id' => self::id(),
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
        ];
    }

    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 4) {
            return '***';
        }

        return '***'.substr($digits, -4);
    }
}
