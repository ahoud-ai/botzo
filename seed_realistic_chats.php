<?php
/**
 * seed_realistic_chats.php
 * يحذف كل المحادثات ويُنشئ محادثات واقعية تحاكي الفلو بلدر
 * تشغيل: php artisan tinker --execute="require 'seed_realistic_chats.php';"
 */

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$orgId  = 2;
$userId = 3; // المستخدم المالك

/* ══════════════════════════════════════════════════
   1. مسح البيانات القديمة
══════════════════════════════════════════════════ */

// احذف chat_tickets المرتبطة بجهات الاتصال التابعة للمؤسسة
$contactIds = DB::table('contacts')
    ->where('organization_id', $orgId)
    ->whereNull('deleted_at')
    ->pluck('id')
    ->toArray();

DB::table('chat_tickets')->whereIn('contact_id', $contactIds)->delete();
$deletedChats = DB::table('chats')->where('organization_id', $orgId)->delete();
DB::table('contacts')->where('organization_id', $orgId)
    ->update(['latest_chat_created_at' => null]);

echo "✓ حُذف {$deletedChats} محادثة قديمة\n";

/* ══════════════════════════════════════════════════
   2. دوال مساعدة
══════════════════════════════════════════════════ */

$wamCounter = 1000;
function wamId(): string {
    global $wamCounter;
    return 'wamid.' . str_pad((string)($wamCounter++), 15, '0', STR_PAD_LEFT);
}

function chatMsg(
    int     $orgId,
    int     $contactId,
    int     $userId,
    string  $type,
    string  $message,
    Carbon  $time,
    bool    $isRead = true,
    string  $status = '',
    ?string $wamId  = null
): void {
    $isInbound = ($type === 'inbound');
    DB::table('chats')->insert([
        'uuid'            => (string) Str::uuid(),
        'organization_id' => $orgId,
        'wam_id'          => $wamId ?? wamId(),
        'contact_id'      => $contactId,
        'user_id'         => $isInbound ? null : $userId,
        'type'            => $type,
        'metadata'        => json_encode(['message' => $message, 'type' => 'text'], JSON_UNESCAPED_UNICODE),
        'media_id'        => null,
        'status'          => $status ?: ($isInbound ? 'received' : 'delivered'),
        'is_read'         => $isInbound ? ($isRead ? 1 : 0) : 1,
        'deleted_by'      => null,
        'deleted_at'      => null,
        'created_at'      => $time->toDateTimeString(),
    ]);
    // تحديث latest_chat_created_at على جهة الاتصال
    DB::table('contacts')->where('id', $contactId)
        ->where(function($q) use ($time) {
            $q->whereNull('latest_chat_created_at')
              ->orWhere('latest_chat_created_at', '<', $time->toDateTimeString());
        })
        ->update(['latest_chat_created_at' => $time->toDateTimeString()]);
}

function buttonsMsg(int $orgId, int $contactId, int $userId, string $body, array $buttons, Carbon $time): void {
    $payload = [
        'message' => $body,
        'type'    => 'interactive',
        'interactive' => [
            'type'   => 'button',
            'body'   => ['text' => $body],
            'action' => ['buttons' => array_map(fn($b) => [
                'type' => 'reply',
                'reply' => ['id' => $b['id'], 'title' => $b['title']],
            ], $buttons)],
        ],
    ];
    DB::table('chats')->insert([
        'uuid'            => (string) Str::uuid(),
        'organization_id' => $orgId,
        'wam_id'          => wamId(),
        'contact_id'      => $contactId,
        'user_id'         => $userId,
        'type'            => 'outbound',
        'metadata'        => json_encode($payload, JSON_UNESCAPED_UNICODE),
        'media_id'        => null,
        'status'          => 'delivered',
        'is_read'         => 1,
        'deleted_by'      => null,
        'deleted_at'      => null,
        'created_at'      => $time->toDateTimeString(),
    ]);
    DB::table('contacts')->where('id', $contactId)
        ->where(function($q) use ($time) {
            $q->whereNull('latest_chat_created_at')
              ->orWhere('latest_chat_created_at', '<', $time->toDateTimeString());
        })
        ->update(['latest_chat_created_at' => $time->toDateTimeString()]);
}

