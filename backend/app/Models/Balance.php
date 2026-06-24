<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Balance extends Model
{
    protected $fillable = [
        'user_id',
        'asset',
        'available',
        'locked',
    ];

    protected function casts(): array
    {
        return [
            'available' => 'decimal:18',
            'locked' => 'decimal:18',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
