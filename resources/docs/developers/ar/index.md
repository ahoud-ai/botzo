# دليل المطوّرين

اربط تطبيقك أو موقعك أو نظامك ببوتزو بثلاث أدوات: **إرسال رسائل واتساب**، و**موتشي** (المساعد الذكي الذي يرد على عملائك)، و**الـ Webhooks** التي تخبر سيرفرك بكل جديد لحظة حدوثه.

هذا الدليل مكتوب لكل مبرمج، حتى لو كانت أول مرة تتعامل مع واتساب. اقرأ هذه الصفحة أولاً، ثم انتقل للقسم الذي تحتاجه.

## ماذا تستطيع أن تبني؟

| تريد أن… | استخدم | القسم |
| --- | --- | --- |
| ترسل لعميلك إشعار طلب أو كود تحقق أو فاتورة على واتساب | API إرسال الرسائل | [إرسال رسائل واتساب](/developers/messages) |
| تضع شات «اسأل موتشي» داخل تطبيق الموبايل أو موقعك بتصميمك الخاص | API موتشي | [API موتشي](/developers/assistant) |
| يعرف سيرفرك فوراً عندما يرد العميل أو تُقرأ رسالتك أو يطلب موظفاً | الـ Webhooks | [الـ Webhooks](/developers/webhooks) |
| تضيف جهات اتصال من نظامك (CRM أو متجر) وتطلق حملة | API جهات الاتصال والحملات | [جهات الاتصال والقوالب والحملات](/developers/resources) |

كل ما يحدث عبر الـ API يظهر في **صندوق المحادثات (Inbox)** في لوحة تحكم بوتزو، ويستطيع فريقك الرد منه مباشرة.

## العنوان الأساسي

كل الطلبات تذهب إلى:

```text
{{BASE_URL}}/api
```

- كل الطلبات والردود بصيغة **JSON** وترميز **UTF-8** (العربية والإيموجي مدعومة).
- أرسل دائماً الهيدرين: `Accept: application/json` و `Content-Type: application/json`.
- الاتصال عبر **HTTPS** فقط.

## ابدأ في 5 دقائق

### 1) أنشئ مفتاح API

من لوحة التحكم: **أدوات المطوّرين ← مفاتيح الوصول**. اكتب اسماً للمفتاح (مثلاً «سيرفر المتجر»)، اختر الصلاحية، ثم اضغط **إنشاء مفتاح**. انسخ المفتاح فوراً لأنه **يظهر مرة واحدة فقط**.

> **مهم:** المفتاح مثل كلمة السر. ضعه في متغيرات البيئة على سيرفرك (`BOTZO_API_KEY`)، ولا تضعه في كود الواجهة أو في تطبيق الموبايل. التفاصيل في [المفاتيح والأمان](/developers/authentication).

### 2) تأكد أن المفتاح يعمل

<div class="code-tabs">

```bash
curl {{BASE_URL}}/api/verify \
  -H "Authorization: Bearer $BOTZO_API_KEY" \
  -H "Accept: application/json"
```

```js
const res = await fetch('{{BASE_URL}}/api/verify', {
  headers: { Authorization: `Bearer ${process.env.BOTZO_API_KEY}`, Accept: 'application/json' },
});
console.log(res.status, await res.json());
```

```php
$response = Http::withToken(env('BOTZO_API_KEY'))->acceptJson()->get('{{BASE_URL}}/api/verify');
echo $response->status(); // 200
```

```python
import os, requests
r = requests.get("{{BASE_URL}}/api/verify",
                 headers={"Authorization": f"Bearer {os.environ['BOTZO_API_KEY']}", "Accept": "application/json"})
print(r.status_code, r.json())
```

</div>

الرد:

```json
{
  "statusCode": 200,
  "message": "API key is valid and active"
}
```

### 3) أرسل أول رسالة

العميل يجب أن يكون **أرسل لك رسالة خلال آخر 24 ساعة** حتى تستطيع إرسال نص حر (هذه قاعدة واتساب، اقرأ [قواعد واتساب](/developers/whatsapp-basics)). للتجربة: أرسل «مرحبا» من جوالك لرقم واتساب مؤسستك، ثم:

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/send \
  -H "Authorization: Bearer $BOTZO_API_KEY" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"phone": "+966501234567", "message": "طلبك في الطريق 🚚"}'
