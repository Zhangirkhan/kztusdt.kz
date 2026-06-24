<?php

declare(strict_types=1);

return [
    // TronGrid HTTP API base URL (mainnet by default; use the Nile/Shasta testnet
    // endpoint while testing). No trailing slash.
    'api_url' => rtrim((string) env('TRON_API_URL', 'https://api.trongrid.io'), '/'),

    // Optional TronGrid API key (sent as TRON-PRO-API-KEY header) to raise rate limits.
    'api_key' => env('TRON_API_KEY'),

    // USDT on TRON (TRC20) — 6 decimals. Mainnet contract by default.
    'usdt_contract' => env('TRON_USDT_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
    'usdt_decimals' => (int) env('TRON_USDT_DECIMALS', 6),

    // Confirmations (solidified blocks) required before crediting a deposit.
    'confirmations' => (int) env('TRON_CONFIRMATIONS', 19),

    // How many TRC20 transfer events to pull per address polling pass.
    'scan_limit' => (int) env('TRON_SCAN_LIMIT', 50),

    // Fee limit (in SUN, 1 TRX = 1_000_000 SUN) for a TRC20 transfer broadcast.
    'fee_limit' => (int) env('TRON_FEE_LIMIT', 30_000_000),

    // System wallets derived from the same mnemonic on dedicated BIP44 accounts
    // (coin type 195) so they never collide with user deposit addresses (account 0').
    'hot_wallet_path' => env('TRON_HOT_WALLET_PATH', "44'/195'/1'/0/0"),
    'gas_wallet_path' => env('TRON_GAS_WALLET_PATH', "44'/195'/2'/0/0"),

    // Master kill-switch for sweeping TRC20 deposits into the hot wallet.
    'sweep_enabled' => (bool) env('TRON_SWEEP_ENABLED', false),

    // Minimum USDT (human units) worth sweeping (dust below this is ignored).
    'min_sweep_amount' => env('TRON_SWEEP_MIN_AMOUNT', '1'),

    // TRX (in SUN) to top up a deposit address that lacks funds to pay for the
    // TRC20 transfer (energy/bandwidth burned as TRX). 30 TRX default.
    'gas_topup_sun' => env('TRON_GAS_TOPUP_SUN', '30000000'),

    'max_attempts' => (int) env('TRON_SWEEP_MAX_ATTEMPTS', 5),
];
