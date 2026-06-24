<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SubscriptionPlan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'fee_percent',
        'timing',
        'description',
        'is_default',
        'is_subscription',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'fee_percent' => 'float',
            'is_default' => 'boolean',
            'is_subscription' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