```

```js
const res = await fetch('{{BASE_URL}}/api/send', {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${process.env.BOTZO_API_KEY}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ phone: '+966501234567', message: 'طلبك في الطريق 🚚' }),
});
const result = await res.json();
console.log(result.message_id); // احفظه لتتابع حالة الرسالة
```

```php
$result = Http::withToken(env('BOTZO_API_KEY'))->acceptJson()
    ->post('{{BASE_URL}}/api/send', ['phone' => '+966501234567', 'message' => 'طلبك في الطريق 🚚'])
    ->json();
echo $result['message_id'];
```

```python
r = requests.post("{{BASE_URL}}/api/send",
                  headers={"Authorization": f"Bearer {os.environ['BOTZO_API_KEY']}", "Accept": "application/json"},
                  json={"phone": "+966501234567", "message": "طلبك في الطريق 🚚"})
print(r.json()["message_id"])
```

</div>

النجاح يرجع `200` ومعه `message_id`. إذا رفضت واتساب الرسالة يرجع خطأ واضح مثل `outside_24h_window`، والتفاصيل في [الأخطاء](/developers/errors).

### 4) استقبل ردود عملائك

من **أدوات المطوّرين ← Webhooks** اضغط **«استخدم المستقبِل التجريبي»** ثم **«إضافة Webhook»**. أرسل رسالة من جوالك، ثم اضغط **«تحديث»** في قسم المستقبِل التجريبي: سترى الحدث `message.received` كما سيصل لسيرفرك تماماً، ومعه حالة التوقيع. عندما يجهز سيرفرك ضع رابطه بدل المستقبِل التجريبي. التفاصيل في [الـ Webhooks](/developers/webhooks).

## شكل الردود

**نجاح:** يحتوي دائماً على `statusCode` بنفس رقم الـ HTTP، وباقي الحقول حسب الطلب.

**خطأ:** نفس الشكل في كل الـ API:

```json
{
  "statusCode": 422,
  "code": "outside_24h_window",
  "message": "(#131047) Re-engagement message",
  "request_id": "9fb64a88-f0f1-4a36-a1e8-0ba3eef81360"
}
```

| الحقل | معناه |
| --- | --- |
| `statusCode` | رقم حالة الـ HTTP نفسه |
| `code` | **اعتمد عليه في الكود**، قيمته ثابتة لا تتغير. القائمة الكاملة في [الأخطاء](/developers/errors) |
| `message` | شرح للبشر، قد يتغير نصه، فلا تبنِ عليه منطقاً |
| `request_id` | رقم الطلب. أرسله لنا عند التواصل مع الدعم لنجد الطلب فوراً، ويرجع أيضاً في الهيدر `X-Request-Id` |
| `errors` | (في أخطاء التحقق فقط) الحقول الخاطئة وسبب كل منها |

## اصطلاحات مهمة

- **أرقام الجوال** بالصيغة الدولية E.164 مع رمز الدولة: `+966501234567`. لو أرسلتها بدون `+` نضيفها لك.
- **الأوقات** بتوقيت UTC. أغلب الحقول بصيغة ISO 8601 مثل `2026-10-22T18:23:06+00:00`، وبعضها بصيغة `2026-10-22 18:23:06` (UTC أيضاً).
- **المعرّفات:** رسائل واتساب لها `message_id` يبدأ بـ `wamid.`، وجهات الاتصال والمجموعات والقوالب لها `uuid`، ومحادثات موتشي لها `conversation_id`.
- **الهيدر `Server-Timing`** يرجع مع كل رد ويقول أين ذهب الوقت، مثلاً: `app;dur=27.4, db;dur=12.1, meta;dur=980`. القيمة `app` هي الطلب كله، و`meta` وقت خوادم واتساب، و`db` وقت قاعدة البيانات. مفيد جداً لو احتجت تعرف سبب أي بطء.

## الخطوة التالية

- اختر نوع المفتاح المناسب وتعرّف على قواعد الأمان: [المفاتيح والأمان](/developers/authentication)
- لو ستستخدم واتساب، اقرأ [قواعد واتساب](/developers/whatsapp-basics) قبل أي شيء، فهي توفر عليك أغلب الأخطاء.
- تريد كوداً جاهزاً؟ اذهب إلى [أمثلة كاملة](/developers/examples).
