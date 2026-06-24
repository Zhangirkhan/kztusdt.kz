<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\LocaleManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        LocaleManager::apply(LocaleManager::resolve($request));

        return $next($request);
    }
}
