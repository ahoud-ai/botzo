<?php

namespace App\Console\Commands;

use App\Services\System\TestingDatabasePreparationService;
use Illuminate\Console\Command;

class SystemTestSafetyCheckCommand extends Command
{
    protected $signature = 'system:test-safety-check';

    protected $description = 'Validate testing environment safety before running phpunit.';

    public function handle(TestingDatabasePreparationService $preparation): int
    {
        $report = $preparation->safetyReport();
        $errors = $report['errors'];
        $warnings = $report['warnings'];

        if (! empty($errors)) {
            $this->error('Test safety check failed:');
            foreach ($errors as $error) {
                $this->line(" - {$error}");
            }

            if (! empty($warnings)) {
                $this->warn('Warnings:');
                foreach ($warnings as $warning) {
                    $this->line(" - {$warning}");
                }
            }

            return self::FAILURE;
        }

        $this->info('Test safety check passed.');
        $this->line(" - environment: {$report['environment']}");
        $this->line(" - default connection: {$report['connection']}");
        $this->line(" - database: {$report['database']}");

        if (! empty($warnings)) {
            $this->warn('Warnings:');
            foreach ($warnings as $warning) {
                $this->line(" - {$warning}");
            }
        }

        return self::SUCCESS;
    }
}
