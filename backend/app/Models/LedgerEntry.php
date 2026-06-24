<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LedgerEntry extends Model
{
    protected $fillable = [
        'user_id',
        'account',
        'asset',
        'debit',
        'credit',
        'ref_type',
        'ref_id',
        'memo',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:18',
            'credit' => 'decimal:18',
            'ref_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