function addTicket(int $contactId, int $assignedTo = null, string $status = 'open'): void {
    $existing = DB::table('chat_tickets')->where('contact_id', $contactId)->first();
    if (!$existing) {
        DB::table('chat_tickets')->insert([
            'contact_id'  => $contactId,
            'assigned_to' => $assignedTo,
            'status'      => $status,
            'created_at'  => now()->toDateTimeString(),
            'updated_at'  => now()->toDateTimeString(),
        ]);
    }
}

/* ══════════════════════════════════════════════════
   3. المحادثات الواقعية
   الوقت الأساسي = الآن - 3 ساعات، كل محادثة تبدأ بعد 15 دقيقة
══════════════════════════════════════════════════ */

$base = Carbon::now()->subHours(3);
$t    = clone $base;

/* ─────────────────────────────────────────────
   [1] أحمد - المنتجات → الأزياء (محادثة مكتملة)
───────────────────────────────────────────── */
$c1 = 1; $t->addMinutes(0);
chatMsg($orgId, $c1, $userId, 'inbound', 'مرحبا', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c1, $userId, 'outbound',
    "مرحباً بك في متجر النجاح الإلكتروني! 🌟\nأنا مساعدك الذكي، هنا لخدمتك على مدار الساعة.\nكيف يمكنني مساعدتك اليوم؟",
    (clone $t));
$t->addSeconds(10);
buttonsMsg($orgId, $c1, $userId, 'اختر ما تحتاجه:', [
    ['id' => 'products', 'title' => 'تصفح المنتجات'],
    ['id' => 'orders',   'title' => 'متابعة طلب'],
    ['id' => 'support',  'title' => 'الدعم والمساعدة'],
], (clone $t));
$t->addSeconds(25);
chatMsg($orgId, $c1, $userId, 'inbound', 'تصفح المنتجات', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c1, $userId, 'outbound',
    "يسعدنا تسوّقك معنا!\nلدينا تشكيلة واسعة من أفضل المنتجات.",
    (clone $t));
$t->addSeconds(6);
buttonsMsg($orgId, $c1, $userId, 'اختر القسم الذي يهمك:', [
    ['id' => 'fashion',     'title' => 'الأزياء والموضة'],
    ['id' => 'electronics', 'title' => 'الإلكترونيات'],
    ['id' => 'home',        'title' => 'المنزل والمطبخ'],
], (clone $t));
$t->addSeconds(40);
chatMsg($orgId, $c1, $userId, 'inbound', 'الأزياء والموضة', (clone $t));
$t->addSeconds(7);
chatMsg($orgId, $c1, $userId, 'outbound',
    "قسم الأزياء والموضة 👗\n\nعرض الأسبوع: خصم 30% على كل الملابس!\nشحن مجاني للطلبات فوق 200 ريال\n\nأرسل رقم هاتفك لتلقي العروض الحصرية.",
    (clone $t));
$t->addSeconds(60);
chatMsg($orgId, $c1, $userId, 'inbound', '+966501111111', (clone $t));
$t->addSeconds(5);
chatMsg($orgId, $c1, $userId, 'outbound',
    "تم تسجيل رقمك بنجاح! ✅\n\nستصلك عروضنا الحصرية أولاً.\nيمكنك تصفح منتجاتنا على: www.store.com",
    (clone $t));
addTicket($c1, null, 'closed');
echo "✓ محادثة 1: أحمد (منتجات → أزياء، مكتملة)\n";

/* ─────────────────────────────────────────────
   [2] فاطمة - المنتجات → إلكترونيات → متخصص
───────────────────────────────────────────── */
$c2 = 2; $t->addMinutes(15);
chatMsg($orgId, $c2, $userId, 'inbound', 'هلا', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c2, $userId, 'outbound',
    "مرحباً بك في متجر النجاح الإلكتروني! 🌟\nكيف يمكنني مساعدتك اليوم؟",
    (clone $t));
