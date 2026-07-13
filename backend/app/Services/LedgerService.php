<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Balance;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * Double-entry ledger. Every balance change is recorded as paired entries.
 * Financial records are append-only — never deleted or mutated retroactively.
 */
final class LedgerService
{
    /**
     * Credit a user's available balance (e.g. confirmed crypto deposit).
     */
    public function creditDeposit(
        int $userId,
        string $asset,
        string $amount,
        string $refType,
        int $refId,
        ?string $memo = null,
    ): void {
        DB::transaction(function () use ($userId, $asset, $amount, $refType, $refId, $memo): void {
            // External source (debit) -> user available (credit).
            LedgerEntry::query()->create([
                'user_id' => null,
                'account' => 'external_crypto',
                'asset' => $asset,
                'debit' => $amount,
                'credit' => 0,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'memo' => $memo,
            ]);

            LedgerEntry::query()->create([
                'user_id' => $userId,
                'account' => 'user_available',
                'asset' => $asset,
                'debit' => 0,
                'credit' => $amount,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'memo' => $memo,
            ]);

            $balance = Balance::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['user_id' => $userId, 'asset' => $asset],
                    ['available' => '0', 'locked' => '0'],
                );

            $balance->update([
                'available' => bcadd((string) $balance->available, $amount, 18),
            ]);
        });
    }

    /**
     * Credit USDT bought for fiat (KZT received by the exchanger).
     * Gross = net credited to user + fee retained by the exchange.
     */
    public function creditBuyOrder(
        int $userId,
        string $asset,
        string $grossAmount,
        string $feeAmount,
        string $refType,
        int $refId,
        ?string $memo = null,
    ): void {
        DB::transaction(function () use ($userId, $asset, $grossAmount, $feeAmount, $refType, $refId, $memo): void {
            $alreadyCredited = LedgerEntry::query()
                ->where('ref_type', $refType)
                ->where('ref_id', $refId)
                ->where('account', 'user_available')
                ->lockForUpdate()
                ->exists();

            if ($alreadyCredited) {
                return;
            }

            $netAmount = bcsub($grossAmount, $feeAmount, 18);

            $this->entry(null, 'external_fiat', $asset, $grossAmount, '0', $refType, $refId, $memo);
            $this->entry($userId, 'user_available', $asset, '0', $netAmount, $refType, $refId, $memo);
            $this->entry(null, 'fee_revenue', $asset, '0', $feeAmount, $refType, $refId, $memo);

            $balance = $this->lockedBalanceRow($userId, $asset);

            $balance->update([
                'available' => bcadd((string) $balance->available, $netAmount, 18),
            ]);
        });
    }

    /**
     * Move funds from available to locked (hold for a sell order / withdrawal).
     *
     * @throws InsufficientBalanceException
     */
    public function lock(
        int $userId,
        string $asset,
        string $amount,
        string $refType,
        int $refId,
        ?string $memo = null,
    ): void {
        DB::transaction(function () use ($userId, $asset, $amount, $refType, $refId, $memo): void {
            $balance = $this->lockedBalanceRow($userId, $asset);

            if (bccomp((string) $balance->available, $amount, 18) < 0) {
                throw new InsufficientBalanceException('Недостаточно средств на балансе.');
            }

            $this->entry($userId, 'user_available', $asset, $amount, '0', $refType, $refId, $memo);
            $this->entry($userId, 'user_locked', $asset, '0', $amount, $refType, $refId, $memo);

            $balance->update([
                'available' => bcsub((string) $balance->available, $amount, 18),
                'locked' => bcadd((string) $balance->locked, $amount, 18),
            ]);
        });
    }

    /**
     * Release a hold back to the available balance (cancelled/rejected operation).
     */
    public function unlock(
        int $userId,
        string $asset,
        string $amount,
        string $refType,
        int $refId,
        ?string $memo = null,
    ): void {
        DB::transaction(function () use ($userId, $asset, $amount, $refType, $refId, $memo): void {
            $balance = $this->lockedBalanceRow($userId, $asset);

            if (bccomp((string) $balance->locked, $amount, 18) < 0) {
                throw new InsufficientBalanceException('Заблокированная сумма меньше ожидаемой.');
            }

            $this->entry($userId, 'user_locked', $asset, $amount, '0', $refType, $refId, $memo);
            $this->entry($userId, 'user_available', $asset, '0', $amount, $refType, $refId, $memo);

            $balance->update([
                'available' => bcadd((string) $balance->available, $amount, 18),
                'locked' => bcsub((string) $balance->locked, $amount, 18),
            ]);
        });
    }

    /**
     * Settle a sell order: burn the locked gross USDT; the net part is owed to the
     * user in fiat (paid manually), the fee part is exchange revenue.
     */
    public function settleSellOrder(
        int $userId,
        string $asset,
        string $grossAmount,
        string $feeAmount,
        string $refType,
        int $refId,
        ?string $memo = null,
    ): void {
        DB::transaction(function () use ($userId, $asset, $grossAmount, $feeAmount, $refType, $refId, $memo): void {
            $balance = $this->lockedBalanceRow($userId, $asset);

            if (bccomp((string) $balance->locked, $grossAmount, 18) < 0) {
                throw new InsufficientBalanceException('Заблокированная сумма меньше суммы заявки.');
            }

            $netAmount = bcsub($grossAmount, $feeAmount, 18);

            $this->entry($userId, 'user_locked', $asset, $grossAmount, '0', $refType, $refId, $memo);
            $this->entry(null, 'external_fiat', $asset, '0', $netAmount, $refType, $refId, $memo);
            $this->entry(null, 'fee_revenue', $asset, '0', $feeAmount, $refType, $refId, $memo);

            $balance->update([
                'locked' => bcsub((string) $balance->locked, $grossAmount, 18),
            ]);
        });
    }

    /**
     * Settle a completed on-chain withdrawal: burn the locked total
     * (sent amount goes to the external crypto account, fees to revenue).
     */
    public function settleWithdrawal(
        int $userId,
        string $asset,
        string $totalDebit,
        string $sentAmount,
        string $feeTotal,
        string $refType,
        int $refId,
        ?string $memo = null,
    ): void {
        DB::transaction(function () use ($userId, $asset, $totalDebit, $sentAmount, $feeTotal, $refType, $refId, $memo): void {
            $balance = $this->lockedBalanceRow($userId, $asset);

            if (bccomp((string) $balance->locked, $totalDebit, 18) < 0) {
                throw new InsufficientBalanceException('Заблокированная сумма меньше суммы вывода.');
            }

            $this->entry($userId, 'user_locked', $asset, $totalDebit, '0', $refType, $refId, $memo);
            $this->entry(null, 'external_crypto', $asset, '0', $sentAmount, $refType, $refId, $memo);
            $this->entry(null, 'fee_revenue', $asset, '0', $feeTotal, $refType, $refId, $memo);

            $balance->update([
                'locked' => bcsub((string) $balance->locked, $totalDebit, 18),
            ]);
        });
    }

    public function availableBalance(int $userId, string $asset): string
    {
        return $this->balancesFor($userId, $asset)['available'];
    }

    public function lockedBalance(int $userId, string $asset): string
    {
        return $this->balancesFor($userId, $asset)['locked'];
    }

    /**
     * @return array{available: string, locked: string}
     */
    public function balancesFor(int $userId, string $asset): array
    {
        $balance = Balance::query()
            ->where('user_id', $userId)
            ->where('asset', $asset)
            ->first(['available', 'locked']);

        return [
            'available' => $balance?->available ?? '0',
            'locked' => $balance?->locked ?? '0',
        ];
    }

    private function lockedBalanceRow(int $userId, string $asset): Balance
    {
        return Balance::query()
            ->lockForUpdate()
            ->firstOrCreate(
                ['user_id' => $userId, 'asset' => $asset],
                ['available' => '0', 'locked' => '0'],
            );
    }

    private function entry(
        ?int $userId,
        string $account,
        string $asset,
        string $debit,
        string $credit,
        string $refType,
        int $refId,
        ?string $memo,
    ): void {
        LedgerEntry::query()->create([
            'user_id' => $userId,
            'account' => $account,
            'asset' => $asset,
            'debit' => $debit,
            'credit' => $credit,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'memo' => $memo,
        ]);
    }
}
