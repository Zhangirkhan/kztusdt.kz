<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\AdminNavPresenter;
use App\Support\AdminUrl;
use App\Support\LocaleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && AdminNavPresenter::canAccessAdmin($user)) {
            $landing = AdminNavPresenter::landingPath($user) ?? '/admin';

            return redirect()->to($landing);
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'signedInAsClient' => $user !== null && ! AdminNavPresenter::canAccessAdmin($user),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if ($user !== null) {
            $landing = AdminNavPresenter::landingPath($user);

            if ($landing !== null) {
                if (AdminUrl::isAdminHost($request)) {
                    return redirect()->intended($landing);
                }

                return redirect()->away(AdminNavPresenter::landingUrl($user));
            }
        }

        if (AdminUrl::isAdminHost($request)) {
            return redirect()->intended('/admin');
        }

        return redirect()->intended(route('home', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if (AdminUrl::isAdminHost($request)) {
            return redirect('/admin/login');
        }

        return redirect(route('auth.phone', [
            'locale' => LocaleManager::resolve($request),
        ]));
    }
}
