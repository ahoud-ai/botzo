<?php
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

$orgId    = 2;
$userId   = 3;
$flowName = 'خدمة عملاء متجر النجاح الإلكتروني';

$deleted = DB::table('automation_flows')->where('organization_id', $orgId)->delete();
echo "حُذف {$deleted} فلو قديم\n";

// helpers
function flowNode(string $id, string $type, int $x, int $y, array $config = [], bool $exp = false): array {
    return ['id' => $id, 'type' => $type, 'position' => ['x' => $x, 'y' => $y], 'config' => $config, 'ui' => ['expanded' => $exp]];
}
function flowEdge(string $id, string $src, string $tgt, string $branch = 'default'): array {
    return ['id' => $id, 'source_id' => $src, 'target_id' => $tgt, 'branch' => $branch];
}

$graph = [
    'start_node_id' => 'trigger-1',
    'nodes' => [

        /* ─────────── المحور الرئيسي Y=600 ─────────── */

        flowNode('trigger-1', 'trigger', 80, 600, [
            'match_mode' => 'keyword_match',
            'keywords'   => ['مرحبا', 'هلا', 'أهلا', 'hi', 'hello', 'start', 'بدء'],
        ], true),

        flowNode('welcome-text', 'send_text', 380, 600, [
            'text' => "مرحباً بك في متجر النجاح الإلكتروني! 🌟\n\nأنا مساعدك الذكي، هنا لخدمتك على مدار الساعة.\nكيف يمكنني مساعدتك اليوم؟",
        ]),

        flowNode('main-menu', 'send_buttons', 720, 600, [
            'body'    => 'اختر ما تحتاجه:',
            'buttons' => [
                ['id' => 'products', 'title' => 'تصفح المنتجات'],
                ['id' => 'orders',   'title' => 'متابعة طلب'],
                ['id' => 'support',  'title' => 'الدعم والمساعدة'],
            ],
            'invalid_reply_behavior' => 'release_to_fallback',
        ]),

        /* ─────────── فرع 1: المنتجات Y=0-400 ─────────── */

        flowNode('products-intro', 'send_text', 1120, 200, [
            'text' => "يسعدنا تسوّقك معنا!\nلدينا تشكيلة واسعة من أفضل المنتجات.",
        ]),

        flowNode('categories-menu', 'send_buttons', 1460, 200, [
            'body'    => 'اختر القسم الذي يهمك:',
            'buttons' => [
                ['id' => 'fashion',     'title' => 'الأزياء والموضة'],
                ['id' => 'electronics', 'title' => 'الإلكترونيات'],
                ['id' => 'home',        'title' => 'المنزل والمطبخ'],
            ],
            'invalid_reply_behavior' => 'release_to_fallback',
        ]),

        // 1.1 الأزياء
        flowNode('fashion-offer', 'send_text', 1860, 0, [
            'text' => "قسم الأزياء والموضة\n\nعرض الأسبوع: خصم 30% على كل الملابس!\nشحن مجاني للطلبات فوق 200 ريال\n\nأرسل رقم هاتفك لتلقي العروض الحصرية.",
        ]),
        flowNode('fashion-save-phone', 'save_reply_to_field', 2240, 0, [
            'save_target' => 'session_variable', 'field_uuid' => '', 'variable_key' => 'customer_phone',
        ]),
        flowNode('fashion-confirm', 'send_text', 2580, 0, [
            'text' => "تم تسجيل رقمك بنجاح!\n\nستصلك عروضنا الحصرية أولاً.\nيمكنك تصفح منتجاتنا على: www.store.com",
        ]),
        flowNode('fashion-end', 'end', 2900, 0),

        // 1.2 الإلكترونيات
        flowNode('electronics-intro', 'send_text', 1860, 200, [
            'text' => "قسم الإلكترونيات\n\nأحدث الهواتف والأجهزة بأفضل الأسعار!\nضمان سنتين على جميع الأجهزة\nتوصيل خلال 24 ساعة",
        ]),
        flowNode('electronics-choice', 'send_buttons', 2200, 200, [
            'body'    => 'كيف تود المتابعة؟',
            'buttons' => [
                ['id' => 'talk_specialist', 'title' => 'تحدث مع متخصص'],
                ['id' => 'see_catalog',     'title' => 'عرض الكتالوج'],
            ],
            'invalid_reply_behavior' => 'release_to_fallback',
        ]),
        flowNode('electronics-assign', 'assign_to_agent', 2560, 140, [
            'message' => 'عميل مهتم بقسم الإلكترونيات - يحتاج مساعدة متخصص.',
        ]),
        flowNode('electronics-catalog', 'send_text', 2560, 260, [
            'text' => "كتالوج الإلكترونيات:\n\n- هواتف ذكية: ابتداءً من 899 ريال\n- لابتوب: ابتداءً من 2,499 ريال\n- تابلت: ابتداءً من 1,299 ريال\n- سماعات: ابتداءً من 299 ريال\n\nللطلب أرسل اسم المنتج.",
        ]),
        flowNode('electronics-end', 'end', 2900, 200),

        // 1.3 المنزل
        flowNode('home-intro', 'send_text', 1860, 400, [
            'text' => "قسم المنزل والمطبخ\n\nأكثر من 5,000 منتج للمنزل!\nأدوات مطبخ عالية الجودة\nديكور منزلي بأذواق عصرية\n\nتفضل بزيارة موقعنا للاطلاع على التشكيلة الكاملة.",
        ]),
        flowNode('home-end', 'end', 2240, 400),

        /* ─────────── فرع 2: متابعة الطلب Y=600 ─────────── */

        flowNode('orders-prompt', 'send_text', 1120, 620, [
            'text' => "خدمة متابعة الطلبات\n\nأرسل لي رقم طلبك وسأعطيك آخر المستجدات فوراً.\n\nرقم الطلب يبدأ بـ ORD مثال: ORD-12345",
        ]),
        flowNode('orders-save', 'save_reply_to_field', 1460, 620, [
            'save_target' => 'session_variable', 'field_uuid' => '', 'variable_key' => 'order_number',
        ]),
        flowNode('orders-condition', 'condition', 1800, 620, [
            'source' => 'flow_variable', 'variable_key' => 'order_number', 'operator' => 'starts_with', 'value' => 'ORD',
        ]),
        flowNode('orders-found', 'send_text', 2160, 540, [
            'text' => "تم العثور على طلبك!\n\nطلبك قيد المعالجة.\nالحالة: تم استلامه وجاري التجهيز\nالتوصيل المتوقع: خلال 2-3 أيام عمل",
        ]),
        flowNode('orders-found-end', 'end', 2540, 540),
        flowNode('orders-not-found', 'send_text', 2160, 700, [
            'text' => "لم أتمكن من التحقق من رقم الطلب.\n\nتأكد أن الرقم يبدأ بـ ORD مثل: ORD-12345\n\nإذا استمرت المشكلة، تواصل مع فريق الدعم.",
        ]),
        flowNode('orders-retry-choice', 'send_buttons', 2540, 700, [
            'body'    => 'ماذا تريد الآن؟',
            'buttons' => [
                ['id' => 'retry_order',  'title' => 'أعد إدخال الرقم'],
                ['id' => 'talk_support', 'title' => 'تحدث مع الدعم'],
            ],
            'invalid_reply_behavior' => 'release_to_fallback',
        ]),
        flowNode('orders-retry-end', 'end', 2900, 640),
        flowNode('orders-support-handoff', 'human_handoff', 2900, 760, [
            'message' => 'عميل يحتاج مساعدة في متابعة طلبه.',
        ]),
        flowNode('orders-end', 'end', 3200, 760),

        /* ─────────── فرع 3: الدعم Y=1000-1200 ─────────── */

        flowNode('support-intro', 'send_text', 1120, 1020, [
            'text' => "مركز الدعم والمساعدة\n\nفريقنا متاح 24/7 لخدمتك\nنحن هنا لحل أي مشكلة تواجهها.",
        ]),
        flowNode('support-menu', 'send_buttons', 1460, 1020, [
            'body'    => 'اختر نوع مشكلتك:',
            'buttons' => [
                ['id' => 'payment_issue', 'title' => 'مشكلة في الدفع'],
                ['id' => 'return_item',   'title' => 'إرجاع أو استبدال'],
                ['id' => 'complaint',     'title' => 'شكوى أو اقتراح'],
            ],
            'invalid_reply_behavior' => 'release_to_fallback',
        ]),

        // 3.1 مشكلة دفع
        flowNode('payment-intro', 'send_text', 1860, 880, [
            'text' => "فهمت مشكلة الدفع.\n\nسيتولى وكيل متخصص في المدفوعات مساعدتك مباشرةً.\nيرجى الانتظار لحظة...",
        ]),
        flowNode('payment-handoff', 'human_handoff', 2200, 880, [
            'message' => 'عميل لديه مشكلة في الدفع - يحتاج وكيل متخصص.',
        ]),
        flowNode('payment-end', 'end', 2540, 880),

        // 3.2 إرجاع
        flowNode('return-prompt', 'send_text', 1860, 1040, [
            'text' => "طلب إرجاع أو استبدال\n\nيسعدنا استقبال طلبك.\nأخبرني سبب الإرجاع:\n(معيب / لا يناسب / غير مطابق / أخرى)",
        ]),
        flowNode('return-save-reason', 'save_reply_to_field', 2200, 1040, [
            'save_target' => 'session_variable', 'field_uuid' => '', 'variable_key' => 'return_reason',
        ]),
        flowNode('return-update-field', 'update_contact_field', 2540, 1040, [
            'updates' => [['field_key' => 'return_requested', 'value' => 'yes']],
        ]),
        flowNode('return-confirm', 'send_text', 2880, 1040, [
            'text' => "تم تسجيل طلب الإرجاع بنجاح!\n\nسيتواصل معك فريقنا خلال 24 ساعة.\nشكراً لثقتك بمتجر النجاح.",
        ]),
        flowNode('return-end', 'end', 3200, 1040),

        // 3.3 شكوى/اقتراح
        flowNode('complaint-prompt', 'send_text', 1860, 1200, [
            'text' => "شكاواكم واقتراحاتكم تهمنا!\n\nنحن نستمع ونتحسن باستمرار.\nاكتب ملاحظتك أو اقتراحك بالتفصيل.",
        ]),
        flowNode('complaint-assign', 'assign_to_agent', 2200, 1200, [
            'message' => 'عميل أرسل شكوى أو اقتراح - يحتاج متابعة من الإدارة.',
        ]),
        flowNode('complaint-confirm', 'send_text', 2540, 1200, [
            'text' => "شكراً جزيلاً على ملاحظتك القيّمة!\n\nتم تسجيل رأيك وسيُراجَع من قِبل فريق الجودة.\nنعدك بالتحسين المستمر لخدمتك.",
        ]),
        flowNode('complaint-end', 'end', 2880, 1200),
    ],

    'edges' => [
        /* ─ المحور الرئيسي ─ */
        flowEdge('e-trigger-welcome',    'trigger-1',          'welcome-text'),
        flowEdge('e-welcome-main',       'welcome-text',       'main-menu'),
        flowEdge('e-main-products',      'main-menu',          'products-intro',    'products'),
        flowEdge('e-main-orders',        'main-menu',          'orders-prompt',     'orders'),
        flowEdge('e-main-support',       'main-menu',          'support-intro',     'support'),

        /* ─ فرع المنتجات ─ */
        flowEdge('e-products-cat',       'products-intro',     'categories-menu'),
        flowEdge('e-cat-fashion',        'categories-menu',    'fashion-offer',     'fashion'),
        flowEdge('e-cat-electronics',    'categories-menu',    'electronics-intro', 'electronics'),
        flowEdge('e-cat-home',           'categories-menu',    'home-intro',        'home'),

        // الأزياء
        flowEdge('e-fashion-save',       'fashion-offer',      'fashion-save-phone'),
        flowEdge('e-fashion-confirm',    'fashion-save-phone', 'fashion-confirm'),
        flowEdge('e-fashion-end',        'fashion-confirm',    'fashion-end'),

        // الإلكترونيات
        flowEdge('e-elec-choice',        'electronics-intro',  'electronics-choice'),
        flowEdge('e-elec-assign',        'electronics-choice', 'electronics-assign',  'talk_specialist'),
        flowEdge('e-elec-catalog',       'electronics-choice', 'electronics-catalog', 'see_catalog'),
        flowEdge('e-elec-assign-end',    'electronics-assign', 'electronics-end'),
        flowEdge('e-elec-catalog-end',   'electronics-catalog','electronics-end'),

        // المنزل
        flowEdge('e-home-end',           'home-intro',         'home-end'),

        /* ─ فرع الطلبات ─ */
        flowEdge('e-orders-save',        'orders-prompt',      'orders-save'),
        flowEdge('e-orders-cond',        'orders-save',        'orders-condition'),
        flowEdge('e-orders-found',       'orders-condition',   'orders-found',      'matched'),
        flowEdge('e-orders-notfound',    'orders-condition',   'orders-not-found',  'unmatched'),
        flowEdge('e-orders-found-end',   'orders-found',       'orders-found-end'),
        flowEdge('e-orders-retry',       'orders-not-found',   'orders-retry-choice'),
        flowEdge('e-retry-order',        'orders-retry-choice','orders-retry-end',  'retry_order'),
        flowEdge('e-retry-support',      'orders-retry-choice','orders-support-handoff','talk_support'),
        flowEdge('e-handoff-end',        'orders-support-handoff','orders-end'),

        /* ─ فرع الدعم ─ */
        flowEdge('e-support-menu',       'support-intro',      'support-menu'),
        flowEdge('e-support-payment',    'support-menu',       'payment-intro',     'payment_issue'),
        flowEdge('e-support-return',     'support-menu',       'return-prompt',     'return_item'),
        flowEdge('e-support-complaint',  'support-menu',       'complaint-prompt',  'complaint'),

        // مشكلة دفع
        flowEdge('e-payment-handoff',    'payment-intro',      'payment-handoff'),
        flowEdge('e-payment-end',        'payment-handoff',    'payment-end'),

        // إرجاع
        flowEdge('e-return-save',        'return-prompt',      'return-save-reason'),
        flowEdge('e-return-update',      'return-save-reason', 'return-update-field'),
        flowEdge('e-return-confirm',     'return-update-field','return-confirm'),
        flowEdge('e-return-end',         'return-confirm',     'return-end'),

        // شكوى
        flowEdge('e-complaint-assign',   'complaint-prompt',   'complaint-assign'),
        flowEdge('e-complaint-confirm',  'complaint-assign',   'complaint-confirm'),
        flowEdge('e-complaint-end',      'complaint-confirm',  'complaint-end'),
    ],
];

