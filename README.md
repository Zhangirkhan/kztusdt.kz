# kztusdt.kz — Crypto Exchange PWA

PWA крипто-обменник **KZT ↔ USDT** на Laravel 13 + Vue 3 (Inertia). Клиенты работают через мобильное приложение, персонал — через веб-админку.

**Репозиторий:** https://github.com/Zhangirkhan/kztusdt.kz

## Возможности

| Область | Что реализовано |
|---------|-----------------|
| **Клиенты** | Вход по телефону (+ OTP через Telegram Gateway), WebAuthn (Face ID / отпечаток), PWA, ru/kk/en |
| **KYC** | `manual` · `sumsub` · **`aitu`** (вердикт pass/fail из Aitu Passport `id_token`) |
| **OAuth** | [Aitu Passport](https://passport.aitu.io) — вход, logout webhook, client validation, подпись ИИН |
| **Сети** | **BEP20** (BSC) и **TRC20** (TRON) — депозиты, sweep, выводы |
| **Обмен** | Покупка / продажа USDT за KZT с ручным подтверждением оплаты админом |
| **Кошелёк** | HD-деривация (BIP39/BIP44), двойная запись ledger, hot/gas wallet |
| **Выводы** | Ручной апрув СБ → безопасная state machine → per-network broadcasters |
| **Админка** | KYC, ордера, выводы, sweeps, кошельки, подписки и тарифы |

## URL (production)

| Сервис | Адрес |
|--------|-------|
| PWA | https://fin10117.ispiria.net/ |
| Админка | https://fin10117.ispiria.net/admin |
| Вход staff | https://fin10117.ispiria.net/login |

## Стек

- **Backend:** PHP 8.3+, Laravel 13, PostgreSQL (или SQLite для dev), Redis (рекомендуется в prod)
- **Frontend:** Vue 3, Inertia.js, Tailwind CSS, Vite, Service Worker (PWA)
- **Блокчейн:** BSC (USDT BEP-20), TRON (USDT TRC-20), `simplito/elliptic-php`, offline tx signing
- **Тесты:** 214 feature/unit тестов (`php artisan test`)

## Быстрый старт

```bash
git clone https://github.com/Zhangirkhan/kztusdt.kz.git
cd kztusdt.kz/backend

cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed

npm install
npm run build
```

Локальная разработка (сервер + очередь + Vite):

```bash
composer dev
```

Подробная документация для разработчиков: [`backend/README.md`](backend/README.md).

## Учётные записи (после seed)

| Роль | Email | Пароль |
|------|-------|--------|
| Суперадмин | `admin@exchange.local` | `ChangeMeNow!2026` |
| Служба безопасности | `security@exchange.local` | `ChangeMeNow!2026` |

Клиенты входят через `/auth/phone`. **Смените пароли staff сразу после деплоя.**

## Архитектура денежного контура

```
Депозит (BEP20/TRC20)
  indexer (deposits:scan / deposits:scan-tron)
    → detected → confirmed → credited (идемпотентно, lockForUpdate)

Sweep (опционально)
  sweep:run / TRON_SWEEP_ENABLED
    → gas top-up → token transfer → hot wallet

Обмен KZT ↔ USDT
  lock / credit через LedgerService (bcmath, decimal:18)

Вывод USDT
  pending_review → approved → sending → sent → completed
  (+ needs_reconcile при прерванном broadcast, failed, rejected, cancelled)
```

**Выводы** обрабатывает `WithdrawalService` (оркестрация) + `EvmWithdrawalBroadcaster` / `TronWithdrawalBroadcaster` (сетевая логика). После claim строки в `sending` повторная авто-отправка **запрещена** — только ручная сверка по блокчейну.

## Сети USDT

| Сеть | Индексатор | Sweep | Контракт USDT |
|------|------------|-------|---------------|
| BEP20 | `deposits:scan` | `SWEEP_ENABLED` | `0x55d398326f99059fF775485246999027B3197955` (18 dec) |
| TRC20 | `deposits:scan-tron` | `TRON_SWEEP_ENABLED` | `TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t` (6 dec) |

Включение сетей: `WALLET_NETWORKS=BEP20,TRC20` в `.env`.

### RPC и API-ключи

Публичный BSC RPC лимитирует `eth_getLogs`. Для production укажите keyed-провайдера:

```env
BSC_RPC_URL=https://rpc.ankr.com/bsc/<API_KEY>
TRON_API_KEY=<TronGrid API key>
```

Инструкция: [`INSTRUKCIYA-RPC.md`](INSTRUKCIYA-RPC.md).

## KYC-провайдеры

```env
KYC_PROVIDER=manual   # ручная проверка СБ (по умолчанию)
KYC_PROVIDER=sumsub   # Sumsub WebSDK + webhook
KYC_PROVIDER=aitu     # Aitu Passport — вердикт в id_token claims
```

| Провайдер | Документация |
|-----------|--------------|
| Manual / Sumsub | [`INSTRUKCIYA-KYC.md`](INSTRUKCIYA-KYC.md) |
| Aitu Passport OAuth | [`INSTRUKCIYA-AITU-PASSPORT.md`](INSTRUKCIYA-AITU-PASSPORT.md) |
| Aitu KYC | [`INSTRUKCIYA-KYC-AITU.md`](INSTRUKCIYA-KYC-AITU.md) |

Для Aitu KYC задайте scope верификации в консоли партнёра, например: `openid phone CONFIDENCE_LEVEL`.

## Ключевые переменные `.env`

Скопируйте `backend/.env.example` → `backend/.env`. Минимум для production:

```env
APP_URL=https://your-domain.kz
APP_DEBUG=false

WALLET_MNEMONIC=...          # BIP-39, НИКОГДА не коммитить
WALLET_NETWORKS=BEP20,TRC20

BSC_RPC_URL=...              # keyed RPC
TRON_API_KEY=...

SWEEP_ENABLED=false          # включить после пополнения gas wallet
TRON_SWEEP_ENABLED=false
WITHDRAWALS_ENABLED=false    # kill-switch выводов

KYC_PROVIDER=aitu
AITU_CLIENT_ID=...
AITU_CLIENT_SECRET=...
AITU_ID_TOKEN_PUBLIC_KEY_PATH=...   # или JWKS URI
AITU_LOGOUT_WEBHOOK_SECRET=...
AITU_CLIENT_VALIDATION_SECRET=...

TELEGRAM_BOT_TOKEN=...
TELEGRAM_GATEWAY_TOKEN=...   # OTP-коды входа
```

## Планировщик (cron)

```cron
* * * * * cd /path/to/kztusdt.kz/backend && php artisan schedule:run >> /dev/null 2>&1
```

В расписании (`routes/console.php`):

| Команда | Интервал | Условие |
|---------|----------|---------|
| `deposits:scan` | каждую минуту | BEP20 включён |
| `deposits:scan-tron` | каждую минуту | TRC20 включён |
| `sweep:run` | каждую минуту | `SWEEP_ENABLED=true` |
| `withdrawals:process` | каждую минуту | `WITHDRAWALS_ENABLED=true` |
| `rates:refresh` | каждую минуту | всегда |
| `subscriptions:expire` | ежечасно | всегда |

Альтернатива для BEP20: long-running `php artisan deposits:watch --interval=15`.

## Artisan-команды

```bash
php artisan wallet:generate-mnemonic   # новая мнемоника (только dev)
php artisan wallet:verify              # проверка деривации адресов
php artisan wallet:system --balances   # hot/gas + on-chain балансы
php artisan aitu:generate-iin-keys       # RSA для iin_signature
php artisan telegram:set-webhook
php artisan deposits:scan                # один проход BEP20
php artisan deposits:scan-tron           # один проход TRC20
php artisan sweep:run
php artisan withdrawals:process
php artisan rates:refresh
php artisan test                         # 214 тестов
```

## Безопасность (реализовано)

- Идемпотентное зачисление депозитов (`lockForUpdate` + повторная проверка статуса)
- Безопасная state machine выводов с `needs_reconcile` (защита от double-send)
- Атомарный счётчик попыток OTP
- Валидация Aitu `id_token` (RS256, exp, iss, aud)
- Fail-closed webhooks Aitu в production без секретов
- `RateService::quoteForOrder()` — ордера не ценятся по fallback-курсу
- Структурированное логирование через `AppLog`
- `.env`, ключи и `vendor/` исключены из git

## Структура репозитория

```
kztusdt.kz/
├── backend/                 # Laravel-приложение (основной код)
│   ├── app/Services/        # бизнес-логика
│   │   └── Withdrawals/     # EVM/TRON broadcasters
│   ├── resources/js/        # Vue / Inertia PWA
│   ├── tests/               # PHPUnit (214 тестов)
│   └── .env.example
├── INSTRUKCIYA-*.md         # инструкции по интеграциям
├── scripts/                 # вспомогательные скрипты
└── stitch-design/           # HTML-макеты дизайна
```

## Production checklist

- [ ] `APP_DEBUG=false`, HTTPS, корректный `APP_URL`
- [ ] Redis для сессий и кэша (WebAuthn, курсы)
- [ ] Keyed BSC RPC + `TRON_API_KEY`
- [ ] Gas wallet пополнен (BNB / TRX) перед включением sweep
- [ ] Hot wallet пополнен USDT перед включением выводов
- [ ] `SWEEP_ENABLED` / `TRON_SWEEP_ENABLED` / `WITHDRAWALS_ENABLED` — осознанно
- [ ] Секреты Aitu, Telegram, Sumsub заданы
- [ ] `php artisan config:cache route:cache view:cache`
- [ ] Cron `schedule:run` + `npm run build` после изменений frontend
- [ ] Пароли staff изменены, мнемоника только на сервере

## Лицензия

MIT (Laravel framework). Проприетарный код проекта — по согласованию с правообладателем.
