# أمثلة كاملة

أمثلة جاهزة للنسخ تغطي أشهر الاستخدامات. غيّر المفاتيح والروابط وابدأ.

## متجر: إشعار الطلب ومتابعته (Node.js)

سيرفر Express واحد يرسل إشعار شحن، ويستقبل ضغطات الأزرار وحالات الرسائل.

```js
// npm i express
import express from 'express';
import crypto from 'node:crypto';

const BOTZO = '{{BASE_URL}}/api';
const headers = {
  Authorization: `Bearer ${process.env.BOTZO_API_KEY}`,
  Accept: 'application/json',
  'Content-Type': 'application/json',
};

// 1) إرسال إشعار الشحن: نص بأزرار لو النافذة مفتوحة، وإلا قالب
export async function notifyShipped(order) {
  const send = (path, body) => fetch(`${BOTZO}${path}`, {
    method: 'POST',
    headers: { ...headers, 'Idempotency-Key': `order-${order.id}-shipped` },
    body: JSON.stringify(body),
  }).then(async (res) => ({ ok: res.ok, body: await res.json() }));

  let result = await send('/send', {
    phone: order.phone,
    message: `طلبك رقم ${order.id} في الطريق 🚚`,
    buttons: [{ id: `track_${order.id}`, title: 'تتبع الطلب' }],
  });

  if (!result.ok && result.body.code === 'outside_24h_window') {
    result = await send('/send/template', {
      phone: order.phone,
      template: {
        name: 'order_shipped',
        language: { code: 'ar' },
        components: [{ type: 'body', parameters: [
          { type: 'text', text: order.customerName },
          { type: 'text', text: String(order.id) },
        ] }],
      },
    });
  }

  if (!result.ok) throw new Error(`${result.body.code}: ${result.body.message}`);
  await db.orders.update(order.id, { whatsappMessageId: result.body.message_id });
}

// 2) استقبال الأحداث
const app = express();
const seen = new Set(); // في الإنتاج استخدم Redis أو جدولاً

app.post('/botzo/webhook', express.raw({ type: 'application/json' }), async (req, res) => {
  const ts = req.get('X-Botzo-Timestamp') ?? '';
  const expected = 'sha256=' + crypto.createHmac('sha256', process.env.BOTZO_WEBHOOK_SECRET)
    .update(`${ts}.${req.body}`).digest('hex');
  const sig = req.get('X-Botzo-Signature') ?? '';
  if (sig.length !== expected.length || !crypto.timingSafeEqual(Buffer.from(sig), Buffer.from(expected))) {
    return res.sendStatus(401);
  }
  res.sendStatus(200);

  const event = JSON.parse(req.body);
  if (seen.has(event.id)) return;
  seen.add(event.id);

  if (event.type === 'message.received' && event.data.button?.id?.startsWith('track_')) {
    const orderId = event.data.button.id.replace('track_', '');
    const order = await db.orders.find(orderId);
    await fetch(`${BOTZO}/send`, {
      method: 'POST', headers,
      body: JSON.stringify({ phone: event.data.from, message: `رابط التتبع: ${order.trackingUrl}` }),
    });
  }

  if (event.type === 'message.status') {
    await db.orders.updateWhere(
      { whatsappMessageId: event.data.message_id },
      { whatsappStatus: event.data.status },
    );
  }
});

app.listen(3000);
```

## Laravel: خدمة إرسال ومستقبل أحداث

```php
// config/services.php
'botzo' => [
    'url' => env('BOTZO_URL', '{{BASE_URL}}/api'),
    'key' => env('BOTZO_API_KEY'),
    'webhook_secret' => env('BOTZO_WEBHOOK_SECRET'),
],
```

