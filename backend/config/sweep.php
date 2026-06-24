<?php

declare(strict_types=1);

return [
    // Master kill-switch. Keep FALSE until tested on BSC testnet with a funded gas wallet.
    'enabled' => (bool) env('SWEEP_ENABLED', false),

    'chain_id' => (int) env('BSC_CHAIN_ID', 56),

    // System wallets derived from the same mnemonic on dedicated BIP44 accounts,
    // so they never collide with user deposit addresses (account 0').
    'hot_wallet_path' => env('SWEEP_HOT_WALLET_PATH', "44'/60'/1'/0/0"),
    'gas_wallet_path' => env('SWEEP_GAS_WALLET_PATH', "44'/60'/2'/0/0"),

    // Gas params for a BEP20 transfer.
    'transfer_gas_limit' => (int) env('SWEEP_TRANSFER_GAS_LIMIT', 100000),
    'bnb_transfer_gas_limit' => 21000,

    // How much BNB (in wei) to top up a deposit address that lacks gas.
    // 0.0008 BNB default — enough for one BEP20 transfer.
    'gas_topup_wei' => env('SWEEP_GAS_TOPUP_WEI', '800000000000000'),

    // Minimum USDT (human units) worth sweeping (avoid dust costing more in gas).
    'min_sweep_amount' => env('SWEEP_MIN_AMOUNT', '1'),

    'max_attempts' => (int) env('SWEEP_MAX_ATTEMPTS', 5),

    // ERC20 selectors.
    'erc20_transfer_selector' => 'a9059cbb',
    'erc20_balanceof_selector' => '70a08231',
];
