# API موتشي (المساعد الذكي)

موتشي هو المساعد الذكي لمؤسستك: يرد على عملائك بشخصيتها ومعلوماتها (الأسئلة الشائعة والملفات المرفوعة)، ويحوّل العميل لموظف عندما يطلب. بهذا الـ API تضع موتشي **داخل تطبيقك أو موقعك بتصميمك أنت**: أنت ترسم الشاشة، وموتشي يرد.

كل محادثة تظهر في صندوق المحادثات في بوتزو بعلامة **«تطبيق: اسم المفتاح»**، ويستطيع فريقك الرد منها، ويصل رد الموظف لتطبيقك.

> **قبل البدء:** المساعد الذكي يجب أن يكون **مفعّلاً** في المؤسسة (لوحة التحكم ← الأتمتة ← المساعد الذكي)، ومعلوماته مضافة. وإلا يرجع `403 assistant_not_active`.

## الفكرة في 30 ثانية

```text
تطبيقك                                 بوتزو
  │  1) POST /conversations  ───────────▶  ينشئ أو يكمل محادثة
  │  ◀─────────── conversation_id (+ token للمفتاح العام)
  │  2) POST /conversations/{id}/messages  "كم سعر باقة النمو؟"
  │  ◀─────────── رد موتشي في نفس الطلب (2–5 ثوانٍ)
  │  3) GET  /conversations/{id}/messages?after=…   (أو Webhook)
  │  ◀─────────── ردود الموظفين من صندوق المحادثات
```

| الطلب | الوصف |
| --- | --- |
| <span class="method post">POST</span> `/api/v1/assistant/conversations` | فتح محادثة أو إكمالها، مع تاريخها |
| <span class="method post">POST</span> `/api/v1/assistant/conversations/{id}/messages` | إرسال رسالة واستلام رد موتشي |
| <span class="method get">GET</span> `/api/v1/assistant/conversations/{id}/messages` | الرسائل الجديدة (منها ردود الموظفين) |

## أي مفتاح أستخدم؟

| | مفتاح سري «موتشي فقط» | مفتاح عام `pk_` |
| --- | --- | --- |
| أين يوضع | سيرفرك فقط | داخل التطبيق أو الموقع (مسموح) |
| من يحدد المستخدم | سيرفرك يرسل `user.id` | كل جهاز يأخذ محادثته الخاصة |
| نفس المستخدم من أكثر من جهاز | نفس المحادثة بنفس `user.id` | محادثة لكل جهاز |
| الوصول لمحادثة | بـ `conversation_id` | بـ `conversation_id` **و** `X-Conversation-Token` |
| الحدود | حدود الـ API العادية للمؤسسة | حدود صارمة لكل جهاز (تحت) |
| مثال | تطبيق له سيرفر، وموقع فيه تسجيل دخول | تطبيق بدون سيرفر، صفحة عامة |

**القاعدة:** لو عندك سيرفر ومستخدمين مسجلين، استخدم المفتاح السري واجعل سيرفرك وسيطاً. لو لا يوجد سيرفر، استخدم المفتاح العام.

## 1) فتح محادثة

<span class="method post">POST</span> `/api/v1/assistant/conversations`

| الحقل | النوع | مطلوب | الوصف |
| --- | --- | --- | --- |
| `user.id` | string | نعم مع المفتاح السري | معرّف المستخدم في نظامك (حتى 191 حرفاً). نفس القيمة = نفس المحادثة دائماً |
| `user.name` | string | لا | اسم المستخدم، يظهر لفريقك في صندوق المحادثات |
| `locale` | string | لا | `ar` أو `en` |

مع **المفتاح العام** لا ترسل `user`. أول مرة يرجع لك `conversation_token`: **احفظه على الجهاز** (مثلاً SharedPreferences أو Keychain أو localStorage). في المرات التالية أرسله في الهيدر `X-Conversation-Token` مع نفس الطلب فتكمل نفس المحادثة بدل فتح واحدة جديدة.

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/v1/assistant/conversations \
  -H "Authorization: Bearer $BOTZO_MOCHI_KEY" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"user": {"id": "customer-42", "name": "سارة"}, "locale": "ar"}'
