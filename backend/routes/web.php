<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditAdminController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DisputeAdminController;
use App\Http\Controllers\Admin\FinanceAdminController;
use App\Http\Controllers\Admin\KycReviewController;
use App\Http\Controllers\Admin\ListingController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\SettingsAdminController;
use App\Http\Controllers\Admin\SupportChatController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\SweepController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Admin\WalletAdminController;
use App\Http\Controllers\Admin\WithdrawalAdminController;
use App\Http\Controllers\AituPassportController;
use App\Http\Controllers\Api\AituPassportLogoutController;
use App\Http\Controllers\Api\AituPassportValidationController;
use App\Http\Controllers\Api\BiometricAuthController;
use App\Http\Controllers\Api\LegalEntityEdsController;
use App\Http\Controllers\Api\PhoneAuthController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\SupportChatController as ApiSupportChatController;
use App\Http\Controllers\Api\SumsubWebhookController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\ExchangeOrderController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PhoneAuthPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SupportChatPageController;
use App\Http\Controllers\WithdrawalController;
use App\Support\CompanyPresenter;
use App\Support\LocaleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    return Inertia::render('Landing', [
        'company' => CompanyPresenter::layout(),
    ]);
});

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

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

Route::post('/api/auth/biometric/check', [BiometricAuthController::class, 'check'])
    ->middleware('throttle:30,1');
Route::post('/api/auth/phone/start', [PhoneAuthController::class, 'start'])
    ->middleware('throttle:10,1');
Route::post('/api/auth/legal-entity/eds/start', [LegalEntityEdsController::class, 'start'])
    ->middleware('throttle:10,1');
Route::post('/api/auth/legal-entity/eds/{loginCode}/verify', [LegalEntityEdsController::class, 'verify'])
    ->middleware('throttle:10,1');
Route::post('/api/auth/phone/resend/{loginCode}', [PhoneAuthController::class, 'resend'])
    ->middleware('throttle:10,1');
Route::post('/api/auth/phone/verify/{loginCode}', [PhoneAuthController::class, 'verify'])
    ->middleware('throttle:10,1');
Route::post('/api/kyc/sumsub/webhook', SumsubWebhookController::class)
    ->middleware('throttle:60,1');

