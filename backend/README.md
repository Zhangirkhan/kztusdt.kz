# kztusdt.kz — Crypto Exchange

> Внутренняя документация для разработчиков. Файл лежит вне `public/` и не предназначен для скачивания пользователями через сайт.

Laravel 13 + Vue 3 (Inertia) PWA для обмена KZT ↔ USDT. Клиенты работают через мобильное приложение (PWA), персонал — через веб-админку.

## Стек

| Слой | Технологии |
|------|------------|
| Backend | PHP 8.3+, Laravel 13, PostgreSQL, Redis |
| Frontend | Vue 3, Inertia.js, Tailwind CSS, Vite, PWA |
| Auth (клиенты) | Telegram + номер телефона, WebAuthn (Face ID / отпечаток) |
| Auth (staff) | Email + пароль (`/login`) |
| KYC | Sumsub |
| Блокчейн | BSC (USDT BEP-20), HD-кошелёк |

## Возможности

### Клиентское PWA

- Вход по казахстанскому номеру `+7 7XX …` через Telegram-бота
- Быстрый повторный вход по биометрии (WebAuthn) после первой регистрации на устройстве
- KYC (документ + liveness через Sumsub)
- Обмен KZT ↔ USDT, кошелёк, депозиты USDT, заявки на вывод
- Интерфейс на русском, казахском и английском

### Админка (`/admin`)

- KYC-ревью, ордера, выводы, подписки и тарифы (виды подписок, комиссии)
- Кошельки: hot/gas wallet, клиентские адреса, история депозитов
- Sweeps (сбор USDT с депозитных адресов)

Роли: `super_admin`, `security_officer`, `super_admin_manager`, `exchange_admin`.

## Требования

- PHP 8.3+ с расширениями: `pdo_pgsql`, `redis`, `bcmath`, `gmp`
- Composer 2.x
- Node.js 20+ и npm
- PostgreSQL 14+
- Redis (сессии, кэш, WebAuthn challenge)
- HTTPS в production (обязательно для PWA и биометрии)

## Быстрый старт

```bash
cd backend
composer setup          # install, .env, key, migrate, npm build
# или по шагам:
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install
npm run build
```

Локальная разработка (сервер + очередь + логи + Vite):

```bash
composer dev
```

Тесты:

```bash
composer test
# или
php artisan test
```

## Переменные окружения

Скопируйте `.env.example` в `.env` и задайте минимум:

### Приложение

| Переменная | Описание |
|------------|----------|
| `APP_URL` | Публичный URL (`https://kztusdt.kz`) |
| `APP_LOCALE` | Язык по умолчанию (`ru`) |
| `SESSION_DRIVER` | `redis` в production |
| `SESSION_LIFETIME` | Минуты жизни сессии (43200 = 30 дней) |
| `SESSION_ENCRYPT` | `true` |
| `CACHE_STORE` | `redis` (нужен для WebAuthn) |

### Telegram

| Переменная | Описание |
|------------|----------|
| `TELEGRAM_BOT_TOKEN` | Токен бота от @BotFather |
| `TELEGRAM_BOT_USERNAME` | Username без `@` |
| `TELEGRAM_WEBHOOK_SECRET` | Секрет для заголовка webhook (обязателен в production) |
| `TELEGRAM_LOGIN_CODE_TTL` | TTL кода входа в минутах (по умолчанию 10) |

После смены URL или секрета:

```bash
php artisan telegram:set-webhook
```

### KYC (Sumsub)

| Переменная | Описание |
|------------|----------|
| `KYC_PROVIDER` | `sumsub` |
| `SUMSUB_APP_TOKEN` | App token |
| `SUMSUB_SECRET_KEY` | Secret key |
| `SUMSUB_LEVEL_NAME` | Уровень верификации |
| `SUMSUB_WEBHOOK_SECRET` | Секрет webhook |

### Кошелёк и BSC

| Переменная | Описание |
|------------|----------|
| `WALLET_MNEMONIC` | BIP-39 мнемоника HD-кошелька (**хранить в секрете**) |
| `BSC_RPC_URL` | RPC-нода BSC |
| `BSC_USDT_CONTRACT` | Контракт USDT на BSC |
| `BSC_CONFIRMATIONS` | Подтверждений для зачисления депозита |
| `SWEEP_ENABLED` | Автосбор USDT на hot wallet |
| `WITHDRAWALS_ENABLED` | Автоотправка выводов |

Сгенерировать новую мнемонику (только для dev):

```bash
php artisan wallet:generate-mnemonic
```

### Обмен и комиссии

Базовые и подписочные комиссии настраиваются в админке: `/admin/subscriptions` (тарифы в БД, `subscription_plans`).

| Переменная | Описание |
|------------|----------|
| `FEE_PERCENT_DEFAULT` | Стартовая базовая комиссия при первом деплое (далее — в админке) |
| `FEE_PERCENT_SUBSCRIPTION` | Стартовая подписочная комиссия при первом деплое (далее — в админке) |
| `RATE_MARKUP_BUY` / `RATE_MARKUP_SELL` | Наценка к курсу |
| `EXCHANGE_BANK_*` | Реквизиты для оплаты KZT |