```

```js
// على سيرفرك (Node.js) بالمفتاح السري
const res = await fetch('{{BASE_URL}}/api/v1/assistant/conversations', {
  method: 'POST',
  headers: { Authorization: `Bearer ${process.env.BOTZO_MOCHI_KEY}`, Accept: 'application/json', 'Content-Type': 'application/json' },
  body: JSON.stringify({ user: { id: String(currentUser.id), name: currentUser.name }, locale: 'ar' }),
});
const { conversation_id, messages } = await res.json();
```

```dart
// Flutter بالمفتاح العام: يحفظ توكن المحادثة على الجهاز
final prefs = await SharedPreferences.getInstance();
final saved = prefs.getString('mochi_token');
final res = await http.post(
  Uri.parse('{{BASE_URL}}/api/v1/assistant/conversations'),
  headers: {
    'Authorization': 'Bearer pk_your_public_key',
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    if (saved != null) 'X-Conversation-Token': saved,
  },
  body: jsonEncode({'locale': 'ar'}),
);
final body = jsonDecode(res.body);
if (body['conversation_token'] != null) {
  await prefs.setString('mochi_token', body['conversation_token']);
}
final conversationId = body['conversation_id'];
```

```swift
// Swift بالمفتاح العام
var request = URLRequest(url: URL(string: "{{BASE_URL}}/api/v1/assistant/conversations")!)
request.httpMethod = "POST"
request.setValue("Bearer pk_your_public_key", forHTTPHeaderField: "Authorization")
request.setValue("application/json", forHTTPHeaderField: "Accept")
request.setValue("application/json", forHTTPHeaderField: "Content-Type")
if let token = UserDefaults.standard.string(forKey: "mochi_token") {
    request.setValue(token, forHTTPHeaderField: "X-Conversation-Token")
}
request.httpBody = try JSONSerialization.data(withJSONObject: ["locale": "ar"])
let (data, _) = try await URLSession.shared.data(for: request)
let body = try JSONSerialization.jsonObject(with: data) as! [String: Any]
if let token = body["conversation_token"] as? String {
    UserDefaults.standard.set(token, forKey: "mochi_token")
}
let conversationId = body["conversation_id"] as! String
```

```kotlin
// Kotlin (OkHttp) بالمفتاح العام
val prefs = context.getSharedPreferences("mochi", Context.MODE_PRIVATE)
val request = Request.Builder()
    .url("{{BASE_URL}}/api/v1/assistant/conversations")
    .header("Authorization", "Bearer pk_your_public_key")
    .header("Accept", "application/json")
    .apply { prefs.getString("token", null)?.let { header("X-Conversation-Token", it) } }
    .post("""{"locale":"ar"}""".toRequestBody("application/json".toMediaType()))
    .build()
val body = JSONObject(client.newCall(request).execute().body!!.string())
body.optString("conversation_token").takeIf { it.isNotEmpty() }?.let {
    prefs.edit().putString("token", it).apply()
}
val conversationId = body.getString("conversation_id")
```

</div>

الرد (محادثة جديدة: `201`):

```json
{
  "statusCode": 201,
  "conversation_id": "2b4c1e1b-6609-4832-844b-e50fa911ed1d",
  "conversation_token": "YuR5mNlad1Og5BUgVIv6Z5b6sEaRVgTdmnkQkeE2QVHyz0rfoRy4hEqrn4uH9DzR",
  "messages": []
}
```

- `conversation_token` يرجع **فقط** مع المفتاح العام وعند إنشاء محادثة جديدة.
- لو المحادثة موجودة يرجع `200` ومعه `messages`: آخر 50 رسالة بالترتيب، لتعرضها مباشرة عند فتح الشاشة.

## 2) إرسال رسالة واستلام الرد

<span class="method post">POST</span> `/api/v1/assistant/conversations/{conversation_id}/messages`

| الحقل | النوع | مطلوب | الوصف |
| --- | --- | --- | --- |
| `text` | string | نعم | رسالة المستخدم، حتى 500 حرف |
| `wait` | boolean | لا | `true` (الافتراضي): ينتظر رد موتشي ويرجعه في نفس الطلب. `false`: يرجع فوراً والرد يأتي لاحقاً |

مع المفتاح العام أضف الهيدر `X-Conversation-Token`.

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/v1/assistant/conversations/7f6c5f69-f8a3-4fc3-89f7-50cdb64f69b6/messages \
  -H "Authorization: Bearer $BOTZO_MOCHI_KEY" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"text": "كم سعر باقة النمو؟"}'
```

