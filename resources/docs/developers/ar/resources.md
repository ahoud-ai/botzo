# جهات الاتصال والقوالب والحملات

هذه الطلبات تربط بوتزو بنظامك (متجر، CRM، نظام حجوزات): تضيف العملاء وتنظمهم في مجموعات، تعرض قوالبك، وتطلق حملة لمجموعة كاملة.

الصلاحية المطلوبة: **كل الصلاحيات** (والقراءة تكفيها «قراءة فقط»).

## القوائم والصفحات

كل طلبات القوائم (`GET`) تدعم التقسيم لصفحات:

| المعامل | الافتراضي | الوصف |
| --- | --- | --- |
| `page` | 1 | رقم الصفحة |
| `per_page` | 10 | عدد العناصر، حتى 100 |

وترجع بهذا الشكل:

```json
{
  "data": [ { "...": "..." } ],
  "links": { "first": "…?page=1", "last": "…?page=3", "prev": null, "next": "…?page=2" },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 10, "total": 27 }
}
```

لتمر على كل العناصر: ابدأ بـ `page=1` واستمر طالما `links.next` ليست `null`.

## جهات الاتصال

| الطلب | الوصف |
| --- | --- |
| <span class="method get">GET</span> `/api/contacts` | قائمة جهات الاتصال |
| <span class="method post">POST</span> `/api/contacts` | إضافة جهة اتصال |
| <span class="method put">PUT</span> `/api/contacts/{uuid}` | تعديل جهة اتصال |
| <span class="method delete">DELETE</span> `/api/contacts/{uuid}` | حذف جهة اتصال |

### الحقول (إضافة وتعديل)

| الحقل | النوع | مطلوب | الوصف |
| --- | --- | --- | --- |
| `first_name` | string | نعم في الإضافة | الاسم الأول |
| `last_name` | string | لا | اسم العائلة |
| `phone` | string | نعم | رقم صحيح بالصيغة الدولية، **ولا يتكرر** داخل مؤسستك |
| `email` | string | لا | البريد |
| `group` | array | لا | `uuid` المجموعات التي ينضم لها: `["6f1c…", "a92b…"]` |
| `metadata` | object | لا | حقول خاصة بك، مثل `{"city": "الرياض", "tier": "gold"}`. تستطيع استخدامها في متغيرات الحملات |

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/contacts \
  -H "Authorization: Bearer $BOTZO_API_KEY" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "first_name": "سارة",
    "last_name": "العلي",
    "phone": "+966501234567",
    "email": "sara@example.com",
    "metadata": {"city": "الرياض", "tier": "gold"}
  }'
```

```js
const res = await fetch('{{BASE_URL}}/api/contacts', {
  method: 'POST',
  headers: { Authorization: `Bearer ${process.env.BOTZO_API_KEY}`, Accept: 'application/json', 'Content-Type': 'application/json' },
  body: JSON.stringify({ first_name: 'سارة', phone: '+966501234567', metadata: { city: 'الرياض' } }),
});
const { id } = await res.json(); // uuid جهة الاتصال
```

```php
$id = Http::withToken(env('BOTZO_API_KEY'))->acceptJson()
    ->post('{{BASE_URL}}/api/contacts', ['first_name' => 'سارة', 'phone' => '+966501234567'])
    ->json('id');
```

```python
r = requests.post("{{BASE_URL}}/api/contacts", headers=HEADERS,
                  json={"first_name": "سارة", "phone": "+966501234567"})
