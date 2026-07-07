<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\CreateWalletAfterKycApproved;
use App\Models\KycProfile;
use App\Models\User;
use App\Support\AppLog;
use Illuminate\Support\Facades\DB;

/**
 * KYC via Aitu Passport (KYC_PROVIDER=aitu).
 *
 * Aitu Passport performs the identity verification on its side and returns only
 * a pass/fail result inside the OpenID id_token (e.g. the CONFIDENCE_LEVEL claim,
 * enabled via the partner's subscription scopes). This service reads that result
 * from the decoded claims and auto-approves (or rejects) the user's KYC, mirroring
 * the post-approval hook used by the manual and Sumsub flows.
 */
final class AituKycService
{
    private const PROVIDER = 'aitu';

    public function __construct(
        private readonly AituPassportService $passport,
        private readonly AuditLogService $auditLogService,
        private readonly UserNotificationService $notifier,
    ) {}

    /**
     * Aitu is the active KYC provider and the OAuth client is configured.
     */
    public function isEnabled(): bool
    {
        return config('kyc.provider') === self::PROVIDER && $this->passport->isConfigured();
    }

    /**
     * Resolve the verification verdict from the id_token claims.
     *
     * @param  array<string, mixed>  $claims
     * @return bool|null  true = passed, false = failed, null = no verdict present
     */
    public function verificationResult(array $claims): ?bool
    {
        /** @var list<string> $keys */
        $keys = (array) config('aitu.verification.claims', []);
        /** @var list<string> $passed */
        $passed = (array) config('aitu.verification.passed_values', []);
        /** @var list<string> $failed */
        $failed = (array) config('aitu.verification.failed_values', []);

        foreach ($keys as $key) {
            if (! array_key_exists($key, $claims)) {
                continue;
            }

            $value = $this->normalizeClaim($claims[$key]);

            if ($value === '') {
                continue;
            }

            if (in_array($value, $passed, true)) {
                return true;
            }

            if (in_array($value, $failed, true)) {
                return false;
            }
        }

        return null;
    }

    /**
     * Apply the Aitu verification verdict to the user's KYC status.
     *
     * @param  array<string, mixed>  $claims
     * @return string  the resulting kyc_status
     */
    public function applyFromClaims(User $user, array $claims): string
    {
        if ($user->kyc_status === 'approved') {
            return 'approved';
        }

        $result = $this->verificationResult($claims);

        if ($result === true) {
            $this->approve($user, $claims);

            return 'approved';
        }

        if ($result === false) {
            $this->reject($user, $claims);

            return 'rejected';
        }

        // No verdict in the token — most likely the verification scope is not yet
        // granted. Log the available claim keys (never the values) so the operator
        // can map the correct claim name in AITU_VERIFY_CLAIMS.
        AppLog::authWarning('kyc.aitu.no_verdict', [
            'user_id' => $user->id,
            'claim_keys' => array_keys($claims),
        ]);

        return (string) $user->kyc_status;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function approve(User $user, array $claims): void
    {
        DB::transaction(function () use ($user, $claims): void {
            $profile = KycProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'provider' => self::PROVIDER,
                    'first_name' => $this->claim($claims, ['given_name', 'first_name']) ?? $user->kycProfile?->first_name,
                    'last_name' => $this->claim($claims, ['family_name', 'last_name']) ?? $user->kycProfile?->last_name,
                    'document_number' => $this->claim($claims, ['iin', 'sub']) ?? $user->kycProfile?->document_number,
                    'status' => 'approved',
                    'submitted_at' => now(),
                    'reviewed_at' => now(),
                    'rejection_reason' => null,
                ],
            );

            $user->update(['kyc_status' => 'approved']);

            $this->auditLogService->log(
                action: 'kyc.aitu.approved',
                userId: $user->id,
                entityType: 'kyc_profile',
                entityId: $profile->id,
            );

            $this->notifier->notifyKey(
                $user,
                'kyc_aitu_approved',
            );
        });

        // Same post-approval hook as the manual and Sumsub flows.
        CreateWalletAfterKycApproved::dispatch($user->id);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function reject(User $user, array $claims): void
    {
        DB::transaction(function () use ($user, $claims): void {
            $profile = KycProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'provider' => self::PROVIDER,
                    'status' => 'rejected',
                    'reviewed_at' => now(),
                    'rejection_reason' => 'Верификация Aitu Passport не пройдена.',
                ],
            );

            $user->update(['kyc_status' => 'rejected']);

            $this->auditLogService->log(
                action: 'kyc.aitu.rejected',
                userId: $user->id,
                entityType: 'kyc_profile',
                entityId: $profile->id,
            );

            $this->notifier->notifyKey(
                $user,
                'kyc_aitu_rejected',
            );
        });
    }

    private function normalizeClaim(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return '';
        }

        return mb_strtolower(trim((string) $value));
    }

    /**
     * Return the first non-empty claim among the given keys.
     *
     * @param  array<string, mixed>  $claims
     * @param  list<string>  $keys
     */
    private function claim(array $claims, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($claims[$key]) && is_scalar($claims[$key]) && (string) $claims[$key] !== '') {
                return (string) $claims[$key];
            }
        }

        return null;
    }
}
