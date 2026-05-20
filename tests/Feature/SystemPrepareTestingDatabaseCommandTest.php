<?php

namespace Tests\Feature;

use App\Services\System\TestingDatabasePreparationService;
use Mockery;
use Tests\TestCase;

class SystemPrepareTestingDatabaseCommandTest extends TestCase
{
    public function test_prepare_testing_database_requires_force(): void
    {
        $mock = Mockery::mock(TestingDatabasePreparationService::class);
        $mock->shouldReceive('safetyReport')->once()->andReturn([
            'environment' => 'testing',
            'connection' => 'mysql',
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'app_testing',
            'username' => 'root',
            'errors' => [],
            'warnings' => [],
        ]);
        $mock->shouldNotReceive('prepare');
        $this->instance(TestingDatabasePreparationService::class, $mock);

        $this->artisan('system:prepare-testing-database')
            ->expectsOutput('Refusing to recreate the testing database without --force.')
            ->assertExitCode(1);
    }

    public function test_prepare_testing_database_uses_service_when_force_is_present(): void
    {
        $mock = Mockery::mock(TestingDatabasePreparationService::class);
        $mock->shouldReceive('safetyReport')->once()->andReturn([
            'environment' => 'testing',
            'connection' => 'mysql',
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'app_testing',
            'username' => 'root',
            'errors' => [],
            'warnings' => [],
        ]);
        $mock->shouldReceive('prepare')->once()->andReturn([
            'database' => 'app_testing',
            'migrate_output' => 'Migrated',
        ]);
        $this->instance(TestingDatabasePreparationService::class, $mock);

        $this->artisan('system:prepare-testing-database', ['--force' => true])
            ->expectsOutput('Preparing isolated testing database...')
            ->expectsOutput(' - environment: testing')
            ->expectsOutput(' - connection: mysql')
            ->expectsOutput(' - database: app_testing')
            ->expectsOutput('Testing database prepared successfully.')
            ->expectsOutput('Migrated')
            ->assertExitCode(0);
    }
}
