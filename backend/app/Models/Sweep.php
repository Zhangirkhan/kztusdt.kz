<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Sweep extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_WAITING_GAS = 'waiting_gas';
    public const STATUS_GAS_SENT = 'gas_sent';
    public const STATUS_SWEEPING = 'sweeping';
    public const STATUS_SWEPT = 'swept';
    public const STATUS_FAILED = 'failed';
    public const STATUS_MANUAL_REVIEW = 'manual_review';

    protected $fillable = [
        'deposit_id',
        'user_id',
        'wallet_address_id',
        'network',
        'asset',
        'from_address',
        'to_address',
        'amount',
        'amount_raw',
        'status',
        'gas_tx_hash',
        'sweep_tx_hash',
        'attempts',
        'last_error',
        'gas_sent_at',
        'swept_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'gas_sent_at' => 'datetime',
            'swept_at' => 'datetime',
        ];
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletAddress(): BelongsTo
    {
        return $this->belongsTo(WalletAddress::class);
    }
}
