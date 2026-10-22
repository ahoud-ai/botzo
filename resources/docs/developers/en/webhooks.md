# Webhooks

Instead of your server asking Botzo every minute "anything new?", Botzo sends your server a `POST` request **the moment** something happens: a customer message, a message status change, a request for a person, or a reply in a Mochi conversation.

## Setup

1. In **Developer tools → Webhooks**, enter your server's URL (must start with `https://`), pick the events, then **Add webhook**.
2. The **signing secret** (starts with `whsec_`) is shown **once**. Store it on your server (`BOTZO_WEBHOOK_SECRET`).
3. Press **Send test event**: you get a `webhook.test` event and see the result under "Recent deliveries".

Up to 3 URLs per organization, each with its own events and secret. You can pause any URL or rotate its secret.

### The test receiver

No server yet? Press **Use the test receiver**: a private URL for your organization inside Botzo that keeps the last 20 requests it receives, shows their content, and tells you whether the **signature is valid**. Add it as a webhook and try (send a message from your phone, for example) to see every real event before writing a line of code.

## The request

Every notification is a `POST` with a JSON body and these headers:

| Header | Example | Description |
| --- | --- | --- |
| `Content-Type` | `application/json` | |
| `User-Agent` | `Botzo-Webhooks/1.0` | |
| `X-Botzo-Event` | `message.received` | Event type |
| `X-Botzo-Delivery` | `evt_QM7BsjgZLhxD1uKuq8vpyuOC` | Event id, **the same on every retry** |
| `X-Botzo-Timestamp` | `1792693386` | Send time (Unix seconds) |
| `X-Botzo-Signature` | `sha256=60a52fe7…` | Signature (below) |

The body always has this envelope:

```json
{
  "id": "evt_QM7BsjgZLhxD1uKuq8vpyuOC",
  "type": "message.received",
  "created_at": "2026-10-22T18:23:11+00:00",
  "data": { "...": "depends on the event" }
}
```

## Verifying the signature

Anyone who knows your URL can send it a fake request. The signature proves the request came from Botzo and was not changed:

```text
signature = "sha256=" + HMAC_SHA256(secret, timestamp + "." + raw_body)
```

1. Take the **raw body** exactly as received (before parsing JSON).
2. Compute HMAC-SHA256 with the signing secret over `X-Botzo-Timestamp` + `.` + body.
3. Compare with `X-Botzo-Signature` using a **constant-time** comparison.
4. Reject requests whose `X-Botzo-Timestamp` is older than 5 minutes (protects against replays).

<div class="code-tabs">

```js
// Node.js (Express): note express.raw to get the raw body
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
  res.sendStatus(200);            // answer right away
  handleEvent(event);             // then process
});
```

```php
// PHP / Laravel
Route::post('/botzo/webhook', function (Illuminate\Http\Request $request) {
    $timestamp = (string) $request->header('X-Botzo-Timestamp');
    $body = $request->getContent(); // raw body
    $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, env('BOTZO_WEBHOOK_SECRET'));

    if (! hash_equals($expected, (string) $request->header('X-Botzo-Signature'))
        || abs(time() - (int) $timestamp) > 300) {
        abort(401);
    }

    $event = json_decode($body, true);
    dispatch(new HandleBotzoEvent($event)); // process in the background
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
    body = request.get_data(as_text=True)  # raw body
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

> **The most common mistake:** signing the JSON after parsing and re-serializing it. Spacing or key order can change, so the signature won't match. Always use the **raw body**.

## Events

| Event | When |
| --- | --- |
| `message.received` | A customer sent a WhatsApp message (text, image, audio, file, button tap) |
| `message.status` | A message you sent changed status: `sent` → `delivered` → `read`, or `failed` |
| `conversation.handoff` | The conversation moved to a person (customer asked, Mochi handed over, or an automation) |
| `conversation.reply` | Mochi or an agent replied in a website widget or Mochi API conversation |
| `webhook.test` | The "Send test event" button |

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
    "text": "When will my order arrive?",
    "button": null,
    "media": null,
    "timestamp": "2026-10-22T18:23:11+00:00"
  }
}
```

