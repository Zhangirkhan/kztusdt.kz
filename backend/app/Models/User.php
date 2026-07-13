<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientType;
use App\Support\KycClientOptions;
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
    'client_type',
    'iin',
    'kyc_iin',
    'bin',
    'company_name',
    'eds_verified_at',
    'eds_certificate_subject',
    'representative_iin',
    'phone_verified',
    'phone_verified_at',
    'kyc_status',
    'manual_kyc_enabled',
    'has_subscription',
    'tenant_id',
    'status',
    'locale',
    'bank_name',
    'bank_holder',
    'bank_account',
    'notification_preferences',
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
            'eds_verified_at' => 'datetime',
            'phone_verified' => 'boolean',
            'manual_kyc_enabled' => 'boolean',
            'has_subscription' => 'boolean',
            'password' => 'hashed',
            'notification_preferences' => 'array',
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

    public function bankCards(): HasMany
    {
        return $this->hasMany(UserBankCard::class);
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
        return $this->phone_verified
            && $this->kyc_status === 'approved'
            && ! $this->hasIinMismatch();
    }

    public function hasIinMismatch(): bool
    {
        return app(\App\Services\KycIinReconciler::class)->hasMismatch($this);
    }

    public function clientType(): ClientType
    {
        return ClientType::tryFrom((string) $this->client_type) ?? ClientType::Individual;
    }

    public function isLegalEntity(): bool
    {
        return $this->clientType() === ClientType::LegalEntity;
    }

    public function displayName(): string
    {
        if ($this->isLegalEntity() && is_string($this->company_name) && $this->company_name !== '') {
            return $this->company_name;
        }

        return (string) ($this->name ?: $this->phone ?: $this->email);
    }

    /**
     * @return array{provider: string, needs_verification: bool, inline_sumsub: bool}
     */
    public function kycMeta(): array
    {
        return KycClientOptions::forUser($this);
    }
}
