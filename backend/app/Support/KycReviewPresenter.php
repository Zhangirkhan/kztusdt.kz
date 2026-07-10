<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\KycProfile;
use App\Services\SumsubService;

final class KycReviewPresenter
{
    private const STATUS_LABELS = [
        'draft' => 'Черновик',
        'pending_review' => 'На проверке',
        'approved' => 'Одобрено',
        'rejected' => 'Отклонено',
    ];

    private const DOCUMENT_LABELS = [
        'id_front' => 'Лицевая сторона',
        'id_back' => 'Обратная сторона',
        'selfie' => 'Селфи с документом',
    ];

    public function __construct(
        private readonly SumsubService $sumsubService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function showPayload(KycProfile $profile): array
    {
        $profile->loadMissing(['user', 'documents', 'reviewer:id,name']);

        $user = $profile->user;

        return [
            'id' => $profile->id,
            'provider' => (string) ($profile->provider ?? 'manual'),
            'provider_label' => $this->providerLabel((string) ($profile->provider ?? 'manual')),
            'status' => (string) $profile->status,
            'status_label' => self::STATUS_LABELS[$profile->status] ?? $profile->status,
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
            'document_type' => $profile->document_type,
            'document_number' => $profile->document_number,
            'rejection_reason' => $profile->rejection_reason,
            'submitted_at' => $profile->submitted_at?->toIso8601String(),
            'reviewed_at' => $profile->reviewed_at?->toIso8601String(),
            'sumsub_applicant_id' => $profile->sumsub_applicant_id,
            'provider_verification_id' => $profile->provider_verification_id,
            'provider_session_id' => $profile->provider_session_id,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'iin' => $user->iin,
                'kyc_status' => $user->kyc_status,
            ] : null,
            'reviewer' => $profile->reviewer ? [
                'id' => $profile->reviewer->id,
                'name' => $profile->reviewer->name,
            ] : null,
            'documents' => $profile->documents
                ->map(fn ($document): array => [
                    'id' => $document->id,
                    'type' => $document->type,
                    'label' => self::DOCUMENT_LABELS[$document->type] ?? $document->type,
                    'original_name' => $document->original_name,
                ])
                ->values()
                ->all(),
            'sumsub' => $this->sumsubDetails($profile),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sumsubDetails(KycProfile $profile): ?array
    {
        if (! (bool) config('kyc.admin_show_sumsub', false)) {
            return null;
        }

        if (($profile->provider ?? 'manual') !== 'sumsub' || $profile->sumsub_applicant_id === null) {
            return null;
        }

        if (! $this->sumsubService->isConfigured()) {
            return [
                'configured' => false,
                'error' => 'Sumsub не настроен в .env',
            ];
        }

        try {
            $details = $this->sumsubService->fetchApplicantDetails((string) $profile->sumsub_applicant_id);
        } catch (\Throwable $exception) {
            return [
                'configured' => true,
                'error' => $exception->getMessage(),
            ];
        }

        return [
            'configured' => true,
            'applicant_id' => $profile->sumsub_applicant_id,
            'dashboard_url' => $this->sumsubDashboardUrl((string) $profile->sumsub_applicant_id),
            ...$details,
        ];
    }

    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'sumsub' => 'Sumsub (внешняя верификация)',
            'aitu' => 'Aitu Passport',
            default => 'Ручная проверка',
        };
    }

    private function sumsubDashboardUrl(string $applicantId): ?string
    {
        $base = rtrim((string) config('kyc.sumsub.dashboard_url', ''), '/');

        if ($base === '') {
            return null;
        }

        return $base.'/applicant/'.$applicantId.'/basicInfo';
    }
}