```js
const res = await fetch(`{{BASE_URL}}/api/v1/assistant/conversations/${conversationId}/messages`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${process.env.BOTZO_MOCHI_KEY}`, Accept: 'application/json', 'Content-Type': 'application/json' },
  body: JSON.stringify({ text: 'كم سعر باقة النمو؟' }),
});
const { replies, awaiting_team } = await res.json();
replies.forEach((r) => showBubble(r.from, r.text));
if (awaiting_team) showNote('سيرد عليك أحد أفراد الفريق قريباً');
```

```dart
final res = await http.post(
  Uri.parse('{{BASE_URL}}/api/v1/assistant/conversations/$conversationId/messages'),
  headers: {
    'Authorization': 'Bearer pk_your_public_key',
    'X-Conversation-Token': token,
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  body: jsonEncode({'text': 'كم سعر باقة النمو؟'}),
);
final body = jsonDecode(res.body);
for (final reply in body['replies']) {
  messages.add(ChatMessage(from: reply['from'], text: reply['text']));
}
```

```swift
var request = URLRequest(url: URL(string: "{{BASE_URL}}/api/v1/assistant/conversations/\(conversationId)/messages")!)
request.httpMethod = "POST"
request.setValue("Bearer pk_your_public_key", forHTTPHeaderField: "Authorization")
request.setValue(token, forHTTPHeaderField: "X-Conversation-Token")
request.setValue("application/json", forHTTPHeaderField: "Content-Type")
request.setValue("application/json", forHTTPHeaderField: "Accept")
request.timeoutInterval = 45
request.httpBody = try JSONSerialization.data(withJSONObject: ["text": "كم سعر باقة النمو؟"])
let (data, _) = try await URLSession.shared.data(for: request)
```

```kotlin
val request = Request.Builder()
    .url("{{BASE_URL}}/api/v1/assistant/conversations/$conversationId/messages")
    .header("Authorization", "Bearer pk_your_public_key")
    .header("X-Conversation-Token", token)
    .header("Accept", "application/json")
    .post(JSONObject(mapOf("text" to "كم سعر باقة النمو؟")).toString().toRequestBody("application/json".toMediaType()))
    .build()
val replies = JSONObject(client.newCall(request).execute().body!!.string()).getJSONArray("replies")
```

</div>

الرد مع `wait=true`:

```json
{
  "statusCode": 200,
  "message_id": 113,
  "replies": [
    {
      "id": 114,
      "from": "assistant",
      "name": null,
      "text": "أهلاً سارة! باقة النمو بـ 599 ريال في الشهر، وفيها المساعد الذكي وحتى 5 موظفين.",
      "at": "2026-10-22T18:23:06+00:00"
    }
  ],
  "awaiting_team": false
}
```

| الحقل | الوصف |
| --- | --- |
| `message_id` | رقم رسالة المستخدم. استخدمه في `after` لجلب ما بعدها |
| `replies` | الرسائل التي جاءت بعد رسالة المستخدم (غالباً رد موتشي) |
| `awaiting_team` | `true` يعني موتشي **لم** يرد تلقائياً: العميل طلب موظفاً، أو الفريق يتولى المحادثة الآن، أو انتهى حد الردود. اعرض للمستخدم أن الفريق سيرد، واستمع لرد الموظف (القسم 3) |

الرد مع `wait=false` يرجع فوراً بـ `202` و `replies: []`، والرد يأتي خلال ثوانٍ عبر `GET` أو حدث `conversation.reply`. استخدمه عندما لا تريد إبقاء الاتصال مفتوحاً.

> **زمن الرد:** عادة 2 إلى 5 ثوانٍ (وقت الذكاء الاصطناعي). اجعل مهلة الطلب 45 ثانية على الأقل، واعرض مؤشر «موتشي يكتب…» أثناء الانتظار.

## 3) جلب الرسائل الجديدة

<span class="method get">GET</span> `/api/v1/assistant/conversations/{conversation_id}/messages?after={id}`

يرجع كل الرسائل بعد الرقم `after` بالترتيب. بدون `after` يرجع آخر 50 رسالة.

```json
{
  "statusCode": 200,
  "messages": [
    { "id": 115, "from": "user", "name": null, "text": "وهل فيها تجربة مجانية؟", "at": "2026-10-22T18:23:11+00:00" },
    { "id": 116, "from": "assistant", "name": null, "text": "نعم، عندنا تجربة مجانية 14 يوم…", "at": "2026-10-22T18:23:13+00:00" },
    { "id": 117, "from": "agent", "name": "سالم", "text": "معك سالم من فريق المبيعات، كيف أقدر أساعدك؟", "at": "2026-10-22T18:30:02+00:00" }
  ]
}
```

| `from` | المعنى |
| --- | --- |
| `user` | المستخدم (رسالته هو) |
| `assistant` | موتشي |
| `agent` | موظف من فريقك رد من صندوق المحادثات، واسمه في `name` |

**متى تسأل؟** عندما تكون شاشة الشات مفتوحة وآخر رد فيه `awaiting_team: true`، اسأل كل 10 إلى 15 ثانية واحفظ آخر `id` وصلك. الأفضل للتطبيقات التي لها سيرفر: حدث `conversation.reply` في [الـ Webhooks](/developers/webhooks) يصلك لحظياً مع كل رد من موتشي أو موظف، ثم يرسله سيرفرك للتطبيق (مثلاً بإشعار).

## التحويل لموظف

عندما يكتب المستخدم شيئاً مثل «أبي أكلم موظف» يحوّل موتشي المحادثة للفريق، ويرد برسالة تأكيد، وتصبح `awaiting_team: true` للرسائل التالية، ويصل حدث `conversation.handoff` لسيرفرك. عندما يرد الموظف يظهر رده بـ `from: agent`.

## الحدود

| الحد | المفتاح العام | المفتاح السري |
| --- | --- | --- |
| محادثات جديدة لكل IP في الساعة | 10 | بدون حد |
| رسائل لكل محادثة في الساعة | 30 | 30 |
| أقل وقت بين رسالتين في نفس المحادثة | 3 ثوانٍ | 3 ثوانٍ |
| طلبات في الدقيقة | 60 لكل جهاز (IP) | 120 للمؤسسة كلها |
| ردود موتشي في اليوم | حسب إعدادات المؤسسة (الافتراضي 300) | نفس الحد |

تجاوز الحد يرجع `429` بأحد الأكواد: `too_many_conversations` أو `too_many_messages` أو `rate_limited`. انتظر وأعد المحاولة.

## الأخطاء الخاصة بموتشي

| الحالة | `code` | السبب |
| --- | --- | --- |
| 400 | `validation_error` | `user.id` ناقص مع المفتاح السري، أو `text` فارغ أو أطول من 500 |
| 403 | `assistant_not_active` | المساعد الذكي مقفول في المؤسسة |
| 403 | `api_key_scope_denied` | المفتاح ليس «موتشي فقط» أو «عام» أو «كل الصلاحيات» |
| 404 | `conversation_not_found` | `conversation_id` خاطئ، أو من مؤسسة أخرى، أو (للمفتاح العام) التوكن ناقص أو خاطئ |
| 429 | `too_many_conversations` / `too_many_messages` / `rate_limited` | تجاوزت الحدود أعلاه |

مثال: محادثة بدون توكن الجهاز:

```json
{
  "statusCode": 404,
  "code": "conversation_not_found",
  "message": "Conversation not found.",
  "request_id": "85b33f9a-40c7-407a-bc56-62aa26ba472e"
}
```

## نصائح لتجربة ممتازة

- **احفظ التاريخ محلياً** وحمّله من الرد عند فتح الشاشة، فلا يرى المستخدم شاشة فارغة.
- **مؤشر الكتابة:** اعرض «موتشي يكتب…» من لحظة الإرسال حتى يصل الرد.
- **لا ترسل رسالتين في نفس اللحظة:** عطّل زر الإرسال حتى يصل الرد (هناك حد 3 ثوانٍ بين الرسائل).
- **ردود الموظف:** عند `awaiting_team: true` ابدأ السؤال كل 10–15 ثانية أو استخدم Webhook.
- **المفتاح العام فقط داخل التطبيق.** المفتاح السري يبقى على سيرفرك دائماً.

تطبيق شات كامل بـ Flutter وآخر بـ JavaScript في [أمثلة كاملة](/developers/examples).
