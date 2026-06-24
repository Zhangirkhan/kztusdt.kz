<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
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

        // No test may ever talk to real Telegram / Binance / Sumsub / RPC nodes.
        Http::preventStrayRequests();
    }
}
