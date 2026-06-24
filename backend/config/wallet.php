<?php

declare(strict_types=1);

return [
    // BIP39 mnemonic for the custodial HD wallet master seed.
    // NEVER commit a real mnemonic. Set WALLET_MNEMONIC in .env (out of VCS).
    'mnemonic' => env('WALLET_MNEMONIC'),

    // Optional BIP39 passphrase (25th word).
    'passphrase' => env('WALLET_PASSPHRASE', ''),

    // BIP44 base path for BNB Smart Chain (BEP20) — coin type 60 (Ethereum).
    // Full path: m/44'/60'/0'/0/{index}
    'base_path' => env('WALLET_BASE_PATH', "44'/60'/0'/0"),

    'network' => env('WALLET_NETWORK', 'BEP20'),
    'asset' => env('WALLET_ASSET', 'USDT'),
];
