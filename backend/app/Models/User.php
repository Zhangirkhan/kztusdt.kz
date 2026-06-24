<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use LaravelWebauthn\WebauthnAuthenticatable;

#[Fillable([
    'name',
    'email',
    'password',
    'phone',
    'iin',
    'phone_verified',
    'phone_verified_at',
    'kyc_status',
    'has_subscription',
    'tenant_id',
    'status',
    'locale',
])]
#[Hidden(['password', 'remember_token'])]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, WebauthnAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'phone_verified' => 'boolean',
            'has_subscription' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function telegramAccount(): HasOne
    {
        return $this->hasOne(UserTelegramAccount::class);
    }

    public function kycProfile(): HasOne
    {
        return $this->hasOne(KycProfile::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function walletAddresses(): HasMany
    {
        return $this->hasMany(WalletAddress::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(Balance::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function exchangeOrders(): HasMany
    {
        return $this->hasMany(ExchangeOrder::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['tenant_id', 'exchange_point_id'])
            ->withTimestamps();
    }

    public function hasRole(string $code): bool
    {
        // Use the (memoised) loaded relation so repeated role checks in one request
        // — middleware, nav presenter, shared Inertia props — issue a single query.
        return $this->roles->contains('code', $code);
    }

    public function hasAnyRole(array $codes): bool
    {
        return $this->roles->whereIn('code', $codes)->isNotEmpty();
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(['super_admin', 'security_officer', 'super_admin_manager']);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()->active()->exists();
    }

    public function hasReducedFee(): bool
    {
        if ($this->has_subscription) {
            return true;
        }

        return Cache::remember(
            "user:{$this->id}:active_subscription",
            60,
            fn (): bool => $this->hasActiveSubscription(),
        );
    }

    public function feePercent(): float
    {
        return app(\App\Services\SubscriptionPlanService::class)->feePercentFor($this);
    }

    public function canUseWallet(): bool
    {
        return $this->phone_verified && $this->kyc_status === 'approved';
    }

    /**
     * @return array{provider: string, needs_verification: bool, inline_sumsub: bool}
     */
    public function kycMeta(): array
    {
        $provider = (string) config('kyc.provider', 'manual');

        if ($provider === 'sumsub') {
            $sumsub = app(\App\Services\SumsubService::class);

            if (! $sumsub->isConfigured()) {
                $provider = 'manual';
            }
        }

        if ($provider === 'aitu' && ! app(\App\Services\AituPassportService::class)->isConfigured()) {
            $provider = 'manual';
        }

        $status = (string) $this->kyc_status;

        return [
            'provider' => $provider,
            'needs_verification' => ! in_array($status, ['approved', 'pending_review'], true),
            'inline_sumsub' => $provider === 'sumsub' && $status !== 'approved',
        ];
    }
}
