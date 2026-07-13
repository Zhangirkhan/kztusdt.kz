<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FiatPaymentRequest extends Model
{
    public const DIRECTION_USER_TO_EXCHANGE = 'user_to_exchange';
    public const DIRECTION_EXCHANGE_TO_USER = 'exchange_to_user';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROOF_UPLOADED = 'proof_uploaded';
    public const STATUS_MANUAL_REVIEW = 'manual_review';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'exchange_order_id',
        'user_id',
        'tenant_id',
        'direction',
        'currency',
        'amount',
        'bank_name',
        'recipient_name',
        'recipient_account',
        'payment_reference',
        'proof_file_path',
        'proof_original_name',
        'proof_mime_type',
        'status',
        'confirmed_by',
        'confirmed_at',
        'comment',
    ];

    protected $hidden = [
        'proof_file_path',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    public function exchangeOrder(): BelongsTo
    {
        return $this->belongsTo(ExchangeOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