$t->addSeconds(10);
buttonsMsg($orgId, $c2, $userId, 'اختر ما تحتاجه:', [
    ['id' => 'products', 'title' => 'تصفح المنتجات'],
    ['id' => 'orders',   'title' => 'متابعة طلب'],
    ['id' => 'support',  'title' => 'الدعم والمساعدة'],
], (clone $t));
$t->addSeconds(18);
chatMsg($orgId, $c2, $userId, 'inbound', 'تصفح المنتجات', (clone $t));
$t->addSeconds(7);
chatMsg($orgId, $c2, $userId, 'outbound',
    "يسعدنا تسوّقك معنا!\nلدينا تشكيلة واسعة من أفضل المنتجات.",
    (clone $t));
$t->addSeconds(6);
buttonsMsg($orgId, $c2, $userId, 'اختر القسم الذي يهمك:', [
    ['id' => 'fashion',     'title' => 'الأزياء والموضة'],
    ['id' => 'electronics', 'title' => 'الإلكترونيات'],
    ['id' => 'home',        'title' => 'المنزل والمطبخ'],
], (clone $t));
$t->addSeconds(30);
chatMsg($orgId, $c2, $userId, 'inbound', 'الإلكترونيات', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c2, $userId, 'outbound',
    "قسم الإلكترونيات 📱\n\nأحدث الهواتف والأجهزة بأفضل الأسعار!\nضمان سنتين على جميع الأجهزة\nتوصيل خلال 24 ساعة",
    (clone $t));
$t->addSeconds(7);
buttonsMsg($orgId, $c2, $userId, 'كيف تود المتابعة؟', [
    ['id' => 'talk_specialist', 'title' => 'تحدث مع متخصص'],
    ['id' => 'see_catalog',     'title' => 'عرض الكتالوج'],
], (clone $t));
$t->addSeconds(22);
chatMsg($orgId, $c2, $userId, 'inbound', 'تحدث مع متخصص', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c2, $userId, 'outbound',
    "تم تحويلك إلى متخصص الإلكترونيات 🎯\nسيتواصل معك أحد خبرائنا في أقرب وقت.\nيرجى الانتظار...",
    (clone $t));
addTicket($c2, $userId, 'open');
echo "✓ محادثة 2: فاطمة (منتجات → إلكترونيات → متخصص، مفتوحة)\n";

/* ─────────────────────────────────────────────
   [3] خالد - المنتجات → إلكترونيات → كتالوج
───────────────────────────────────────────── */
$c3 = 3; $t->addMinutes(15);
chatMsg($orgId, $c3, $userId, 'inbound', 'أهلاً', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c3, $userId, 'outbound',
    "مرحباً بك في متجر النجاح الإلكتروني! 🌟\nكيف يمكنني مساعدتك اليوم؟",
    (clone $t));
$t->addSeconds(10);
buttonsMsg($orgId, $c3, $userId, 'اختر ما تحتاجه:', [
    ['id' => 'products', 'title' => 'تصفح المنتجات'],
    ['id' => 'orders',   'title' => 'متابعة طلب'],
    ['id' => 'support',  'title' => 'الدعم والمساعدة'],
], (clone $t));
$t->addSeconds(35);
chatMsg($orgId, $c3, $userId, 'inbound', 'تصفح المنتجات', (clone $t));
$t->addSeconds(7);
chatMsg($orgId, $c3, $userId, 'outbound', "يسعدنا تسوّقك معنا!", (clone $t));
$t->addSeconds(6);
buttonsMsg($orgId, $c3, $userId, 'اختر القسم الذي يهمك:', [
    ['id' => 'fashion',     'title' => 'الأزياء والموضة'],
    ['id' => 'electronics', 'title' => 'الإلكترونيات'],
    ['id' => 'home',        'title' => 'المنزل والمطبخ'],
], (clone $t));
$t->addSeconds(20);
chatMsg($orgId, $c3, $userId, 'inbound', 'الإلكترونيات', (clone $t));
$t->addSeconds(7);
chatMsg($orgId, $c3, $userId, 'outbound',
    "قسم الإلكترونيات 📱\nأحدث الهواتف والأجهزة بأفضل الأسعار!\nضمان سنتين.",
    (clone $t));
