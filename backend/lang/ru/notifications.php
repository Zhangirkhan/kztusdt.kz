<?php

declare(strict_types=1);

return [
    'kyc_submitted' => "📋 KYC отправлен на проверку.\n\nМы уведомим вас после решения СБ.",
    'kyc_approved' => "✅ KYC одобрен!\n\nСкоро будет создан ваш USDT кошелёк.",
    'kyc_manual_approved' => "✅ KYC одобрен вручную.\n\nСкоро будет создан ваш USDT кошелёк.",
    'kyc_rejected' => "❌ KYC отклонён.\n\nПричина: :reason\n\nИсправьте данные и отправьте снова.",
    'kyc_reset' => "🔄 Верификация KYC сброшена.\n\nПройдите проверку заново на странице /kyc.",
    'kyc_sumsub_approved' => "✅ KYC пройден (Sumsub)!\n\nСкоро будет создан ваш USDT кошелёк.",
    'kyc_sumsub_rejected' => "❌ KYC отклонён (Sumsub).\n\nПричина: :reason:retry",
    'kyc_sumsub_retry' => "\n\nВы можете исправить документы и пройти проверку снова на странице /kyc.",
    'kyc_aitu_approved' => "✅ Верификация пройдена (Aitu Passport)!\n\nСкоро будет создан ваш USDT кошелёк.",
    'kyc_aitu_rejected' => "❌ Верификация Aitu Passport не пройдена.\n\nПопробуйте пройти проверку повторно на странице /kyc.",

    'wallets_created' => "💼 Ваши кошельки созданы!\n\nАдреса для пополнения:\n\n:lines",

    'deposit_detected' => "🔎 Обнаружен депозит :amount :asset :network.\nОжидаем подтверждений сети.",
    'deposit_credited' => "✅ Депозит зачислен: :amount :asset (:network).\nTx: <code>:tx</code>",

    'subscription_granted' => "⭐ Вам активирована подписка «:plan» до :date.\n\nКомиссия обмена: :fee.",

    'order_buy_created' => "🟢 Заявка №:id на покупку создана.\n\nСумма: :fiat ₸ → :usdt USDT\nКурс: :rate ₸/USDT\n\nПереведите KZT по реквизитам на странице заявки и загрузите скриншот оплаты.",
    'order_sell_created' => "🟢 Заявка №:id на продажу создана.\n\n:usdt USDT заблокировано → :fiat ₸\nКурс: :rate ₸/USDT\n\nАдминистратор переведёт KZT на ваши реквизиты и подтвердит заявку.",
    'order_proof_uploaded' => "📎 Скрин оплаты по заявке №:id получен.\n\nОжидайте подтверждения администратором.",
    'order_buy_completed' => "✅ Заявка №:id выполнена!\n\nОплата KZT подтверждена, :usdt USDT зачислено на ваш баланс.",
    'order_sell_kzt_sent' => "💸 По заявке №:id отправлены KZT: :fiat ₸.\n\nПроверьте поступление и подтвердите получение на странице заявки.",
    'order_sell_completed' => "✅ Заявка №:id выполнена!\n\nПолучение KZT подтверждено: :fiat ₸.",
    'order_rejected' => "❌ Заявка №:id отклонена.\n\nПричина: :reason",
    'order_cancelled' => "🚫 Заявка №:id отменена.",

    'withdrawal_created' => "📋 Заявка на вывод №:id создана.\n\nСумма: :amount USDT\nКомиссия сервиса: :fee USDT\nКомиссия сети: :network_fee USDT\nИтого к списанию: :total USDT\n\nАдрес (:network):\n<code>:address</code>\n\nЗаявка передана на проверку службе безопасности.",
    'withdrawal_approved' => "✅ Вывод №:id одобрен службой безопасности и поставлен в очередь на отправку.",
    'withdrawal_retry' => "🔁 Вывод №:id снова поставлен в очередь на отправку.",
    'withdrawal_rejected' => "❌ Вывод №:id отклонён.\n\nПричина: :reason\nСредства разблокированы.",
    'withdrawal_cancelled' => "🚫 Вывод №:id отменён. Средства разблокированы.",
    'withdrawal_sent' => "📤 Вывод №:id отправлен в сеть.\n\nTx: <code>:tx</code>\nОжидаем подтверждения блокчейна.",
    'withdrawal_completed' => "✅ Вывод №:id выполнен!\n\n:amount :asset отправлено на\n<code>:address</code>\n\n:explorer",
    'withdrawal_interrupted' => "⚠️ Вывод №:id: отправка прервана, проверяем статус в сети. Средства остаются заблокированными до выяснения.",
];
