# إرسال رسائل واتساب

أربع طلبات تغطي كل الإرسال: نص (مع أزرار اختيارية)، قالب، ميديا، ومتابعة حالة رسالة. كل رسالة ترسلها تظهر أيضاً في صندوق المحادثات في بوتزو.

| الطلب | الاستخدام |
| --- | --- |
| <span class="method post">POST</span> `/api/send` | نص، أو نص بأزرار رد سريع (داخل نافذة الـ 24 ساعة) |
| <span class="method post">POST</span> `/api/send/template` | قالب معتمد (في أي وقت، حتى لعميل لم يراسلك) |
| <span class="method post">POST</span> `/api/send/media` | صورة أو فيديو أو صوت أو ملف (داخل النافذة) |
| <span class="method get">GET</span> `/api/messages/{message_id}` | حالة رسالة: أُرسلت، وصلت، قُرئت، فشلت |

الصلاحية المطلوبة: **كل الصلاحيات** أو **إرسال الرسائل فقط**.

## إرسال نص

<span class="method post">POST</span> `/api/send`

| الحقل | النوع | مطلوب | الوصف |
| --- | --- | --- | --- |
| `phone` | string | نعم | رقم العميل بالصيغة الدولية `+966501234567` |
| `message` | string | نعم | نص الرسالة. حتى 4096 حرفاً للنص، و1024 مع الأزرار. الروابط تظهر بمعاينة |
| `buttons` | array | لا | حتى 3 أزرار رد سريع: `[{"id": "track", "title": "تتبع الطلب"}]`. `title` حتى 20 حرفاً و `id` حتى 256 |
| `header` | string | لا | عنوان فوق الرسالة (مع الأزرار فقط)، حتى 60 حرفاً |
| `footer` | string | لا | سطر صغير تحت الرسالة (مع الأزرار فقط)، حتى 60 حرفاً |
| `first_name`, `last_name`, `email` | string | لا | تُستخدم فقط إذا لم يكن الرقم موجوداً في جهات الاتصال، فننشئه بها |

لو الرقم غير موجود في جهات اتصالك ننشئه تلقائياً (ضمن حد جهات الاتصال في باقتك).

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/send \
  -H "Authorization: Bearer $BOTZO_API_KEY" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Idempotency-Key: order-1045-shipped" \
  -d '{
    "phone": "+966501234567",
    "message": "طلبك رقم 1045 في الطريق 🚚 يوصلك خلال يومين.",
    "header": "تحديث الطلب",
    "footer": "متجر الريحان",
    "buttons": [
      {"id": "track_1045", "title": "تتبع الطلب"},
      {"id": "support", "title": "كلّم الدعم"}
    ]
  }'
```

```js
const res = await fetch('{{BASE_URL}}/api/send', {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${process.env.BOTZO_API_KEY}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'Idempotency-Key': 'order-1045-shipped',
  },
  body: JSON.stringify({
    phone: '+966501234567',
    message: 'طلبك رقم 1045 في الطريق 🚚 يوصلك خلال يومين.',
    buttons: [{ id: 'track_1045', title: 'تتبع الطلب' }],
  }),
});
const body = await res.json();
if (!res.ok) throw new Error(`${body.code}: ${body.message}`);
console.log(body.message_id);
```

```php
$response = Http::withToken(env('BOTZO_API_KEY'))
    ->acceptJson()
    ->withHeaders(['Idempotency-Key' => 'order-1045-shipped'])
    ->post('{{BASE_URL}}/api/send', [
        'phone' => '+966501234567',
        'message' => 'طلبك رقم 1045 في الطريق 🚚 يوصلك خلال يومين.',
        'buttons' => [['id' => 'track_1045', 'title' => 'تتبع الطلب']],
    ]);

if ($response->failed()) {
    logger()->warning('WhatsApp not sent', ['code' => $response->json('code')]);
}
$messageId = $response->json('message_id');
```

```python
r = requests.post(
    "{{BASE_URL}}/api/send",
    headers={"Authorization": f"Bearer {API_KEY}", "Accept": "application/json",
             "Idempotency-Key": "order-1045-shipped"},
    json={"phone": "+966501234567",
          "message": "طلبك رقم 1045 في الطريق 🚚 يوصلك خلال يومين.",
          "buttons": [{"id": "track_1045", "title": "تتبع الطلب"}]},
    timeout=30,
)
body = r.json()
if r.status_code != 200:
    print(body["code"], body["message"])
