<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ExchangeListing extends Model
{
    public const DIRECTION_SELL_USDT = 'sell_usdt';
    public const DIRECTION_BUY_USDT = 'buy_usdt';

    public const PRICE_FIXED = 'fixed';
    public const PRICE_FLOATING = 'floating';

    protected $fillable = [
        'tenant_id',
        'created_by',
        'direction',
        'price_type',
        'fixed_rate',
        'margin_percent',
        'total_usdt',
        'remaining_usdt',
        'min_limit_kzt',
        'max_limit_kzt',
        'payment_methods',
        'payment_term',
        'conditions_text',
        'is_active',
        'sort_order',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_methods' => 'array',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ExchangeOrder::class);
    }

    public function clientDirection(): string
    {
        return $this->direction === self::DIRECTION_SELL_USDT
            ? ExchangeOrder::DIRECTION_BUY
            : ExchangeOrder::DIRECTION_SELL;
    }

    public function titleLabel(): string
    {
        return $this->direction === self::DIRECTION_SELL_USDT
            ? 'Купить USDT'
            : 'Продать USDT';
    }
}
