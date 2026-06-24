<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AppLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LogHttpRequests
{
    /** @var list<string> */
    private const TRACKED_PREFIXES = [
        'auth/',
        'api/auth/',
        'webauthn/',
        'home',
        'admin/',
        'exchange/',
        'withdraw',
        'kyc',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->shouldSkip($request)) {
            return;
        }

        $status = $response->getStatusCode();
        $startedAt = (float) ($request->attributes->get('log_started_at') ?? microtime(true));
        $durationMs = round((microtime(true) - $startedAt) * 1000, 1);
        $tracked = $this->isTrackedPath($request);

        if (! $tracked && $status < 400 && $durationMs < 2000) {
            return;
        }

        $level = match (true) {
            $status >= 500 => 'error',
            $status >= 400 => 'warning',
            default => 'info',
        };

        AppLog::http(
            sprintf('%s %s %d', $request->method(), '/'.$request->path(), $status),
            [
                'status' => $status,
                'duration_ms' => $durationMs,
                'user_id' => $request->user()?->id,
                'referer' => $request->headers->get('referer'),
                'tracked' => $tracked,
            ],
            $level,
        );
    }

    private function shouldSkip(Request $request): bool
    {
        if ($request->is('internal/health')) {
            return true;
        }

        return $request->is(
            'build/*',
            'storage/*',
            'favicon.ico',
            'robots.txt',
            'sitemap.xml',
        );
    }

    private function isTrackedPath(Request $request): bool
    {
        $path = $request->path();

        foreach (self::TRACKED_PREFIXES as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