## Вход пользователей

### Клиенты (`/auth/phone`)

1. Ввод номера → создаётся одноразовая сессия.
2. Открытие Telegram-бота по deep-link → «Поделиться номером».
3. Номер из Telegram **должен совпасть** с введённым в приложении; чужой Telegram чужой номер не подтвердит.
4. После первого входа — предложение включить Face ID / Touch ID / отпечаток.
5. Повторный вход: кнопка «Войти по Face ID / отпечатку» на экране телефона.

WebAuthn API:

- `POST /api/auth/biometric/check` — проверка, есть ли ключ для номера
- `POST /webauthn/auth/options` + `POST /webauthn/auth` — вход
- `POST /webauthn/keys/options` + `POST /webauthn/keys` — регистрация ключа (требует auth)

### Staff (`/login`)

Email и пароль. Учётки создаются сидером:

| Email | Пароль | Роль |
|-------|--------|------|
| `admin@kztusdt.kz` | `Flatronezt717b@` | super_admin |
| `security@kztusdt.kz` | `Flatronezt717b@` | security_officer |

**Смените пароли сразу после первого входа в production.**

## Artisan-команды

| Команда | Назначение |
|---------|------------|
| `telegram:set-webhook` | Регистрация webhook бота |
| `deposits:watch` | Мониторинг входящих USDT (long-running) |
| `deposits:scan` | Разовое сканирование блоков |
| `sweep:run` | Сбор USDT с депозитных адресов |
| `withdrawals:process` | Обработка очереди выводов |
| `rates:refresh` | Обновление курса KZT/USDT |
| `subscriptions:expire` | Истечение подписок |
| `wallet:system` | Статус системных кошельков |
| `wallet:verify` | Проверка деривации адресов |

## Планировщик (cron)

```cron
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

В расписании: `sweep:run`, `withdrawals:process`, `rates:refresh`, `subscriptions:expire`.

Депозиты обычно запускают отдельным процессом:

```bash
php artisan deposits:watch --interval=15
```

## Основные маршруты

| URL | Описание |
|-----|----------|
| `/auth/phone` | Вход клиента |
| `/home`, `/wallet`, `/exchange` | PWA после входа |
| `/login` | Вход staff |
| `/admin` | Админ-панель |
| `/api/auth/telegram/webhook` | Webhook Telegram |
| `/api/kyc/sumsub/webhook` | Webhook Sumsub |

## Сборка frontend

```bash
npm run dev    # разработка с HMR
npm run build  # production + service worker (PWA)
```

## Структура проекта

```
app/
  Http/Controllers/     # Web + API контроллеры
  Services/               # Бизнес-логика
  Console/Commands/       # CLI: депозиты, sweeps, кошелёк
resources/js/
  Pages/                  # Inertia-страницы (Vue)
  composables/            # useBiometricAuth и др.
routes/web.php            # Маршруты приложения
config/                   # exchange, telegram, wallet, webauthn, kyc
tests/Feature/            # Feature-тесты (164+)
```

## Логи и диагностика

Каждый HTTP-запрос получает `request_id` — он попадает во все связанные записи, по нему можно собрать цепочку событий.

| Файл | Что пишется |
|------|-------------|
| `storage/logs/laravel-*.log` | Общие события приложения (курсы, депозиты, sweeps) |
| `storage/logs/auth-*.log` | Вход/выход, Telegram complete, WebAuthn (через `Login` event) |
| `storage/logs/http-*.log` | Запросы к `/auth/*`, `/webauthn/*`, `/home`, `/admin/*`, все 4xx/5xx, медленные (>2 с) |
| `storage/logs/errors-*.log` | Необработанные исключения (500) с URL, user_id, stack trace |

Примеры поиска:

```bash
# все события одного запроса
grep 'REQUEST_ID' storage/logs/http-*.log storage/logs/auth-*.log storage/logs/errors-*.log

# ошибки на /home
grep '/home' storage/logs/http-*.log storage/logs/errors-*.log

# вход через Telegram
grep 'telegram.complete' storage/logs/auth-*.log
```

Переменные окружения (опционально): `LOG_AUTH_LEVEL`, `LOG_HTTP_LEVEL`, `LOG_DAILY_DAYS`.

## Production checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] HTTPS и корректный `APP_URL`
- [ ] Redis для `SESSION_DRIVER` и `CACHE_STORE`
- [ ] `TELEGRAM_WEBHOOK_SECRET` задан, webhook перерегистрирован
- [ ] Пароли staff изменены, `WALLET_MNEMONIC` только на сервере
- [ ] `php artisan config:cache route:cache view:cache`
- [ ] Cron + `deposits:watch` + queue worker при необходимости
- [ ] `npm run build` после изменений frontend

## Лицензия

MIT (Laravel framework). Проприетарный код проекта — по согласованию с правообладателем.
