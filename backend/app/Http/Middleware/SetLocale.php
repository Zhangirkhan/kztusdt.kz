<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\LocaleManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = LocaleManager::resolve($request);

        LocaleManager::apply($locale);
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }
}