else:
    print(body["message_id"])
```

</div>

عندما يضغط العميل زراً يصلك حدث `message.received` بنوع `interactive` وفيه `button.id` الذي حددته (مثلاً `track_1045`)، فتعرف ماذا اختار. انظر [الـ Webhooks](/developers/webhooks).

### الرد الناجح (200)

```json
{
  "statusCode": 200,
  "message_id": "wamid.HBgMOTY2NTAxMjM0NTY3FQIAERgSMEE2",
  "status": "sent",
  "data": { "success": true, "data": { "messaging_product": "whatsapp", "messages": [{ "id": "wamid.HBgMOTY2NTAxMjM0NTY3FQIAERgSMEE2" }], "chat": { "...": "..." } } }
}
```

| الحقل | الوصف |
| --- | --- |
| `message_id` | معرّف الرسالة عند واتساب. **احفظه**: به تتابع حالتها، ويأتي في أحداث `message.status` |
| `status` | دائماً `sent` عند النجاح (Meta استلمت الرسالة). الوصول والقراءة تأتي لاحقاً |
| `data` | رد واتساب الخام وسجل الرسالة في بوتزو. موجود للتوافق مع التكاملات القديمة، و**لا ننصح بالاعتماد عليه** |

### الرد عند الفشل

لو رفضت واتساب الرسالة يرجع كود خطأ واضح، ومعه تفاصيل Meta في `meta_error`:

```json
{
  "statusCode": 422,
  "code": "outside_24h_window",
  "message": "(#131047) Re-engagement message",
  "request_id": "9fb64a88-f0f1-4a36-a1e8-0ba3eef81360",
  "meta_error": {
    "code": 131047,
    "subcode": null,
    "message": "(#131047) Re-engagement message",
    "details": "Message failed to send because more than 24 hours have passed since the customer last replied to this number."
  },
  "data": { "success": false, "...": "..." }
}
```

الرسائل الفاشلة تظهر أيضاً في صندوق المحادثات بعلامة فشل. جدول كل الأكواد في [الأخطاء](/developers/errors).

## إرسال قالب

<span class="method post">POST</span> `/api/send/template`

| الحقل | النوع | مطلوب | الوصف |
| --- | --- | --- | --- |
| `phone` | string | نعم | رقم العميل |
| `template.name` | string | نعم | اسم القالب كما في `GET /api/templates` |
| `template.language.code` | string | نعم | لغة القالب: `ar`، `en`، `en_US`… كما اعتمدته Meta |
| `template.components` | array | حسب القالب | قيم المتغيرات بصيغة Meta (أدناه) |

صيغة `components` هي نفس صيغة Meta تماماً:

| المكوّن | الشكل |
| --- | --- |
| متغيرات النص `{{1}}` `{{2}}` | `{"type": "body", "parameters": [{"type": "text", "text": "سارة"}, {"type": "text", "text": "1045"}]}` |
| رأس بصورة | `{"type": "header", "parameters": [{"type": "image", "image": {"link": "https://cdn.example.com/p.jpg"}}]}` |
| رأس بملف PDF | `{"type": "header", "parameters": [{"type": "document", "document": {"link": "https://…/invoice.pdf", "filename": "فاتورة.pdf"}}]}` |
| رأس بنص متغير | `{"type": "header", "parameters": [{"type": "text", "text": "عرض اليوم"}]}` |
| زر رابط بجزء متغير | `{"type": "button", "sub_type": "url", "index": "0", "parameters": [{"type": "text", "text": "1045"}]}` |
| زر رد سريع | `{"type": "button", "sub_type": "quick_reply", "index": "1", "parameters": [{"type": "payload", "payload": "CONFIRM_1045"}]}` |
| زر نسخ كود | `{"type": "button", "sub_type": "copy_code", "index": "0", "parameters": [{"type": "coupon_code", "coupon_code": "SAVE20"}]}` |

عدد المتغيرات وترتيبها **يجب أن يطابق القالب** تماماً، وإلا يرجع `template_parameters_mismatch`.

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/send/template \
  -H "Authorization: Bearer $BOTZO_API_KEY" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "phone": "+966501234567",
    "template": {
      "name": "order_shipped",
      "language": {"code": "ar"},
      "components": [
        {"type": "body", "parameters": [
          {"type": "text", "text": "سارة"},
          {"type": "text", "text": "1045"}
        ]}
      ]
    }
  }'
```

