<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use App\Models\UserTelegramAccount;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Http;

trait ExchangeTestHelpers
{
    /**
     * Verified client with approved KYC — may trade, withdraw, etc.
     */
    protected function createClient(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'phone' => '+7'.fake()->unique()->numerify('7#########'),
            'phone_verified' => true,
            'phone_verified_at' => now(),
            'kyc_status' => 'approved',
        ], $attributes));
    }

    /**
     * Verified client that has not passed KYC yet.
     */
    protected function createUnverifiedClient(array $attributes = []): User
    {
        return $this->createClient(array_merge(['kyc_status' => 'none'], $attributes));
    }

    protected function createStaff(string $roleCode = 'security_officer', array $attributes = []): User
    {
        $user = $this->createClient($attributes);
        $this->assignRole($user, $roleCode);

        return $user;
    }

    protected function assignRole(User $user, string $roleCode): void
    {
        $role = Role::query()->firstOrCreate(
            ['code' => $roleCode],
            ['name' => $roleCode],
        );

        $user->roles()->attach($role->id);
    }

    protected function linkTelegram(User $user, string $telegramId = '111222333'): UserTelegramAccount
    {
        return UserTelegramAccount::query()->create([
            'user_id' => $user->id,
            'telegram_id' => $telegramId,
            'telegram_username' => 'test_user',
            'phone' => $user->phone,
            'is_verified' => true,
            'linked_at' => now(),
        ]);
    }

    /**
     * Credit USDT to the user through the double-entry ledger (as a deposit would).
     */
    protected function giveBalance(User $user, string $amount, string $asset = 'USDT'): void
    {
        app(LedgerService::class)->creditDeposit(
            $user->id,
            $asset,
            $amount,
            'test_deposit',
            0,
            'test seed balance',
        );
    }

    /**
     * Deterministic external HTTP world: Binance rate + Telegram API always OK.
     */
    protected function fakeExternalApis(float $binancePrice = 500.0): void
    {
        Http::fake([
            'api.binance.com/*' => Http::response(['symbol' => 'USDTKZT', 'price' => number_format($binancePrice, 8, '.', '')]),
            'api.coingecko.com/*' => Http::response(['tether' => ['kzt' => $binancePrice]]),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
            'gatewayapi.telegram.org/*' => Http::response(['ok' => true, 'result' => ['request_id' => 'req_'.uniqid()]]),
            '*/api/otp/send' => Http::response(['success' => true, 'expires_in' => 300]),
            '*/api/otp/verify' => Http::response(['success' => true]),
        ]);
    }

    protected function enableOtp(): void
    {
        config([
            'otp.token' => 'test-otp-token',
        ]);
    }

    /** @deprecated use enableOtp() */
    protected function enableTelegram(): void
    {
        $this->enableOtp();
    }
}
