# الـ Webhooks

بدل أن يسأل سيرفرك بوتزو كل دقيقة «هل في جديد؟»، بوتزو يرسل لسيرفرك طلب `POST` **لحظة** حدوث أي شيء: رسالة من عميل، تغيّر حالة رسالة، طلب موظف، أو رد في محادثة موتشي.

## الإعداد

1. من **أدوات المطوّرين ← Webhooks** اكتب رابط سيرفرك (يجب أن يبدأ بـ `https://`)، واختر الأحداث، ثم **إضافة Webhook**.
2. يظهر **سر التوقيع** (يبدأ بـ `whsec_`) **مرة واحدة**. احفظه في سيرفرك (`BOTZO_WEBHOOK_SECRET`).
3. اضغط **«ابعت إشعار تجريبي»**: يصلك حدث `webhook.test` وترى نتيجته في «آخر الإشعارات».

حتى 3 روابط لكل مؤسسة، لكل رابط أحداثه وسره الخاص. وتستطيع إيقاف أي رابط مؤقتاً أو تدوير سره.

### المستقبِل التجريبي

لا يوجد سيرفر بعد؟ اضغط **«استخدم المستقبِل التجريبي»**: هذا رابط خاص بمؤسستك داخل بوتزو، يحفظ آخر 20 طلباً يصله ويعرض محتواها، ويقول لك هل **التوقيع صحيح**. أضفه كـ Webhook وجرّب (أرسل رسالة من جوالك مثلاً) لترى شكل كل حدث حقيقي قبل أن تكتب سطر كود.

## شكل الطلب

كل إشعار طلب `POST` بجسم JSON وهذه الهيدرز:

| الهيدر | مثال | الوصف |
| --- | --- | --- |
| `Content-Type` | `application/json` | |
| `User-Agent` | `Botzo-Webhooks/1.0` | |
| `X-Botzo-Event` | `message.received` | نوع الحدث |
| `X-Botzo-Delivery` | `evt_QM7BsjgZLhxD1uKuq8vpyuOC` | رقم الحدث، **نفسه في كل إعادة محاولة** |
| `X-Botzo-Timestamp` | `1792693386` | وقت الإرسال (ثوانٍ Unix) |
| `X-Botzo-Signature` | `sha256=60a52fe7…` | التوقيع (تحت) |

والجسم دائماً بهذا الغلاف:

```json
{
  "id": "evt_QM7BsjgZLhxD1uKuq8vpyuOC",
  "type": "message.received",
  "created_at": "2026-10-22T18:23:11+00:00",
  "data": { "...": "حسب نوع الحدث" }
}
```

## التحقق من التوقيع

أي أحد يعرف رابطك يستطيع أن يرسل له طلباً مزيفاً. التوقيع يثبت أن الطلب من بوتزو ولم يتغير:

```text
signature = "sha256=" + HMAC_SHA256(secret, timestamp + "." + raw_body)
```

1. خذ **الجسم الخام** كما وصل بالضبط (قبل تحويله لـ JSON).
2. احسب HMAC-SHA256 بسر التوقيع على النص `X-Botzo-Timestamp` + `.` + الجسم.
3. قارن النتيجة مع `X-Botzo-Signature` بمقارنة **ثابتة الوقت**.
4. ارفض الطلب لو `X-Botzo-Timestamp` أقدم من 5 دقائق (يحميك من إعادة إرسال طلب قديم).

<div class="code-tabs">

```js
// Node.js (Express): لاحظ express.raw لنحصل على الجسم الخام
import crypto from 'node:crypto';
import express from 'express';

const app = express();
app.post('/botzo/webhook', express.raw({ type: 'application/json' }), (req, res) => {
  const timestamp = req.get('X-Botzo-Timestamp') ?? '';
  const signature = req.get('X-Botzo-Signature') ?? '';
  const expected = 'sha256=' + crypto
    .createHmac('sha256', process.env.BOTZO_WEBHOOK_SECRET)
    .update(`${timestamp}.${req.body}`)
    .digest('hex');

  const valid = signature.length === expected.length
    && crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected));
  if (!valid || Math.abs(Date.now() / 1000 - Number(timestamp)) > 300) {
    return res.status(401).send('invalid signature');
  }

  const event = JSON.parse(req.body);
  res.sendStatus(200);            // رد فوراً
  handleEvent(event);             // ثم عالج الحدث
});
```

