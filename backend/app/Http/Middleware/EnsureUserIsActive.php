<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AdminUrl;
use App\Support\LocaleManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isActive()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Аккаунт заблокирован.'], 403);
            }

            if (AdminUrl::isAdminHost($request)) {
                return redirect('/admin/login')
                    ->withErrors(['email' => 'Аккаунт заблокирован.']);
            }

            return redirect()
                ->route('auth.phone', ['locale' => LocaleManager::resolve($request)])
                ->withErrors(['phone' => 'Аккаунт заблокирован.']);
        }

        return $next($request);
    }
}
