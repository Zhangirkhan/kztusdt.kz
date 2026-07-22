<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AuthSession extends Model
{
    protected $fillable = [
        'phone',
        'client_type',
        'iin',
        'bin',
        'company_name',
        'eds_challenge',
        'eds_challenge_expires_at',
        'eds_verified_at',
        'eds_certificate_subject',
        'eds_signer_iin',
        'eds_signer_bin',
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
        'referred_by_user_id',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'eds_challenge_expires_at' => 'datetime',
            'eds_verified_at' => 'datetime',
            'code_attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function isLegalEntity(): bool
    {
        return $this->client_type === 'legal_entity';
    }

    public function hasEdsVerified(): bool
    {
        return $this->eds_verified_at !== null;
    }

    public function requiresEds(): bool
    {
        return $this->isLegalEntity() && (bool) config('ncanode.legal_entity_eds_required');
    }
}
