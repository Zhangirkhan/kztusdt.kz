<?php

declare(strict_types=1);

return [
    'kyc_submitted' => "📋 KYC has been submitted for review.\n\nWe will notify you after the security team makes a decision.",
    'kyc_approved' => "✅ KYC approved!\n\nYour USDT wallet will be created shortly.",
    'kyc_manual_approved' => "✅ KYC approved manually.\n\nYour USDT wallet will be created shortly.",
    'kyc_rejected' => "❌ KYC rejected.\n\nReason: :reason\n\nPlease correct the data and submit again.",
    'kyc_reset' => "🔄 KYC verification has been reset.\n\nPlease complete verification again on the /kyc page.",
    'kyc_sumsub_approved' => "✅ KYC passed (Sumsub)!\n\nYour USDT wallet will be created shortly.",
    'kyc_sumsub_rejected' => "❌ KYC rejected (Sumsub).\n\nReason: :reason:retry",
    'kyc_sumsub_retry' => "\n\nYou can fix the documents and complete verification again on the /kyc page.",
    'kyc_aitu_approved' => "✅ Verification passed (Aitu Passport)!\n\nYour USDT wallet will be created shortly.",
    'kyc_aitu_rejected' => "❌ Aitu Passport verification failed.\n\nPlease try verification again on the /kyc page.",

    'wallets_created' => "💼 Your wallets have been created!\n\nDeposit addresses:\n\n:lines",

    'deposit_detected' => "🔎 Deposit detected: :amount :asset :network.\nWaiting for network confirmations.",
    'deposit_credited' => "✅ Deposit credited: :amount :asset (:network).\nTx: <code>:tx</code>",

    'subscription_granted' => "⭐ Subscription “:plan” is active until :date.\n\nExchange fee: :fee.",

    'order_buy_created' => "🟢 Buy order #:id created.\n\nAmount: :fiat ₸ → :usdt USDT\nRate: :rate ₸/USDT\n\nTransfer KZT using the requisites on the order page and upload the payment screenshot.",
    'order_sell_created' => "🟢 Sell order #:id created.\n\n:usdt USDT locked → :fiat ₸\nRate: :rate ₸/USDT\n\nAn administrator will transfer KZT to your details and confirm the order.",
    'order_proof_uploaded' => "📎 Payment screenshot for order #:id received.\n\nPlease wait for administrator confirmation.",
    'order_buy_completed' => "✅ Order #:id completed!\n\nKZT payment confirmed, :usdt USDT has been credited to your balance.",
    'order_sell_kzt_sent' => "💸 KZT sent for order #:id: :fiat ₸.\n\nCheck your account and confirm receipt on the order page.",
    'order_sell_completed' => "✅ Order #:id completed!\n\nKZT receipt confirmed: :fiat ₸.",
    'order_rejected' => "❌ Order #:id rejected.\n\nReason: :reason",
    'order_cancelled' => "🚫 Order #:id cancelled.",

    'withdrawal_created' => "📋 Withdrawal request #:id created.\n\nAmount: :amount USDT\nService fee: :fee USDT\nNetwork fee: :network_fee USDT\nTotal debit: :total USDT\n\nAddress (:network):\n<code>:address</code>\n\nThe request has been sent to the security team for review.",
    'withdrawal_approved' => "✅ Withdrawal #:id approved by security and queued for sending.",
    'withdrawal_retry' => "🔁 Withdrawal #:id has been queued for sending again.",
    'withdrawal_rejected' => "❌ Withdrawal #:id rejected.\n\nReason: :reason\nFunds have been unlocked.",
    'withdrawal_cancelled' => "🚫 Withdrawal #:id cancelled. Funds have been unlocked.",
    'withdrawal_sent' => "📤 Withdrawal #:id sent to the network.\n\nTx: <code>:tx</code>\nWaiting for blockchain confirmation.",
    'withdrawal_completed' => "✅ Withdrawal #:id completed!\n\n:amount :asset sent to\n<code>:address</code>\n\n:explorer",
    'withdrawal_interrupted' => "⚠️ Withdrawal #:id: sending was interrupted, we are checking the network status. Funds remain locked until resolved.",
];
