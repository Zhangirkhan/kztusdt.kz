<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final class AdminNavPresenter
{
    /**
     * @return array{landing: string|null, sections: array<string, bool>}
     */
    public static function forUser(?User $user): array
    {
        if ($user === null) {
            return [
                'landing' => null,
                'sections' => [],
            ];
        }

        return [
            'landing' => self::landingPath($user),
            'sections' => [
                'dashboard' => $user->hasAnyRole(['super_admin', 'super_admin_manager']),
                'kyc' => $user->hasAnyRole(['super_admin', 'security_officer', 'super_admin_manager']),
                'orders' => $user->hasAnyRole(['super_admin', 'super_admin_manager', 'exchange_admin', 'security_officer']),
                'withdrawals' => $user->hasAnyRole(['super_admin', 'security_officer', 'super_admin_manager']),
                'wallets' => $user->hasAnyRole(['super_admin', 'super_admin_manager']),
                'sweeps' => $user->hasAnyRole(['super_admin', 'super_admin_manager']),
                'subscriptions' => self::canManageSubscriptions($user),
            ],
        ];
    }

    public static function canManageSubscriptions(User $user): bool
    {
        return $user->hasRole('super_admin')
            && ! $user->hasRole('security_officer');
    }

    public static function ziggyGroup(?User $user): string
    {
        if ($user === null) {
            return 'guest';
        }

        if ($user->hasAnyRole(['super_admin', 'super_admin_manager'])) {
            return 'admin';
        }

        if ($user->hasRole('exchange_admin')) {
            return 'admin_exchange';
        }

        if ($user->hasRole('security_officer')) {
            return 'admin_security';
        }

        return 'app';
    }

    public static function landingPath(User $user): ?string
    {
        if ($user->hasAnyRole(['super_admin', 'super_admin_manager'])) {
            return '/admin';
        }

        if ($user->hasRole('security_officer')) {
            return '/admin/kyc';
        }

        if ($user->hasRole('exchange_admin')) {
            return '/admin/orders';
        }

        return null;
    }

    public static function canAccessAdmin(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $sections = self::forUser($user)['sections'];

        return in_array(true, $sections, true);
    }

    public static function isSecurityOnly(User $user): bool
    {
        return $user->hasRole('security_officer')
            && ! $user->hasAnyRole(['super_admin', 'super_admin_manager', 'exchange_admin']);
    }

    public static function canAccessPwa(?User $user): bool
    {
        if ($user === null) {
            return true;
        }

        return ! self::isSecurityOnly($user);
    }
}
