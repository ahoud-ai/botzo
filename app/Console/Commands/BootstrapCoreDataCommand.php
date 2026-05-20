<?php

namespace App\Console\Commands;

use App\Models\Addon;
use App\Models\Language;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Support\SaClientPlanProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BootstrapCoreDataCommand extends Command
{
    protected $signature = 'system:bootstrap-core-data {--strict : Fail if core tables are missing}';

    protected $description = 'Bootstrap minimum required rows in settings, addons, and languages.';

    public function handle(): int
    {
        $requiredTables = ['settings', 'addons', 'languages'];
        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("Missing table: {$table}");
                return $this->option('strict') ? self::FAILURE : self::SUCCESS;
            }
        }

        $this->bootstrapSettings();
        $this->bootstrapLanguages();
        $this->bootstrapAddons();
        $this->bootstrapPlanMetadata();

        $this->newLine();
        $this->info('Core data bootstrap completed.');
        $this->line('settings: '.Setting::query()->count());
        $this->line('addons: '.Addon::query()->count());
        $this->line('languages: '.Language::query()->count());

        return self::SUCCESS;
    }

    private function bootstrapSettings(): void
    {
        $defaults = [
            'storage_system' => 'local',
            'currency' => 'SAR',
            'timezone' => 'Asia/Riyadh',
            'broadcast_driver' => 'pusher',
            'date_format' => 'd-M-y',
            'time_format' => 'H:i',
            'verify_email' => '0',
            'frontend_variant' => 'classic',
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    private function bootstrapLanguages(): void
    {
        Language::query()->updateOrCreate(
            ['code' => 'en'],
            ['name' => 'English', 'status' => 'active', 'is_rtl' => false, 'deleted_at' => null, 'deleted_by' => null]
        );

        Language::query()->updateOrCreate(
            ['code' => 'ar'],
            ['name' => 'Arabic', 'status' => 'active', 'is_rtl' => true, 'deleted_at' => null, 'deleted_by' => null]
        );
    }

    private function bootstrapAddons(): void
    {
        Addon::query()
            ->whereIn('name', SaClientPlanProfile::retiredAddonNames())
            ->delete();

        Addon::query()
            ->whereNotIn('name', SaClientPlanProfile::planAddonNames())
            ->where('is_plan_restricted', 1)
            ->update(['is_plan_restricted' => 0]);

        $rows = [
            [
                'name' => 'AI Assistant',
                'category' => 'ai',
                'logo' => 'ai.png',
                'description' => __('The AI assistant delivers intelligent, AI-driven responses by utilizing user data for training.'),
                'metadata' => '{"name":"IntelliReply"}',
                'status' => 1,
                'is_active' => 0,
                'is_plan_restricted' => 1,
            ],
            [
                'name' => 'Flow builder',
                'category' => 'automation',
                'logo' => 'flow_builder.png',
                'description' => __('Build WhatsApp qualification journeys with CRM updates and visual customer paths.'),
                'metadata' => '{"name":"FlowBuilder"}',
                'status' => 1,
                'is_active' => 0,
                'is_plan_restricted' => 1,
            ],
        ];

        foreach ($rows as $row) {
            $addon = Addon::query()->where('name', $row['name'])->first();
            if (!$addon) {
                Addon::query()->create($row);
                continue;
            }

            $updates = [];
            foreach (['category', 'logo', 'description', 'metadata'] as $column) {
                $current = $addon->{$column};
                if (!is_string($current) || trim($current) === '') {
                    $updates[$column] = $row[$column];
                }
            }

            if ($addon->name === 'Flow builder') {
                $metadata = is_string($addon->metadata) && trim($addon->metadata) !== ''
                    ? json_decode($addon->metadata, true)
                    : [];
                $metadata = is_array($metadata) ? $metadata : [];
                unset($metadata['decommissioned'], $metadata['internal_only']);
                $metadata['name'] = $metadata['name'] ?? 'FlowBuilder';

                $updates['description'] = $row['description'];
                $updates['metadata'] = json_encode($metadata);
                $updates['is_plan_restricted'] = 1;
            }

            foreach (['status', 'is_active', 'is_plan_restricted'] as $column) {
                if ($addon->{$column} === null) {
                    $updates[$column] = $row[$column];
                }
            }

            if (!empty($updates)) {
                $addon->update($updates);
            }
        }
    }

    private function bootstrapPlanMetadata(): void
    {
        if (!Schema::hasTable('subscription_plans')) {
            return;
        }

        SubscriptionPlan::query()
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(100, function ($plans): void {
                foreach ($plans as $plan) {
                    $metadata = json_decode((string) $plan->metadata, true);
                    if (!is_array($metadata)) {
                        continue;
                    }

                    $sanitized = SaClientPlanProfile::sanitizePlanMetadata($metadata);
                    if ($sanitized === $metadata) {
                        continue;
                    }

                    $plan->update(['metadata' => json_encode($sanitized)]);
                }
            });
    }
}
