# Mochi API (AI assistant)

Mochi is your organization's AI assistant: it answers customers with your organization's persona and knowledge (FAQs and uploaded files), and hands over to a person when asked. With this API you put Mochi **inside your app or website with your own design**: you draw the screen, Mochi answers.

Every conversation shows in the Botzo inbox labeled **"App: key name"**; your team can answer there, and their replies reach your app.

> **Before you start:** the AI assistant must be **turned on** for the organization (Dashboard → Automation → AI assistant), with its knowledge added. Otherwise you get `403 assistant_not_active`.

## The idea in 30 seconds

```text
Your app                                Botzo
  │  1) POST /conversations  ───────────▶  opens or resumes a conversation
  │  ◀─────────── conversation_id (+ token for the public key)
  │  2) POST /conversations/{id}/messages  "How much is the Growth plan?"
  │  ◀─────────── Mochi's answer in the same request (2–5 s)
  │  3) GET  /conversations/{id}/messages?after=…   (or a webhook)
  │  ◀─────────── replies from your team in the inbox
```

| Request | Description |
| --- | --- |
| <span class="method post">POST</span> `/api/v1/assistant/conversations` | Open or resume a conversation, with its history |
| <span class="method post">POST</span> `/api/v1/assistant/conversations/{id}/messages` | Send a message and get Mochi's answer |
| <span class="method get">GET</span> `/api/v1/assistant/conversations/{id}/messages` | New messages (including your team's replies) |

## Which key?

| | Secret key "Mochi only" | Public key `pk_` |
| --- | --- | --- |
| Where it lives | Your server only | Inside the app or website (allowed) |
| Who identifies the user | Your server sends `user.id` | Each device gets its own conversation |
| Same user on several devices | Same conversation for the same `user.id` | One conversation per device |
| Accessing a conversation | `conversation_id` | `conversation_id` **and** `X-Conversation-Token` |
| Limits | The organization's normal API limits | Strict per-device limits (below) |
| Example | An app with a backend, a site with logins | An app without a backend, a public page |

**Rule of thumb:** with a server and signed-in users, use the secret key and let your server sit in the middle. Without a server, use the public key.

## 1) Open a conversation

<span class="method post">POST</span> `/api/v1/assistant/conversations`

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `user.id` | string | yes with a secret key | The user's id in your system (up to 191 characters). Same value = same conversation, always |
| `user.name` | string | no | The user's name, shown to your team in the inbox |
| `locale` | string | no | `ar` or `en` |

With a **public key** don't send `user`. The first time you get a `conversation_token`: **store it on the device** (SharedPreferences, Keychain or localStorage). Next time send it in the `X-Conversation-Token` header on the same request to resume the conversation instead of opening a new one.

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/v1/assistant/conversations \
  -H "Authorization: Bearer $BOTZO_MOCHI_KEY" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"user": {"id": "customer-42", "name": "Sara"}, "locale": "en"}'
```

```js
// On your server (Node.js) with the secret key
const res = await fetch('{{BASE_URL}}/api/v1/assistant/conversations', {
  method: 'POST',
  headers: { Authorization: `Bearer ${process.env.BOTZO_MOCHI_KEY}`, Accept: 'application/json', 'Content-Type': 'application/json' },
  body: JSON.stringify({ user: { id: String(currentUser.id), name: currentUser.name }, locale: 'en' }),
});
const { conversation_id, messages } = await res.json();
```

```dart
// Flutter with the public key: keeps the conversation token on the device
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
  body: jsonEncode({'locale': 'en'}),
);
final body = jsonDecode(res.body);
if (body['conversation_token'] != null) {
  await prefs.setString('mochi_token', body['conversation_token']);
}
final conversationId = body['conversation_id'];
```

```swift
// Swift with the public key
var request = URLRequest(url: URL(string: "{{BASE_URL}}/api/v1/assistant/conversations")!)
request.httpMethod = "POST"
request.setValue("Bearer pk_your_public_key", forHTTPHeaderField: "Authorization")
request.setValue("application/json", forHTTPHeaderField: "Accept")
request.setValue("application/json", forHTTPHeaderField: "Content-Type")
if let token = UserDefaults.standard.string(forKey: "mochi_token") {
    request.setValue(token, forHTTPHeaderField: "X-Conversation-Token")
}
request.httpBody = try JSONSerialization.data(withJSONObject: ["locale": "en"])
let (data, _) = try await URLSession.shared.data(for: request)
let body = try JSONSerialization.jsonObject(with: data) as! [String: Any]
if let token = body["conversation_token"] as? String {
    UserDefaults.standard.set(token, forKey: "mochi_token")
}
let conversationId = body["conversation_id"] as! String
```

```kotlin
// Kotlin (OkHttp) with the public key
val prefs = context.getSharedPreferences("mochi", Context.MODE_PRIVATE)
val request = Request.Builder()
    .url("{{BASE_URL}}/api/v1/assistant/conversations")
    .header("Authorization", "Bearer pk_your_public_key")
    .header("Accept", "application/json")
    .apply { prefs.getString("token", null)?.let { header("X-Conversation-Token", it) } }
    .post("""{"locale":"en"}""".toRequestBody("application/json".toMediaType()))
    .build()
