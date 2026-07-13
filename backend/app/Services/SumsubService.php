<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\CreateWalletAfterKycApproved;
use App\Models\KycProfile;
use App\Models\User;
use App\Support\AppLog;
use App\Support\LocaleManager;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sumsub external KYC provider integration (https://docs.sumsub.com).
 *
 * Requests are signed with HMAC-SHA256: ts + METHOD + path(+query) + body,
 * using SUMSUB_SECRET_KEY; the app token goes into the X-App-Token header.
 * Active only when KYC_PROVIDER=sumsub.
 */
final class SumsubService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly UserNotificationService $notifier,
        private readonly KycIinReconciler $iinReconciler,
    ) {}

    public function isConfigured(): bool
    {
        return config('kyc.sumsub.app_token') !== ''
            && config('kyc.sumsub.secret_key') !== '';
    }

    /**
     * Create (or reuse) a Sumsub applicant for the user and return its id.
     */
    public function ensureApplicant(User $user): string
    {
        $profile = KycProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['provider' => 'sumsub', 'status' => 'draft'],
        );

        if ($profile->sumsub_applicant_id !== null) {
            return $profile->sumsub_applicant_id;
        }

        $levelName = (string) config('kyc.sumsub.level_name');

        $response = $this->request('POST', '/resources/applicants?levelName='.rawurlencode($levelName), [
            'externalUserId' => (string) $user->id,
            'phone' => $user->phone,
        ]);

        // 409 — applicant already exists for this externalUserId; look it up.
        if ($response->status() === 409) {
            $response = $this->request(
                'GET',
                '/resources/applicants/-;externalUserId='.rawurlencode((string) $user->id).'/one',
            );
        }

        $applicantId = (string) ($response->json('id') ?? '');

        if (! $response->successful() || $applicantId === '') {
            AppLog::warning('sumsub.applicant_create_failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ], 'errors');

            throw new RuntimeException('KYC временно недоступен. Попробуйте позже.');
        }

        $profile->update([
            'provider' => 'sumsub',
            'sumsub_applicant_id' => $applicantId,
        ]);

        return $applicantId;
    }

    /**
     * Short-lived access token for the WebSDK on the /kyc page.
     */
    public function accessToken(User $user): string
    {
        $this->ensureApplicant($user);

        $query = http_build_query([
            'userId' => (string) $user->id,
            'levelName' => (string) config('kyc.sumsub.level_name'),
            'ttlInSecs' => (int) config('kyc.sumsub.access_token_ttl', 600),
        ]);

        $response = $this->request('POST', '/resources/accessTokens?'.$query);

        $token = (string) ($response->json('token') ?? '');

        if (! $response->successful() || $token === '') {
            AppLog::warning('sumsub.access_token_failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ], 'errors');

            throw new RuntimeException('KYC временно недоступен. Попробуйте позже.');
        }

        return $token;
    }

    /**
     * Verify the X-Payload-Digest webhook signature (HMAC over the raw body).
     */
    public function verifyWebhookSignature(string $rawBody, string $digest, string $algorithm = 'HMAC_SHA256_HEX'): bool
    {
        $secret = (string) config('kyc.sumsub.webhook_secret');

        if ($secret === '' || $digest === '') {
            return false;
        }

        $algo = match (strtoupper($algorithm)) {
            'HMAC_SHA1_HEX' => 'sha1',
            'HMAC_SHA512_HEX' => 'sha512',
            default => 'sha256',
        };

        return hash_equals(hash_hmac($algo, $rawBody, $secret), strtolower($digest));
    }

    /**
     * Handle the applicantReviewed webhook: map GREEN/RED to approved/rejected.
     */
    public function handleApplicantReviewed(array $payload): void
    {
        $applicantId = (string) ($payload['applicantId'] ?? '');
        $externalUserId = (string) ($payload['externalUserId'] ?? '');
        $reviewAnswer = strtoupper((string) ($payload['reviewResult']['reviewAnswer'] ?? ''));

        $profile = KycProfile::query()
            ->when($applicantId !== '', fn ($q) => $q->where('sumsub_applicant_id', $applicantId))
            ->first();

        if ($profile === null && $externalUserId !== '') {
            $profile = KycProfile::query()->where('user_id', (int) $externalUserId)->first();
        }

        if ($profile === null) {
            AppLog::warning('kyc.sumsub.profile_not_found', [
                'applicant_id' => $applicantId,
                'external_user_id' => $externalUserId,
            ]);

            return;
        }

        if ($reviewAnswer === 'GREEN') {
            $this->approveFromWebhook($profile, $payload);

            return;
        }

        if ($reviewAnswer === 'RED') {
            $this->rejectFromWebhook($profile, $payload);

            return;
        }

        AppLog::info('kyc.sumsub.unhandled_review_answer', [
            'review_answer' => $reviewAnswer,
            'profile_id' => $profile->id,
        ]);
    }

    /**
     * Pull the latest review result from Sumsub (fallback when webhook is delayed).
     *
     * @return array{kyc_status: string, profile_status: string|null, synced: bool, iin_mismatch: bool}
     */
    public function syncApplicantStatus(User $user): array
    {
        $profile = KycProfile::query()->where('user_id', $user->id)->first();

        if ($profile === null || $profile->sumsub_applicant_id === null) {
            $applicantId = $this->ensureApplicant($user);
            $profile = KycProfile::query()->where('user_id', $user->id)->firstOrFail();
        } else {
            $applicantId = $profile->sumsub_applicant_id;
        }

        $response = $this->request('GET', '/resources/applicants/'.rawurlencode($applicantId).'/one');

        if (! $response->successful()) {
            AppLog::warning('sumsub.applicant_status_failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ], 'errors');

            throw new RuntimeException('KYC временно недоступен. Попробуйте позже.');
        }

        $review = $response->json('review') ?? [];
        $reviewAnswer = strtoupper((string) ($review['reviewResult']['reviewAnswer'] ?? ''));
        $reviewStatus = strtolower((string) ($review['reviewStatus'] ?? ''));

        $payload = [
            'applicantId' => $applicantId,
            'externalUserId' => (string) $user->id,
            'reviewResult' => $review['reviewResult'] ?? [],
        ];

        if ($reviewAnswer === 'GREEN' && $user->kyc_status !== 'approved') {
            $this->approveFromWebhook($profile, $payload);
        } elseif ($reviewAnswer === 'RED' && $user->kyc_status !== 'rejected') {
            $this->rejectFromWebhook($profile, $payload);
        } elseif (in_array($reviewStatus, ['pending', 'queued', 'onhold', 'prechecked', 'completed'], true)
            && $reviewAnswer === ''
            && ! in_array($user->kyc_status, ['approved', 'rejected'], true)) {
            $profile->update(['status' => 'pending_review']);
            $user->update(['kyc_status' => 'pending_review']);
        }

        $user->refresh();
        $profile->refresh();

        return [
            'kyc_status' => (string) $user->kyc_status,
            'profile_status' => $profile->status,
            'synced' => true,
            'iin_mismatch' => $user->hasIinMismatch(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchApplicantDetails(string $applicantId): array
    {
        $response = $this->request('GET', '/resources/applicants/'.rawurlencode($applicantId).'/one');

        if (! $response->successful()) {
            AppLog::warning('sumsub.applicant_details_failed', [
                'applicant_id' => $applicantId,
                'status' => $response->status(),
                'body' => $response->body(),
            ], 'errors');

            throw new RuntimeException('KYC временно недоступен. Попробуйте позже.');
        }

        $data = $response->json() ?? [];
        $info = is_array($data['info'] ?? null) ? $data['info'] : [];
        $review = is_array($data['review'] ?? null) ? $data['review'] : [];
        $reviewResult = is_array($review['reviewResult'] ?? null) ? $review['reviewResult'] : [];
        $idDoc = is_array($info['idDocs'][0] ?? null) ? $info['idDocs'][0] : [];

        return [
            'created_at' => (string) ($data['createdAt'] ?? ''),
            'platform' => (string) ($data['applicantPlatform'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'first_name' => (string) ($info['firstName'] ?? ''),
            'last_name' => (string) ($info['lastName'] ?? ''),
            'middle_name' => (string) ($info['middleName'] ?? ''),
            'dob' => (string) ($info['dob'] ?? ''),
            'country' => (string) ($info['country'] ?? ''),
            'tin' => (string) ($info['tin'] ?? ''),
            'document_type' => (string) ($idDoc['idDocType'] ?? ''),
            'document_number' => (string) ($idDoc['number'] ?? ''),
            'document_additional_number' => (string) ($idDoc['additionalNumber'] ?? ''),
            'document_valid_until' => (string) ($idDoc['validUntil'] ?? ''),
            'review_status' => (string) ($review['reviewStatus'] ?? ''),
            'review_answer' => (string) ($reviewResult['reviewAnswer'] ?? ''),
            'moderation_comment' => (string) ($reviewResult['moderationComment'] ?? ''),
            'reject_labels' => array_values(array_filter(
                (array) ($reviewResult['rejectLabels'] ?? []),
                fn ($label): bool => is_string($label) && $label !== '',
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function extractIinFromApplicantDetails(array $details): ?string
    {
        foreach ([
            $details['tin'] ?? null,
            $details['document_additional_number'] ?? null,
            $details['document_number'] ?? null,
        ] as $candidate) {
            if (! is_string($candidate) && ! is_numeric($candidate)) {
                continue;
            }

            $normalized = $this->iinReconciler->normalize((string) $candidate);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function approveFromWebhook(KycProfile $profile, array $payload): void
    {
        $details = [];
        $applicantId = (string) ($profile->sumsub_applicant_id ?? $payload['applicantId'] ?? '');

        if ($applicantId !== '') {
            try {
                $details = $this->fetchApplicantDetails($applicantId);
            } catch (RuntimeException $exception) {
                AppLog::warning('kyc.sumsub.applicant_details_failed', [
                    'applicant_id' => $applicantId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $kycIin = $this->extractIinFromApplicantDetails($details);

        DB::transaction(function () use ($profile, $payload, $details, $kycIin): void {
            $profileUpdates = [
                'status' => 'approved',
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ];

            if ($kycIin !== null) {
                $profileUpdates['document_number'] = $kycIin;
            } elseif (($details['document_number'] ?? '') !== '') {
                $profileUpdates['document_number'] = (string) $details['document_number'];
            }

            if (($details['first_name'] ?? '') !== '') {
                $profileUpdates['first_name'] = (string) $details['first_name'];
            }

            if (($details['last_name'] ?? '') !== '') {
                $profileUpdates['last_name'] = (string) $details['last_name'];
            }

            if (($details['document_type'] ?? '') !== '') {
                $profileUpdates['document_type'] = (string) $details['document_type'];
            }

            $profile->update($profileUpdates);

            $profile->user->update(['kyc_status' => 'approved']);
            $this->iinReconciler->apply($profile->user->fresh(), $kycIin);

            $this->auditLogService->log(
                action: 'kyc.sumsub.approved',
                userId: $profile->user_id,
                entityType: 'kyc_profile',
                entityId: $profile->id,
                payload: [
                    'applicant_id' => $profile->sumsub_applicant_id,
                    'review' => $payload['reviewResult'] ?? null,
                    'kyc_iin' => $kycIin,
                ],
            );

            $this->notifier->notifyKey(
                $profile->user,
                'kyc_sumsub_approved',
            );
        });

        // Same post-approval hook as the manual flow.
        CreateWalletAfterKycApproved::dispatch($profile->user_id);
    }

    private function rejectFromWebhook(KycProfile $profile, array $payload): void
    {
        $isFinal = strtoupper((string) ($payload['reviewResult']['reviewRejectType'] ?? '')) === 'FINAL';
        $reason = (string) ($payload['reviewResult']['moderationComment'] ?? 'Проверка не пройдена');
        $locale = LocaleManager::normalize($profile->user->locale) ?? LocaleManager::default();

        DB::transaction(function () use ($profile, $reason, $isFinal, $payload, $locale): void {
            $profile->update([
                'status' => 'rejected',
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $profile->user->update(['kyc_status' => 'rejected']);

            $this->auditLogService->log(
                action: 'kyc.sumsub.rejected',
                userId: $profile->user_id,
                entityType: 'kyc_profile',
                entityId: $profile->id,
                payload: ['final' => $isFinal, 'review' => $payload['reviewResult'] ?? null],
            );

            $this->notifier->notifyKey(
                $profile->user,
                'kyc_sumsub_rejected',
                [
                    'reason' => $reason,
                    'retry' => $isFinal ? '' : trans('notifications.kyc_sumsub_retry', [], $locale),
                ],
            );
        });
    }

    private function request(string $method, string $pathWithQuery, ?array $body = null): Response
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Sumsub не настроен: заполните SUMSUB_APP_TOKEN и SUMSUB_SECRET_KEY в .env.');
        }

        $baseUrl = rtrim((string) config('kyc.sumsub.base_url'), '/');
        $ts = (string) time();
        $bodyJson = $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : '';

        $signature = hash_hmac(
            'sha256',
            $ts.strtoupper($method).$pathWithQuery.$bodyJson,
            (string) config('kyc.sumsub.secret_key'),
        );

        $request = Http::withHeaders([
            'X-App-Token' => (string) config('kyc.sumsub.app_token'),
            'X-App-Access-Sig' => $signature,
            'X-App-Access-Ts' => $ts,
            'Accept' => 'application/json',
        ])->timeout(15);

        if ($bodyJson !== '') {
            $request = $request->withBody($bodyJson, 'application/json');
        }

        return $request->send($method, $baseUrl.$pathWithQuery);
    }
}
