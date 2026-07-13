<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AdminNavPresenter;
use App\Support\AdminUrl;
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

        if ($user->hasAnyRole($roles)) {
            return $next($request);
        }

        if (AdminUrl::isAdminHost($request)) {
            if (! AdminNavPresenter::canAccessAdmin($user)) {
                return redirect('/admin/login');
            }

            $landing = AdminNavPresenter::landingPath($user);

            if ($landing !== null) {
                return redirect($landing);
            }
        }

        abort(403, 'Недостаточно прав.');
    }
}
