<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReferralBenefitType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserReferralBenefit extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'value',
        'note',
        'is_active',
        'expires_at',
        'granted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function typeEnum(): ReferralBenefitType
    {
        return ReferralBenefitType::tryFrom((string) $this->type) ?? ReferralBenefitType::FeeDiscount;
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
