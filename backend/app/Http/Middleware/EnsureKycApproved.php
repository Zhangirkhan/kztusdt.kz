<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\KycAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureKycApproved
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature = 'default'): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($deny = KycAccess::denyResponse($user, $feature)) {
            return $deny;
        }

        return $next($request);
    }
}
