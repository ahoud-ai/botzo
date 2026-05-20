<?php

namespace App\Console\Commands;

use App\Services\AutomationFlows\AutomationFlowAccessService;
use App\Services\System\RuntimeReadinessService;
use App\Models\Addon;
use App\Models\Language;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SystemHealthCheckCommand extends Command
{
    protected $signature = 'system:health-check {--strict : Return non-zero on any warning}';

    protected $description = 'Validate core data and critical configuration before deployment.';

    public function handle(
        RuntimeReadinessService $runtimeReadiness,
        AutomationFlowAccessService $automationFlowAccess
    ): int
    {
        $errors = [];
        $warnings = [];

        foreach (['settings', 'addons', 'languages'] as $table) {
            if (!Schema::hasTable($table)) {
                $errors[] = "Missing required table: {$table}";
            }
        }

        if (empty($errors)) {
            $this->checkSettings($errors, $warnings);
            $this->checkAddons($errors, $warnings);
            $this->checkLanguages($errors, $warnings);
            $this->checkAutomationFlowSchema($errors, $warnings, $automationFlowAccess);
            $this->checkRuntimeReadiness($errors, $warnings, $runtimeReadiness);
        }

        if (!empty($errors)) {
            $this->error('Health check failed:');
            foreach ($errors as $error) {
                $this->line(" - {$error}");
            }
        } else {
            $this->info('Health check passed.');
        }

        if (!empty($warnings)) {
            $this->warn('Warnings:');
            foreach ($warnings as $warning) {
                $this->line(" - {$warning}");
            }
        }

        if (!empty($errors)) {
            return self::FAILURE;
        }

        if ($this->option('strict') && !empty($warnings)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function checkSettings(array &$errors, array &$warnings): void
    {
        $requiredKeys = ['storage_system', 'currency', 'timezone', 'broadcast_driver'];
        foreach ($requiredKeys as $key) {
            $value = Setting::query()->where('key', $key)->value('value');
            if (!is_string($value) || trim($value) === '') {
                $errors[] = "Missing critical setting: {$key}";
            }
        }

        if (Setting::query()->count() < 10) {
            $warnings[] = 'Settings table has unusually low row count.';
        }
    }

    private function checkAddons(array &$errors, array &$warnings): void
    {
        $requiredAddons = ['AI Assistant', 'Flow builder'];
        foreach ($requiredAddons as $name) {
            $exists = Addon::query()->where('name', $name)->exists();
            if (!$exists) {
                $errors[] = "Missing required addon row: {$name}";
            }
        }

        if (Addon::query()->count() < count($requiredAddons)) {
            $warnings[] = 'Addons table has unusually low row count.';
        }
    }

    private function checkLanguages(array &$errors, array &$warnings): void
    {
        foreach (['en', 'ar'] as $code) {
            $language = Language::query()->where('code', $code)->first();
            if (!$language) {
                $errors[] = "Missing required language: {$code}";
                continue;
            }

            if ($language->status !== 'active') {
                $warnings[] = "Language {$code} is not active.";
            }
        }

        if (Language::query()->count() < 2) {
            $warnings[] = 'Languages table has fewer than two rows.';
        }
    }

    private function checkRuntimeReadiness(array &$errors, array &$warnings, RuntimeReadinessService $runtimeReadiness): void
    {
        $report = $runtimeReadiness->evaluate();
        foreach ($report['checks'] as $name => $status) {
            if (in_array($status, ['down', 'mismatch'], true)) {
                $errors[] = "Runtime readiness check failed: {$name}={$status}";
            }
        }

        if (($report['checks']['workers'] ?? null) === 'configured' && count($report['checks']) < 5) {
            $warnings[] = 'Runtime readiness report is unexpectedly incomplete.';
        }
    }

    private function checkAutomationFlowSchema(
        array &$errors,
        array &$warnings,
        AutomationFlowAccessService $automationFlowAccess
    ): void {
        $report = $automationFlowAccess->readinessReport();

        if ($automationFlowAccess->runtimeEnabled()) {
            foreach (($report['missing_tables'] ?? []) as $table) {
                $errors[] = "Missing Flow Builder table: {$table}";
            }

            return;
        }

        foreach (($report['missing_tables'] ?? []) as $table) {
            $warnings[] = "Flow Builder runtime is disabled and table {$table} is missing.";
        }
    }
}