$t->addSeconds(6);
buttonsMsg($orgId, $c3, $userId, 'كيف تود المتابعة؟', [
    ['id' => 'talk_specialist', 'title' => 'تحدث مع متخصص'],
    ['id' => 'see_catalog',     'title' => 'عرض الكتالوج'],
], (clone $t));
$t->addSeconds(15);
chatMsg($orgId, $c3, $userId, 'inbound', 'عرض الكتالوج', (clone $t));
$t->addSeconds(7);
chatMsg($orgId, $c3, $userId, 'outbound',
    "كتالوج الإلكترونيات 📋\n\n- هواتف ذكية: ابتداءً من 899 ريال\n- لابتوب: ابتداءً من 2,499 ريال\n- تابلت: ابتداءً من 1,299 ريال\n- سماعات: ابتداءً من 299 ريال\n\nللطلب أرسل اسم المنتج.",
    (clone $t));
$t->addSeconds(45);
chatMsg($orgId, $c3, $userId, 'inbound', 'أريد لابتوب ابدأ بالسعر', (clone $t));
addTicket($c3, $userId, 'open');
echo "✓ محادثة 3: خالد (إلكترونيات → كتالوج، مفتوحة)\n";

/* ─────────────────────────────────────────────
   [4] سارة - متابعة طلب → وُجد (ORD-78901)
───────────────────────────────────────────── */
$c4 = 4; $t->addMinutes(15);
chatMsg($orgId, $c4, $userId, 'inbound', 'start', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c4, $userId, 'outbound',
    "مرحباً بك في متجر النجاح الإلكتروني! 🌟\nكيف يمكنني مساعدتك اليوم؟",
    (clone $t));
$t->addSeconds(10);
buttonsMsg($orgId, $c4, $userId, 'اختر ما تحتاجه:', [
    ['id' => 'products', 'title' => 'تصفح المنتجات'],
    ['id' => 'orders',   'title' => 'متابعة طلب'],
    ['id' => 'support',  'title' => 'الدعم والمساعدة'],
], (clone $t));
$t->addSeconds(12);
chatMsg($orgId, $c4, $userId, 'inbound', 'متابعة طلب', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c4, $userId, 'outbound',
    "خدمة متابعة الطلبات 📦\n\nأرسل لي رقم طلبك وسأعطيك آخر المستجدات فوراً.\nرقم الطلب يبدأ بـ ORD مثال: ORD-12345",
    (clone $t));
$t->addSeconds(30);
chatMsg($orgId, $c4, $userId, 'inbound', 'ORD-78901', (clone $t));
$t->addSeconds(6);
chatMsg($orgId, $c4, $userId, 'outbound',
    "تم العثور على طلبك! ✅\n\nرقم الطلب: ORD-78901\nالحالة: تم استلامه وجاري التجهيز 📦\nالتوصيل المتوقع: خلال 2-3 أيام عمل\n\nشكراً لتسوّقك معنا!",
    (clone $t));
addTicket($c4, null, 'closed');
echo "✓ محادثة 4: سارة (طلبات → وُجد، مكتملة)\n";

/* ─────────────────────────────────────────────
   [5] محمد - طلب خاطئ → دعم بشري
───────────────────────────────────────────── */
$c5 = 5; $t->addMinutes(15);
chatMsg($orgId, $c5, $userId, 'inbound', 'hi', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c5, $userId, 'outbound',
    "مرحباً بك في متجر النجاح الإلكتروني! 🌟\nكيف يمكنني مساعدتك اليوم؟",
    (clone $t));
