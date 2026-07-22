<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AdminUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectClientAdminToSubdomain
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        if (AdminUrl::isAdminHost($request)) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        if ($path !== 'admin' && ! str_starts_with($path, 'admin/')) {
            return $next($request);
        }

        $target = AdminUrl::base().'/'.$path;

        if ($request->getQueryString() !== null) {
            $target .= '?'.$request->getQueryString();
        }

        return redirect()->away($target, 301);
    }
}