```js
await fetch('{{BASE_URL}}/api/send/template', {
  method: 'POST',
  headers: { Authorization: `Bearer ${process.env.BOTZO_API_KEY}`, Accept: 'application/json', 'Content-Type': 'application/json' },
  body: JSON.stringify({
    phone: '+966501234567',
    template: {
      name: 'order_shipped',
      language: { code: 'ar' },
      components: [{ type: 'body', parameters: [{ type: 'text', text: 'سارة' }, { type: 'text', text: '1045' }] }],
    },
  }),
});
```

```php
Http::withToken(env('BOTZO_API_KEY'))->acceptJson()->post('{{BASE_URL}}/api/send/template', [
    'phone' => '+966501234567',
    'template' => [
        'name' => 'order_shipped',
        'language' => ['code' => 'ar'],
        'components' => [
            ['type' => 'body', 'parameters' => [
                ['type' => 'text', 'text' => 'سارة'],
                ['type' => 'text', 'text' => '1045'],
            ]],
        ],
    ],
]);
```

```python
requests.post("{{BASE_URL}}/api/send/template", headers=HEADERS, json={
    "phone": "+966501234567",
    "template": {
        "name": "order_shipped",
        "language": {"code": "ar"},
        "components": [{"type": "body", "parameters": [
            {"type": "text", "text": "سارة"}, {"type": "text", "text": "1045"}]}],
    },
})
```

</div>

الرد بنفس شكل إرسال النص (`message_id` و `status`).

## إرسال صورة أو ملف

<span class="method post">POST</span> `/api/send/media`

| الحقل | النوع | مطلوب | الوصف |
| --- | --- | --- | --- |
| `phone` | string | نعم | رقم العميل |
| `media_type` | string | نعم | واحد من: `image`، `video`، `audio`، `document` |
| `media_url` | string | نعم | رابط **https عام** للملف. واتساب يحمّله من الرابط مباشرة |
| `caption` | string | لا | نص تحت الصورة أو الفيديو أو الملف (لا يُدعم مع الصوت)، حتى 1024 حرفاً |
| `file_name` | string | لا | اسم الملف كما يراه العميل (مهم للمستندات). الافتراضي من آخر الرابط |

**شروط الرابط:** يبدأ بـ `https://`، ويفتح بدون تسجيل دخول، ولا يكون عنواناً داخلياً (`localhost` أو `10.x` أو `192.168.x`). أي رابط آخر يرجع `400 validation_error`. نحن لا نفتح الرابط بأنفسنا، واتساب هي من تحمّله.

| النوع | الصيغ المقبولة عند واتساب | أقصى حجم |
| --- | --- | --- |
| `image` | JPEG, PNG | 5 ميجا |
| `video` | MP4, 3GP | 16 ميجا |
| `audio` | AAC, MP3, OGG (Opus), AMR, M4A | 16 ميجا |
| `document` | PDF, Word, Excel, PowerPoint, TXT… | 100 ميجا |

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/send/media \
  -H "Authorization: Bearer $BOTZO_API_KEY" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "phone": "+966501234567",
    "media_type": "document",
    "media_url": "https://cdn.example.com/invoices/1045.pdf",
    "file_name": "فاتورة-1045.pdf",
    "caption": "فاتورة طلبك رقم 1045"
  }'