$t->addSeconds(10);
buttonsMsg($orgId, $c5, $userId, 'اختر ما تحتاجه:', [
    ['id' => 'products', 'title' => 'تصفح المنتجات'],
    ['id' => 'orders',   'title' => 'متابعة طلب'],
    ['id' => 'support',  'title' => 'الدعم والمساعدة'],
], (clone $t));
$t->addSeconds(20);
chatMsg($orgId, $c5, $userId, 'inbound', 'متابعة طلب', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c5, $userId, 'outbound',
    "خدمة متابعة الطلبات 📦\n\nأرسل لي رقم طلبك.\nمثال: ORD-12345",
    (clone $t));
$t->addSeconds(35);
chatMsg($orgId, $c5, $userId, 'inbound', '123456', (clone $t));
$t->addSeconds(6);
chatMsg($orgId, $c5, $userId, 'outbound',
    "لم أتمكن من التحقق من رقم الطلب.\n\nتأكد أن الرقم يبدأ بـ ORD مثل: ORD-12345\n\nإذا استمرت المشكلة، تواصل مع فريق الدعم.",
    (clone $t));
$t->addSeconds(8);
buttonsMsg($orgId, $c5, $userId, 'ماذا تريد الآن؟', [
    ['id' => 'retry_order',  'title' => 'أعد إدخال الرقم'],
    ['id' => 'talk_support', 'title' => 'تحدث مع الدعم'],
], (clone $t));
$t->addSeconds(18);
chatMsg($orgId, $c5, $userId, 'inbound', 'تحدث مع الدعم', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c5, $userId, 'outbound',
    "تم تحويلك إلى فريق الدعم 🎯\nسيساعدك أحد موظفينا في متابعة طلبك فوراً.",
    (clone $t));
addTicket($c5, $userId, 'open');
echo "✓ محادثة 5: محمد (طلب خاطئ → دعم بشري، مفتوحة)\n";

/* ─────────────────────────────────────────────
   [6] نورة - دعم → مشكلة دفع → human_handoff
───────────────────────────────────────────── */
$c6 = 6; $t->addMinutes(15);
chatMsg($orgId, $c6, $userId, 'inbound', 'مرحبا', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c6, $userId, 'outbound',
    "مرحباً بك في متجر النجاح الإلكتروني! 🌟\nكيف يمكنني مساعدتك اليوم؟",
    (clone $t));
$t->addSeconds(10);
buttonsMsg($orgId, $c6, $userId, 'اختر ما تحتاجه:', [
    ['id' => 'products', 'title' => 'تصفح المنتجات'],
    ['id' => 'orders',   'title' => 'متابعة طلب'],
    ['id' => 'support',  'title' => 'الدعم والمساعدة'],
], (clone $t));
$t->addSeconds(16);
chatMsg($orgId, $c6, $userId, 'inbound', 'الدعم والمساعدة', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c6, $userId, 'outbound',
    "مركز الدعم والمساعدة 💬\n\nفريقنا متاح 24/7 لخدمتك.\nنحن هنا لحل أي مشكلة تواجهها.",
    (clone $t));
$t->addSeconds(7);
buttonsMsg($orgId, $c6, $userId, 'اختر نوع مشكلتك:', [
    ['id' => 'payment_issue', 'title' => 'مشكلة في الدفع'],
    ['id' => 'return_item',   'title' => 'إرجاع أو استبدال'],
    ['id' => 'complaint',     'title' => 'شكوى أو اقتراح'],
], (clone $t));
$t->addSeconds(22);
chatMsg($orgId, $c6, $userId, 'inbound', 'مشكلة في الدفع', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c6, $userId, 'outbound',
    "فهمت مشكلة الدفع.\n\nسيتولى وكيل متخصص في المدفوعات مساعدتك مباشرةً.\nيرجى الانتظار لحظة...",
    (clone $t));
$t->addSeconds(10);
chatMsg($orgId, $c6, $userId, 'outbound',
    "تم تحويلك لفريق المدفوعات المتخصص 💳\nسيتواصل معك أحد موظفينا خلال دقائق.",
    (clone $t));
