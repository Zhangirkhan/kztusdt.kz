# Project Index

Root: `/var/www/crypto-exchange/backend`

## Stack

- Laravel 13, PHP 8.3+
- Inertia 2, Vue 3, Vite 8
- Tailwind CSS 4
- Ziggy routes, Sanctum, WebAuthn, Web Push
- PHPUnit / Laravel feature tests

## Main Commands

```bash
npm run build
npm run dev
php artisan test
composer test
php artisan route:list
```

Production build note:

```bash
chown -R www-data:www-data public/build
find public/build -type d -exec chmod 755 {} +
find public/build -type f -exec chmod 644 {} +
```

## Route Map

- `/` - landing page
- `/{locale}` - localized landing page, supported locales: `ru`, `kk`, `en`
- `/{locale}/auth/phone` - phone auth page (IIN + phone)
- `/{locale}/auth/whatsapp/{loginCode}` - WhatsApp OTP waiting page
- `/{locale}/auth/telegram/{loginCode}` - legacy redirect to WhatsApp wait page
- `/{locale}/home` - app home page
- `/{locale}/exchange`, `/{locale}/exchange/{order}` - exchange flow
- `/{locale}/wallet` - wallet
- `/{locale}/withdraw` - withdrawals
- `/{locale}/kyc` - KYC
- `/{locale}/history` - history
- `/{locale}/profile` - profile
- `/{locale}/legal`, `/{locale}/legal/{slug}` - legal pages
- `/admin`, `/admin/*` - admin area, not localized
- `/api/*` - API routes, not localized
- `/auth/aitu/*` - Aitu auth routes, not localized

Routing entrypoint: `routes/web.php`

Locale handling:

- `app/Support/LocaleManager.php`
- `app/Http/Middleware/SetLocale.php`
- `app/Http/Controllers/LocaleController.php`

## Backend Structure

### Controllers

- `app/Http/Controllers/Auth/*` - login, registration, verification
- `app/Http/Controllers/Api/*` - phone auth, push, Sumsub webhook
- `app/Http/Controllers/Admin/*` - admin dashboard, users, orders, finance, settings
- `app/Http/Controllers/ExchangeController.php`
- `app/Http/Controllers/ExchangeOrderController.php`
- `app/Http/Controllers/WithdrawalController.php`
- `app/Http/Controllers/WalletController.php`
- `app/Http/Controllers/KycController.php`
- `app/Http/Controllers/LegalController.php`
- `app/Http/Controllers/RobotsController.php`
- `app/Http/Controllers/SitemapController.php`

### Services

- Auth and identity:
  - `app/Services/PhoneAuthService.php`
  - `app/Services/WhatsAppOtpService.php`
  - `app/Services/AituPassportService.php`
  - `app/Services/AituKycService.php`
  - `app/Services/KycService.php`
  - `app/Services/SumsubService.php`
- Exchange and rates:
  - `app/Services/ExchangeOrderService.php`
  - `app/Services/RateService.php`
  - `app/Services/LedgerService.php`
- Wallets and blockchain:
  - `app/Services/WalletService.php`
  - `app/Services/SystemWalletService.php`
  - `app/Services/DepositIndexerService.php`
  - `app/Services/DepositConfirmationService.php`
  - `app/Services/SweepService.php`
  - `app/Services/WithdrawalService.php`
  - `app/Services/BscRpcClient.php`
  - `app/Services/EthereumTxService.php`
  - `app/Services/EvmAddressValidator.php`
  - `app/Services/Tron/*`
  - `app/Services/Withdrawals/*`
- Other:
  - `app/Services/LegalDocumentService.php`
  - `app/Services/UserNotificationService.php`
  - `app/Services/ProfilePhotoService.php`
  - `app/Services/WebPushService.php`
  - `app/Services/AuditLogService.php`

### Models

- Users and auth:
  - `app/Models/User.php`
  - `app/Models/AuthSession.php`
  - `app/Models/Role.php`
  - `app/Models/UserRole.php`
  - `app/Models/UserTelegramAccount.php`
- KYC:
  - `app/Models/KycProfile.php`
  - `app/Models/KycDocument.php`
  - `app/Models/ManualApproval.php`
- Exchange and money:
  - `app/Models/ExchangeOrder.php`
  - `app/Models/FiatPaymentRequest.php`
  - `app/Models/Balance.php`
  - `app/Models/LedgerEntry.php`
  - `app/Models/WalletAddress.php`
  - `app/Models/Deposit.php`
  - `app/Models/Withdrawal.php`
  - `app/Models/Sweep.php`
  - `app/Models/IndexerState.php`
  - `app/Models/WalletCounter.php`
- Subscriptions and notifications:
  - `app/Models/Subscription.php`
  - `app/Models/SubscriptionPlan.php`
  - `app/Models/PushSubscription.php`
- System:
  - `app/Models/AuditLog.php`
  - `app/Models/Tenant.php`

