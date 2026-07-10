<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserBankCard extends Model
{
    protected $fillable = [
        'user_id',
        'bank_code',
        'bik',
        'label',
        'holder_name',
        'phone',
        'iban',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankName(): string
    {
        return self::bankNameForCode($this->bank_code);
    }

    public static function bankNameForCode(string $code): string
    {
        $entry = config("banks.catalog.{$code}");

        if (is_array($entry)) {
            return (string) ($entry['name'] ?? $code);
        }

        return (string) ($entry ?? $code);
    }

    public static function bikForCode(string $code): ?string
    {
        $entry = config("banks.catalog.{$code}");

        if (! is_array($entry)) {
            return null;
        }

        $bik = strtoupper(trim((string) ($entry['bik'] ?? '')));

        return $bik !== '' ? $bik : null;
    }

    public function hasPhone(): bool
    {
        return is_string($this->phone) && $this->phone !== '';
    }

    public function hasIban(): bool
    {
        return is_string($this->iban) && $this->iban !== '';
    }

    /**
     * @return list<string>
     */
    public function availablePayoutTypes(): array
    {
        $types = [];

        if ($this->hasPhone()) {
            $types[] = 'phone';
        }

        if ($this->hasIban()) {
            $types[] = 'iban';
        }

        return $types;
    }

    public function accountForPayout(string $payoutType): ?string
    {
        return match ($payoutType) {
            'phone' => $this->hasPhone() ? $this->phone : null,
            'iban' => $this->hasIban() ? $this->iban : null,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'bank_code' => $this->bank_code,
            'bank_name' => $this->bankName(),
            'bik' => $this->bik,
            'label' => $this->label,
            'holder_name' => $this->holder_name,
            'phone' => $this->phone,
            'iban' => $this->iban,
            'phone_masked' => $this->maskPhone($this->phone),
            'iban_masked' => $this->maskIban($this->iban),
            'available_payout_types' => $this->availablePayoutTypes(),
        ];
    }

    private function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 4) {
            return $phone;
        }

        return '+7 *** *** '.substr($digits, -4);
    }

    private function maskIban(?string $iban): ?string
    {
        if ($iban === null || $iban === '') {
            return null;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');

        if (strlen($normalized) < 8) {
            return $normalized;
        }

        return substr($normalized, 0, 4).' **** **** **** '.substr($normalized, -4);
    }
}