addTicket($c6, $userId, 'open');
echo "✓ محادثة 6: نورة (دعم → مشكلة دفع، مفتوحة)\n";

/* ─────────────────────────────────────────────
   [7] عبدالله - دعم → إرجاع منتج (مكتمل)
───────────────────────────────────────────── */
$c7 = 7; $t->addMinutes(15);
chatMsg($orgId, $c7, $userId, 'inbound', 'مرحبا', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c7, $userId, 'outbound',
    "مرحباً بك في متجر النجاح الإلكتروني! 🌟\nكيف يمكنني مساعدتك اليوم؟",
    (clone $t));
$t->addSeconds(10);
buttonsMsg($orgId, $c7, $userId, 'اختر ما تحتاجه:', [
    ['id' => 'products', 'title' => 'تصفح المنتجات'],
    ['id' => 'orders',   'title' => 'متابعة طلب'],
    ['id' => 'support',  'title' => 'الدعم والمساعدة'],
], (clone $t));
$t->addSeconds(14);
chatMsg($orgId, $c7, $userId, 'inbound', 'الدعم والمساعدة', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c7, $userId, 'outbound',
    "مركز الدعم والمساعدة 💬",
    (clone $t));
$t->addSeconds(7);
buttonsMsg($orgId, $c7, $userId, 'اختر نوع مشكلتك:', [
    ['id' => 'payment_issue', 'title' => 'مشكلة في الدفع'],
    ['id' => 'return_item',   'title' => 'إرجاع أو استبدال'],
    ['id' => 'complaint',     'title' => 'شكوى أو اقتراح'],
], (clone $t));
$t->addSeconds(18);
chatMsg($orgId, $c7, $userId, 'inbound', 'إرجاع أو استبدال', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c7, $userId, 'outbound',
    "طلب إرجاع أو استبدال 🔄\n\nيسعدنا استقبال طلبك.\nأخبرني سبب الإرجاع:\n(معيب / لا يناسب / غير مطابق / أخرى)",
    (clone $t));
$t->addSeconds(50);
chatMsg($orgId, $c7, $userId, 'inbound', 'المنتج لا يناسب المقاس المطلوب', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c7, $userId, 'outbound',
    "تم تسجيل طلب الإرجاع بنجاح! ✅\n\nسبب الإرجاع: لا يناسب المقاس\nسيتواصل معك فريقنا خلال 24 ساعة.\nشكراً لثقتك بمتجر النجاح.",
    (clone $t));
addTicket($c7, null, 'closed');
echo "✓ محادثة 7: عبدالله (دعم → إرجاع، مكتملة)\n";

/* ─────────────────────────────────────────────
   [8] ريم - دعم → شكوى → assign_to_agent
───────────────────────────────────────────── */
$c8 = 8; $t->addMinutes(15);
chatMsg($orgId, $c8, $userId, 'inbound', 'السلام عليكم', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c8, $userId, 'outbound',
    "مرحباً بك في متجر النجاح الإلكتروني! 🌟\nكيف يمكنني مساعدتك اليوم؟",
    (clone $t));
$t->addSeconds(10);
buttonsMsg($orgId, $c8, $userId, 'اختر ما تحتاجه:', [
    ['id' => 'products', 'title' => 'تصفح المنتجات'],
    ['id' => 'orders',   'title' => 'متابعة طلب'],
    ['id' => 'support',  'title' => 'الدعم والمساعدة'],
], (clone $t));
$t->addSeconds(20);
chatMsg($orgId, $c8, $userId, 'inbound', 'الدعم والمساعدة', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c8, $userId, 'outbound', "مركز الدعم والمساعدة 💬", (clone $t));
$t->addSeconds(7);
buttonsMsg($orgId, $c8, $userId, 'اختر نوع مشكلتك:', [
    ['id' => 'payment_issue', 'title' => 'مشكلة في الدفع'],
    ['id' => 'return_item',   'title' => 'إرجاع أو استبدال'],
    ['id' => 'complaint',     'title' => 'شكوى أو اقتراح'],
], (clone $t));
$t->addSeconds(25);
chatMsg($orgId, $c8, $userId, 'inbound', 'شكوى أو اقتراح', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c8, $userId, 'outbound',
    "شكاواكم واقتراحاتكم تهمنا! 💡\n\nنحن نستمع ونتحسن باستمرار.\nاكتب ملاحظتك أو اقتراحك بالتفصيل.",
    (clone $t));