### Console Commands

- `app/Console/Commands/WalletGenerateMnemonic.php`
- `app/Console/Commands/WalletSystem.php`
- `app/Console/Commands/WalletVerify.php`
- `app/Console/Commands/DepositsScan.php`
- `app/Console/Commands/DepositsScanTron.php`
- `app/Console/Commands/DepositsWatch.php`
- `app/Console/Commands/RatesRefresh.php`
- `app/Console/Commands/SweepRun.php`
- `app/Console/Commands/WithdrawalsProcess.php`
- `app/Console/Commands/SubscriptionsExpire.php`
- `app/Console/Commands/WebPushVapid.php`
- `app/Console/Commands/AituGenerateIinKeys.php`

## Frontend Structure

Entry files:

- `resources/js/app.js`
- `resources/css/app.css`
- `resources/views/app.blade.php`

Pages:

- `resources/js/Pages/Landing.vue`
- `resources/js/Pages/Auth/Phone.vue`
- `resources/js/Pages/Auth/WhatsAppWait.vue`
- `resources/js/Pages/Home.vue`
- `resources/js/Pages/Exchange.vue`
- `resources/js/Pages/Exchange/OrderShow.vue`
- `resources/js/Pages/Wallet.vue`
- `resources/js/Pages/Withdraw.vue`
- `resources/js/Pages/Kyc.vue`
- `resources/js/Pages/History/Index.vue`
- `resources/js/Pages/Profile.vue`
- `resources/js/Pages/Legal.vue`
- `resources/js/Pages/Admin/*`

Components:

- `resources/js/Components/AppLogo.vue`
- `resources/js/Components/LocaleSwitcher.vue`
- `resources/js/Components/SeoHead.vue`
- `resources/js/Components/PwaInstallPrompt.vue`
- `resources/js/Components/SumsubKycWidget.vue`
- `resources/js/Components/CompanyIntro.vue`
- `resources/js/Components/ServiceHero.vue`
- `resources/js/Components/*` - form and UI components

Frontend modules:

- `resources/js/i18n/*` - locale dictionaries and helpers
- `resources/js/shared/*` - shared UI and helpers
- `resources/js/entities/*` - domain entities
- `resources/js/features/*` - feature modules
- `resources/js/widgets/*` - larger UI widgets
- `resources/js/lib/*` - app libraries

## Config Files

Core:

- `config/app.php`
- `config/auth.php`
- `config/database.php`
- `config/services.php`
- `config/sanctum.php`

Project:

- `config/company.php`
- `config/locales.php`
- `config/legal.php`
- `config/seo.php`
- `config/kyc.php`
- `config/exchange.php`
- `config/wallet.php`
- `config/networks.php`
- `config/tron.php`
- `config/bsc.php`
- `config/withdrawal.php`
- `config/sweep.php`
- `config/otp.php`
- `config/telegram.php`
- `config/aitu.php`
- `config/webpush.php`
- `config/webauthn.php`

## Database

Migrations: `database/migrations/*`

Main areas:

- users, auth sessions, roles
- KYC profiles, documents, manual approvals
- wallet addresses, balances, ledger entries
- deposits, withdrawals, sweeps
- exchange orders and fiat payment requests
- subscriptions and subscription plans
- OTP attempts, push subscriptions, audit logs
- profile fields and operational indexes

Seeders:

- `database/seeders/DatabaseSeeder.php`
- `database/seeders/RoleSeeder.php`
- `database/seeders/SubscriptionPlanSeeder.php`

## Tests

Feature tests:

- auth and phone auth
- Aitu auth
- KYC and Sumsub
- exchange buy/sell flows
- wallet, deposits, withdrawals
- admin access and admin workflows
- legal pages and SEO
- push subscriptions
- profile and request logging

Unit tests:

- Aitu passport helpers
- EVM address validation
- phone normalization
- WhatsApp OTP behavior

Test root: `tests/`

## Public Assets

- `public/logo.svg`
- `public/logo-wordmark.png`
- `public/icons/*`
- `public/manifest.webmanifest`
- `public/sw.js`
- `public/build/*` - generated Vite build output

## Legal Content

- `resources/legal/ru/*`
- `resources/legal/kk/*`
- `resources/legal/en/*`

## Do Not Manually Index Deeply

These folders are generated, external, or runtime-heavy:

- `vendor/`
- `node_modules/`
- `public/build/`
- `storage/logs/`
- `storage/framework/`
- `bootstrap/cache/`

## Recent Localized App Areas

- Landing: `resources/js/Pages/Landing.vue`
- Locale switcher: `resources/js/Components/LocaleSwitcher.vue`
- Locale manager: `app/Support/LocaleManager.php`
- Locale middleware: `app/Http/Middleware/SetLocale.php`
- Routes: `routes/web.php`
- Legal links: landing footer and `resources/legal/*`