$uuid = (string) Str::uuid();
$now  = now();

DB::table('automation_flows')->insert([
    'uuid'                    => $uuid,
    'organization_id'         => $orgId,
    'name'                    => $flowName,
    'description'             => 'فلو متكامل لخدمة عملاء متجر إلكتروني: تصفح المنتجات، متابعة الطلبات، الدعم الفني. ثلاثة فروع رئيسية، كل فرع يحتوي على 3 فروع فرعية.',
    'goal_preset'             => 'sales_qualification',
    'channel'                 => 'whatsapp',
    'trigger_type'            => 'keyword_match',
    'status'                  => 'draft',
    'graph_json'              => json_encode($graph, JSON_UNESCAPED_UNICODE),
    'ui_json'                 => json_encode(['active_node_id' => 'main-menu', 'zoom' => 0.45]),
    'has_unpublished_changes' => 1,
    'runs_count'              => 0,
    'created_by'              => $userId,
    'updated_by'              => $userId,
    'created_at'              => $now,
    'updated_at'              => $now,
]);

echo "تم انشاء الفلو:\n";
echo "  UUID: {$uuid}\n";
echo "  Nodes: " . count($graph['nodes']) . "\n";
echo "  Edges: " . count($graph['edges']) . "\n";
echo "  URL: http://localhost:8000/automation/flows/{$uuid}\n";
