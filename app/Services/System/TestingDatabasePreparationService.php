<?php

namespace App\Services\System;

use Symfony\Component\Process\Process;

class TestingDatabasePreparationService
{
    /**
     * @return array{
     *     environment:string,
     *     connection:string,
     *     driver:string,
     *     host:string,
     *     port:string,
     *     database:string,
     *     username:string,
     *     errors:array<int,string>,
     *     warnings:array<int,string>
     * }
     */
    public function safetyReport(): array
    {
        $environment = app()->environment();
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $host = (string) config("database.connections.{$connection}.host", '');
        $port = (string) config("database.connections.{$connection}.port", '');
        $database = (string) config("database.connections.{$connection}.database", '');
        $username = (string) config("database.connections.{$connection}.username", '');
        $errors = [];
        $warnings = [];

        if ($environment !== 'testing') {
            $errors[] = 'APP_ENV must be testing before preparing the testing database.';
        }

        if (app()->configurationIsCached()) {
            $errors[] = 'Configuration cache is enabled. Run `php artisan config:clear` before preparing the testing database.';
        }

        if ($driver !== 'mysql') {
            $errors[] = "Testing database preparation only supports mysql connections. Current driver: {$driver}.";
        }

        if ($database === '') {
            $errors[] = "Database name is empty for connection [{$connection}].";
        }

        if (strtolower($database) === 'app') {
            $errors[] = 'Unsafe testing database detected: DB_DATABASE=app.';
        }

        if ($database !== '' && ! preg_match('/(^|_)(test|testing)(_|$)/i', $database)) {
            $errors[] = "Testing database [{$database}] must clearly be an isolated test database.";
        }

        if ($host === '') {
            $warnings[] = "Database host is empty for connection [{$connection}].";
        }

        return [
            'environment' => $environment,
            'connection' => $connection,
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{database:string,migrate_output:string}
     */
    public function prepare(): array
    {
        $report = $this->safetyReport();
        if ($report['errors'] !== []) {
            throw new \RuntimeException(implode(PHP_EOL, $report['errors']));
        }

        $pdo = new \PDO(
            sprintf(
                'mysql:host=%s;port=%s;charset=utf8mb4',
                $report['host'] !== '' ? $report['host'] : '127.0.0.1',
                $report['port'] !== '' ? $report['port'] : '3306'
            ),
            $report['username'],
            (string) config("database.connections.{$report['connection']}.password", ''),
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]
        );

        $quotedDatabase = sprintf('`%s`', str_replace('`', '``', $report['database']));
        $pdo->exec("DROP DATABASE IF EXISTS {$quotedDatabase}");
        $pdo->exec("CREATE DATABASE {$quotedDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $prepareTestingDatabase = new Process([
            PHP_BINARY,
            base_path('artisan'),
            'migrate',
            '--env=testing',
            '--force',
        ], base_path());

        $prepareTestingDatabase->run();

        if (! $prepareTestingDatabase->isSuccessful()) {
            throw new \RuntimeException(
                "Failed to prepare the testing database.\n"
                .$prepareTestingDatabase->getErrorOutput()
                .$prepareTestingDatabase->getOutput()
            );
        }

        return [
            'database' => $report['database'],
            'migrate_output' => trim($prepareTestingDatabase->getOutput()),
        ];
    }
}
