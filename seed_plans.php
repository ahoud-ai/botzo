<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->boot();

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

$plans = [
    // ===== MONTHLY =====
    [
        'uuid'     => (string) Str::uuid(),
        'name'     => 'Starter',
        'name_ar'  => 'المبتدئ',
        'name_en'  => 'Starter',
        'price'    => 99.00,
        'period'   => 'monthly',
        'status'   => 'active',
        'metadata' => json_encode([
            'tier_rank'                         => 1,
            'campaign_limit'                    => 5,
            'message_limit'                     => 2000,
            'contacts_limit'                    => 500,
            'canned_replies_limit'              => 10,
            'team_limit'                        => 2,
            'ai_text_response_limit'            => 50,
            'ai_audio_response_limit'           => -1,
            'ai_organization_key_enabled'       => false,
            'branches_limit'                    => 0,
            'ai_system_key_monthly_quota'       => 50,
            'flow_builder_active_flows_limit'   => 2,
            'flow_builder_nodes_per_flow_limit' => 10,
            'flow_builder_monthly_runs_limit'   => 500,
            'flow_builder_advanced_enabled'     => false,
            'receive_messages_after_expiration' => false,
            'addons' => [
                'Embedded Signup' => false,
                'AI Assistant'    => true,
                'Flow builder'    => true,
            ],
            'custom_features' => [],
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'uuid'     => (string) Str::uuid(),
        'name'     => 'Growth',
        'name_ar'  => 'النمو',
        'name_en'  => 'Growth',
        'price'    => 249.00,
        'period'   => 'monthly',
        'status'   => 'active',
        'metadata' => json_encode([
            'tier_rank'                         => 2,
            'campaign_limit'                    => 20,
            'message_limit'                     => 10000,
            'contacts_limit'                    => 2000,
            'canned_replies_limit'              => 50,
            'team_limit'                        => 5,
            'ai_text_response_limit'            => 300,
            'ai_audio_response_limit'           => -1,
            'ai_organization_key_enabled'       => false,
            'branches_limit'                    => 2,
            'ai_system_key_monthly_quota'       => 300,
            'flow_builder_active_flows_limit'   => 10,
            'flow_builder_nodes_per_flow_limit' => 30,
            'flow_builder_monthly_runs_limit'   => 5000,
            'flow_builder_advanced_enabled'     => true,
            'receive_messages_after_expiration' => false,
            'addons' => [
                'Embedded Signup' => true,
                'AI Assistant'    => true,
                'Flow builder'    => true,
            ],
            'custom_features' => [],
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'uuid'     => (string) Str::uuid(),
        'name'     => 'Pro',
        'name_ar'  => 'المحترف',
        'name_en'  => 'Pro',
        'price'    => 499.00,
        'period'   => 'monthly',
        'status'   => 'active',
        'metadata' => json_encode([
            'tier_rank'                         => 3,
            'campaign_limit'                    => -1,
            'message_limit'                     => -1,
            'contacts_limit'                    => -1,
            'canned_replies_limit'              => -1,
            'team_limit'                        => -1,
            'ai_text_response_limit'            => -1,
            'ai_audio_response_limit'           => -1,
            'ai_organization_key_enabled'       => true,
            'branches_limit'                    => -1,
            'ai_system_key_monthly_quota'       => -1,
            'flow_builder_active_flows_limit'   => -1,
            'flow_builder_nodes_per_flow_limit' => -1,
            'flow_builder_monthly_runs_limit'   => -1,
            'flow_builder_advanced_enabled'     => true,
            'receive_messages_after_expiration' => true,
            'addons' => [
                'Embedded Signup' => true,
                'AI Assistant'    => true,
                'Flow builder'    => true,
            ],
            'custom_features' => [],
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ],
    // ===== YEARLY =====
    [
        'uuid'     => (string) Str::uuid(),
        'name'     => 'Starter',
        'name_ar'  => 'المبتدئ',
        'name_en'  => 'Starter',
        'price'    => 990.00,
        'period'   => 'yearly',
        'status'   => 'active',
        'metadata' => json_encode([
            'tier_rank'                         => 1,
            'campaign_limit'                    => 5,
            'message_limit'                     => 2000,
            'contacts_limit'                    => 500,
            'canned_replies_limit'              => 10,
            'team_limit'                        => 2,
            'ai_text_response_limit'            => 50,
            'ai_audio_response_limit'           => -1,
            'ai_organization_key_enabled'       => false,
            'branches_limit'                    => 0,
            'ai_system_key_monthly_quota'       => 50,
            'flow_builder_active_flows_limit'   => 2,
            'flow_builder_nodes_per_flow_limit' => 10,
            'flow_builder_monthly_runs_limit'   => 500,
            'flow_builder_advanced_enabled'     => false,
            'receive_messages_after_expiration' => false,
            'addons' => [
                'Embedded Signup' => false,
                'AI Assistant'    => true,
                'Flow builder'    => true,
            ],
            'custom_features' => [],
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'uuid'     => (string) Str::uuid(),
        'name'     => 'Growth',
        'name_ar'  => 'النمو',
        'name_en'  => 'Growth',
        'price'    => 2490.00,
        'period'   => 'yearly',
        'status'   => 'active',
        'metadata' => json_encode([
            'tier_rank'                         => 2,
            'campaign_limit'                    => 20,
            'message_limit'                     => 10000,
            'contacts_limit'                    => 2000,
            'canned_replies_limit'              => 50,
            'team_limit'                        => 5,
            'ai_text_response_limit'            => 300,
            'ai_audio_response_limit'           => -1,
            'ai_organization_key_enabled'       => false,
            'branches_limit'                    => 2,
            'ai_system_key_monthly_quota'       => 300,
            'flow_builder_active_flows_limit'   => 10,
            'flow_builder_nodes_per_flow_limit' => 30,
            'flow_builder_monthly_runs_limit'   => 5000,
            'flow_builder_advanced_enabled'     => true,
            'receive_messages_after_expiration' => false,
            'addons' => [
                'Embedded Signup' => true,
                'AI Assistant'    => true,
                'Flow builder'    => true,
            ],
            'custom_features' => [],
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'uuid'     => (string) Str::uuid(),
        'name'     => 'Pro',
        'name_ar'  => 'المحترف',
        'name_en'  => 'Pro',
        'price'    => 4990.00,
        'period'   => 'yearly',
        'status'   => 'active',
        'metadata' => json_encode([
            'tier_rank'                         => 3,
            'campaign_limit'                    => -1,
            'message_limit'                     => -1,
            'contacts_limit'                    => -1,
            'canned_replies_limit'              => -1,
            'team_limit'                        => -1,
            'ai_text_response_limit'            => -1,
            'ai_audio_response_limit'           => -1,
            'ai_organization_key_enabled'       => true,
            'branches_limit'                    => -1,
            'ai_system_key_monthly_quota'       => -1,
            'flow_builder_active_flows_limit'   => -1,
            'flow_builder_nodes_per_flow_limit' => -1,
            'flow_builder_monthly_runs_limit'   => -1,
            'flow_builder_advanced_enabled'     => true,
            'receive_messages_after_expiration' => true,
            'addons' => [
                'Embedded Signup' => true,
                'AI Assistant'    => true,
                'Flow builder'    => true,
            ],
            'custom_features' => [],
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ],
];

DB::table('subscription_plans')->insert($plans);

$inserted = DB::table('subscription_plans')->select('id', 'name', 'name_ar', 'price', 'period', 'status')->get();

echo str_pad('ID', 4) . ' | ' . str_pad('الاسم', 14) . ' | ' . str_pad('السعر', 10) . ' | ' . str_pad('الفترة', 10) . ' | الحالة' . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;

foreach ($inserted as $p) {
    echo str_pad($p->id, 4)
        . ' | ' . str_pad($p->name_ar, 14)
        . ' | ' . str_pad($p->price . ' SAR', 10)
        . ' | ' . str_pad($p->period, 10)
        . ' | ' . $p->status . PHP_EOL;
}

echo PHP_EOL . 'تم إضافة ' . count($plans) . ' باقة بنجاح.' . PHP_EOL;
