<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KycReviewController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\SweepController;
use App\Http\Controllers\Admin\WalletAdminController;
use App\Http\Controllers\Admin\WithdrawalAdminController;
use App\Http\Controllers\AituPassportController;
use App\Http\Controllers\Api\AituPassportLogoutController;
use App\Http\Controllers\Api\AituPassportValidationController;
use App\Http\Controllers\Api\BiometricAuthController;
use App\Http\Controllers\Api\PhoneAuthController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\SumsubWebhookController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\ExchangeOrderController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PhoneAuthPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/auth/phone');

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/legal', [LegalController::class, 'index'])->name('legal.index');
Route::get('/legal/{slug}', [LegalController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('legal.show');

// Aitu Passport (OAuth 2.0 / OpenID) — единая аутентификация.
// Эти пути регистрируются в клиентской консоли Aitu Passport как URI сервиса.
Route::prefix('auth/aitu')->name('auth.aitu.')->group(function (): void {
    // Старт авторизации (кнопка «Войти через Aitu Passport»).
    Route::get('/redirect', [AituPassportController::class, 'redirect'])
        ->middleware('throttle:20,1')
        ->name('redirect');

    // Redirect URI — возврат пользователя после согласия (?code&state).
    Route::get('/callback', [AituPassportController::class, 'callback'])
        ->middleware('throttle:20,1')
        ->name('callback');

    // Post Logout Redirect URI — возврат пользователя после logout.
    Route::get('/logout/callback', [AituPassportController::class, 'postLogout'])
        ->name('logout.callback');

    // Phone Change Redirect URI — возврат после смены номера телефона.
    Route::get('/phone-changed', [AituPassportController::class, 'phoneChanged'])
        ->name('phone-changed');

    // Инициация logout (редирект в Aitu Passport) — только для авторизованных.
    Route::post('/logout', [AituPassportController::class, 'logout'])
        ->middleware('auth')
        ->name('logout');
});

// Logout Callback URI — серверный вебхук Aitu Passport после выхода пользователя.
Route::post('/api/auth/aitu/logout', AituPassportLogoutController::class)
    ->middleware('throttle:120,1')
    ->name('api.auth.aitu.logout');

// «Валидация на стороне клиента» — endpoint, вызываемый Aitu Passport.
Route::match(['get', 'post'], '/api/auth/aitu/validate', AituPassportValidationController::class)
    ->middleware('throttle:120,1')
    ->name('api.auth.aitu.validate');

Route::middleware('guest')->group(function (): void {
    Route::get('/auth/phone', [PhoneAuthPageController::class, 'show'])->name('auth.phone');
    Route::post('/auth/phone', [PhoneAuthPageController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('auth.phone.store');
});

// Onboarding continues here after Telegram login — must stay outside "guest" middleware.
Route::get('/auth/telegram/{loginCode}', [PhoneAuthPageController::class, 'wait'])
    ->name('auth.telegram.wait');

Route::post('/api/auth/biometric/check', [BiometricAuthController::class, 'check'])
    ->middleware('throttle:30,1');
Route::post('/api/auth/phone/start', [PhoneAuthController::class, 'start'])
    ->middleware('throttle:10,1');
Route::post('/api/auth/phone/resend/{loginCode}', [PhoneAuthController::class, 'resend'])
    ->middleware('throttle:10,1');
Route::post('/api/auth/phone/verify/{loginCode}', [PhoneAuthController::class, 'verify'])
    ->middleware('throttle:10,1');
Route::post('/api/kyc/sumsub/webhook', SumsubWebhookController::class)
    ->middleware('throttle:60,1');

Route::middleware('auth')->group(function (): void {
    Route::post('/api/push/subscribe', [PushSubscriptionController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('push.subscribe');
    Route::post('/api/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('push.unsubscribe');
});

Route::middleware(['auth', 'no_security_pwa'])->group(function (): void {
    Route::get('/home', [ExchangeController::class, 'home'])->name('home');
    Route::get('/wallet', [ExchangeController::class, 'wallet'])->name('wallet');
    Route::get('/exchange', [ExchangeController::class, 'exchange'])->name('exchange');

    Route::post('/exchange/orders', [ExchangeOrderController::class, 'store'])->name('exchange.orders.store');
    Route::get('/exchange/orders/{order}', [ExchangeOrderController::class, 'show'])->name('exchange.orders.show');
    Route::post('/exchange/orders/{order}/proof', [ExchangeOrderController::class, 'uploadProof'])->name('exchange.orders.proof');
    Route::post('/exchange/orders/{order}/cancel', [ExchangeOrderController::class, 'cancel'])->name('exchange.orders.cancel');

    Route::get('/withdraw', [WithdrawalController::class, 'index'])->name('withdraw');
    Route::post('/withdraw', [WithdrawalController::class, 'store'])->name('withdraw.store');
    Route::post('/withdraw/{withdrawal}/cancel', [WithdrawalController::class, 'cancel'])->name('withdraw.cancel');

    Route::get('/kyc', [KycController::class, 'show'])->name('kyc');
    Route::post('/kyc', [KycController::class, 'store'])->name('kyc.store');
    Route::post('/kyc/sumsub/token', [KycController::class, 'sumsubToken'])->name('kyc.sumsub.token');
    Route::post('/kyc/sumsub/sync', [KycController::class, 'sumsubSync'])->name('kyc.sumsub.sync');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'role:super_admin,security_officer,super_admin_manager,exchange_admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/account', AccountController::class)->name('account');
});

Route::middleware(['auth', 'role:super_admin,security_officer,super_admin_manager'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/kyc', [KycReviewController::class, 'index'])->name('kyc.index');
    Route::get('/kyc/{kycProfile}', [KycReviewController::class, 'show'])->name('kyc.show');
    Route::post('/kyc/{kycProfile}/approve', [KycReviewController::class, 'approve'])->name('kyc.approve');
    Route::post('/kyc/{kycProfile}/reject', [KycReviewController::class, 'reject'])->name('kyc.reject');
    Route::post('/kyc/{kycProfile}/reset', [KycReviewController::class, 'reset'])->name('kyc.reset');
    Route::get('/kyc/{kycProfile}/documents/{type}', [KycReviewController::class, 'document'])->name('kyc.document');

    Route::get('/withdrawals', [WithdrawalAdminController::class, 'index'])->name('withdrawals.index');
    Route::post('/withdrawals/{withdrawal}/approve', [WithdrawalAdminController::class, 'approve'])->name('withdrawals.approve');
    Route::post('/withdrawals/{withdrawal}/reject', [WithdrawalAdminController::class, 'reject'])->name('withdrawals.reject');
    Route::post('/withdrawals/{withdrawal}/retry', [WithdrawalAdminController::class, 'retry'])->name('withdrawals.retry');
});

Route::middleware(['auth', 'role:super_admin,super_admin_manager'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/sweeps', [SweepController::class, 'index'])->name('sweeps.index');
    Route::post('/sweeps/{sweep}/retry', [SweepController::class, 'retry'])->name('sweeps.retry');

    Route::get('/wallets', [WalletAdminController::class, 'index'])->name('wallets.index');
});

// Exchange orders: KZT payment confirmation and USDT settlement.
Route::middleware(['auth', 'role:super_admin,super_admin_manager,exchange_admin,security_officer'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/proof', [OrderController::class, 'proof'])->name('orders.proof');
    Route::post('/orders/{order}/confirm-payment', [OrderController::class, 'confirmPayment'])->name('orders.confirm');
    Route::post('/orders/{order}/mark-kzt-sent', [OrderController::class, 'markKztSent'])->name('orders.kzt-sent');
    Route::post('/orders/{order}/reject', [OrderController::class, 'reject'])->name('orders.reject');
});

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::post('/subscriptions/plans', [SubscriptionController::class, 'storePlan'])->name('subscriptions.plans.store');
    Route::patch('/subscriptions/plans/{plan}', [SubscriptionController::class, 'updatePlan'])->name('subscriptions.plans.update');
    Route::post('/subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
    Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
});

require __DIR__.'/auth.php';