```php
// PHP / Laravel
Route::post('/botzo/webhook', function (Illuminate\Http\Request $request) {
    $timestamp = (string) $request->header('X-Botzo-Timestamp');
    $body = $request->getContent(); // الجسم الخام
    $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, env('BOTZO_WEBHOOK_SECRET'));

    if (! hash_equals($expected, (string) $request->header('X-Botzo-Signature'))
        || abs(time() - (int) $timestamp) > 300) {
        abort(401);
    }

    $event = json_decode($body, true);
    dispatch(new HandleBotzoEvent($event)); // عالج في الخلفية
    return response()->noContent();
});
```

```python
# Python (Flask)
import hmac, hashlib, os, time
from flask import Flask, request, abort

app = Flask(__name__)

@app.post("/botzo/webhook")
def botzo_webhook():
    timestamp = request.headers.get("X-Botzo-Timestamp", "")
    body = request.get_data(as_text=True)  # الجسم الخام
    expected = "sha256=" + hmac.new(os.environ["BOTZO_WEBHOOK_SECRET"].encode(),
                                    f"{timestamp}.{body}".encode(), hashlib.sha256).hexdigest()
    if not hmac.compare_digest(expected, request.headers.get("X-Botzo-Signature", "")) \
            or abs(time.time() - int(timestamp or 0)) > 300:
        abort(401)
    event = request.get_json()
    handle_event(event)
    return "", 200
```

</div>

> **أشهر خطأ:** حساب التوقيع على JSON بعد تحويله وإعادة كتابته. المسافات أو ترتيب الحقول قد يتغير، فلا يتطابق التوقيع. استخدم **الجسم الخام** دائماً.

## الأحداث

| الحدث | متى |
| --- | --- |
| `message.received` | عميل أرسل رسالة واتساب (نص، صورة، صوت، ملف، ضغطة زر) |
| `message.status` | رسالة أرسلتها تغيرت حالتها: `sent` ← `delivered` ← `read`، أو `failed` |
| `conversation.handoff` | المحادثة تحولت لموظف (العميل طلب، أو موتشي حوّل، أو أتمتة) |
| `conversation.reply` | موتشي أو موظف رد في محادثة من الويدجت أو من API موتشي |
| `webhook.test` | زر «ابعت إشعار تجريبي» |

### message.received

```json
{
  "id": "evt_QM7BsjgZLhxD1uKuq8vpyuOC",
  "type": "message.received",
  "created_at": "2026-10-22T18:23:11+00:00",
  "data": {
    "message_id": "wamid.INBOUND1",
    "channel": "whatsapp",
    "from": "+966501234567",
    "contact": { "name": "Sara Ali", "phone": "+966501234567" },
    "type": "text",
    "text": "متى يوصل طلبي؟",
    "button": null,
    "media": null,
    "timestamp": "2026-10-22T18:23:11+00:00"
  }
}
```

| `data.type` | ماذا يحتوي |
| --- | --- |
| `text` | `text`: نص الرسالة |
| `interactive` أو `button` | `button.id` و `button.title`: الزر الذي ضغطه العميل (الـ `id` الذي أرسلته أنت) |
| `image`، `video`، `audio`، `document`، `sticker` | `media.type` و `media.mime_type` و `media.caption` و `media.filename`، و `media.voice: true` للرسائل الصوتية المسجلة |

مثال ضغطة زر:

```json
"data": {
  "message_id": "wamid.INBOUND3",
  "from": "+966501234567",
  "type": "interactive",
  "text": null,
  "button": { "id": "track_order", "title": "تتبع الطلب" },
  "media": null
}
```

مثال صورة:

```json
"data": {
  "message_id": "wamid.INBOUND2",
  "type": "image",
  "media": { "type": "image", "mime_type": "image/jpeg", "caption": "صورة المنتج", "filename": null, "voice": false }
}
```

> الملف نفسه لا يُرسل في الحدث. يظهر للفريق في صندوق المحادثات.

### message.status

```json
{
  "id": "evt_RuQMhTerKNbXdPBmpqRgyGNs",
  "type": "message.status",
  "created_at": "2026-10-22T18:23:11+00:00",
  "data": {
    "message_id": "wamid.HBgMOTY2NTAxMjM0NTY3FQIAERgSMEE2",
    "channel": "whatsapp",
    "to": "+966501234567",
    "status": "read",
    "error": null,
    "timestamp": "2026-10-22T18:23:11+00:00"
  }
}
```

عند الفشل:

```json
"data": {
  "message_id": "wamid.TEMPLATE1",
  "to": "+966501234567",
  "status": "failed",
  "error": { "code": 131026, "title": "Message undeliverable", "message": "Message undeliverable" }
}
```

`message_id` هو نفس الذي رجع لك عند الإرسال، فتربط الحدث بالرسالة في نظامك. الحدث يُرسل فقط عندما **تتقدم** الحالة (لا يرسل `delivered` بعد `read`).