$t->addSeconds(90);
chatMsg($orgId, $c8, $userId, 'inbound',
    "وصلتني طلبية لكن التغليف كان تالف والمنتج فيه خدش، أريد استبدال أو تعويض",
    (clone $t));
$t->addSeconds(10);
chatMsg($orgId, $c8, $userId, 'outbound',
    "شكراً جزيلاً على تواصلك معنا وأسفنا على هذه التجربة.\n\nتم تسجيل شكواك وتحويلها لفريق الجودة.\nسيتواصل معك أحد مديرينا خلال ساعتين لحل المشكلة فوراً.",
    (clone $t));
addTicket($c8, $userId, 'open');
echo "✓ محادثة 8: ريم (دعم → شكوى، مفتوحة)\n";

/* ─────────────────────────────────────────────
   [9] عمر - المنتجات → المنزل (محادثة كاملة)
───────────────────────────────────────────── */
$c9 = 9; $t->addMinutes(15);
chatMsg($orgId, $c9, $userId, 'inbound', 'مرحبا', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c9, $userId, 'outbound',
    "مرحباً بك في متجر النجاح الإلكتروني! 🌟\nكيف يمكنني مساعدتك اليوم؟",
    (clone $t));
$t->addSeconds(10);
buttonsMsg($orgId, $c9, $userId, 'اختر ما تحتاجه:', [
    ['id' => 'products', 'title' => 'تصفح المنتجات'],
    ['id' => 'orders',   'title' => 'متابعة طلب'],
    ['id' => 'support',  'title' => 'الدعم والمساعدة'],
], (clone $t));
$t->addSeconds(22);
chatMsg($orgId, $c9, $userId, 'inbound', 'تصفح المنتجات', (clone $t));
$t->addSeconds(7);
chatMsg($orgId, $c9, $userId, 'outbound', "يسعدنا تسوّقك معنا!", (clone $t));
$t->addSeconds(6);
buttonsMsg($orgId, $c9, $userId, 'اختر القسم الذي يهمك:', [
    ['id' => 'fashion',     'title' => 'الأزياء والموضة'],
    ['id' => 'electronics', 'title' => 'الإلكترونيات'],
    ['id' => 'home',        'title' => 'المنزل والمطبخ'],
], (clone $t));
$t->addSeconds(28);
chatMsg($orgId, $c9, $userId, 'inbound', 'المنزل والمطبخ', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c9, $userId, 'outbound',
    "قسم المنزل والمطبخ 🏠\n\nأكثر من 5,000 منتج للمنزل!\nأدوات مطبخ عالية الجودة\nديكور منزلي بأذواق عصرية\n\nتفضل بزيارة موقعنا للاطلاع على التشكيلة الكاملة.\nwww.store.com/home",
    (clone $t));
addTicket($c9, null, 'closed');
echo "✓ محادثة 9: عمر (منتجات → منزل، مكتملة)\n";

/* ─────────────────────────────────────────────
   [10] هند - جديد، لم يُرَد عليه (UNREAD)
───────────────────────────────────────────── */
$c10 = 10; $t->addMinutes(5);
chatMsg($orgId, $c10, $userId, 'inbound', 'مرحبا ابي اعرف اسعاركم', (clone $t), false); // is_read=false
echo "✓ محادثة 10: هند (رسالة جديدة غير مقروءة)\n";

