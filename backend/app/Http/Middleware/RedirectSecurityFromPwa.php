<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AdminNavPresenter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectSecurityFromPwa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && AdminNavPresenter::isSecurityOnly($user)) {
            $landing = AdminNavPresenter::landingUrl($user);

            if ($landing !== null) {
                return redirect()->away($landing);
            }
        }

        return $next($request);
    }
}
