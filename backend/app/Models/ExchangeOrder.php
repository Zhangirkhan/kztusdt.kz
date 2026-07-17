<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\BankCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class ExchangeOrder extends Model
{
    public const DIRECTION_BUY = 'buy';
    public const DIRECTION_SELL = 'sell';

    public const STATUS_CREATED = 'created';
    public const STATUS_AWAITING_KZT_PAYMENT = 'awaiting_kzt_payment';
    public const STATUS_PAYMENT_PROOF_UPLOADED = 'payment_proof_uploaded';
    public const STATUS_PENDING_ADMIN_CONFIRMATION = 'pending_admin_confirmation';
    public const STATUS_KZT_SENT = 'kzt_sent';
    public const STATUS_KZT_RECEIVED = 'kzt_received';
    public const STATUS_CRYPTO_SENDING = 'crypto_sending';
    public const STATUS_CRYPTO_SENT = 'crypto_sent';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DISPUTE = 'dispute';
    public const STATUS_MANUAL_REVIEW = 'manual_review';

    /** Statuses in which a buy order still waits for admin confirmation. */
    public const BUY_CONFIRMABLE_STATUSES = [
        self::STATUS_AWAITING_KZT_PAYMENT,
        self::STATUS_PAYMENT_PROOF_UPLOADED,
        self::STATUS_PENDING_ADMIN_CONFIRMATION,
    ];

    protected $appends = [
        'payment_bank_name',
    ];

    protected $fillable = [
        'user_id',
        'tenant_id',
        'exchange_listing_id',
        'direction',
        'status',
        'fiat_currency',
        'crypto_asset',
        'network',
        'rate',
        'payment_term',
        'payment_bank_code',
        'listing_conditions',
        'payment_marked_at',
        'fiat_amount',
        'crypto_amount',
        'fee_percent',
        'fee_amount',
        'reject_reason',
        'confirmed_by',
        'kzt_received_at',
        'kzt_sent_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'kzt_received_at' => 'datetime',
            'payment_marked_at' => 'datetime',
            'kzt_sent_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function exchangeListing(): BelongsTo
    {
        return $this->belongsTo(ExchangeListing::class);
    }

    public function fiatPaymentRequest(): HasOne
    {
        return $this->hasOne(FiatPaymentRequest::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(OrderAppeal::class);
    }

    public function openAppeal(): HasOne
    {
        return $this->hasOne(OrderAppeal::class)->where('status', OrderAppeal::STATUS_OPEN);
    }

    public function isBuy(): bool
    {
        return $this->direction === self::DIRECTION_BUY;
    }

    public function isSell(): bool
    {
        return $this->direction === self::DIRECTION_SELL;
    }

    public function paymentBankName(): ?string
    {
        if ($this->payment_bank_code === null || $this->payment_bank_code === '') {
            return null;
        }

        return BankCatalog::nameForCode($this->payment_bank_code);
    }

    public function getPaymentBankNameAttribute(): ?string
    {
        return $this->paymentBankName();
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_FAILED,
        ], true);
    }
}
