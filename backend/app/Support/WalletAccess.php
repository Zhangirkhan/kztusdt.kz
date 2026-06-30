<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class WalletAccess
{
    /**
     * Redirect when the user cannot open wallet / withdraw pages yet.
     */
    public static function denyResponse(User $user): ?RedirectResponse
    {
        if ($user->canUseWallet()) {
            return null;
        }

        if (! $user->phone_verified) {
            return redirect()->route('auth.phone')
                ->withErrors(['phone' => 'Подтвердите номер телефона, чтобы открыть кошелёк.']);
        }

        return redirect()->route('kyc')
            ->withErrors(['form' => 'Пройдите KYC-верификацию, чтобы открыть кошелёк USDT.']);
    }
}
