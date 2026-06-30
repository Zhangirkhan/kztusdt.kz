<?php

namespace App\Providers;

use App\Actions\Webauthn\LoginUserRetrieval as AppLoginUserRetrieval;
use App\Listeners\LogAuthenticationEvents;
use App\Services\Withdrawals\EvmWithdrawalBroadcaster;
use App\Services\Withdrawals\TronWithdrawalBroadcaster;
use App\Services\Withdrawals\WithdrawalBroadcasterRegistry;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use LaravelWebauthn\Actions\LoginUserRetrieval;
use LaravelWebauthn\Listeners\LoginViaRemember;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginUserRetrieval::class, AppLoginUserRetrieval::class);

        $this->app->singleton(WithdrawalBroadcasterRegistry::class, function ($app): WithdrawalBroadcasterRegistry {
            $evm = $app->make(EvmWithdrawalBroadcaster::class);
            $tron = $app->make(TronWithdrawalBroadcaster::class);

            // Route TRC20 to the Tron broadcaster; everything else (BEP20 / EVM)
            // falls back to the EVM broadcaster, preserving the historical behaviour.
            return new WithdrawalBroadcasterRegistry(
                [
                    $evm->network() => $evm,
                    $tron->network() => $tron,
                ],
                $evm,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Event::subscribe(LoginViaRemember::class);

        $authLogger = app(LogAuthenticationEvents::class);
        Event::listen(Login::class, [$authLogger, 'handleLogin']);
        Event::listen(Failed::class, [$authLogger, 'handleFailed']);
        Event::listen(Logout::class, [$authLogger, 'handleLogout']);
    }
}