val body = JSONObject(client.newCall(request).execute().body!!.string())
body.optString("conversation_token").takeIf { it.isNotEmpty() }?.let {
    prefs.edit().putString("token", it).apply()
}
val conversationId = body.getString("conversation_id")
```

</div>

Response (new conversation: `201`):

```json
{
  "statusCode": 201,
  "conversation_id": "2b4c1e1b-6609-4832-844b-e50fa911ed1d",
  "conversation_token": "YuR5mNlad1Og5BUgVIv6Z5b6sEaRVgTdmnkQkeE2QVHyz0rfoRy4hEqrn4uH9DzR",
  "messages": []
}
```

- `conversation_token` comes **only** with a public key, when a new conversation is created.
- An existing conversation returns `200` with `messages`: the last 50 messages in order, ready to show when the screen opens.

## 2) Send a message and get the answer

<span class="method post">POST</span> `/api/v1/assistant/conversations/{conversation_id}/messages`

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `text` | string | yes | The user's message, up to 500 characters |
| `wait` | boolean | no | `true` (default): waits for Mochi and returns the answer in this request. `false`: returns at once; the answer comes later |

With a public key add the `X-Conversation-Token` header.

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/v1/assistant/conversations/7f6c5f69-f8a3-4fc3-89f7-50cdb64f69b6/messages \
  -H "Authorization: Bearer $BOTZO_MOCHI_KEY" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"text": "How much is the Growth plan?"}'
```

```js
const res = await fetch(`{{BASE_URL}}/api/v1/assistant/conversations/${conversationId}/messages`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${process.env.BOTZO_MOCHI_KEY}`, Accept: 'application/json', 'Content-Type': 'application/json' },
  body: JSON.stringify({ text: 'How much is the Growth plan?' }),
});
const { replies, awaiting_team } = await res.json();
replies.forEach((r) => showBubble(r.from, r.text));
if (awaiting_team) showNote('Someone from the team will reply soon');
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
  body: jsonEncode({'text': 'How much is the Growth plan?'}),
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
request.httpBody = try JSONSerialization.data(withJSONObject: ["text": "How much is the Growth plan?"])
let (data, _) = try await URLSession.shared.data(for: request)
```

```kotlin
val request = Request.Builder()
    .url("{{BASE_URL}}/api/v1/assistant/conversations/$conversationId/messages")
    .header("Authorization", "Bearer pk_your_public_key")
    .header("X-Conversation-Token", token)
    .header("Accept", "application/json")
    .post(JSONObject(mapOf("text" to "How much is the Growth plan?")).toString().toRequestBody("application/json".toMediaType()))
    .build()
