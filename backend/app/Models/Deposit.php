<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Deposit extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_address_id',
        'network',
        'asset',
        'tx_hash',
        'log_index',
        'from_address',
        'to_address',
        'amount',
        'amount_raw',
        'block_number',
        'confirmations',
        'status',
        'detected_at',
        'confirmed_at',
        'credited_at',
    ];

    protected function casts(): array
    {
        return [
            'block_number' => 'integer',
            'confirmations' => 'integer',
            'log_index' => 'integer',
            'detected_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'credited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletAddress(): BelongsTo
    {
        return $this->belongsTo(WalletAddress::class);
    }

    public function sweepRelation(): HasOne
    {
        return $this->hasOne(Sweep::class);
    }
}