| `data.type` | What it carries |
| --- | --- |
| `text` | `text`: the message |
| `interactive` or `button` | `button.id` and `button.title`: the button the customer tapped (the `id` you set) |
| `image`, `video`, `audio`, `document`, `sticker` | `media.type`, `media.mime_type`, `media.caption`, `media.filename`, and `media.voice: true` for recorded voice notes |

Button tap:

```json
"data": {
  "message_id": "wamid.INBOUND3",
  "from": "+966501234567",
  "type": "interactive",
  "text": null,
  "button": { "id": "track_order", "title": "Track order" },
  "media": null
}
```

Image:

```json
"data": {
  "message_id": "wamid.INBOUND2",
  "type": "image",
  "media": { "type": "image", "mime_type": "image/jpeg", "caption": "Product photo", "filename": null, "voice": false }
}
```

> The file itself is not sent in the event. Your team sees it in the inbox.

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

On failure:

```json
"data": {
  "message_id": "wamid.TEMPLATE1",
  "to": "+966501234567",
  "status": "failed",
  "error": { "code": 131026, "title": "Message undeliverable", "message": "Message undeliverable" }
}
```

`message_id` is the one you got when sending, so you can match the event to the message in your system. The event is only sent when the status **moves forward** (no `delivered` after `read`).

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

| `reason` | Cause |
| --- | --- |
| `human_handoff` | The customer asked for a person ("I want to talk to someone") |
| `ai_knowledge_gap` | Mochi didn't find an answer in your knowledge and handed over |
| `ai_unavailable` | The AI could not answer (for example no credit left) and handed over |
| `voice_call_handoff` | A person was requested during a voice call with Mochi |
| `flow_assignment` | An "assign to agent" step in an automation |

`channel` is `whatsapp` or `web` (widget and Mochi API); `contact.phone` is `null` for web visitors.

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
    "text": "Salem from the sales team here, how can I help?",
    "timestamp": "2026-10-22T18:23:11+00:00"
  }
}
```

| Field | Description |
| --- | --- |
| `source` | `api` (Mochi API conversation) or `widget` (website widget) |
| `user_id` | The `user.id` your server sent (secret key), otherwise `null` |
| `from` | `assistant` (Mochi) or `agent` (team member) |

Use it to notify the user in your app when an agent answers, without polling the API.

### webhook.test

```json
{
  "id": "evt_gh6R8NqOyRV786sZqdhjWwQr",
  "type": "webhook.test",
  "created_at": "2026-10-22T18:23:11+00:00",
  "data": { "message": "Test event from Botzo. If you can read this, your webhook works.", "timestamp": "2026-10-22T18:23:11+00:00" }
}
```

## Responding and retries

- Respond with any **2xx** within **5 seconds**. Anything else, a timeout, or a redirect (3xx) counts as a failure.
- **Respond first, then process:** store the event, return `200` at once, and do heavy work in the background.
- On failure we retry with the same `X-Botzo-Delivery` after **1 minute, 5 minutes, 30 minutes, 2 hours** (up to 5 attempts). Retries run every few minutes, so they may land a little later.
- After **20 failures in a row** the URL is switched off and shows "Stopped after repeated failures". Fix your server, then press "Turn on".
- The last 50 deliveries (result, attempts, time, error) are in **Developer tools → Recent deliveries**; we keep the log for 14 days.

## Best practices

- **Ignore duplicates:** the same event may arrive more than once (a retry after a timeout). Store each processed `id` and skip repeats.
- **Don't rely on order:** `read` may rarely arrive before `delivered`. Keep the highest status you've seen.
- **Always verify the signature** before acting.
- The URL must be `https` and publicly reachable. We never send to internal addresses (`localhost`, `10.x`, `192.168.x`).
- Rotating the secret in the dashboard stops the old one at once, so update your server right away.
