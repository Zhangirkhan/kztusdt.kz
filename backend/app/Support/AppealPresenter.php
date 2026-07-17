<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\OrderAppeal;
use App\Models\OrderAppealAttachment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class AppealPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function appealPayload(?OrderAppeal $appeal, bool $includeAttachments = false): ?array
    {
        if ($appeal === null) {
            return null;
        }

        return [
            'id' => $appeal->id,
            'side' => $appeal->side,
            'reason' => $appeal->reason,
            'description' => $appeal->description,
            'status' => $appeal->status,
            'created_at' => $appeal->created_at?->toIso8601String(),
            'attachments_count' => $appeal->relationLoaded('attachments')
                ? $appeal->attachments->count()
                : $appeal->attachments()->count(),
            'attachments' => $includeAttachments ? self::attachmentsPayload($appeal) : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function attachmentsPayload(OrderAppeal $appeal): array
    {
        $attachments = $appeal->relationLoaded('attachments')
            ? $appeal->attachments
            : $appeal->attachments()->get();

        return $attachments
            ->map(fn (OrderAppealAttachment $attachment): array => self::attachmentPayload($appeal, $attachment))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function attachmentPayload(OrderAppeal $appeal, OrderAppealAttachment $attachment, ?string $url = null): array
    {
        $mimeType = (string) ($attachment->mime_type ?? 'application/octet-stream');

        return [
            'id' => $attachment->id,
            'url' => $url,
            'filename' => $attachment->original_name,
            'mime_type' => $mimeType,
            'is_image' => str_starts_with($mimeType, 'image/'),
            'is_pdf' => $mimeType === 'application/pdf',
        ];
    }

    /**
     * @param  Collection<int, OrderAppeal>  $appeals
     * @return list<array<string, mixed>>
     */
    public static function indexRows(Collection $appeals): array
    {
        return $appeals->map(function (OrderAppeal $appeal): array {
            $order = $appeal->exchangeOrder;

            return [
                'id' => $appeal->id,
                'order_id' => $order?->id,
                'client' => $order?->user?->phone ?? '—',
                'side' => $appeal->side,
                'reason' => $appeal->reason,
                'status' => $appeal->status,
                'fiat_amount' => $order?->fiat_amount,
                'crypto_amount' => $order?->crypto_amount,
                'direction' => $order?->direction,
                'created_at' => $appeal->created_at?->toIso8601String(),
                'href' => route('admin.appeals.show', $appeal),
            ];
        })->all();
    }

    public static function fileExists(OrderAppealAttachment $attachment): bool
    {
        return Storage::disk('local')->exists($attachment->file_path);
    }
}
