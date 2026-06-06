<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AutomationFlow;
use App\Models\AutomationFlowVersion;
use Illuminate\Support\Str;

// Cleanup
AutomationFlow::where('organization_id', 2)
    ->where('name', 'اختبار حقيقي - خدمة العملاء')
    ->forceDelete();

$graph = [
    'start_node_id' => 'trigger-1',
    'nodes' => [
        [
            'id' => 'trigger-1', 'type' => 'trigger',
            'position' => ['x' => 80, 'y' => 200],
            'config' => ['match_mode' => 'any_incoming', 'keywords' => []],
            'ui' => ['expanded' => true],
        ],
        [
            'id' => 'welcome-1', 'type' => 'send_text',
            'position' => ['x' => 380, 'y' => 200],
            'config' => ['text' => "أهلاً وسهلاً بك في خدمة عملاء Botzo! 👋\nكيف يمكننا مساعدتك اليوم؟"],
            'ui' => [],
        ],
        [
            'id' => 'menu-1', 'type' => 'send_buttons',
            'position' => ['x' => 680, 'y' => 200],
            'config' => [
                'body'   => 'اختر من الخيارات التالية:',
                'header' => 'قائمة الخدمات',
                'footer' => 'Botzo Platform',
                'buttons' => [
                    ['id' => 'pricing', 'title' => 'الأسعار والباقات'],
                    ['id' => 'support', 'title' => 'دعم تقني'],
                    ['id' => 'demo',    'title' => 'تجربة مجانية'],
                ],
                'invalid_reply_behavior' => 'repeat_prompt',
            ],
            'ui' => [],
        ],
        [
            'id' => 'reply-pricing', 'type' => 'send_text',
            'position' => ['x' => 1080, 'y' => 40],
            'config' => ['text' => "باقاتنا:\n\nStarter: 149 ريال شهرياً\nPro: 349 ريال شهرياً\nEnterprise: تواصل معنا\n\nجميع الباقات تشمل واتساب مؤتمت وذكاء اصطناعي وتقارير مفصّلة."],
            'ui' => [],
        ],
        [
            'id' => 'reply-support', 'type' => 'send_text',
            'position' => ['x' => 1080, 'y' => 240],
            'config' => ['text' => "للدعم التقني:\n\nسيتواصل معك أحد المختصين خلال دقائق.\nأو راسلنا على: support@botzo.net"],
            'ui' => [],
        ],
        [
            'id' => 'reply-demo', 'type' => 'send_text',
            'position' => ['x' => 1080, 'y' => 440],
            'config' => ['text' => "رائع! سنرتّب لك تجربة مجانية.\n\nسيتواصل معك فريق المبيعات خلال 24 ساعة.\n\nشكراً لاختيارك Botzo!"],
            'ui' => [],
        ],
        [
            'id' => 'end-1', 'type' => 'end',
            'position' => ['x' => 1420, 'y' => 240],
            'config' => [],
            'ui' => [],
        ],
    ],
    'edges' => [
        ['id' => 'e1', 'source_id' => 'trigger-1',     'target_id' => 'welcome-1',    'branch' => 'default'],
        ['id' => 'e2', 'source_id' => 'welcome-1',     'target_id' => 'menu-1',        'branch' => 'default'],
        ['id' => 'e3', 'source_id' => 'menu-1',        'target_id' => 'reply-pricing', 'branch' => 'pricing'],
        ['id' => 'e4', 'source_id' => 'menu-1',        'target_id' => 'reply-support', 'branch' => 'support'],
        ['id' => 'e5', 'source_id' => 'menu-1',        'target_id' => 'reply-demo',    'branch' => 'demo'],
        ['id' => 'e6', 'source_id' => 'reply-pricing', 'target_id' => 'end-1',         'branch' => 'default'],
        ['id' => 'e7', 'source_id' => 'reply-support', 'target_id' => 'end-1',         'branch' => 'default'],
        ['id' => 'e8', 'source_id' => 'reply-demo',    'target_id' => 'end-1',         'branch' => 'default'],
    ],
];

// Create Flow
$flow = AutomationFlow::create([
    'uuid'                    => (string) Str::uuid(),
    'organization_id'         => 2,
    'name'                    => 'اختبار حقيقي - خدمة العملاء',
    'description'             => 'مسار خدمة عملاء كامل للاختبار',
    'goal_preset'             => 'support_routing',
    'channel'                 => 'whatsapp',
    'trigger_type'            => 'incoming_whatsapp_message',
    'status'                  => 'draft',
    'has_unpublished_changes' => true,
    'created_by'              => 4,
    'updated_by'              => 4,
    'graph_json'              => $graph,
    'ui_json'                 => [
        'surface'    => ['variant' => 'canvas'],
        'mode'       => 'simple',
        'selection'  => ['active_node_id' => 'welcome-1'],
        'canvas'     => ['expanded_node_id' => null],
        'right_dock' => ['tab' => 'inspector'],
        'preview'    => ['collapsed' => false, 'mode' => 'whatsapp'],
    ],
]);

// Publish it
$builderService = app(App\Services\AutomationFlows\AutomationFlowBuilderService::class);
$published = $builderService->publish($flow, 2, 4);

echo json_encode([
    'uuid'    => $flow->uuid,
    'name'    => $flow->name,
    'status'  => $published->status,
    'version' => $published->currentVersion->version_number,
]);
