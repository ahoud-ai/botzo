<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Makes a copy of production data safe to run as staging: wipes platform
 * secrets, forces payment gateways into test mode, and disconnects every
 * organization from WhatsApp so staging can never send or receive as a real
 * customer. Refuses to run in production and requires the database name.
 */
class SystemSanitizeStagingCommand extends Command
{
    protected $signature = 'system:sanitize-staging
        {--confirm-database= : Must equal the configured database name}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Strip secrets and WhatsApp connections from a staging copy of production data.';

    private const NULLED_SETTINGS = [
        'whatsapp_access_token',
        'whatsapp_client_secret',
        'ai_global_api_key',
        'ai_global_api_key_encrypted',
        'aws_access_key',
        'aws_secret_key',
        'pusher_app_id',
        'pusher_app_key',
        'pusher_app_secret',
    ];

    private const ORGANIZATION_SECRET_KEYS = [
        'whatsapp',
        'ai.api_key',
        'ai.api_key_encrypted',
    ];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to sanitize: APP_ENV is production.');

            return self::FAILURE;
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($database === '' || $this->option('confirm-database') !== $database) {
            $this->error("Refusing to sanitize: pass --confirm-database={$database} to confirm the target database.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info(($dryRun ? '[dry run] ' : '')."Sanitizing database: {$database}");

        $report = [];
        $apply = function () use (&$report, $dryRun): void {
            $report['settings'] = $this->sanitizeSettings($dryRun);
            $report['payment_gateways'] = $this->sanitizePaymentGateways($dryRun);
            $report['organizations_disconnected'] = $this->sanitizeOrganizations($dryRun);
            $report['api_keys_revoked'] = $this->revokeApiKeys($dryRun);
            $report['flow_node_secrets_deleted'] = $this->deleteFlowNodeSecrets($dryRun);
            $report['password_reset_tokens_deleted'] = $this->deletePasswordResetTokens($dryRun);
            $report['remember_tokens_cleared'] = $this->clearRememberTokens($dryRun);
        };

        $dryRun ? $apply() : DB::transaction($apply);

        foreach ($report as $item => $count) {
            $this->line(sprintf(' - %s: %d', $item, $count));
        }

        $this->info($dryRun ? 'Dry run finished; nothing was written.' : 'Staging data sanitized.');

        return self::SUCCESS;
    }

    private function sanitizeSettings(bool $dryRun): int
    {
        $replacements = array_fill_keys(self::NULLED_SETTINGS, null) + [
            // New random verify token so Meta can never verify this host as the production webhook.
            'whatsapp_callback_token' => Str::random(40),
            'broadcast_driver' => 'log',
            'mail_config' => json_encode(['driver' => 'log']),
            'google_login' => json_encode(['client_id' => null, 'client_secret' => null]),
            'facebook_login' => json_encode(['client_id' => null, 'client_secret' => null]),
            'allow_google_login' => '0',
            'allow_facebook_login' => '0',
        ];

        $existing = DB::table('settings')->whereIn('key', array_keys($replacements))->pluck('key')->all();

        if (! $dryRun) {
            foreach ($existing as $key) {
                DB::table('settings')->where('key', $key)->update(['value' => $replacements[$key]]);
            }
        }

        return count($existing);
    }

    private function sanitizePaymentGateways(bool $dryRun): int
    {
        $count = 0;

        foreach (DB::table('payment_gateways')->get(['id', 'metadata']) as $gateway) {
            $metadata = json_decode((string) $gateway->metadata, true);
            if (! is_array($metadata)) {
                continue;
            }

            unset($metadata['live']);
            foreach (['mode', 'active_mode'] as $modeKey) {
                if (array_key_exists($modeKey, $metadata)) {
                    $metadata[$modeKey] = 'test';
                }
            }

            // Top-level keys mirror the active mode; point them at the test credentials (or nothing).
            $testKeys = is_array($metadata['test'] ?? null) ? $metadata['test'] : [];
            foreach (['publishable_key', 'secret_key', 'webhook_secret'] as $credential) {
                if (array_key_exists($credential, $metadata)) {
                    $metadata[$credential] = $testKeys[$credential] ?? null;
                }
            }

            if (! $dryRun) {
                DB::table('payment_gateways')->where('id', $gateway->id)->update(['metadata' => json_encode($metadata)]);
            }
            $count++;
        }

        return $count;
    }

    private function sanitizeOrganizations(bool $dryRun): int
    {
        $count = 0;

        foreach (DB::table('organizations')->whereNotNull('metadata')->get(['id', 'metadata']) as $organization) {
            $metadata = json_decode((string) $organization->metadata, true);
            if (! is_array($metadata) || ! Arr::hasAny($metadata, self::ORGANIZATION_SECRET_KEYS)) {
                continue;
            }

            Arr::forget($metadata, self::ORGANIZATION_SECRET_KEYS);

            if (! $dryRun) {
                DB::table('organizations')->where('id', $organization->id)->update(['metadata' => json_encode($metadata)]);
            }
            $count++;
        }

        return $count;
    }

    private function revokeApiKeys(bool $dryRun): int
    {
        $query = DB::table('organization_api_keys')->whereNull('deleted_at');

        return $dryRun ? $query->count() : $query->update(['deleted_at' => now()]);
    }

    private function deleteFlowNodeSecrets(bool $dryRun): int
    {
        if (! Schema::hasTable('automation_flow_node_secrets')) {
            return 0;
        }

        $query = DB::table('automation_flow_node_secrets');

        return $dryRun ? $query->count() : $query->delete();
    }

    private function deletePasswordResetTokens(bool $dryRun): int
    {
        $query = DB::table('password_reset_tokens');

        return $dryRun ? $query->count() : $query->delete();
    }

    private function clearRememberTokens(bool $dryRun): int
    {
        $query = DB::table('users')->whereNotNull('remember_token');

        return $dryRun ? $query->count() : $query->update(['remember_token' => null]);
    }
}
