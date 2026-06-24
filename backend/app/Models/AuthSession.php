<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AuthSession extends Model
{
    protected $fillable = [
        'phone',
        'iin',
        'login_code',
        'code_hash',
        'gateway_request_id',
        'code_attempts',
        'telegram_id',
        'telegram_username',
        'telegram_phone',
        'status',
        'expires_at',
        'verified_at',
        'user_id',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'code_attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }
}