### conversation.handoff

```json
{
  "id": "evt_30gW1n7TG1SoQkRadkKZyjBX",
  "type": "conversation.handoff",
  "created_at": "2026-10-22T18:23:11+00:00",
  "data": {
    "channel": "whatsapp",
    "contact": { "name": "Sara Ali", "phone": "+966501234567" },
    "reason": "human_handoff",
    "timestamp": "2026-10-22T18:23:11+00:00"
  }
}
```

| `reason` | السبب |
| --- | --- |
| `human_handoff` | العميل طلب موظفاً بكلمات مثل «أبي أكلم موظف» |
| `ai_knowledge_gap` | موتشي لم يجد إجابة في معلومات المؤسسة فحوّل للفريق |
| `ai_unavailable` | الذكاء الاصطناعي لم يستطع الرد (مثلاً انتهى الرصيد) فحوّل للفريق |
| `voice_call_handoff` | طُلب موظف أثناء مكالمة صوتية مع موتشي |
| `flow_assignment` | خطوة «تحويل لموظف» في أتمتة |

`channel` قيمته `whatsapp` أو `web` (الويدجت وAPI موتشي)، و `contact.phone` يكون `null` لزوار الويب.

### conversation.reply

```json
{
  "id": "evt_xTXJcJDSuckQj3Btd0MY6nSQ",
  "type": "conversation.reply",
  "created_at": "2026-10-22T18:23:11+00:00",
  "data": {
    "conversation_id": "7f6c5f69-f8a3-4fc3-89f7-50cdb64f69b6",
    "source": "api",
    "user_id": "customer-42",
    "message_id": 127,
    "from": "agent",
    "text": "معك سالم من فريق المبيعات، كيف أقدر أساعدك؟",
    "timestamp": "2026-10-22T18:23:11+00:00"
  }
}
```

| الحقل | الوصف |
| --- | --- |
| `source` | `api` (محادثة من API موتشي) أو `widget` (ويدجت الموقع) |
| `user_id` | الـ `user.id` الذي أرسله سيرفرك (مع المفتاح السري)، وإلا `null` |
| `from` | `assistant` (موتشي) أو `agent` (موظف) |

استخدمه لإرسال إشعار للمستخدم في تطبيقك عندما يرد الموظف، دون أن تسأل الـ API كل شوية.

### webhook.test

```json
{
  "id": "evt_gh6R8NqOyRV786sZqdhjWwQr",
  "type": "webhook.test",
  "created_at": "2026-10-22T18:23:11+00:00",
  "data": { "message": "Test event from Botzo. If you can read this, your webhook works.", "timestamp": "2026-10-22T18:23:11+00:00" }
}
```

## الرد على الإشعار وإعادة المحاولة

- رد بأي كود **2xx** خلال **5 ثوانٍ**. أي كود آخر، أو انتهاء المهلة، أو تحويل (3xx) يعتبر فشلاً.
- **رد أولاً ثم عالج:** احفظ الحدث وأرجع `200` فوراً، ونفّذ العمل الثقيل في الخلفية.
- عند الفشل نعيد المحاولة بنفس `X-Botzo-Delivery` بعد: **دقيقة، 5 دقائق، 30 دقيقة، ساعتين** (حتى 5 محاولات). المحاولات تتم كل بضع دقائق، فقد تتأخر قليلاً عن الموعد.
- بعد **20 فشلاً متتالياً** يتوقف الرابط تلقائياً وتظهر حالته «اتوقف بعد فشل متكرر». أصلح سيرفرك ثم اضغط «تشغيل».
- سجل آخر 50 إشعاراً (النتيجة، المحاولات، الزمن، الخطأ) في **أدوات المطوّرين ← آخر الإشعارات**، ونحتفظ بالسجل 14 يوماً.

## أفضل الممارسات

- **تجاهل المكرر:** قد يصلك نفس الحدث أكثر من مرة (إعادة محاولة بعد مهلة). احفظ `id` لكل حدث عالجته وتجاهل المكرر.
- **لا تعتمد على الترتيب:** قد يصل `read` قبل `delivered` نادراً. اعتمد على أعلى حالة وصلتك.
- **تحقق من التوقيع دائماً** قبل أي عمل.
- الرابط يجب أن يكون `https` وعنوانه عام. الروابط الداخلية (`localhost`، `10.x`، `192.168.x`) لا نرسل لها أبداً.
- عند تدوير السر من لوحة التحكم يتوقف القديم فوراً، فحدّث سيرفرك مباشرة.
