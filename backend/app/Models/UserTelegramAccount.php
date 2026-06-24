<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserTelegramAccount extends Model
{
    protected $fillable = [
        'user_id',
        'telegram_id',
        'telegram_username',
        'phone',
        'is_verified',
        'linked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'linked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
