# Crypto Exchange PWA

PWA крипто-обменник на Laravel 11 + Vue 3 (Inertia) с дизайном из [Stitch](https://stitch.withgoogle.com/projects/14310390296135004081).

## URL

- PWA: https://fin10117.ispiria.net/
- Admin: https://fin10117.ispiria.net/admin
- Admin login: https://fin10117.ispiria.net/login

## Учётные записи (seed)

| Роль | Email | Password |
|------|-------|----------|
| Суперадмин | admin@exchange.local | ChangeMeNow!2026 |
| СБ | security@exchange.local | ChangeMeNow!2026 |

Клиенты входят через `/auth/phone` + Telegram.

## Telegram Bot

```bash
# .env
TELEGRAM_BOT_TOKEN=your_token_from_botfather
TELEGRAM_BOT_USERNAME=YourExchangeBot
APP_URL=https://fin10117.ispiria.net

cd /var/www/crypto-exchange/backend
php artisan telegram:set-webhook
```

## SSL

Сейчас: self-signed (браузер покажет предупреждение).

Когда DNS укажет на сервер:
```bash
apt install certbot python3-certbot-nginx
certbot --nginx -d fin10117.ispiria.net
```

## Этап 2 — KYC Admin

- Клиент: `/kyc` — анкета + загрузка документов (лицевая, обратная, селфи)
- СБ: `/admin/kyc` — список заявок, одобрение/отклонение
- Telegram-уведомления при submit/approve/reject
- Audit logs для всех KYC действий

## API Auth

| Method | Path |
|--------|------|
| POST | `/api/auth/phone/start` |
| GET | `/api/auth/phone/status/{code}` |
| POST | `/api/auth/telegram/webhook` |
| POST | `/api/auth/telegram/complete/{code}` |

## Этап 3 — HD-wallet (custodial)

После `kyc_status = approved` job `CreateWalletAfterKycApproved` создаёт детерминированный адрес.

- Деривация: BIP39 seed + BIP32/BIP44, путь `m/44'/60'/0'/0/{index}` (coin type 60 = BSC/ETH)
- Реализация: `simplito/elliptic-php` (secp256k1) + `kornrunner/keccak` + EIP-55 checksum
- Корректность проверена эталонным вектором: `php artisan wallet:verify`
- `wallet_counters` — глобальный индекс с row-lock; `wallet_addresses` — выданные адреса
- Адрес показывается на `/wallet`, дублируется уведомлением в Telegram

### Безопасность seed

- Master mnemonic — в `.env` (`WALLET_MNEMONIC`), вне VCS, не пишется в логи
- Сгенерировать новый: `php artisan wallet:generate-mnemonic`
- Проверить деривацию: `php artisan wallet:verify`

### Queue worker

Job выполняется через systemd-сервис `crypto-queue` (`queue:work`, БД-очередь):
```bash
systemctl status crypto-queue
```

## Этап 4 — BEP20 indexer депозитов

- `BscRpcClient` — JSON-RPC к BSC (`eth_blockNumber`, `eth_getLogs`)
- `DepositIndexerService` — сканирует `Transfer` события USDT-контракта на адреса пользователей
- Статусы депозита: `detected` → `confirmed` → `credited`
- Зачисление через `LedgerService` (двойная запись) при достижении `BSC_CONFIRMATIONS` (12)
- Таблицы: `deposits`, `ledger_entries`, `balances`, `indexer_states`
- Баланс и история депозитов на `/wallet` (ссылки на BscScan)
- Telegram-уведомления: депозит найден / зачислен

### RPC (важно)

Публичный `bsc-dataseed.binance.org` лимитирует `eth_getLogs` (`-32005 limit exceeded`).
Для рабочего сканирования укажите keyed-провайдера в `.env`:
```
BSC_RPC_URL=https://rpc.ankr.com/bsc/<API_KEY>   # или QuickNode / NodeReal
php artisan config:clear && systemctl restart crypto-indexer
```

### Сервисы

```bash
systemctl status crypto-indexer   # deposits:watch (loop, interval 15s)
systemctl status crypto-queue     # очередь job'ов
php artisan deposits:scan         # один проход вручную
```

USDT BEP20 контракт: `0x55d398326f99059fF775485246999027B3197955` (18 decimals).

## Этап 5 — Sweep на hot wallet (gas worker + sweeper)

Собирает подтверждённые депозиты с пользовательских адресов на главный hot wallet.

- Системные кошельки выводятся из той же мнемоники на отдельных BIP44-аккаунтах
  (не пересекаются с пользовательскими `44'/60'/0'`):
  - Hot wallet: `44'/60'/1'/0/0`
  - Gas wallet: `44'/60'/2'/0/0`
- `EthereumTxService` — офлайн-сборка и подпись legacy/EIP-155 транзакций
  (`kornrunner/ethereum-offline-raw-tx`: RLP + secp256k1 + keccak), broadcast через `eth_sendRawTransaction`.
- `SweepService` — конечный автомат:
  `pending → waiting_gas → gas_sent → sweeping → swept` (`manual_review` / `failed` при проблемах).
  1. gas worker докидывает BNB на адрес депозита (`SWEEP_GAS_TOPUP_WEI`);
  2. sweeper отправляет BEP20 `transfer` на hot wallet;
  3. подтверждение по receipt (`status 0x1`).
- Таблица `sweeps`, audit-логи `sweep.gas_sent` / `sweep.broadcast` / `sweep.completed` / `sweep.manual_review`.
- Админка `/admin/sweeps` — мониторинг + кнопка «Повторить» для застрявших.
- Планировщик `crypto-scheduler` запускает `sweep:run` ежеминутно.

### Безопасность / запуск

Sweeper по умолчанию ВЫКЛЮЧЕН (`SWEEP_ENABLED=false`) — пока флаг false, транзакции не отправляются.

```bash
php artisan wallet:system            # показать адреса hot/gas
php artisan wallet:system --balances # + on-chain BNB/USDT (нужен keyed RPC)
```

Перед включением:
1. Пополнить **gas wallet** реальным BNB.
2. Протестировать на BSC testnet (`BSC_CHAIN_ID=97`, testnet RPC/контракт).
3. Указать keyed RPC (см. Этап 4) — публичный нода лимитирует вызовы.
4. `SWEEP_ENABLED=true` → `php artisan config:cache` → `systemctl restart crypto-scheduler`.

```bash
php artisan sweep:run                # один проход вручную
systemctl status crypto-scheduler    # schedule:work (sweep:run каждую минуту)
```

## Этап 6 — Покупка USDT за KZT

- Клиент: `/exchange` → «Купить» → сумма KZT (или желаемые USDT) → заявка → реквизиты обменника → загрузка скрина оплаты (приватный storage) → ждёт подтверждения.
- Админ: `/admin/orders` — просмотр proof-файла (`/admin/orders/{id}/proof`, только авторизованно), «Подтвердить оплату» (зачисление USDT через `LedgerService` с комиссией) или «Отклонить» (с причиной).
- Таблицы: `exchange_orders` (статусы `created → awaiting_kzt_payment → payment_proof_uploaded → pending_admin_confirmation → completed | cancelled | failed | dispute | manual_review`), `fiat_payment_requests` (направление `user_to_exchange`/`exchange_to_user`, статусы `pending/proof_uploaded/manual_review/confirmed/rejected/cancelled`).
- Реквизиты обменника: `.env` → `EXCHANGE_BANK_NAME`, `EXCHANGE_BANK_RECIPIENT`, `EXCHANGE_BANK_ACCOUNT`.
- Audit log + Telegram-уведомления на каждый переход статуса.

## Этап 7 — Продажа USDT за KZT

- Клиент: «Продать» → сумма USDT + свои банковские реквизиты → USDT блокируются (`balances.locked`, двойная запись `user_available → user_locked`).
- Админ: переводит KZT вручную → в заявке вводит банк/референс/комментарий → «KZT отправлены» → ledger списывает locked USDT (нетто → `external_fiat`, комиссия → `fee_revenue`), статус `completed`.
- Отмена (клиентом или админом) — разблокировка USDT.

## Этап 8 — Вывод USDT на внешний адрес

- Клиент: `/withdraw` — адрес (валидация EVM + EIP-55 checksum, `EvmAddressValidator`), сумма; показываются комиссия сервиса (feePercent), комиссия сети (`WITHDRAWAL_NETWORK_FEE_USDT`) и итог; итог блокируется на балансе.
- Telegram-подтверждение: бот шлёт inline-кнопки «Подтвердить»/«Отменить» (`callback_data` вида `wd:c|x:{id}:{token}`, обработка в `TelegramWebhookController::handleCallbackQuery`). Без подтверждения заявка не идёт дальше (TTL 30 мин).
- Risk-check: сумма > `WITHDRAWAL_AUTO_LIMIT` (500 USDT) → статус `pending_review` + запись в `manual_approvals` → ручной апрув СБ в `/admin/withdrawals`.
- Отправка: `php artisan withdrawals:process` (планировщик, ежеминутно) — BEP20 `transfer` с hot wallet (`44'/60'/1'/0/0`) через `EthereumTxService`, подтверждение по receipt, settle через ledger.
- Статусы: `created → awaiting_telegram_confirmation → pending_review|approved → sending → sent → completed` (+ `cancelled/failed/rejected`).
- **Kill-switch: `WITHDRAWALS_ENABLED=false` (по умолчанию)** — заявки доходят до `approved` и ждут; реальный broadcast только при `true`.

## Живой курс USDT/KZT

- `RateService`: Binance (`/api/v3/ticker/price?symbol=USDTKZT`) + fallback CoinGecko (tether→KZT), кэш `RATE_CACHE_TTL` (120 c), последний удачный курс хранится вечно (при недоступности API показывается со временем обновления и пометкой stale).
- Наценка обменника: `RATE_MARKUP_BUY` / `RATE_MARKUP_SELL` (в %, по умолчанию 1.0). buy-курс выше базового, sell — ниже.
- Резерв при полном отсутствии данных: `RATE_FALLBACK`.

## Подписка (комиссия 0.05%)

- Таблица `subscriptions` (`user_id, status, starts_at, expires_at, granted_by`). `User::feePercent()` учитывает активную подписку (и legacy-флаг `has_subscription`).
- Суперадмин выдаёт/продлевает вручную: `/admin/subscriptions` (поиск клиента, число месяцев). Продление активной подписки добавляет месяцы к текущему сроку.
- `php artisan subscriptions:expire` (планировщик, ежечасно) переводит просроченные в `expired`. Онлайн-оплаты нет (намеренно).

## KYC-провайдер (manual | Sumsub)

- `KYC_PROVIDER=manual` (по умолчанию) — ручная проверка СБ, как раньше.
- `KYC_PROVIDER=sumsub` — страница `/kyc` встраивает Sumsub WebSDK; `SumsubService` создаёт applicant (HMAC-подпись запросов), выдаёт access token (`POST /kyc/sumsub/token`), webhook `POST /api/kyc/sumsub/webhook` (подпись `X-Payload-Digest` по `SUMSUB_WEBHOOK_SECRET`) маппит `applicantReviewed` GREEN/RED → `approved`/`rejected` и запускает `CreateWalletAfterKycApproved`.
- `.env`: `SUMSUB_APP_TOKEN`, `SUMSUB_SECRET_KEY`, `SUMSUB_LEVEL_NAME` (по умолчанию `basic-kyc-level`), `SUMSUB_WEBHOOK_SECRET`. Пока ключи пустые — автоматически работает ручная анкета.
- Инструкция для владельца: `/var/www/crypto-exchange/INSTRUKCIYA-KYC.md`.

## SaaS-фундамент (этап 10, частично)

- Таблица `tenants` + дефолтный tenant; `tenant_id` у заявок/платежей/выводов.
- Роль `exchange_admin` видит в `/admin/orders` только заявки своего tenant. Полноценный SaaS (свои курсы/реквизиты/домены) — не реализован.

## Следующие шаги

- Перед включением выводов: пополнить hot wallet (USDT + BNB на газ), включить `WITHDRAWALS_ENABLED=true` → `php artisan config:cache` → `systemctl restart crypto-scheduler`.
- Вписать реальные банковские реквизиты обменника в `.env`.
- Полноценный SaaS (этап 10) и онлайн-оплата подписки — не реализованы.
