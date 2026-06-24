<?php

declare(strict_types=1);

return [
    'rpc_url' => env('BSC_RPC_URL', 'https://bsc-dataseed.binance.org'),

    // USDT on BNB Smart Chain (BEP20) — 18 decimals (NOT 6 like on Ethereum).
    'usdt_contract' => env('BSC_USDT_CONTRACT', '0x55d398326f99059fF775485246999027B3197955'),
    'usdt_decimals' => (int) env('BSC_USDT_DECIMALS', 18),

    // Confirmations required before crediting a deposit.
    'confirmations' => (int) env('BSC_CONFIRMATIONS', 12),

    // Max block span per eth_getLogs call (public nodes limit the range).
    'scan_batch' => (int) env('BSC_SCAN_BATCH', 1000),

    // First block to scan from. 0 = start from current head on first run.
    'start_block' => (int) env('BSC_START_BLOCK', 0),

    // ERC20/BEP20 Transfer(address,address,uint256) event topic.
    'transfer_topic' => '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
];
