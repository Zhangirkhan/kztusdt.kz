<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\CreateWalletAfterKycApproved;
use App\Models\KycDocument;
use App\Models\KycProfile;
use App\Models\ManualApproval;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class KycService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly UserNotificationService $notifier,
    ) {}

    /**
     * @param  array<string, UploadedFile|null>  $documents
     */
    public function submit(User $user, array $data, array $documents): KycProfile
    {
        if (! $user->phone_verified) {
            throw new RuntimeException('Сначала подтвердите телефон через Telegram.');
        }

        if (in_array($user->kyc_status, ['approved', 'pending_review'], true)) {
            throw new RuntimeException('KYC уже отправлен или одобрен.');
        }

        return DB::transaction(function () use ($user, $data, $documents): KycProfile {
            $profile = KycProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'document_type' => $data['document_type'],
                    'document_number' => $data['document_number'],
                    'status' => 'pending_review',
                    'submitted_at' => now(),
                    'rejection_reason' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ],
            );

            foreach ($documents as $type => $file) {
                if ($file === null) {
                    continue;
                }

                $path = $file->store("kyc/{$user->id}", 'local');

                KycDocument::query()->updateOrCreate(
                    ['kyc_profile_id' => $profile->id, 'type' => $type],
                    [
                        'file_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ],
                );
            }

            $user->update(['kyc_status' => 'pending_review']);

            ManualApproval::query()->updateOrCreate(
                [
                    'entity_type' => 'kyc_profile',
                    'entity_id' => $profile->id,
                    'status' => 'pending',
                ],
                [
                    'required_role' => 'security_officer',
                    'requested_by' => $user->id,
                ],
            );

            $this->auditLogService->log(
                action: 'kyc.submitted',
                userId: $user->id,
                entityType: 'kyc_profile',
                entityId: $profile->id,
                request: request(),
            );

            $this->notifier->notifyUser(
                $user,
                "📋 KYC отправлен на проверку.\n\nМы уведомим вас после решения СБ.",
            );

            return $profile->fresh(['documents']);
        });
    }

    public function approve(KycProfile $profile, User $reviewer, ?string $comment = null): void
    {
        DB::transaction(function () use ($profile, $reviewer, $comment): void {
            $profile->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $profile->user->update(['kyc_status' => 'approved']);

            ManualApproval::query()
                ->where('entity_type', 'kyc_profile')
                ->where('entity_id', $profile->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'approved',
                    'approved_by' => $reviewer->id,
                    'approved_at' => now(),
                    'comment' => $comment,
                ]);

            $this->auditLogService->log(
                action: 'kyc.approved',
                userId: $reviewer->id,
                entityType: 'kyc_profile',
                entityId: $profile->id,
                payload: ['comment' => $comment],
                request: request(),
            );

            $this->notifier->notifyUser(
                $profile->user,
                "✅ KYC одобрен!\n\nСкоро будет создан ваш USDT кошелёк.",
            );
        });

        // Create the HD wallet after KYC approval is committed.
        CreateWalletAfterKycApproved::dispatch($profile->user_id);
    }

    public function reject(KycProfile $profile, User $reviewer, string $reason): void
    {
        DB::transaction(function () use ($profile, $reviewer, $reason): void {
            $profile->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $profile->user->update(['kyc_status' => 'rejected']);

            ManualApproval::query()
                ->where('entity_type', 'kyc_profile')
                ->where('entity_id', $profile->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'rejected_by' => $reviewer->id,
                    'rejected_at' => now(),
                    'comment' => $reason,
                ]);

            $this->auditLogService->log(
                action: 'kyc.rejected',
                userId: $reviewer->id,
                entityType: 'kyc_profile',
                entityId: $profile->id,
                payload: ['reason' => $reason],
                request: request(),
            );

            $this->notifier->notifyUser(
                $profile->user,
                "❌ KYC отклонён.\n\nПричина: {$reason}\n\nИсправьте данные и отправьте снова.",
            );
        });
    }

    /**
     * Allow the client to pass KYC verification again (manual, Aitu, etc.).
     * Does not remove an existing wallet or ledger balances.
     */
    public function reset(KycProfile $profile, User $reviewer, ?string $comment = null): void
    {
        DB::transaction(function () use ($profile, $reviewer, $comment): void {
            $profile->update([
                'status' => 'draft',
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'submitted_at' => null,
            ]);

            $profile->user->update(['kyc_status' => 'none']);

            ManualApproval::query()
                ->where('entity_type', 'kyc_profile')
                ->where('entity_id', $profile->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'rejected_by' => $reviewer->id,
                    'rejected_at' => now(),
                    'comment' => $comment ?: 'Верификация сброшена администратором',
                ]);

            $this->auditLogService->log(
                action: 'kyc.reset',
                userId: $reviewer->id,
                entityType: 'kyc_profile',
                entityId: $profile->id,
                payload: ['comment' => $comment],
                request: request(),
            );

            $this->notifier->notifyUser(
                $profile->user,
                "🔄 Верификация KYC сброшена.\n\nПройдите проверку заново на странице /kyc.",
            );
        });
    }
}
