<?php

namespace App\Console\Commands;

use App\Services\System\TestingDatabasePreparationService;
use Illuminate\Console\Command;

class SystemPrepareTestingDatabaseCommand extends Command
{
    protected $signature = 'system:prepare-testing-database
        {--force : Allow destructive recreation of the isolated testing database}';

    protected $description = 'Explicitly recreate the isolated testing database and run testing migrations.';

    public function handle(TestingDatabasePreparationService $preparation): int
    {
        $report = $preparation->safetyReport();

        if (! $this->option('force')) {
            $this->error('Refusing to recreate the testing database without --force.');
            $this->line('Run `php artisan system:prepare-testing-database --force --env=testing` once you have verified the connection.');

            return self::FAILURE;
        }

        if ($report['errors'] !== []) {
            $this->error('Testing database preparation failed safety checks:');
            foreach ($report['errors'] as $error) {
                $this->line(" - {$error}");
            }

            if ($report['warnings'] !== []) {
                $this->warn('Warnings:');
                foreach ($report['warnings'] as $warning) {
                    $this->line(" - {$warning}");
                }
            }

            return self::FAILURE;
        }

        $this->info('Preparing isolated testing database...');
        $this->line(' - environment: '.$report['environment']);
        $this->line(' - connection: '.$report['connection']);
        $this->line(' - database: '.$report['database']);

        if ($report['warnings'] !== []) {
            $this->warn('Warnings:');
            foreach ($report['warnings'] as $warning) {
                $this->line(" - {$warning}");
            }
        }

        try {
            $result = $preparation->prepare();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Testing database prepared successfully.');
        if ($result['migrate_output'] !== '') {
            $this->line($result['migrate_output']);
        }

        return self::SUCCESS;
    }
}
