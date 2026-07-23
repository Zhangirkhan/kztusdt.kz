<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DueDiligenceProfile extends Model
{
    protected $fillable = [
        'user_id',
        'source_of_funds',
        'source_of_funds_other',
        'occupation',
        'industry',
        'industry_other',
        'annual_income',
        'platform_purpose',
        'platform_purpose_other',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