```php
// app/Services/Botzo.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class Botzo
{
    private function client(?string $idempotencyKey = null)
    {
        return Http::baseUrl(config('services.botzo.url'))
            ->withToken(config('services.botzo.key'))
            ->acceptJson()
            ->timeout(30)
            ->retry(3, fn ($attempt) => 1000 * 2 ** $attempt, fn ($e) => in_array(optional($e->response)->status(), [409, 429, 500, 502, 503]), throw: false)
            ->withHeaders(array_filter(['Idempotency-Key' => $idempotencyKey]));
    }

    public function sendText(string $phone, string $text, ?string $key = null): array
    {
        return $this->client($key)->post('/send', ['phone' => $phone, 'message' => $text])->json();
    }

    public function sendTemplate(string $phone, string $name, array $bodyParams, string $lang = 'ar', ?string $key = null): array
    {
        return $this->client($key)->post('/send/template', [
            'phone' => $phone,
            'template' => [
                'name' => $name,
                'language' => ['code' => $lang],
                'components' => [[
                    'type' => 'body',
                    'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $bodyParams),
                ]],
            ],
        ])->json();
    }
}
```

```php
// routes/web.php (أضف /botzo/webhook لاستثناءات CSRF)
Route::post('/botzo/webhook', function (Illuminate\Http\Request $request) {
    $ts = (string) $request->header('X-Botzo-Timestamp');
    $body = $request->getContent();
    $expected = 'sha256='.hash_hmac('sha256', $ts.'.'.$body, config('services.botzo.webhook_secret'));
    abort_unless(hash_equals($expected, (string) $request->header('X-Botzo-Signature')), 401);
    abort_if(abs(time() - (int) $ts) > 300, 401);

    $event = json_decode($body, true);
    if (Cache::add('botzo-event:'.$event['id'], true, now()->addDay())) {
        dispatch(new App\Jobs\HandleBotzoEvent($event));
    }

    return response()->noContent();
});
```

## Flutter: شاشة شات موتشي كاملة (مفتاح عام)

```dart
// pubspec.yaml: http, shared_preferences
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

const baseUrl = '{{BASE_URL}}/api/v1/assistant';
const publicKey = 'pk_your_public_key'; // المفتاح العام فقط داخل التطبيق

class MochiClient {
  String? conversationId;
  String? token;

  Map<String, String> get _headers => {
        'Authorization': 'Bearer $publicKey',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (token != null) 'X-Conversation-Token': token!,
      };

  Future<List<Map<String, dynamic>>> open() async {
    final prefs = await SharedPreferences.getInstance();
    token = prefs.getString('mochi_token');
    final res = await http.post(Uri.parse('$baseUrl/conversations'),
        headers: _headers, body: jsonEncode({'locale': 'ar'}));
    final body = jsonDecode(res.body);
    if (body['conversation_token'] != null) {
      token = body['conversation_token'];
      await prefs.setString('mochi_token', token!);
    }
    conversationId = body['conversation_id'];
    return List<Map<String, dynamic>>.from(body['messages']);
  }

  Future<Map<String, dynamic>> send(String text) async {
    final res = await http
        .post(Uri.parse('$baseUrl/conversations/$conversationId/messages'),
            headers: _headers, body: jsonEncode({'text': text}))
        .timeout(const Duration(seconds: 45));
    return jsonDecode(res.body);
  }

  Future<List<Map<String, dynamic>>> since(int afterId) async {
    final res = await http.get(
        Uri.parse('$baseUrl/conversations/$conversationId/messages?after=$afterId'),
        headers: _headers);
    return List<Map<String, dynamic>>.from(jsonDecode(res.body)['messages']);
  }
}

class MochiChatScreen extends StatefulWidget {
  const MochiChatScreen({super.key});
  @override
  State<MochiChatScreen> createState() => _MochiChatScreenState();
}

class _MochiChatScreenState extends State<MochiChatScreen> {
  final client = MochiClient();
  final input = TextEditingController();
  final messages = <Map<String, dynamic>>[];
  bool typing = false;

  @override
  void initState() {
    super.initState();
    client.open().then((history) => setState(() => messages.addAll(history)));
  }

  Future<void> sendMessage() async {
    final text = input.text.trim();
    if (text.isEmpty || typing) return;
    input.clear();
    setState(() {
      messages.add({'from': 'user', 'text': text});
      typing = true;
    });
    final body = await client.send(text);
    setState(() {
      typing = false;
      messages.addAll(List<Map<String, dynamic>>.from(body['replies'] ?? []));
      if (body['awaiting_team'] == true) {
        messages.add({'from': 'note', 'text': 'سيرد عليك أحد أفراد الفريق قريباً'});
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        appBar: AppBar(title: const Text('موتشي')),
        body: Column(children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(12),
              children: [
                for (final m in messages)
                  Align(
                    alignment: m['from'] == 'user' ? Alignment.centerRight : Alignment.centerLeft,
                    child: Container(
                      margin: const EdgeInsets.symmetric(vertical: 4),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: m['from'] == 'user' ? Colors.green.shade100 : Colors.grey.shade200,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Text(m['text'] ?? ''),
                    ),
                  ),
                if (typing) const Padding(padding: EdgeInsets.all(8), child: Text('موتشي يكتب…')),
              ],
            ),
          ),
          SafeArea(
            child: Row(children: [
              Expanded(child: TextField(controller: input, decoration: const InputDecoration(hintText: 'اكتب رسالتك…'))),
              IconButton(icon: const Icon(Icons.send), onPressed: typing ? null : sendMessage),
            ]),
          ),
        ]),
      ),
    );
  }
}
```

