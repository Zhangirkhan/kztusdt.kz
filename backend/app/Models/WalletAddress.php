<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WalletAddress extends Model
{
    protected $fillable = [
        'user_id',
        'network',
        'asset',
        'address',
        'derivation_index',
        'derivation_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'derivation_index' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
