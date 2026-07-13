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
        private readonly KycIinReconciler $iinReconciler,
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
        $govDoc = $claims['gov_doc_verification'] ?? null;
        if (is_array($govDoc) && $govDoc !== []) {
            return true;
        }

        if (array_key_exists('gov_doc_verification', $claims) && $this->scalarClaim($claims, 'sessionDocumentId') !== null) {
            return true;
        }

        // confidence_level.faceMatch: VERIFIED | LOW_SIMILARITY
        $confidence = $claims['confidence_level'] ?? $claims['confidenceLevel'] ?? null;
        if (is_array($confidence)) {
            $faceMatch = mb_strtolower(trim((string) ($confidence['faceMatch'] ?? '')));

            if ($faceMatch === 'verified') {
                return true;
            }

            if ($faceMatch === 'low_similarity') {
                return false;
            }
        }

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
            'claim_types' => array_map(
                static fn (mixed $value): string => is_object($value) ? 'object' : gettype($value),
                $claims,
            ),
        ]);

        return (string) $user->kyc_status;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function approve(User $user, array $claims): void
    {
        DB::transaction(function () use ($user, $claims): void {
            /** @var array<string, mixed>|null $govDoc */
            $govDoc = $this->passport->structuredClaim($claims, 'gov_doc_verification');

            $iin = $this->extractIin($claims, $govDoc);
            $phone = $this->passport->phoneFromClaims($claims);
            $sessionDocumentId = $this->scalarClaim($claims, 'sessionDocumentId');
            $sessionId = $this->scalarClaim($claims, 'sid');
            $verifiedAt = now();

            $profile = KycProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'provider' => self::PROVIDER,
                    'first_name' => $this->claim($claims, ['given_name', 'first_name'])
                        ?? (is_string($govDoc['firstName'] ?? null) ? $govDoc['firstName'] : null)
                        ?? $user->kycProfile?->first_name,
                    'last_name' => $this->claim($claims, ['family_name', 'last_name'])
                        ?? (is_string($govDoc['lastName'] ?? null) ? $govDoc['lastName'] : null)
                        ?? $user->kycProfile?->last_name,
                    'document_number' => $iin
                        ?? $this->claim($claims, ['sub'])
                        ?? (is_string($govDoc['documentNumber'] ?? null) ? $govDoc['documentNumber'] : null)
                        ?? $user->kycProfile?->document_number,
                    'provider_verification_id' => $sessionDocumentId,
                    'provider_session_id' => $sessionId,
                    'status' => 'approved',
                    'submitted_at' => $verifiedAt,
                    'reviewed_at' => $verifiedAt,
                    'rejection_reason' => null,
                ],
            );

            $userUpdates = ['kyc_status' => 'approved'];

            if ($phone !== null) {
                $userUpdates['phone'] = $phone;
                $userUpdates['phone_verified'] = true;
                $userUpdates['phone_verified_at'] = $verifiedAt;
            }

            $user->update($userUpdates);
            $this->iinReconciler->apply($user->fresh(), $iin);

            $this->auditLogService->log(
                action: 'kyc.aitu.approved',
                userId: $user->id,
                entityType: 'kyc_profile',
                entityId: $profile->id,
                payload: [
                    'provider' => self::PROVIDER,
                    'provider_verification_id' => $sessionDocumentId,
                    'provider_session_id' => $sessionId,
                    'verified_at' => $verifiedAt->toIso8601String(),
                    'iin' => $iin,
                    'phone' => $phone,
                ],
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

    /**
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>|null  $govDoc
     */
    private function extractIin(array $claims, ?array $govDoc): ?string
    {
        $fromGovDoc = is_string($govDoc['iin'] ?? null) ? trim($govDoc['iin']) : '';

        if ($fromGovDoc !== '') {
            return $fromGovDoc;
        }

        return $this->claim($claims, ['iin']);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function scalarClaim(array $claims, string $key): ?string
    {
        if (! isset($claims[$key]) || ! is_scalar($claims[$key])) {
            return null;
        }

        $value = trim((string) $claims[$key]);

        return $value !== '' ? $value : null;
    }
}
