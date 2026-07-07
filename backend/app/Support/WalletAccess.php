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
    public static function denyResponse(User $user, string $feature = 'wallet'): ?RedirectResponse
    {
        return KycAccess::denyResponse($user, $feature);
    }
}
