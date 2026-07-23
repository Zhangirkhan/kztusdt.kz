<?php

declare(strict_types=1);

use App\Http\Controllers\CaptchaController;
use App\Http\Controllers\AituPassportController;
use App\Http\Controllers\Api\AituPassportLogoutController;
use App\Http\Controllers\Api\AituPassportValidationController;
use App\Http\Controllers\Api\AppLockBiometricController;
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
use App\Http\Controllers\DueDiligenceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PhoneAuthPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SupportChatPageController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\WebAppManifestController;
use App\Support\CompanyPresenter;
use App\Support\LocaleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    $locale = LocaleManager::resolve($request);

    return redirect('/'.$locale.'/', 302);
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

Route::middleware(['auth'])->group(function (): void {
    Route::post('/api/app-lock/biometric/options', [AppLockBiometricController::class, 'options'])
        ->middleware('throttle:30,1');
    Route::post('/api/app-lock/biometric/verify', [AppLockBiometricController::class, 'verify'])
        ->middleware('throttle:30,1');
});

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
            Route::get('/auth/captcha', [CaptchaController::class, 'image'])
                ->middleware('throttle:60,1')
                ->name('auth.captcha');
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
                Route::post('/exchange/orders/{order}/appeal', [ExchangeOrderController::class, 'storeAppeal'])->name('exchange.orders.appeal');
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
            Route::post('/kyc/confirm-iin', [KycController::class, 'confirmIin'])->name('kyc.confirm-iin');

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
            Route::get('/profile/language', fn (Request $request) => redirect()->route('profile.show', [
                'locale' => $request->route('locale'),
            ]))->name('profile.language');
            Route::get('/profile/appearance', [ProfileController::class, 'appearance'])->name('profile.appearance');
            Route::get('/profile/notifications', [ProfileController::class, 'notifications'])->name('profile.notifications');
            Route::patch('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications.update');
            Route::get('/profile/support', [ProfileController::class, 'support'])->name('profile.support');
            Route::get('/profile/referrals', [ProfileController::class, 'referrals'])->name('profile.referrals');
            Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::post('/due-diligence', [DueDiligenceController::class, 'store'])->name('due-diligence.store');
        });

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

Route::get('/manifest.webmanifest', [WebAppManifestController::class, 'show'])
    ->name('manifest');

Route::get('/{path}', function (Request $request, string $path) {
    $firstSegment = LocaleManager::normalize(explode('/', $path)[0] ?? null);

    if ($firstSegment !== null && LocaleManager::isSupported($firstSegment)) {
        abort(404);
    }

    if ($path === 'admin' || str_starts_with($path, 'admin/') || str_starts_with($path, 'api/') || str_starts_with($path, 'auth/aitu/')) {
        abort(404);
    }

    return redirect()->to(LocaleManager::localizedPath(LocaleManager::resolve($request), '/'.$path));
})->where(
    'path',
    // In testing admin routes are registered without a domain after this catch-all;
    // exclude them so GET /admin/* can hit routes/admin.php.
    app()->environment('testing')
        ? '^(?!admin(?:/|$)|api(?:/|$)|auth/aitu(?:/|$)).*'
        : '.*',
);