Route::prefix('{locale}')
    ->where(['locale' => implode('|', LocaleManager::supported())])
    ->group(function (): void {
        Route::get('/', function () {
            return Inertia::render('Landing', [
                'company' => CompanyPresenter::layout(),
            ]);
        });

        Route::get('/legal', [LegalController::class, 'index'])->name('legal.index');
        Route::get('/legal/{slug}', [LegalController::class, 'show'])
            ->where('slug', '[a-z0-9-]+')
            ->name('legal.show');

        Route::middleware('guest')->group(function (): void {
            Route::get('/auth/phone', [PhoneAuthPageController::class, 'show'])->name('auth.phone');
            Route::post('/auth/phone', [PhoneAuthPageController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('auth.phone.store');
        });

        // Onboarding continues here after WhatsApp OTP — must stay outside "guest" middleware.
        Route::get('/auth/whatsapp/{loginCode}', [PhoneAuthPageController::class, 'wait'])
            ->name('auth.whatsapp.wait');
        Route::get('/auth/telegram/{loginCode}', fn (string $locale, string $loginCode) => redirect()->route('auth.whatsapp.wait', [
            'locale' => $locale,
            'loginCode' => $loginCode,
        ]));

        Route::middleware(['auth', 'no_security_pwa'])->group(function (): void {
            Route::get('/home', [ExchangeController::class, 'homeRedirect'])->name('home');
            Route::get('/market', fn () => redirect()->route('exchange'));
            Route::get('/wallet/withdraw', fn () => redirect()->route('wallet', ['tab' => 'withdraw']));

            Route::middleware('kyc.approved:wallet')->group(function (): void {
                Route::get('/wallet', [ExchangeController::class, 'wallet'])->name('wallet');
                Route::get('/wallet/history', [HistoryController::class, 'index'])->name('wallet.history');
            });

            Route::middleware('kyc.approved:exchange')->group(function (): void {
                Route::get('/exchange', [ExchangeController::class, 'exchange'])->name('exchange');
                Route::post('/exchange/orders', [ExchangeOrderController::class, 'store'])->name('exchange.orders.store');
                Route::get('/exchange/orders/{order}', [ExchangeOrderController::class, 'show'])->name('exchange.orders.show');
                Route::get('/exchange/orders/{order}/proof', [ExchangeOrderController::class, 'downloadProof'])->name('exchange.orders.proof.show');
                Route::post('/exchange/orders/{order}/proof', [ExchangeOrderController::class, 'uploadProof'])->name('exchange.orders.proof');
                Route::post('/exchange/orders/{order}/mark-paid', [ExchangeOrderController::class, 'markPaid'])->name('exchange.orders.mark-paid');
                Route::post('/exchange/orders/{order}/mark-received', [ExchangeOrderController::class, 'markReceived'])->name('exchange.orders.mark-received');
                Route::post('/exchange/orders/{order}/cancel', [ExchangeOrderController::class, 'cancel'])->name('exchange.orders.cancel');
                Route::get('/support/chat', [SupportChatPageController::class, 'show'])->name('support.chat');
            });

            Route::middleware('kyc.approved:withdraw')->group(function (): void {
                Route::get('/withdraw', [WithdrawalController::class, 'index'])->name('withdraw');
                Route::post('/withdraw', [WithdrawalController::class, 'store'])->name('withdraw.store');
                Route::post('/withdraw/{withdrawal}/cancel', [WithdrawalController::class, 'cancel'])->name('withdraw.cancel');
            });

            Route::get('/kyc', [KycController::class, 'show'])->name('kyc');
            Route::post('/kyc', [KycController::class, 'store'])->name('kyc.store');
            Route::post('/kyc/sumsub/token', [KycController::class, 'sumsubToken'])->name('kyc.sumsub.token');
            Route::post('/kyc/sumsub/sync', [KycController::class, 'sumsubSync'])->name('kyc.sumsub.sync');

            Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
            Route::get('/profile/personal', [ProfileController::class, 'personal'])->name('profile.personal');
            Route::middleware('kyc.approved:bank')->group(function (): void {
                Route::get('/profile/bank', [ProfileController::class, 'bank'])->name('profile.bank');
                Route::post('/profile/bank/cards', [ProfileController::class, 'storeBankCard'])->name('profile.bank.cards.store');
                Route::patch('/profile/bank/cards/{card}', [ProfileController::class, 'updateBankCard'])->name('profile.bank.cards.update');
                Route::patch('/profile/bank/cards/{card}/rename', [ProfileController::class, 'renameBankCard'])->name('profile.bank.cards.rename');
                Route::delete('/profile/bank/cards/{card}', [ProfileController::class, 'destroyBankCard'])->name('profile.bank.cards.destroy');
            });
            Route::get('/profile/security', [ProfileController::class, 'security'])->name('profile.security');
            Route::get('/profile/language', [ProfileController::class, 'language'])->name('profile.language');
            Route::get('/profile/appearance', [ProfileController::class, 'appearance'])->name('profile.appearance');
            Route::get('/profile/notifications', [ProfileController::class, 'notifications'])->name('profile.notifications');
            Route::patch('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications.update');
            Route::get('/profile/support', [ProfileController::class, 'support'])->name('profile.support');
            Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        });

    });

Route::middleware(['auth', 'role:super_admin,security_officer,super_admin_manager,exchange_admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/account', AccountController::class)->name('account');
});

Route::middleware(['auth', 'role:super_admin,security_officer,super_admin_manager'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/users', [UserAdminController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserAdminController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}/status', [UserAdminController::class, 'updateStatus'])->name('users.status');
    Route::patch('/users/{user}/manual-kyc', [UserAdminController::class, 'updateManualKyc'])->name('users.manual-kyc');
    Route::post('/users/{user}/kyc/manual-approve', [UserAdminController::class, 'manualKycApprove'])->name('users.kyc.manual-approve');
    Route::get('/finance', [FinanceAdminController::class, 'index'])->name('finance.index');
    Route::get('/settings', [SettingsAdminController::class, 'index'])->name('settings.index');
    Route::get('/audit', [AuditAdminController::class, 'index'])->name('audit.index');
    Route::get('/disputes', [DisputeAdminController::class, 'index'])->name('disputes.index');
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

Route::middleware(['auth', 'role:super_admin,super_admin_manager,exchange_admin,security_officer'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
    Route::get('/listings/create', [ListingController::class, 'create'])->name('listings.create');
    Route::post('/listings', [ListingController::class, 'store'])->name('listings.store');
    Route::get('/listings/{listing}/edit', [ListingController::class, 'edit'])->name('listings.edit');
    Route::put('/listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
    Route::patch('/listings/{listing}/toggle', [ListingController::class, 'toggle'])->name('listings.toggle');
    Route::delete('/listings/{listing}', [ListingController::class, 'destroy'])->name('listings.destroy');

    Route::get('/support', [SupportChatController::class, 'index'])->name('support.index');
    Route::get('/support/{conversation}', [SupportChatController::class, 'show'])->name('support.show');
    Route::post('/support/{conversation}/messages', [SupportChatController::class, 'store'])->name('support.messages.store');

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

Route::middleware('auth')->group(function (): void {
    Route::prefix('api/support/chat/orders/{order}')->group(function (): void {
        Route::get('/', [ApiSupportChatController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('support.chat.show');
        Route::get('/unread', [ApiSupportChatController::class, 'unreadCount'])
            ->middleware('throttle:60,1')
            ->name('support.chat.unread');
        Route::post('/messages', [ApiSupportChatController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('support.chat.messages.store');
    });

    Route::post('/api/push/subscribe', [PushSubscriptionController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('push.subscribe');
    Route::post('/api/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('push.unsubscribe');
});

require __DIR__.'/auth.php';

Route::get('/{path}', function (Request $request, string $path) {
    $firstSegment = LocaleManager::normalize(explode('/', $path)[0] ?? null);

    if ($firstSegment !== null && LocaleManager::isSupported($firstSegment)) {
        abort(404);
    }

    if ($path === 'admin' || str_starts_with($path, 'admin/') || str_starts_with($path, 'api/') || str_starts_with($path, 'auth/aitu/')) {
        abort(404);
    }

    return redirect()->to(LocaleManager::localizedPath(LocaleManager::resolve($request), '/'.$path));
})->where('path', '.*');
