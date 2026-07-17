<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OrderAppeal extends Model
{
    public const SIDE_CLIENT = 'client';
    public const SIDE_EXCHANGE = 'exchange';

    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'exchange_order_id',
        'opened_by_user_id',
        'tenant_id',
        'side',
        'reason',
        'description',
        'status',
        'resolution_note',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function exchangeOrder(): BelongsTo
    {
        return $this->belongsTo(ExchangeOrder::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(OrderAppealAttachment::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}

