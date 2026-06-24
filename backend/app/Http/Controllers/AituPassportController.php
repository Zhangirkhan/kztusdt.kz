<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AituKycService;
use App\Services\AituPassportService;
use App\Support\AdminNavPresenter;
use App\Support\AppLog;
use App\Support\RequestLogContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Web endpoints backing the Aitu Passport console URIs:
 *  - redirect()     → стартует авторизацию (кнопка «Войти через Aitu»)
 *  - callback()     → Redirect URI (возврат с ?code&state)
 *  - logout()       → инициирует logout (редирект в Aitu Passport)
 *  - postLogout()   → Post Logout Redirect URI
 *  - phoneChanged() → Phone Change Redirect URI
 */
final class AituPassportController extends Controller
{
    private const STATE_KEY = 'aitu.oauth_state';

    private const ID_TOKEN_KEY = 'aitu.id_token';

    public function __construct(
        private readonly AituPassportService $aituPassport,
        private readonly AituKycService $aituKyc,
    ) {}

    /**
     * Start the OAuth authorization: redirect the user to Aitu Passport.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->aituPassport->isConfigured()) {
            return redirect()->route('auth.phone')
                ->withErrors(['phone' => 'Авторизация через Aitu Passport временно недоступна.']);
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        $url = $this->aituPassport->authorizationUrl(
            redirectUri: route('auth.aitu.callback'),
            state: $state,
            phone: $request->string('phone')->value() ?: null,
            iin: $request->string('iin')->value() ?: null,
        );

        return redirect()->away($url);
    }

    /**
     * Redirect URI — Aitu Passport returns the user here with ?code&state.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            AppLog::authWarning('auth.aitu.callback_error', [
                'error' => $request->string('error')->value(),
                'error_description' => $request->string('error_description')->value(),
            ]);

            return redirect()->route('auth.phone')
                ->withErrors(['phone' => 'Авторизация через Aitu Passport не завершена.']);
        }

        $expectedState = $request->session()->pull(self::STATE_KEY);
        $code = $request->string('code')->value();

        if ($code === '' || $expectedState === null || ! hash_equals((string) $expectedState, $request->string('state')->value())) {
            return redirect()->route('auth.phone')
                ->withErrors(['phone' => 'Сессия авторизации недействительна. Попробуйте ещё раз.']);
        }

        try {
            $tokens = $this->aituPassport->exchangeCode($code, route('auth.aitu.callback'));
            $claims = $this->aituPassport->claimsFromIdToken($tokens['id_token']);
            $user = $this->aituPassport->findOrCreateUser($claims);
        } catch (RuntimeException $exception) {
            AppLog::exception($exception, ['flow' => 'aitu.callback']);

            return redirect()->route('auth.phone')
                ->withErrors(['phone' => $exception->getMessage()]);
        }

        Auth::loginUsingId($user->id, remember: true);
        $request->session()->regenerate();
        $request->session()->put(self::ID_TOKEN_KEY, $tokens['id_token']);

        // When Aitu is the active KYC provider, apply the verification verdict
        // delivered inside the id_token (auto-approve / reject).
        if ($this->aituKyc->isEnabled()) {
            $this->aituKyc->applyFromClaims($user, $claims);
            $user->refresh();
        }

        AppLog::auth('auth.aitu.login.success', [
            'user_id' => $user->id,
            'phone' => RequestLogContext::maskPhone($user->phone),
            'kyc_status' => $user->kyc_status,
        ]);

        return $this->redirectAfterAuth();
    }

    /**
     * Initiate logout: redirect to Aitu Passport's end-session endpoint, then
     * clear the local session. The user comes back at the Post Logout URI.
     */
    public function logout(Request $request): RedirectResponse
    {
        $idToken = $request->session()->pull(self::ID_TOKEN_KEY);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (! is_string($idToken) || $idToken === '' || ! $this->aituPassport->isConfigured()) {
            return redirect('/');
        }

        $url = $this->aituPassport->logoutUrl(
            idToken: $idToken,
            postLogoutRedirectUri: route('auth.aitu.logout.callback'),
            state: Str::random(16),
        );

        return redirect()->away($url);
    }

    /**
     * Post Logout Redirect URI — Aitu Passport returns the user here after
     * the session is terminated.
     */
    public function postLogout(): RedirectResponse
    {
        return redirect()->route('auth.phone');
    }

    /**
     * Phone Change Redirect URI — Aitu Passport returns the user here after a
     * phone-number change. The local identity is keyed on phone, so we drop
     * the session and require a fresh authorization with the new number.
     */
    public function phoneChanged(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            AppLog::auth('auth.aitu.phone_changed', ['user_id' => Auth::id()]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.phone')
            ->with('status', 'Номер телефона изменён. Войдите заново.');
    }

    private function redirectAfterAuth(): RedirectResponse
    {
        $user = Auth::user();
        $landing = $user !== null ? AdminNavPresenter::landingPath($user) : null;

        if ($landing !== null) {
            return redirect($landing);
        }

        return redirect()->route('home');
    }
}
