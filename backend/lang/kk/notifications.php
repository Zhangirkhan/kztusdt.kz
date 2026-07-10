<?php

declare(strict_types=1);

return [
    'kyc_submitted' => "📋 KYC тексеруге жіберілді.\n\nҚауіпсіздік қызметі шешім қабылдағаннан кейін хабарлаймыз.",
    'kyc_approved' => "✅ KYC мақұлданды!\n\nUSDT әмияныңыз жақын арада жасалады.",
    'kyc_manual_approved' => "✅ KYC қолмен мақұлданды.\n\nUSDT әмияныңыз жақын арада жасалады.",
    'kyc_rejected' => "❌ KYC қабылданбады.\n\nСебебі: :reason\n\nДеректерді түзетіп, қайта жіберіңіз.",
    'kyc_reset' => "🔄 KYC тексеруі қайта басталды.\n\n/kyc бетінде тексеруден қайта өтіңіз.",
    'kyc_sumsub_approved' => "✅ KYC өтті (Sumsub)!\n\nUSDT әмияныңыз жақын арада жасалады.",
    'kyc_sumsub_rejected' => "❌ KYC қабылданбады (Sumsub).\n\nСебебі: :reason:retry",
    'kyc_sumsub_retry' => "\n\nҚұжаттарды түзетіп, /kyc бетінде тексеруден қайта өте аласыз.",
    'kyc_aitu_approved' => "✅ Тексеру өтті (Aitu Passport)!\n\nUSDT әмияныңыз жақын арада жасалады.",
    'kyc_aitu_rejected' => "❌ Aitu Passport тексеруі өтпеді.\n\n/kyc бетінде тексеруден қайта өтіп көріңіз.",

    'wallets_created' => "💼 Әмияндарыңыз жасалды!\n\nТолтыру мекенжайлары:\n\n:lines",

    'deposit_detected' => "🔎 Депозит анықталды: :amount :asset :network.\nЖелі растауларын күтеміз.",
    'deposit_credited' => "✅ Депозит есепке алынды: :amount :asset (:network).\nTx: <code>:tx</code>",

    'subscription_granted' => "⭐ «:plan» жазылымы :date күніне дейін қосылды.\n\nАйырбастау комиссиясы: :fee.",

    'order_buy_created' => "🟢 Сатып алу өтінімі №:id жасалды.\n\nСома: :fiat ₸ → :usdt USDT\nКурс: :rate ₸/USDT\n\nӨтінім бетіндегі реквизиттер бойынша KZT аударып, төлем скриншотын жүктеңіз.",
    'order_sell_created' => "🟢 Сату өтінімі №:id жасалды.\n\n:usdt USDT бұғатталды → :fiat ₸\nКурс: :rate ₸/USDT\n\nӘкімші KZT қаражатын сіздің реквизиттеріңізге аударып, өтінімді растайды.",
    'order_proof_uploaded' => "📎 №:id өтінімі бойынша төлем скриншоты алынды.\n\nӘкімші растауын күтіңіз.",
    'order_buy_completed' => "✅ №:id өтінімі орындалды!\n\nKZT төлемі расталды, балансыңызға :usdt USDT есептелді.",
    'order_sell_kzt_sent' => "💸 №:id өтінімі бойынша KZT жіберілді: :fiat ₸.\n\nТүскенін тексеріп, өтінім бетінде алуды растаңыз.",
    'order_sell_completed' => "✅ №:id өтінімі орындалды!\n\nKZT алуы расталды: :fiat ₸.",
    'order_rejected' => "❌ №:id өтінімі қабылданбады.\n\nСебебі: :reason",
    'order_cancelled' => "🚫 №:id өтінімі жойылды.",

    'withdrawal_created' => "📋 Шығару өтінімі №:id жасалды.\n\nСома: :amount USDT\nСервис комиссиясы: :fee USDT\nЖелі комиссиясы: :network_fee USDT\nЖалпы есептен шығару: :total USDT\n\nМекенжай (:network):\n<code>:address</code>\n\nӨтінім қауіпсіздік қызметіне тексеруге жіберілді.",
    'withdrawal_approved' => "✅ №:id шығару өтінімі қауіпсіздік қызметімен мақұлданып, жіберу кезегіне қойылды.",
    'withdrawal_retry' => "🔁 №:id шығару өтінімі қайтадан жіберу кезегіне қойылды.",
    'withdrawal_rejected' => "❌ №:id шығару өтінімі қабылданбады.\n\nСебебі: :reason\nҚаражат бұғаттан шығарылды.",
    'withdrawal_cancelled' => "🚫 №:id шығару өтінімі жойылды. Қаражат бұғаттан шығарылды.",
    'withdrawal_sent' => "📤 №:id шығару өтінімі желіге жіберілді.\n\nTx: <code>:tx</code>\nБлокчейн растауын күтеміз.",
    'withdrawal_completed' => "✅ №:id шығару өтінімі орындалды!\n\n:amount :asset мына мекенжайға жіберілді:\n<code>:address</code>\n\n:explorer",
    'withdrawal_interrupted' => "⚠️ №:id шығару өтінімі: жіберу үзілді, желідегі статусын тексеріп жатырмыз. Мәселе анықталғанша қаражат бұғатта қалады.",
];
