<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RequestLogContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class AttachRequestLogContext
{
    public function handle(Request $request, Closure $next): Response
    {
        RequestLogContext::reset();

        $request->attributes->set('log_started_at', microtime(true));

        Log::shareContext(RequestLogContext::fromRequest($request));

        return $next($request);
    }
}