contact_uuid = r.json()["id"]
```

</div>

الرد (إضافة أو تعديل أو حذف):

```json
{
  "statusCode": 200,
  "id": "dd0d957b-9f2c-4bcb-aa3a-a1a17388ff11",
  "message": "Request processed successfully"
}
```

عنصر من القائمة `GET /api/contacts`:

```json
{
  "uuid": "dd0d957b-9f2c-4bcb-aa3a-a1a17388ff11",
  "first_name": "Sara",
  "last_name": "Ali",
  "full_name": "Sara Ali",
  "phone": "+966501234567",
  "formatted_phone_number": "+966 50 123 4567",
  "email": "sara@example.com",
  "channel": "whatsapp",
  "metadata": "{\"city\":\"الرياض\"}",
  "unread_messages": 0,
  "reply_context": {
    "reply_window_open": false,
    "can_send_text": false,
    "can_send_media": false,
    "can_send_template": true
  },
  "created_at": "2026-10-22 21:23:06"
}
```

> `reply_context.reply_window_open` يقول لك هل نافذة الـ 24 ساعة مفتوحة الآن مع هذا العميل، فتعرف مسبقاً هل ترسل نصاً أم قالباً.

| الخطأ | السبب |
| --- | --- |
| `400 validation_error` ومعه `errors.phone` | الرقم غير صحيح أو موجود مسبقاً |
| `403 contacts_limit_reached` | وصلت لحد جهات الاتصال في باقتك |
| `404 not_found` | `uuid` غير موجود في مؤسستك |

## المجموعات

| الطلب | الوصف |
| --- | --- |
| <span class="method get">GET</span> `/api/contact-groups` | قائمة المجموعات |
| <span class="method post">POST</span> `/api/contact-groups` | إنشاء مجموعة: `{"name": "عملاء VIP"}` (الاسم لا يتكرر) |
| <span class="method put">PUT</span> `/api/contact-groups/{uuid}` | إعادة تسمية |
| <span class="method delete">DELETE</span> `/api/contact-groups/{uuid}` | حذف المجموعة (جهات الاتصال نفسها تبقى) |

الرد مثل جهات الاتصال: `{"statusCode": 200, "id": "<uuid>", "message": "..."}`. لإضافة عميل لمجموعة أرسل `group` عند إضافته أو تعديله.

## الردود التلقائية

ردود ثابتة يرسلها بوتزو عندما تحتوي رسالة العميل على كلمة معينة.

| الطلب | الوصف |
| --- | --- |
| <span class="method get">GET</span> `/api/canned-replies` | القائمة |
| <span class="method post">POST</span> `/api/canned-replies` | إضافة |
| <span class="method put">PUT</span> `/api/canned-replies/{uuid}` | تعديل |
| <span class="method delete">DELETE</span> `/api/canned-replies/{uuid}` | حذف |

| الحقل | القيم | الوصف |
| --- | --- | --- |
| `name` | نص | اسم داخلي |
| `trigger` | نص | الكلمة التي تشغّل الرد، مثل `مواعيد` |
| `match_criteria` | `exact match` أو `contains` | الرسالة تساوي الكلمة تماماً، أو تحتويها |
| `response_type` | `text` أو `image` أو `audio` | نوع الرد |
| `response` | نص أو ملف | النص، أو ملف مرفوع (`multipart/form-data`) للصورة والصوت |

```bash
curl -X POST {{BASE_URL}}/api/canned-replies \
  -H "Authorization: Bearer $BOTZO_API_KEY" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name": "مواعيد العمل", "trigger": "مواعيد", "match_criteria": "contains", "response_type": "text", "response": "نستقبلكم يومياً من 9 صباحاً حتى 11 مساءً."}'