val replies = JSONObject(client.newCall(request).execute().body!!.string()).getJSONArray("replies")
```

</div>

Response with `wait=true`:

```json
{
  "statusCode": 200,
  "message_id": 113,
  "replies": [
    {
      "id": 114,
      "from": "assistant",
      "name": null,
      "text": "Hi Sara! The Growth plan is 599 SAR a month, with the AI assistant and up to 5 team members.",
      "at": "2026-10-22T18:23:06+00:00"
    }
  ],
  "awaiting_team": false
}
```

| Field | Description |
| --- | --- |
| `message_id` | The user's message number. Use it as `after` to fetch what follows |
| `replies` | Messages after the user's message (usually Mochi's answer) |
| `awaiting_team` | `true` means Mochi did **not** answer automatically: the user asked for a person, your team is handling the conversation, or the reply limit was reached. Tell the user the team will answer, and listen for the agent's reply (step 3) |

With `wait=false` you get `202` right away with `replies: []`; the answer arrives within seconds via `GET` or the `conversation.reply` event. Use it when you don't want to keep the connection open.

> **Response time:** usually 2 to 5 seconds (AI time). Use a timeout of at least 45 seconds and show "Mochi is typing…" while waiting.

## 3) Fetch new messages

<span class="method get">GET</span> `/api/v1/assistant/conversations/{conversation_id}/messages?after={id}`

Returns every message after `after`, in order. Without `after` it returns the last 50 messages.

```json
{
  "statusCode": 200,
  "messages": [
    { "id": 115, "from": "user", "name": null, "text": "Is there a free trial?", "at": "2026-10-22T18:23:11+00:00" },
    { "id": 116, "from": "assistant", "name": null, "text": "Yes, there is a 14-day free trial…", "at": "2026-10-22T18:23:13+00:00" },
    { "id": 117, "from": "agent", "name": "Salem", "text": "Salem from the sales team here, how can I help?", "at": "2026-10-22T18:30:02+00:00" }
  ]
}
```

| `from` | Meaning |
| --- | --- |
| `user` | The user (their own message) |
| `assistant` | Mochi |
| `agent` | A team member replying from the inbox; their name is in `name` |

**When to poll?** While the chat screen is open and the last answer had `awaiting_team: true`, poll every 10 to 15 seconds and remember the last `id` you received. Better for apps with a server: the `conversation.reply` event in [Webhooks](/developers/webhooks) reaches you instantly for every Mochi or agent reply, and your server forwards it to the app (for example as a push notification).

## Handing over to a person

When the user writes something like "I want to talk to a person", Mochi hands the conversation to your team, replies with a confirmation, sets `awaiting_team: true` for the following messages, and sends a `conversation.handoff` event to your server. When an agent replies, it shows with `from: agent`.

## Limits

| Limit | Public key | Secret key |
| --- | --- | --- |
| New conversations per IP per hour | 10 | no limit |
| Messages per conversation per hour | 30 | 30 |
| Minimum gap between two messages in a conversation | 3 seconds | 3 seconds |
| Requests per minute | 60 per device (IP) | 120 for the whole organization |
| Mochi answers per day | Set by the organization (default 300) | Same limit |

Going over returns `429` with `too_many_conversations`, `too_many_messages` or `rate_limited`. Wait, then retry.

## Mochi errors

| Status | `code` | Cause |
| --- | --- | --- |
| 400 | `validation_error` | Missing `user.id` with a secret key, or `text` empty or longer than 500 |
| 403 | `assistant_not_active` | The AI assistant is off for the organization |
| 403 | `api_key_scope_denied` | The key is not "Mochi only", "public" or "Full access" |
| 404 | `conversation_not_found` | Wrong `conversation_id`, another organization's, or (public key) a missing or wrong token |
| 429 | `too_many_conversations` / `too_many_messages` / `rate_limited` | Over the limits above |

Example: a conversation without the device token:

```json
{
  "statusCode": 404,
  "code": "conversation_not_found",
  "message": "Conversation not found.",
  "request_id": "85b33f9a-40c7-407a-bc56-62aa26ba472e"
}
```

## Tips for a great experience

- **Keep history locally** and load it from the response when the screen opens, so the user never sees an empty screen.
- **Typing indicator:** show "Mochi is typing…" from sending until the answer arrives.
- **One message at a time:** disable the send button until the answer arrives (there is a 3-second gap limit).
- **Agent replies:** on `awaiting_team: true`, poll every 10–15 seconds or use a webhook.
- **Only the public key goes in the app.** The secret key always stays on your server.

A full Flutter chat app and a JavaScript one are in [Complete examples](/developers/examples).
