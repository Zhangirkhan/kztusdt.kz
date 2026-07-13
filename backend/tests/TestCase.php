<?php

declare(strict_types=1);

namespace Tests;

use App\Support\LocaleManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\Concerns\InteractsWithAdminHost;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithAdminHost;
    /**
     * Hard guard: this code runs on the production server, so make sure the
     * suite can never run (and wipe data) against the production database.
     */
    protected function setUpTraits()
    {
        $connection = $this->app['config']->get('database.default');
        $database = $this->app['config']->get("database.connections.{$connection}.database");

        if ($database !== 'crypto_exchange_test') {
            throw new RuntimeException(
                "Tests must run against 'crypto_exchange_test', got '{$database}'. ".
                'Run via vendor/bin/phpunit (do not clear the production config cache).',
            );
        }

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Named routes use `{locale}`; default it so route() works in assertions.
        URL::defaults(['locale' => LocaleManager::default()]);

        // No test may ever talk to real Telegram / Binance / Sumsub / RPC nodes.
        Http::preventStrayRequests();
    }
}
