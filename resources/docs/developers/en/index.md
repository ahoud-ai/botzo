# Developer guide

Connect your app, website or system to Botzo with three tools: **sending WhatsApp messages**, **Mochi** (the AI assistant that answers your customers), and **webhooks** that tell your server about everything the moment it happens.

This guide is written for every developer, even if it is your first time working with WhatsApp. Read this page first, then jump to the section you need.

## What can you build?

| You want to… | Use | Section |
| --- | --- | --- |
| Send order updates, verification codes or invoices on WhatsApp | Messaging API | [Sending WhatsApp messages](/developers/messages) |
| Put an "Ask Mochi" chat in your mobile app or website with your own design | Mochi API | [Mochi API](/developers/assistant) |
| Have your server know right away when a customer replies, a message is read, or someone asks for a person | Webhooks | [Webhooks](/developers/webhooks) |
| Add contacts from your CRM or store and launch a campaign | Contacts and campaigns API | [Contacts, templates and campaigns](/developers/resources) |

Everything that happens through the API also shows in the Botzo **inbox**, where your team can answer directly.

## Base URL

All requests go to:

```text
{{BASE_URL}}/api
```

- Requests and responses are **JSON** in **UTF-8** (Arabic and emoji are fine).
- Always send `Accept: application/json` and `Content-Type: application/json`.
- **HTTPS** only.

## Start in 5 minutes

### 1) Create an API key

In the dashboard: **Developer tools → Access tokens**. Name the key (for example "Store server"), pick its permissions, and press **Generate API key**. Copy it right away: it is **shown only once**.

> **Important:** a key is like a password. Keep it in your server's environment (`BOTZO_API_KEY`), never in front-end code or a mobile app. Details in [Keys and security](/developers/authentication).

### 2) Check the key works

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

Response:

```json
{
  "statusCode": 200,
  "message": "API key is valid and active"
}
```

### 3) Send your first message

To send free text, the customer must have **messaged you in the last 24 hours** (a WhatsApp rule, see [WhatsApp rules](/developers/whatsapp-basics)). To try it, send "hi" from your phone to your organization's WhatsApp number, then:

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/send \
  -H "Authorization: Bearer $BOTZO_API_KEY" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"phone": "+966501234567", "message": "Your order is on its way 🚚"}'
```

```js
const res = await fetch('{{BASE_URL}}/api/send', {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${process.env.BOTZO_API_KEY}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ phone: '+966501234567', message: 'Your order is on its way 🚚' }),
});
const result = await res.json();
console.log(result.message_id); // keep it to follow the message status
```

```php
$result = Http::withToken(env('BOTZO_API_KEY'))->acceptJson()
    ->post('{{BASE_URL}}/api/send', ['phone' => '+966501234567', 'message' => 'Your order is on its way 🚚'])
    ->json();
echo $result['message_id'];
```

```python
r = requests.post("{{BASE_URL}}/api/send",
                  headers={"Authorization": f"Bearer {os.environ['BOTZO_API_KEY']}", "Accept": "application/json"},
                  json={"phone": "+966501234567", "message": "Your order is on its way 🚚"})
print(r.json()["message_id"])
```

</div>

Success returns `200` with a `message_id`. If WhatsApp refuses the message you get a clear error code such as `outside_24h_window`; see [Errors](/developers/errors).

### 4) Receive your customers' replies

In **Developer tools → Webhooks** press **Use the test receiver**, then **Add webhook**. Send a message from your phone and press **Refresh** in the test receiver: you will see the `message.received` event exactly as your server will get it, with its signature check. When your server is ready, put its URL instead. Details in [Webhooks](/developers/webhooks).

## Response format

**Success:** always includes `statusCode` (same as the HTTP status), plus fields for the request.

**Error:** the same shape across the whole API:

```json
{
  "statusCode": 422,
  "code": "outside_24h_window",
  "message": "(#131047) Re-engagement message",
  "request_id": "9fb64a88-f0f1-4a36-a1e8-0ba3eef81360"
}
```

| Field | Meaning |
| --- | --- |
| `statusCode` | The HTTP status |
| `code` | **Build your logic on this**: a stable value. Full list in [Errors](/developers/errors) |
| `message` | Human-readable text. It may change, so don't branch on it |
| `request_id` | The request's id. Send it to support so we find the request at once. Also in the `X-Request-Id` header |
| `errors` | (validation errors only) the invalid fields and why |

## Conventions

- **Phone numbers** in international E.164 format with the country code: `+966501234567`. Without `+`, we add it.
- **Times** are UTC. Most fields are ISO 8601 such as `2026-10-22T18:23:06+00:00`; a few use `2026-10-22 18:23:06` (also UTC).
- **Ids:** WhatsApp messages have a `message_id` starting with `wamid.`; contacts, groups and templates have a `uuid`; Mochi conversations have a `conversation_id`.
- **The `Server-Timing` header** comes with every response and shows where the time went, for example `app;dur=27.4, db;dur=12.1, meta;dur=980`: `app` is the whole request, `meta` is WhatsApp's servers, `db` is the database. Very handy when something feels slow.

## Next steps

- Pick the right key and learn the security rules: [Keys and security](/developers/authentication)
- Using WhatsApp? Read [WhatsApp rules](/developers/whatsapp-basics) first; it prevents most errors.
- Want ready code? Go to [Complete examples](/developers/examples).
