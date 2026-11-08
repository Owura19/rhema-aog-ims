<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * The ONLY database tests may ever connect to.
     * Tests that use RefreshDatabase drop and rebuild every table, so they
     * must never reach the real (production-copy) database.
     */
    private const SAFE_TEST_DATABASE = 'rhema_aog_ims_test';

    /**
     * Runs after the app boots but BEFORE traits like RefreshDatabase
     * migrate anything — so a misconfiguration aborts before any damage.
     */
    protected function setUpTraits()
    {
        $this->assertSafeTestDatabase();

        return parent::setUpTraits();
    }

    private function assertSafeTestDatabase(): void
    {
        if ($this->app->configurationIsCached()) {
            throw new RuntimeException(
                'Refusing to run tests: config is cached (bootstrap/cache/config.php), so phpunit.xml '
                . 'DB settings are ignored. Run "php artisan config:clear" first.'
            );
        }

        $connection = config('database.default');
        $database   = config("database.connections.{$connection}.database");

        if ($connection !== 'mysql' || $database !== self::SAFE_TEST_DATABASE) {
            throw new RuntimeException(
                "Refusing to run tests against [{$connection}:{$database}]. "
                . 'Tests may only use [mysql:' . self::SAFE_TEST_DATABASE . '].'
            );
        }
    }
}
