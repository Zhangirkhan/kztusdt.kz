<?php

declare(strict_types=1);

/**
 * Central registry of supported blockchain networks.
 *
 * Each network shares the same custodial BIP39 master seed (WALLET_MNEMONIC) but
 * uses its own BIP44 coin type / account branch, address encoding and on-chain
 * driver. The DB layer is already multi-network (network column + per-network
 * wallet_counters), so adding a network here wires it into HD wallet creation,
 * deposit indexing and the wallet UI.
 *
 * Supported address formats:
 *   - "evm"  : secp256k1 -> keccak256(pubkey)[-20:] -> EIP-55 0x address (BEP20)
 *   - "tron" : secp256k1 -> keccak256(pubkey)[-20:] -> base58check(0x41 || hash) (TRC20)
 */
return [
    // Comma-separated list of enabled network codes. Order matters for the UI.
    'enabled' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('WALLET_NETWORKS', 'BEP20,TRC20')),
    ))),

    'networks' => [
        'BEP20' => [
            'code' => 'BEP20',
            'label' => 'BNB Smart Chain (BEP20)',
            'asset' => 'USDT',
            'address_format' => 'evm',
            // BIP44 coin type 60 (Ethereum) — full path m/44'/60'/0'/0/{index}.
            'base_path' => env('WALLET_BASE_PATH', "44'/60'/0'/0"),
            'decimals' => (int) env('BSC_USDT_DECIMALS', 18),
            'confirmations' => (int) env('BSC_CONFIRMATIONS', 12),
            'native_asset' => 'BNB',
            'explorer_tx' => env('BSC_EXPLORER_TX', 'https://bscscan.com/tx/'),
            'explorer_address' => env('BSC_EXPLORER_ADDRESS', 'https://bscscan.com/address/'),
        ],

        'TRC20' => [
            'code' => 'TRC20',
            'label' => 'TRON (TRC20)',
            'asset' => 'USDT',
            'address_format' => 'tron',
            // BIP44 coin type 195 (TRON) — full path m/44'/195'/0'/0/{index}.
            'base_path' => env('TRON_BASE_PATH', "44'/195'/0'/0"),
            'decimals' => (int) env('TRON_USDT_DECIMALS', 6),
            'confirmations' => (int) env('TRON_CONFIRMATIONS', 19),
            'native_asset' => 'TRX',
            'explorer_tx' => env('TRON_EXPLORER_TX', 'https://tronscan.org/#/transaction/'),
            'explorer_address' => env('TRON_EXPLORER_ADDRESS', 'https://tronscan.org/#/address/'),
        ],
    ],
];
