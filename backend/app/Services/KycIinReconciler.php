<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Rules\Iin;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * After KYC, keep registration IIN and provider-verified IIN in sync.
 * Mismatch blocks wallet access until the user confirms the KYC IIN.
 */
final class KycIinReconciler
{
    public function apply(User $user, ?string $kycIin): void
    {
        if ($user->isLegalEntity()) {
            return;
        }

        $normalized = $this->normalizeValid($kycIin);

        if ($normalized === null) {
            return;
        }

        $registered = $this->normalizeDigits($user->iin);
        $updates = ['kyc_iin' => $normalized];

        // No registration IIN yet — take the verified one.
        if ($registered === null) {
            $updates['iin'] = $normalized;
        }

        $user->update($updates);
    }

    /**
     * User re-enters IIN after mismatch; must equal KYC-verified IIN.
     */
    public function confirm(User $user, string $iin): void
    {
        if ($user->isLegalEntity()) {
            throw new RuntimeException('Подтверждение ИИН недоступно для юрлица.');
        }

        $kycIin = $this->normalizeDigits($user->kyc_iin);

        if ($kycIin === null) {
            throw new RuntimeException('Нет ИИН из KYC для сверки.');
        }

        $normalized = $this->normalizeValid($iin);

        if ($normalized === null) {
            throw new RuntimeException('Некорректный ИИН.');
        }

        if ($normalized !== $kycIin) {
            throw new RuntimeException('ИИН не совпадает с данными из KYC. Введите корректный ИИН.');
        }

        $user->update(['iin' => $normalized]);
    }

    public function hasMismatch(User $user): bool
    {
        if ($user->isLegalEntity()) {
            return false;
        }

        $kycIin = $this->normalizeDigits($user->kyc_iin);
        $registered = $this->normalizeDigits($user->iin);

        if ($kycIin === null || $registered === null) {
            return false;
        }

        return $registered !== $kycIin;
    }

    public function normalize(?string $iin): ?string
    {
        return $this->normalizeValid($iin);
    }

    private function normalizeValid(?string $iin): ?string
    {
        $digits = $this->normalizeDigits($iin);

        if ($digits === null) {
            return null;
        }

        $passes = Validator::make(
            ['iin' => $digits],
            ['iin' => [new Iin]],
        )->passes();

        return $passes ? $digits : null;
    }

    private function normalizeDigits(?string $iin): ?string
    {
        if ($iin === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $iin) ?? '';

        return strlen($digits) === 12 ? $digits : null;
    }
}
