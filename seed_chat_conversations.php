<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

// حذف البيانات القديمة
DB::table('chat_logs')->whereIn('contact_id', [1,2,3])->delete();
DB::table('chats')->where('organization_id', 2)->delete();
DB::table('contacts')->where('organization_id', 2)->update(['latest_chat_created_at' => null]);

// ─────────────────────────────────────────────────────────
// محادثة أحمد: اختار "الأسعار والباقات"
// ─────────────────────────────────────────────────────────
$conversations = [
    [
        'contact_id' => 1,
        'messages' => [
            ['inbound',  'text', 'مرحبا'],
            ['outbound', 'text', "أهلاً وسهلاً بك في خدمة عملاء Botzo! 👋\nكيف يمكننا مساعدتك اليوم؟"],
            ['outbound', 'interactive_buttons', json_encode([
                'type'   => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'header' => ['type' => 'text', 'text' => 'قائمة الخدمات'],
                    'body'   => ['text' => 'اختر من الخيارات التالية:'],
                    'footer' => ['text' => 'Botzo Platform'],
                    'action' => ['buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => 'pricing', 'title' => 'الأسعار والباقات']],
                        ['type' => 'reply', 'reply' => ['id' => 'support', 'title' => 'دعم تقني']],
                        ['type' => 'reply', 'reply' => ['id' => 'demo',    'title' => 'تجربة مجانية']],
                    ]],
                ],
            ])],
            ['inbound',  'button_reply', 'الأسعار والباقات'],
            ['outbound', 'text', "باقاتنا:\n\nStarter: 149 ريال شهرياً\nPro: 349 ريال شهرياً\nEnterprise: تواصل معنا\n\nجميع الباقات تشمل واتساب مؤتمت وذكاء اصطناعي وتقارير مفصّلة."],
        ],
    ],
    // ─────────────────────────────────────────────────────────
    // محادثة سارة: اختارت "دعم تقني"
    // ─────────────────────────────────────────────────────────
    [
        'contact_id' => 2,
        'messages' => [
            ['inbound',  'text', 'السلام عليكم'],
            ['outbound', 'text', "أهلاً وسهلاً بك في خدمة عملاء Botzo! 👋\nكيف يمكننا مساعدتك اليوم؟"],
            ['outbound', 'interactive_buttons', json_encode([
                'type'   => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'header' => ['type' => 'text', 'text' => 'قائمة الخدمات'],
                    'body'   => ['text' => 'اختر من الخيارات التالية:'],
                    'footer' => ['text' => 'Botzo Platform'],
                    'action' => ['buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => 'pricing', 'title' => 'الأسعار والباقات']],
                        ['type' => 'reply', 'reply' => ['id' => 'support', 'title' => 'دعم تقني']],
                        ['type' => 'reply', 'reply' => ['id' => 'demo',    'title' => 'تجربة مجانية']],
                    ]],
                ],
            ])],
            ['inbound',  'button_reply', 'دعم تقني'],
            ['outbound', 'text', "للدعم التقني:\n\nسيتواصل معك أحد المختصين خلال دقائق.\nأو راسلنا على: support@botzo.net"],
        ],
    ],
    // ─────────────────────────────────────────────────────────
    // محادثة محمد: اختار "تجربة مجانية"
    // ─────────────────────────────────────────────────────────
    [
        'contact_id' => 3,
        'messages' => [
            ['inbound',  'text', 'أريد معرفة المزيد عن المنصة'],
            ['outbound', 'text', "أهلاً وسهلاً بك في خدمة عملاء Botzo! 👋\nكيف يمكننا مساعدتك اليوم؟"],
            ['outbound', 'interactive_buttons', json_encode([
                'type'   => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'header' => ['type' => 'text', 'text' => 'قائمة الخدمات'],
                    'body'   => ['text' => 'اختر من الخيارات التالية:'],
                    'footer' => ['text' => 'Botzo Platform'],
                    'action' => ['buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => 'pricing', 'title' => 'الأسعار والباقات']],
                        ['type' => 'reply', 'reply' => ['id' => 'support', 'title' => 'دعم تقني']],
                        ['type' => 'reply', 'reply' => ['id' => 'demo',    'title' => 'تجربة مجانية']],
                    ]],
                ],
            ])],
            ['inbound',  'button_reply', 'تجربة مجانية'],
            ['outbound', 'text', "رائع! سنرتّب لك تجربة مجانية.\n\nسيتواصل معك فريق المبيعات خلال 24 ساعة.\n\nشكراً لاختيارك Botzo! 🚀"],
        ],
    ],
];

foreach ($conversations as $conv) {
    $contactId = $conv['contact_id'];
    $baseTime  = now()->subMinutes(rand(20, 120));
    $lastChatCreatedAt = null;

    foreach ($conv['messages'] as $i => $msg) {
        [$type, $msgType, $content] = $msg;
        $createdAt = $baseTime->copy()->addSeconds($i * 25);

        // metadata format
        if ($msgType === 'text') {
            // Frontend reads: JSON.parse(metadata).text?.body  — must be nested
            $metadata = json_encode(['type' => 'text', 'text' => ['body' => $content]]);
        } elseif ($msgType === 'button_reply') {
            // Customer pressing a button: JSON.parse(metadata).button.text
            $metadata = json_encode(['type' => 'button', 'button' => ['text' => $content]]);
        } else {
            // interactive buttons — already JSON
            $metadata = $content;
        }

        // Insert chat
        $chatUuid = (string) Str::uuid();
        DB::table('chats')->insert([
            'uuid'            => $chatUuid,
            'organization_id' => 2,
            'contact_id'      => $contactId,
            'type'            => $type,
            'metadata'        => $metadata,
            'is_read'         => 1,
            'status'          => $type === 'outbound' ? 'delivered' : null,
            'created_at'      => $createdAt,
        ]);

        $chatId = DB::table('chats')->where('uuid', $chatUuid)->value('id');
        $lastChatCreatedAt = $createdAt;

        // Insert chat_log
        DB::table('chat_logs')->insert([
            'contact_id'  => $contactId,
            'entity_type' => 'chat',
            'entity_id'   => $chatId,
            'created_at'  => $createdAt,
        ]);
    }

    // Update latest_chat_created_at
    DB::table('contacts')->where('id', $contactId)->update([
        'latest_chat_created_at' => $lastChatCreatedAt,
        'updated_at'             => now(),
    ]);

    $contact = DB::table('contacts')->where('id', $contactId)->first();
    echo "✅ {$contact->first_name}: " . count($conv['messages']) . " رسالة\n";
}

echo "\nChats total: "  . DB::table('chats')->where('organization_id', 2)->count() . "\n";
echo "ChatLogs total: " . DB::table('chat_logs')->whereIn('contact_id', [1,2,3])->count() . "\n";
