<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Withdrawal extends Model
{
    public const STATUS_CREATED = 'created';
    public const STATUS_AWAITING_TELEGRAM_CONFIRMATION = 'awaiting_telegram_confirmation';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REJECTED = 'rejected';

    // Broadcast was interrupted after the row was claimed: the on-chain tx may or
    // may not exist. Funds stay locked and a human must verify the chain before any
    // retry — never auto-retried (that would risk a double-send).
    public const STATUS_NEEDS_RECONCILE = 'needs_reconcile';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'network',
        'asset',
        'to_address',
        'amount',
        'fee_amount',
        'network_fee',
        'total_debit',
        'status',
        'confirmation_token',
        'telegram_confirmed_at',
        'requires_manual_approval',
        'approved_by',
        'approved_at',
        'reject_reason',
        'tx_hash',
        'broadcast_at',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'attempts',
        'last_error',
    ];

    protected $hidden = ['confirmation_token'];

    protected function casts(): array
    {
        return [
            'requires_manual_approval' => 'boolean',
            'attempts' => 'integer',
            'telegram_confirmed_at' => 'datetime',
            'approved_at' => 'datetime',
            'broadcast_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_FAILED,
            self::STATUS_REJECTED,
            self::STATUS_NEEDS_RECONCILE,
        ], true);
    }
}
