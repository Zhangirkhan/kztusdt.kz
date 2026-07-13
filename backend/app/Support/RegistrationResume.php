<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Where to continue registration / onboarding for an already authenticated user.
 */
final class RegistrationResume
{
    public static function path(?User $user, ?Request $request = null): string
    {
        $request ??= request();
        $locale = LocaleManager::resolve($request);

        if ($user === null) {
            return route('auth.phone', ['locale' => $locale]);
        }

        $landing = AdminNavPresenter::landingPath($user);

        if ($landing !== null) {
            return $landing;
        }

        if ($user->canUseWallet()) {
            return route('wallet', ['locale' => $locale]);
        }

        if (! $user->phone_verified) {
            return route('auth.phone', ['locale' => $locale]);
        }

        $kyc = $user->kycMeta();

        if ($kyc['inline_sumsub'] && $kyc['needs_verification']) {
            $session = AuthSession::query()
                ->where('user_id', $user->id)
                ->where('status', 'verified')
                ->latest('id')
                ->first();

            if ($session !== null) {
                return route('auth.whatsapp.wait', [
                    'locale' => $locale,
                    'loginCode' => $session->login_code,
                ]);
            }
        }

        return route('kyc', ['locale' => $locale]);
    }
}
