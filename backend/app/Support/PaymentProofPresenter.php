<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FiatPaymentRequest;
use Illuminate\Support\Facades\Storage;

final class PaymentProofPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function payload(?FiatPaymentRequest $paymentRequest, string $url): ?array
    {
        if ($paymentRequest === null || $paymentRequest->proof_file_path === null) {
            return null;
        }

        if (! Storage::disk('local')->exists($paymentRequest->proof_file_path)) {
            return null;
        }

        $mimeType = (string) ($paymentRequest->proof_mime_type ?? 'application/octet-stream');

        return [
            'url' => $url,
            'filename' => $paymentRequest->proof_original_name ?? 'payment-proof',
            'mime_type' => $mimeType,
            'is_image' => str_starts_with($mimeType, 'image/'),
            'is_pdf' => $mimeType === 'application/pdf',
        ];
    }
}