## Swift (SwiftUI): موتشي بمفتاح عام

```swift
import SwiftUI

struct MochiMessage: Identifiable, Decodable { let id: Int?; let from: String; let text: String
    var stableId: String { "\(id ?? 0)-\(text)" }
}

@MainActor
final class MochiChat: ObservableObject {
    @Published var messages: [MochiMessage] = []
    @Published var typing = false
    private let base = "{{BASE_URL}}/api/v1/assistant"
    private let key = "pk_your_public_key"
    private var conversationId = ""
    private var token: String? = UserDefaults.standard.string(forKey: "mochi_token")

    private func request(_ path: String, method: String = "GET", body: [String: Any]? = nil) async throws -> [String: Any] {
        var r = URLRequest(url: URL(string: base + path)!)
        r.httpMethod = method
        r.timeoutInterval = 45
        r.setValue("Bearer \(key)", forHTTPHeaderField: "Authorization")
        r.setValue("application/json", forHTTPHeaderField: "Accept")
        r.setValue("application/json", forHTTPHeaderField: "Content-Type")
        if let token { r.setValue(token, forHTTPHeaderField: "X-Conversation-Token") }
        if let body { r.httpBody = try JSONSerialization.data(withJSONObject: body) }
        let (data, _) = try await URLSession.shared.data(for: r)
        return try JSONSerialization.jsonObject(with: data) as? [String: Any] ?? [:]
    }

    private func decode(_ value: Any?) -> [MochiMessage] {
        guard let value, let data = try? JSONSerialization.data(withJSONObject: value) else { return [] }
        return (try? JSONDecoder().decode([MochiMessage].self, from: data)) ?? []
    }

    func open() async {
        let body = try? await request("/conversations", method: "POST", body: ["locale": "ar"])
        if let newToken = body?["conversation_token"] as? String {
            token = newToken
            UserDefaults.standard.set(newToken, forKey: "mochi_token")
        }
        conversationId = body?["conversation_id"] as? String ?? ""
        messages = decode(body?["messages"])
    }

    func send(_ text: String) async {
        messages.append(MochiMessage(id: nil, from: "user", text: text))
        typing = true
        let body = try? await request("/conversations/\(conversationId)/messages", method: "POST", body: ["text": text])
        typing = false
        messages += decode(body?["replies"])
    }
}
```

## Kotlin (Android): موتشي بمفتاح عام

