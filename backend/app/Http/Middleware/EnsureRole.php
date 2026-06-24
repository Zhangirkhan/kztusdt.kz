<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'Недостаточно прав.');
        }

        $user->loadMissing('roles:id,code');

        if (! $user->hasAnyRole($roles)) {
            abort(403, 'Недостаточно прав.');
        }

        return $next($request);
    }
}
