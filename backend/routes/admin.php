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
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Support\AdminNavPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('admin/login', [AuthenticatedSessionController::class, 'create'])
    ->name('login');

Route::post('admin/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:5,1');

Route::middleware(['auth', 'role:super_admin,security_officer,super_admin_manager,exchange_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/account', AccountController::class)->name('account');
    });

Route::middleware(['auth', 'role:super_admin,security_officer,super_admin_manager'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
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

Route::middleware(['auth', 'role:super_admin,super_admin_manager'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/sweeps', [SweepController::class, 'index'])->name('sweeps.index');
        Route::post('/sweeps/{sweep}/retry', [SweepController::class, 'retry'])->name('sweeps.retry');

        Route::get('/wallets', [WalletAdminController::class, 'index'])->name('wallets.index');
    });

Route::middleware(['auth', 'role:super_admin,super_admin_manager,exchange_admin,security_officer'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
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

Route::middleware(['auth', 'role:super_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('/subscriptions/plans', [SubscriptionController::class, 'storePlan'])->name('subscriptions.plans.store');
        Route::patch('/subscriptions/plans/{plan}', [SubscriptionController::class, 'updatePlan'])->name('subscriptions.plans.update');
        Route::post('/subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
    });

Route::get('/', function (Request $request) {
    $user = $request->user();

    if ($user !== null && AdminNavPresenter::canAccessAdmin($user)) {
        return redirect(AdminNavPresenter::landingPath($user) ?? '/admin/login');
    }

    return redirect('/admin/login');
});
Route::redirect('/login', '/admin/login');