```kotlin
// build.gradle: implementation("com.squareup.okhttp3:okhttp:4.12.0")
class MochiRepository(context: Context) {
    private val client = OkHttpClient.Builder().callTimeout(45, TimeUnit.SECONDS).build()
    private val prefs = context.getSharedPreferences("mochi", Context.MODE_PRIVATE)
    private val base = "{{BASE_URL}}/api/v1/assistant"
    private val key = "pk_your_public_key"
    private var conversationId = ""

    private fun call(path: String, json: JSONObject? = null): JSONObject {
        val builder = Request.Builder().url(base + path)
            .header("Authorization", "Bearer $key")
            .header("Accept", "application/json")
        prefs.getString("token", null)?.let { builder.header("X-Conversation-Token", it) }
        if (json != null) builder.post(json.toString().toRequestBody("application/json".toMediaType()))
        client.newCall(builder.build()).execute().use { return JSONObject(it.body!!.string()) }
    }

    suspend fun open(): JSONArray = withContext(Dispatchers.IO) {
        val body = call("/conversations", JSONObject().put("locale", "ar"))
        body.optString("conversation_token").takeIf { it.isNotEmpty() }?.let {
            prefs.edit().putString("token", it).apply()
        }
        conversationId = body.getString("conversation_id")
        body.getJSONArray("messages")
    }

    suspend fun send(text: String): JSONObject = withContext(Dispatchers.IO) {
        call("/conversations/$conversationId/messages", JSONObject().put("text", text))
    }

    suspend fun since(afterId: Int): JSONArray = withContext(Dispatchers.IO) {
        call("/conversations/$conversationId/messages?after=$afterId").getJSONArray("messages")
    }
}
```

## موقع ويب: موتشي عبر سيرفرك (مفتاح سري)

المستخدمون مسجلون في موقعك، فسيرفرك يحدد هويتهم ويخفي المفتاح السري:

```js
// سيرفرك (Express): وسيط بين المتصفح وبوتزو
app.post('/api/support/messages', requireLogin, express.json(), async (req, res) => {
  const headers = {
    Authorization: `Bearer ${process.env.BOTZO_MOCHI_KEY}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };
  const open = await fetch('{{BASE_URL}}/api/v1/assistant/conversations', {
    method: 'POST', headers,
    body: JSON.stringify({ user: { id: String(req.user.id), name: req.user.name } }),
  }).then((r) => r.json());

  const reply = await fetch(`{{BASE_URL}}/api/v1/assistant/conversations/${open.conversation_id}/messages`, {
    method: 'POST', headers, body: JSON.stringify({ text: String(req.body.text).slice(0, 500) }),
  });
  res.status(reply.status).json(await reply.json());
});
```

```js
// المتصفح: لا يرى أي مفتاح
const res = await fetch('/api/support/messages', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ text: 'كم سعر باقة النمو؟' }),
});
const { replies } = await res.json();
```

## Python: حملة من نظامك

```python
import os, requests

API = "{{BASE_URL}}/api"
HEADERS = {"Authorization": f"Bearer {os.environ['BOTZO_API_KEY']}", "Accept": "application/json"}

# 1) أنشئ مجموعة وأضف العملاء
group = requests.post(f"{API}/contact-groups", headers=HEADERS, json={"name": "عملاء رمضان"}).json()["id"]
for customer in customers:
    requests.post(f"{API}/contacts", headers=HEADERS, json={
        "first_name": customer["name"], "phone": customer["phone"], "group": [group],
        "metadata": {"city": customer["city"]},
    })

# 2) اختر القالب
templates = requests.get(f"{API}/templates?per_page=100", headers=HEADERS).json()["data"]
template = next(t for t in templates if t["name"] == "ramadan_offer")

# 3) أطلق الحملة غداً 8 مساءً بتوقيت المؤسسة
campaign = requests.post(f"{API}/campaigns", headers=HEADERS, json={
    "name": "عرض رمضان",
    "template": template["uuid"],
    "contacts": group,
    "skip_schedule": False,
    "time": "2026-11-01 20:00",
    "body": {"parameters": [
        {"type": "text", "selection": "dynamic", "value": "first name"},
        {"type": "text", "selection": "dynamic", "value": "field:city"},
    ]},
}).json()
print(campaign["data"]["contacts_count"], campaign["data"]["scheduled_at_utc"])
```
