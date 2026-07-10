<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AituKycService;
use App\Services\AituPassportService;
use App\Support\AdminNavPresenter;
use App\Support\AppLog;
use App\Support\AituErrorPresenter;
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

    private const RETURN_TO_KEY = 'aitu.return_to';

    private const USER_ID_KEY = 'aitu.user_id';

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

        $returnTo = $request->string('intent')->value() === 'kyc'
            ? 'kyc'
            : 'phone';

        $request->session()->put(self::RETURN_TO_KEY, $returnTo);

        if ($returnTo === 'kyc' && Auth::check()) {
            $request->session()->put(self::USER_ID_KEY, Auth::id());
        }

        $user = Auth::user();

        $url = $this->aituPassport->authorizationUrl(
            redirectUri: route('auth.aitu.callback'),
            state: $state,
            phone: $request->string('phone')->value() ?: $user?->phone,
            iin: $request->string('iin')->value() ?: $user?->iin,
            scope: $this->aituPassport->scopeForIntent($returnTo),
        );

        AppLog::auth('auth.aitu.redirect', [
            'intent' => $returnTo,
            'scope' => $this->aituPassport->scopeForIntent($returnTo),
            'phone' => RequestLogContext::maskPhone($user?->phone),
        ]);

        return redirect()->away($url);
    }

    /**
     * Redirect URI — Aitu Passport returns the user here with ?code&state.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            $message = AituErrorPresenter::callbackMessage(
                $request->string('error')->value(),
                $request->string('error_description')->value(),
            );

            AppLog::authWarning('auth.aitu.callback_error', [
                'error' => $request->string('error')->value(),
                'error_description' => $request->string('error_description')->value(),
            ]);

            return $this->redirectWithError($request, $message);
        }

        $expectedState = $request->session()->pull(self::STATE_KEY);
        $code = $request->string('code')->value();

        if ($code === '' || $expectedState === null || ! hash_equals((string) $expectedState, $request->string('state')->value())) {
            return $this->redirectWithError($request, 'Сессия авторизации недействительна. Попробуйте ещё раз.');
        }

        $returnTo = (string) $request->session()->pull(self::RETURN_TO_KEY, 'phone');
        $linkedUserId = $request->session()->pull(self::USER_ID_KEY);

        try {
            $tokens = $this->aituPassport->exchangeCode($code, route('auth.aitu.callback'));
            $claims = $this->aituPassport->claimsFromIdToken($tokens['id_token']);
            $user = $this->resolveUser($claims, is_numeric($linkedUserId) ? (int) $linkedUserId : null);
        } catch (RuntimeException $exception) {
            AppLog::exception($exception, ['flow' => 'aitu.callback']);

            return $this->redirectWithError($request, $exception->getMessage());
        }

        Auth::loginUsingId($user->id, remember: true);
        $request->session()->regenerate();
        $request->session()->put(self::ID_TOKEN_KEY, $tokens['id_token']);

        // When Aitu is the active KYC provider, apply the verification verdict
        // delivered inside the id_token (auto-approve / reject).
        $kycStatus = (string) $user->kyc_status;
        if ($this->aituKyc->isEnabled()) {
            $kycStatus = $this->aituKyc->applyFromClaims($user, $claims);
            $user->refresh();
        }

        AppLog::auth('auth.aitu.login.success', [
            'user_id' => $user->id,
            'phone' => RequestLogContext::maskPhone($user->phone),
            'kyc_status' => $user->kyc_status,
        ]);

        return $this->redirectAfterAuth($returnTo, $kycStatus);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function resolveUser(array $claims, ?int $linkedUserId): \App\Models\User
    {
        if ($linkedUserId !== null) {
            $linked = \App\Models\User::query()->find($linkedUserId);

            if ($linked !== null) {
                $phone = $this->aituPassport->phoneFromClaims($claims);
                $iin = $this->aituPassport->iinFromClaims($claims);

                if ($phone !== null && $linked->phone === $phone) {
                    $updates = [
                        'phone_verified' => true,
                        'phone_verified_at' => now(),
                    ];

                    if ($iin !== null) {
                        $updates['iin'] = $iin;
                    }

                    $linked->update($updates);

                    return $linked;
                }
            }
        }

        return $this->aituPassport->findOrCreateUser($claims);
    }

    private function redirectWithError(Request $request, string $message): RedirectResponse
    {
        $returnTo = (string) $request->session()->pull(self::RETURN_TO_KEY, 'phone');
        $request->session()->forget(self::USER_ID_KEY);

        if ($returnTo === 'kyc' && Auth::check()) {
            return redirect()->route('kyc')->withErrors(['form' => $message]);
        }

        return redirect()->route('auth.phone')->withErrors(['phone' => $message]);
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

    private function redirectAfterAuth(string $returnTo = 'phone', ?string $kycStatus = null): RedirectResponse
    {
        $user = Auth::user();

        if ($returnTo === 'kyc') {
            if ($kycStatus === 'approved') {
                return redirect()->route('kyc')
                    ->with('success', 'Верификация пройдена! Ваш аккаунт подтверждён.');
            }

            if ($kycStatus === 'rejected') {
                return redirect()->route('kyc')
                    ->withErrors(['form' => 'Верификация Aitu Passport не пройдена. Попробуйте ещё раз или обратитесь в поддержку.']);
            }

            return redirect()->route('kyc');
        }

        $landing = $user !== null ? AdminNavPresenter::landingPath($user) : null;

        if ($landing !== null) {
            return redirect($landing);
        }

        return redirect()->route('home');
    }
}
