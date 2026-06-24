<?php

declare(strict_types=1);

return [
    // Master kill-switch. While FALSE, approved withdrawals are queued but never broadcast.
    'enabled' => (bool) env('WITHDRAWALS_ENABLED', false),

    // Legacy setting — all withdrawals require manual SB approval (0 = no auto-approve).
    'auto_limit' => (float) env('WITHDRAWAL_AUTO_LIMIT', 0),

    'min_amount' => (float) env('WITHDRAWAL_MIN_AMOUNT', 1),

    // Flat network fee charged to the user in USDT (covers BNB gas paid by the hot wallet).
    'network_fee_usdt' => env('WITHDRAWAL_NETWORK_FEE_USDT', '0.01'),

    // How long the Telegram confirmation button stays valid.
    'confirmation_ttl_minutes' => (int) env('WITHDRAWAL_CONFIRMATION_TTL', 30),

    'max_attempts' => (int) env('WITHDRAWAL_MAX_ATTEMPTS', 3),

    // Grace period before a row stuck in "sending" with no tx hash (process died
    // mid-broadcast) is flagged for manual on-chain reconciliation.
    'sending_grace_seconds' => (int) env('WITHDRAWAL_SENDING_GRACE_SECONDS', 180),
];
