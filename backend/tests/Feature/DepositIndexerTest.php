<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Deposit;
use App\Models\User;
use App\Models\WalletAddress;
use App\Services\DepositIndexerService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

/**
 * Этап 4: индексер депозитов USDT BEP20 (сканирование блоков + 12 подтверждений).
 */
final class DepositIndexerTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private const USER_ADDRESS = '0x9858EfFD232B4033E47d90003D41EC34EcaEda94';

    private const USDT_CONTRACT = '0x55d398326f99059fF775485246999027B3197955';

    private const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    private const TX_HASH = '0xabc1230000000000000000000000000000000000000000000000000000000001';

    private int $head = 0;

    /** @var array<int, array<string, mixed>> */
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bsc.rpc_url' => 'https://rpc.test',
            'bsc.confirmations' => 12,
            'bsc.scan_batch' => 1000,
            'bsc.start_block' => 0,
        ]);

        Http::fake([
            'rpc.test*' => function (Request $request) {
                $method = $request->data()['method'] ?? '';

                return match ($method) {
                    'eth_blockNumber' => Http::response([
                        'jsonrpc' => '2.0', 'id' => 1, 'result' => '0x'.dechex($this->head),
                    ]),
                    'eth_getLogs' => Http::response([
                        'jsonrpc' => '2.0', 'id' => 1, 'result' => $this->logs,
                    ]),
                    default => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => null]),
                };
            },
        ]);
    }

    public function test_detects_deposit_and_credits_after_confirmations(): void
    {
        $user = $this->makeUserWithWallet();

        // Блок 1000: входящий transfer 100 USDT на адрес пользователя.
        $this->head = 1001;
        $this->logs = [$this->transferLog(blockNumber: 1001, usdtAmount: '100')];

        $result = app(DepositIndexerService::class)->scan();

        $this->assertSame(1, $result['detected']);
        $this->assertSame(0, $result['credited']);

        $deposit = Deposit::query()->firstOrFail();
        $this->assertSame('detected', $deposit->status);
        $this->assertSame($user->id, $deposit->user_id);
        $this->assertSame(0, bccomp('100', (string) $deposit->amount, 18));

        // Баланс ещё не зачислен — мало подтверждений.
        $this->assertSame(0, bccomp('0', app(LedgerService::class)->availableBalance($user->id, 'USDT'), 18));

        // Сеть ушла вперёд: 12+ подтверждений.
        $this->head = 1013;
        $this->logs = [];

        $result = app(DepositIndexerService::class)->scan();

        $this->assertSame(1, $result['credited']);

        $deposit->refresh();
        $this->assertSame('credited', $deposit->status);
        $this->assertGreaterThanOrEqual(12, $deposit->confirmations);

        $this->assertSame(0, bccomp('100', app(LedgerService::class)->availableBalance($user->id, 'USDT'), 18));
    }

    public function test_same_deposit_is_never_credited_twice(): void
    {
        $user = $this->makeUserWithWallet();

        $this->head = 1001;
        $this->logs = [$this->transferLog(blockNumber: 1001, usdtAmount: '50')];

        app(DepositIndexerService::class)->scan();

        // Тот же лог приходит снова (rescan) + сеть продвинулась.
        $this->head = 1013;
        app(DepositIndexerService::class)->scan();
        $this->logs = [];
        app(DepositIndexerService::class)->scan();

        $this->assertSame(1, Deposit::query()->count());
        $this->assertSame(0, bccomp('50', app(LedgerService::class)->availableBalance($user->id, 'USDT'), 18));
    }

    public function test_transfers_to_unknown_addresses_are_ignored(): void
    {
        $this->makeUserWithWallet();

        $this->head = 1001;
        $this->logs = [$this->transferLog(
            blockNumber: 1001,
            usdtAmount: '100',
            toAddress: '0x000000000000000000000000000000000000dEaD',
        )];

        $result = app(DepositIndexerService::class)->scan();

        $this->assertSame(0, $result['detected']);
        $this->assertSame(0, Deposit::query()->count());
    }

    public function test_zero_amount_transfers_are_ignored(): void
    {
        $this->makeUserWithWallet();

        $this->head = 1001;
        $this->logs = [$this->transferLog(blockNumber: 1001, usdtAmount: '0')];

        $result = app(DepositIndexerService::class)->scan();

        $this->assertSame(0, $result['detected']);
    }

    private function makeUserWithWallet(): User
    {
        $user = $this->createClient();

        WalletAddress::query()->create([
            'user_id' => $user->id,
            'network' => 'BEP20',
            'asset' => 'USDT',
            'address' => self::USER_ADDRESS,
            'derivation_index' => 0,
            'derivation_path' => "m/44'/60'/0'/0/0",
            'is_active' => true,
        ]);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function transferLog(int $blockNumber, string $usdtAmount, ?string $toAddress = null): array
    {
        $raw = bcmul($usdtAmount, bcpow('10', '18', 0), 0);
        $hex = gmp_strval(gmp_init($raw, 10), 16);

        return [
            'address' => strtolower(self::USDT_CONTRACT),
            'topics' => [
                self::TRANSFER_TOPIC,
                '0x'.str_pad('aaaa', 64, '0', STR_PAD_LEFT),
                '0x'.str_pad(strtolower(ltrim($toAddress ?? self::USER_ADDRESS, '0x')), 64, '0', STR_PAD_LEFT),
            ],
            'data' => '0x'.str_pad($hex === '0' ? '' : $hex, 64, '0', STR_PAD_LEFT),
            'blockNumber' => '0x'.dechex($blockNumber),
            'transactionHash' => self::TX_HASH,
            'logIndex' => '0x0',
        ];
    }
}