```

```js
await fetch('{{BASE_URL}}/api/send/media', {
  method: 'POST',
  headers: { Authorization: `Bearer ${process.env.BOTZO_API_KEY}`, Accept: 'application/json', 'Content-Type': 'application/json' },
  body: JSON.stringify({
    phone: '+966501234567',
    media_type: 'image',
    media_url: 'https://cdn.example.com/products/rose-perfume.jpg',
    caption: 'عطر الورد، متوفر الآن',
  }),
});
```

```php
Http::withToken(env('BOTZO_API_KEY'))->acceptJson()->post('{{BASE_URL}}/api/send/media', [
    'phone' => '+966501234567',
    'media_type' => 'document',
    'media_url' => 'https://cdn.example.com/invoices/1045.pdf',
    'file_name' => 'فاتورة-1045.pdf',
]);
```

```python
requests.post("{{BASE_URL}}/api/send/media", headers=HEADERS, json={
    "phone": "+966501234567",
    "media_type": "image",
    "media_url": "https://cdn.example.com/products/rose-perfume.jpg",
    "caption": "عطر الورد، متوفر الآن",
})
```

</div>

لو لم تستطع واتساب تحميل الملف (الرابط مغلق أو الحجم كبير) يرجع `media_download_failed`.

## متابعة حالة الرسالة

<span class="method get">GET</span> `/api/messages/{message_id}`

```bash
curl {{BASE_URL}}/api/messages/wamid.HBgMOTY2NTAxMjM0NTY3FQIAERgSMEE2 \
  -H "Authorization: Bearer $BOTZO_API_KEY" -H "Accept: application/json"
```

```json
{
  "statusCode": 200,
  "data": {
    "message_id": "wamid.HBgMOTY2NTAxMjM0NTY3FQIAERgSMEE2",
    "phone": "+966501234567",
    "status": "read",
    "error": null,
    "sent_at": "2026-10-22 18:23:06",
    "status_updated_at": "2026-10-22 18:24:40"
  }
}
```

| `status` | المعنى |
| --- | --- |
| `sent` | استلمتها خوادم واتساب |
| `delivered` | وصلت لجوال العميل |
| `read` | فتحها العميل (إذا كان مفعّلاً لعلامات القراءة) |
| `failed` | فشلت. السبب في `error.code` و `error.message` |

الحالة تتقدم فقط للأمام: `sent` ← `delivered` ← `read`. لو فشلت بعد الإرسال (مثلاً العميل غيّر رقمه) تصبح `failed` ومعها السبب.

> **الأفضل من السؤال المتكرر:** فعّل حدث `message.status` في [الـ Webhooks](/developers/webhooks)، فيصلك كل تغيير لحظياً بدل أن تسأل كل شوية.

الرقم غير موجود في مؤسستك يرجع `404 message_not_found`.

## منع الإرسال المكرر (Idempotency-Key)

الشبكات ليست مثالية: قد يرسل سيرفرك الطلب، وتصل الرسالة للعميل، لكن الرد يضيع في الطريق فيعيد سيرفرك المحاولة، فتصل الرسالة **مرتين**. الحل: أرسل الهيدر `Idempotency-Key` بقيمة فريدة لكل رسالة منطقية:

```http
Idempotency-Key: order-1045-shipped
```

| الحالة | ماذا يحدث |
| --- | --- |
| أول طلب بهذا المفتاح | يُرسل عادياً ونحفظ النتيجة 24 ساعة |
| نفس المفتاح ونفس البيانات مرة أخرى | **لا يُرسل شيء**، ويرجع نفس الرد الأول ومعه الهيدر `Idempotent-Replayed: true` |
| نفس المفتاح وبيانات مختلفة | `422 idempotency_key_reused` |
| الطلب الأول لم ينتهِ بعد | `409 idempotency_in_progress`، أعد المحاولة بعد ثانية |

- يعمل مع `/api/send` و `/api/send/template` و `/api/send/media`.
- المفتاح حتى 100 حرف، ومحفوظ لكل مؤسسة على حدة لمدة 24 ساعة.
- أخطاء الخادم (5xx) والحدود (429) **لا تُحفظ**، فإعادة المحاولة بنفس المفتاح ترسل فعلاً.
- استخدم قيمة مرتبطة بالحدث نفسه (`order-1045-shipped`) وليس قيمة عشوائية في كل محاولة.

## نصائح

- **أعد المحاولة بذكاء:** أعد فقط عند `429` و `502` و `5xx`، مع انتظار متزايد (1، 2، 4 ثوانٍ…) ونفس `Idempotency-Key`. لا تعد المحاولة عند `422` لأن السبب لن يتغير.
- **مهلة الطلب:** اجعلها 30 ثانية على الأقل، فخوادم واتساب أحياناً تتأخر.
- **الرسائل الكثيرة:** لإرسال نفس القالب لمئات العملاء استخدم [الحملات](/developers/resources) بدل حلقة إرسال.
