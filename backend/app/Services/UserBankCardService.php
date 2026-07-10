<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserBankCard;
use App\Support\KazakhstanAccount;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class UserBankCardService
{
    /**
     * @return list<array{code: string, name: string}>
     */
    public function bankCatalog(): array
    {
        return collect(config('banks.catalog', []))
            ->map(fn (string $name, string $code): array => [
                'code' => $code,
                'name' => $name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, UserBankCard>
     */
    public function cardsFor(User $user): Collection
    {
        return $user->bankCards()->latest('id')->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cardsPayload(User $user): array
    {
        return $this->cardsFor($user)
            ->map(fn (UserBankCard $card): array => $card->toClientArray())
            ->values()
            ->all();
    }

    /**
     * @param  array{bank_code: string, label: string, holder_name: string, phone?: string|null, iban?: string|null}  $data
     */
    public function create(User $user, array $data): UserBankCard
    {
        [$phone, $iban] = $this->normalizeRequisites($data);

        $this->assertAtLeastOneRequisite($phone, $iban);
        $this->assertUnique($user, $data['bank_code'], $phone, $iban);

        return $user->bankCards()->create([
            'bank_code' => $data['bank_code'],
            'label' => trim($data['label']),
            'holder_name' => trim($data['holder_name']),
            'phone' => $phone,
            'iban' => $iban,
        ]);
    }

    /**
     * @param  array{bank_code?: string, label?: string, holder_name?: string, phone?: string|null, iban?: string|null}  $data
     */
    public function update(UserBankCard $card, array $data): UserBankCard
    {
        $bankCode = $data['bank_code'] ?? $card->bank_code;
        $label = array_key_exists('label', $data) ? trim((string) $data['label']) : $card->label;
        $holder = array_key_exists('holder_name', $data) ? trim((string) $data['holder_name']) : $card->holder_name;

        $phoneSource = array_key_exists('phone', $data) ? $data['phone'] : $card->phone;
        $ibanSource = array_key_exists('iban', $data) ? $data['iban'] : $card->iban;

        [$phone, $iban] = $this->normalizeRequisites([
            'phone' => $phoneSource,
            'iban' => $ibanSource,
        ]);

        $this->assertAtLeastOneRequisite($phone, $iban);
        $this->assertUnique($card->user, $bankCode, $phone, $iban, $card->id);

        $card->update([
            'bank_code' => $bankCode,
            'label' => $label,
            'holder_name' => $holder,
            'phone' => $phone,
            'iban' => $iban,
        ]);

        return $card->fresh();
    }

    public function rename(UserBankCard $card, string $label): UserBankCard
    {
        $card->update(['label' => trim($label)]);

        return $card->fresh();
    }

    public function delete(UserBankCard $card): void
    {
        $card->delete();
    }

    /**
     * @return array{bank_name: string, recipient_name: string, recipient_account: string}
     */
    public function payoutDetails(UserBankCard $card, string $payoutType): array
    {
        $account = $card->accountForPayout($payoutType);

        if ($account === null) {
            throw ValidationException::withMessages([
                'payout_type' => 'У выбранной карты нет этого способа получения.',
            ]);
        }

        return [
            'bank_name' => $card->bankName(),
            'recipient_name' => $card->holder_name,
            'recipient_account' => $account,
        ];
    }

    public function findOwnedCard(User $user, int $cardId): UserBankCard
    {
        $card = $user->bankCards()->whereKey($cardId)->first();

        if ($card === null) {
            throw ValidationException::withMessages([
                'card_id' => 'Карта не найдена. Добавьте реквизиты в профиле.',
            ]);
        }

        return $card;
    }

    /**
     * @param  array{phone?: string|null, iban?: string|null}  $data
     * @return array{0: ?string, 1: ?string}
     */
    private function normalizeRequisites(array $data): array
    {
        $phoneRaw = $data['phone'] ?? null;
        $ibanRaw = $data['iban'] ?? null;

        $phone = is_string($phoneRaw) ? KazakhstanAccount::normalizePhone($phoneRaw) : null;
        $iban = is_string($ibanRaw) ? KazakhstanAccount::normalizeIban($ibanRaw) : null;

        if (is_string($phoneRaw) && trim($phoneRaw) !== '' && trim($phoneRaw) !== '+7' && $phone === null) {
            throw ValidationException::withMessages([
                'phone' => 'Укажите корректный номер телефона в формате +7XXXXXXXXXX.',
            ]);
        }

        if ($phone !== null && ! KazakhstanAccount::isValidPhone($phone)) {
            throw ValidationException::withMessages([
                'phone' => 'Укажите корректный мобильный номер Казахстана.',
            ]);
        }

        if (is_string($ibanRaw) && trim($ibanRaw) !== '' && strtoupper(preg_replace('/\s+/', '', $ibanRaw) ?? '') !== 'KZ' && $iban === null) {
            throw ValidationException::withMessages([
                'iban' => 'Укажите корректный IBAN (KZ…).',
            ]);
        }

        if ($iban !== null && ! KazakhstanAccount::isValidIban($iban)) {
            throw ValidationException::withMessages([
                'iban' => 'IBAN должен быть в формате KZ и содержать 20 символов.',
            ]);
        }

        return [$phone, $iban];
    }

    private function assertAtLeastOneRequisite(?string $phone, ?string $iban): void
    {
        if ($phone === null && $iban === null) {
            throw ValidationException::withMessages([
                'phone' => 'Укажите телефон и/или IBAN.',
                'iban' => 'Укажите телефон и/или IBAN.',
            ]);
        }
    }

    private function assertUnique(User $user, string $bankCode, ?string $phone, ?string $iban, ?int $ignoreId = null): void
    {
        if ($iban !== null) {
            $exists = $user->bankCards()
                ->where('bank_code', $bankCode)
                ->where('iban', $iban)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'iban' => 'Такой IBAN уже добавлен для этого банка.',
                ]);
            }
        }

        if ($phone !== null) {
            $exists = $user->bankCards()
                ->where('bank_code', $bankCode)
                ->where('phone', $phone)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'phone' => 'Такой телефон уже добавлен для этого банка.',
                ]);
            }
        }
    }
}
