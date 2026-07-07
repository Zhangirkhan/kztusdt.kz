<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Services\AituPassportService;
use App\Services\SumsubService;

final class KycClientOptions
{
    /**
     * @return array{
     *     provider: string,
     *     manual_enabled: bool,
     *     show_aitu: bool,
     *     show_sumsub: bool,
     *     show_manual_form: bool,
     *     aitu_verify_url: string|null,
     *     aitu_kyc_scope_configured: bool,
     *     inline_sumsub: bool,
     *     needs_verification: bool
     * }
     */
    public static function forUser(User $user): array
    {
        $configuredProvider = (string) config('kyc.provider', 'manual');
        $manualEnabled = self::manualEnabled();

        $automatedProvider = self::resolveAutomatedProvider($configuredProvider);
        $effectiveProvider = $automatedProvider ?? 'manual';
        $status = (string) $user->kyc_status;
        $needsVerification = ! in_array($status, ['approved', 'pending_review'], true);
        $showManualForm = $manualEnabled && $needsVerification;

        return [
            'provider' => $effectiveProvider,
            'manual_enabled' => $manualEnabled,
            'show_aitu' => $automatedProvider === 'aitu' && $needsVerification,
            'show_sumsub' => $automatedProvider === 'sumsub' && $status !== 'approved',
            'show_manual_form' => $showManualForm,
            'aitu_verify_url' => $automatedProvider === 'aitu'
                ? route('auth.aitu.redirect', ['intent' => 'kyc'])
                : null,
            'aitu_kyc_scope_configured' => $automatedProvider === 'aitu'
                && app(AituPassportService::class)->kycScopeConfigured(),
            'inline_sumsub' => $automatedProvider === 'sumsub' && $status !== 'approved',
            'needs_verification' => $needsVerification,
        ];
    }

    public static function manualEnabled(): bool
    {
        if ((string) config('kyc.provider', 'manual') === 'manual') {
            return true;
        }

        return (bool) config('kyc.manual_enabled', true);
    }

    private static function resolveAutomatedProvider(string $configuredProvider): ?string
    {
        if ($configuredProvider === 'sumsub') {
            return app(SumsubService::class)->isConfigured() ? 'sumsub' : null;
        }

        if ($configuredProvider === 'aitu') {
            return app(AituPassportService::class)->isConfigured() ? 'aitu' : null;
        }

        if ($configuredProvider === 'manual') {
            return null;
        }

        return null;
    }
}
