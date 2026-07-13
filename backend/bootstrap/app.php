<?php

use App\Support\AdminUrl;
use App\Support\LocaleManager;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/internal/health',
        then: function (): void {
            if (app()->environment('testing')) {
                Route::middleware('web')->group(base_path('routes/admin.php'));

                return;
            }

            Route::middleware('web')
                ->domain(AdminUrl::domain())
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request): string {
            if (AdminUrl::isAdminHost($request)) {
                return '/admin/login';
            }

            return route('auth.phone', [
                'locale' => LocaleManager::resolve($request),
            ]);
        });

        $middleware->redirectUsersTo(function (Request $request): string {
            return \App\Support\RegistrationResume::path($request->user(), $request);
        });

        $middleware->trustProxies(at: '*');
        $middleware->web(prepend: [
            \App\Http\Middleware\ResetZiggyRouteGenerator::class,
            \App\Http\Middleware\AttachRequestLogContext::class,
            \App\Http\Middleware\RedirectClientAdminToSubdomain::class,
            \App\Http\Middleware\SetLocale::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\HideTechnologyHeaders::class,
            \App\Http\Middleware\LogHttpRequests::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/auth/telegram/webhook',
            'api/auth/phone/start',
            'api/auth/phone/resend/*',
            'api/auth/phone/verify/*',
            'api/auth/biometric/check',
            'api/kyc/sumsub/webhook',
            'api/auth/aitu/logout',
            'api/auth/aitu/validate',
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'no_security_pwa' => \App\Http\Middleware\RedirectSecurityFromPwa::class,
            'kyc.approved' => \App\Http\Middleware\EnsureKycApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->reportable(function (\Throwable $exception): void {
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                && $exception->getStatusCode() < 500) {
                return;
            }

            \App\Support\AppLog::exception($exception, [
                'url' => request()->fullUrl(),
                'user_id' => auth()->id(),
            ]);
        });
    })->create();
