<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $isPhpUnitRun = defined('PHPUNIT_COMPOSER_INSTALL') || app()->runningUnitTests();

        if ($isPhpUnitRun) {
            $this->withoutVite();

            $defaultConnection = (string) config('database.default');
            $databaseName = (string) config("database.connections.{$defaultConnection}.database");

            if ($databaseName === 'app') {
                throw new RuntimeException(__('Unsafe test database configuration detected: DB_DATABASE=app in testing environment. Use an isolated testing database.'));
            }
        }
    }
}
