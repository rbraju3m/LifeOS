<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Test-only tables (tests/Fixtures/Migrations) are migrated with the
     * real ones, so fixtures never need DDL inside a test transaction.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $app->make('migrator')->path(__DIR__.'/Fixtures/Migrations');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Tests refresh (wipe) their database. Never let that happen to a
        // development or production database because of a misconfigured env.
        $database = config('database.connections.'.config('database.default').'.database');

        if (! is_string($database) || ! str_ends_with($database, '_test')) {
            throw new RuntimeException("Refusing to run tests against database [{$database}]; its name must end in _test.");
        }
    }
}