/* ─────────────────────────────────────────────
   [11] يوسف - طلب، لم يُعاد إدخاله (UNREAD)
───────────────────────────────────────────── */
$c11 = 11; $t->addMinutes(3);
chatMsg($orgId, $c11, $userId, 'inbound', 'بغا أتابع طلبي', (clone $t), false);
$t->addSeconds(10);
chatMsg($orgId, $c11, $userId, 'outbound',
    "أرسل رقم طلبك للمتابعة. مثال: ORD-12345",
    (clone $t));
$t->addSeconds(90);
chatMsg($orgId, $c11, $userId, 'inbound', 'ما عندي الرقم، كيف أحصل عليه؟', (clone $t), false);
addTicket($c11, null, 'open');
echo "✓ محادثة 11: يوسف (طلب، سؤال معلق، غير مقروء)\n";

/* ─────────────────────────────────────────────
   [12] منى - محادثة نشطة مع الوكيل
───────────────────────────────────────────── */
$c12 = 12; $t->addMinutes(10);
chatMsg($orgId, $c12, $userId, 'inbound', 'هلا', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c12, $userId, 'outbound', "مرحباً! كيف يمكنني مساعدتك؟", (clone $t));
$t->addSeconds(10);
buttonsMsg($orgId, $c12, $userId, 'اختر ما تحتاجه:', [
    ['id' => 'products', 'title' => 'تصفح المنتجات'],
    ['id' => 'orders',   'title' => 'متابعة طلب'],
    ['id' => 'support',  'title' => 'الدعم والمساعدة'],
], (clone $t));
$t->addSeconds(12);
chatMsg($orgId, $c12, $userId, 'inbound', 'الدعم والمساعدة', (clone $t));
$t->addSeconds(8);
buttonsMsg($orgId, $c12, $userId, 'اختر نوع مشكلتك:', [
    ['id' => 'payment_issue', 'title' => 'مشكلة في الدفع'],
    ['id' => 'return_item',   'title' => 'إرجاع أو استبدال'],
    ['id' => 'complaint',     'title' => 'شكوى أو اقتراح'],
], (clone $t));
$t->addSeconds(20);
chatMsg($orgId, $c12, $userId, 'inbound', 'مشكلة في الدفع', (clone $t));
$t->addSeconds(8);
chatMsg($orgId, $c12, $userId, 'outbound', "تم تحويلك لفريق المدفوعات المتخصص 💳", (clone $t));
$t->addMinutes(5);
// الوكيل يرد يدوياً
chatMsg($orgId, $c12, $userId, 'outbound',
    "مرحباً منى، أنا أحمد من قسم المدفوعات.\nما نوع مشكلة الدفع التي تواجهينها بالضبط؟",
    (clone $t));
$t->addMinutes(2);
chatMsg($orgId, $c12, $userId, 'inbound',
    "دفعت بالبطاقة وخُصم المبلغ لكن ما جاني تأكيد الطلب",
    (clone $t), false);
addTicket($c12, $userId, 'open');
echo "✓ محادثة 12: منى (دعم → دفع، محادثة نشطة مع وكيل، غير مقروء)\n";

/* ══════════════════════════════════════════════════
   4. تحقق من النتائج
══════════════════════════════════════════════════ */

$totalChats    = DB::table('chats')->where('organization_id', $orgId)->count();
$totalUnread   = DB::table('chats')->where('organization_id', $orgId)->where('type','inbound')->where('is_read',0)->count();
$totalTickets  = DB::table('chat_tickets')
    ->whereIn('contact_id', $contactIds)
    ->count();
$withTimestamp = DB::table('contacts')->where('organization_id', $orgId)->whereNotNull('latest_chat_created_at')->count();

echo "\n══════════════════════════════════════════════\n";
echo "✅ اكتملت العملية:\n";
echo "   محادثات أُنشئت : {$totalChats}\n";
echo "   غير مقروءة    : {$totalUnread}\n";
echo "   تذاكر دعم     : {$totalTickets}\n";
echo "   جهات مفعّلة  : {$withTimestamp}\n";
echo "══════════════════════════════════════════════\n";
echo "🔗 صفحة المحادثات: http://localhost:8000/chats\n";