```

## القوالب

<span class="method get">GET</span> `/api/templates`

```json
{
  "data": [
    {
      "uuid": "1d3f9a0e-…",
      "name": "order_shipped",
      "metadata": "{\"name\":\"order_shipped\",\"language\":\"ar\",\"status\":\"APPROVED\",\"category\":\"UTILITY\",\"components\":[{\"type\":\"BODY\",\"text\":\"مرحبا {{1}}، طلبك رقم {{2}} في الطريق.\"}]}",
      "updated_at": "2026-10-22 21:23"
    }
  ],
  "links": { "...": "..." },
  "meta": { "...": "..." }
}
```

`metadata` نص JSON فيه تعريف القالب الكامل كما في Meta: اللغة والحالة والفئة والمكونات والمتغيرات. استخدم `name` واللغة منه عند [إرسال قالب](/developers/messages). القوالب تُنشأ وتُعدّل من لوحة التحكم.

## الحملات

<span class="method post">POST</span> `/api/campaigns`

ترسل قالباً واحداً لكل أفراد مجموعة (أو لكل جهات الاتصال)، الآن أو في موعد محدد. بوتزو يرسلها على دفعات ويحترم حدود واتساب، ويتابع النتيجة لكل عميل في لوحة التحكم.

| الحقل | النوع | مطلوب | الوصف |
| --- | --- | --- | --- |
| `template` | string | نعم | `uuid` القالب من `GET /api/templates` |
| `contacts` | string | نعم | `uuid` المجموعة، أو `all` لكل جهات الاتصال |
| `name` | string | لا | اسم الحملة (الافتراضي «API Campaign» مع التاريخ) |
| `skip_schedule` | boolean | لا | `true` (الافتراضي) للإرسال الآن، `false` للجدولة |
| `time` | string | عند الجدولة | موعد الإرسال **بتوقيت مؤسستك**، مثل `2026-11-01 10:00` |
| `header` | object | حسب القالب | متغيرات الرأس |
| `body` | object | حسب القالب | متغيرات النص |
| `buttons` | array | حسب القالب | متغيرات الأزرار |

### المتغيرات في الحملات

لأن الحملة لعملاء كثيرين، كل متغير إما **ثابت** لكل الناس أو **يُملأ من بيانات كل عميل**:

```json
{
  "body": {
    "parameters": [
      { "type": "text", "selection": "dynamic", "value": "first name" },
      { "type": "text", "selection": "static", "value": "20%" }
    ]
  }
}
```

| `selection` | `value` | النتيجة |
| --- | --- | --- |
| `static` | أي نص | نفس النص لكل العملاء |
| `dynamic` | `first name`، `last name`، `name`، `phone`، `email` | من بيانات العميل |
| `dynamic` | `field:city` (أي حقل في `metadata`) | من حقولك الخاصة للعميل |

رأس بصورة: `"header": {"parameters": [{"type": "IMAGE", "value": "https://cdn.example.com/offer.jpg"}]}` (أو `VIDEO` أو `DOCUMENT`).

زر رابط بجزء متغير: `"buttons": [{"type": "URL", "parameters": [{"type": "static", "value": "summer"}]}]`. الأنواع: `URL`، `QUICK_REPLY`، `COPY_CODE`، و `type` داخل parameters هو `static` أو `dynamic`.

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/campaigns \
  -H "Authorization: Bearer $BOTZO_API_KEY" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "name": "عرض نهاية الأسبوع",
    "template": "1d3f9a0e-7c2b-4f3e-9a51-2b8f0c6d4e11",
    "contacts": "6f1c2d3e-…",
    "skip_schedule": false,
    "time": "2026-11-01 10:00",
    "body": {"parameters": [
      {"type": "text", "selection": "dynamic", "value": "first name"},
      {"type": "text", "selection": "static", "value": "20%"}
    ]}
  }'
```

```js
await fetch('{{BASE_URL}}/api/campaigns', {
  method: 'POST',
  headers: { Authorization: `Bearer ${process.env.BOTZO_API_KEY}`, Accept: 'application/json', 'Content-Type': 'application/json' },
  body: JSON.stringify({
    template: '1d3f9a0e-7c2b-4f3e-9a51-2b8f0c6d4e11',
    contacts: 'all',
    body: { parameters: [{ type: 'text', selection: 'dynamic', value: 'first name' }] },
  }),
});
```

</div>

الرد:

```json
{
  "statusCode": 200,
  "id": "c0a8012e-…",
  "message": "Campaign created successfully!",
  "data": {
    "campaign_status": "scheduled",
    "scheduled_at_utc": "2026-11-01 07:00:00",
    "contacts_count": 248
  }
}
```

| الخطأ | السبب |
| --- | --- |
| `404 template_not_found` | `uuid` القالب غير صحيح |
| `404 contact_group_not_found` | `uuid` المجموعة غير صحيح |
| `422 campaign_audience_empty` | المجموعة فارغة |
| `403 campaign_limit_reached` | وصلت لحد الحملات في باقتك |
| `403 whatsapp_not_connected` | لم يُربط رقم واتساب بالمؤسسة بعد |

> **سرعة الحملة:** الرسائل تخرج على دفعات كل بضع دقائق، والحملة المجدولة قد تبدأ بعد موعدها بدقائق قليلة. نتائج كل عميل (نجح أو فشل وسبب الفشل) في لوحة التحكم ← الحملات.
