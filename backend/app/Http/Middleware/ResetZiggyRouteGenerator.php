<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tighten\Ziggy\BladeRouteGenerator;

/**
 * PHP-FPM reuses workers across requests; Ziggy's BladeRouteGenerator keeps a
 * static flag that would otherwise emit merge-only scripts without a full route list.
 */
final class ResetZiggyRouteGenerator
{
    public function handle(Request $request, Closure $next): Response
    {
        BladeRouteGenerator::$generated = false;

        return $next($request);
    }
}
