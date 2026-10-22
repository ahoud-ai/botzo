# Sending WhatsApp messages

Four requests cover all sending: text (with optional buttons), templates, media, and message status. Every message you send also appears in the Botzo inbox.

| Request | Use |
| --- | --- |
| <span class="method post">POST</span> `/api/send` | Text, or text with quick-reply buttons (inside the 24-hour window) |
| <span class="method post">POST</span> `/api/send/template` | An approved template (any time, even to a customer who never messaged you) |
| <span class="method post">POST</span> `/api/send/media` | Image, video, audio or document (inside the window) |
| <span class="method get">GET</span> `/api/messages/{message_id}` | A message's status: sent, delivered, read, failed |

Required permission: **Full access** or **Send messages only**.

## Send text

<span class="method post">POST</span> `/api/send`

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `phone` | string | yes | Customer number in international format `+966501234567` |
| `message` | string | yes | Message text. Up to 4096 characters, or 1024 with buttons. Links get a preview |
| `buttons` | array | no | Up to 3 quick-reply buttons: `[{"id": "track", "title": "Track order"}]`. `title` up to 20 characters, `id` up to 256 |
| `header` | string | no | Title above the message (with buttons only), up to 60 characters |
| `footer` | string | no | Small line under the message (with buttons only), up to 60 characters |
| `first_name`, `last_name`, `email` | string | no | Used only when the number is not in your contacts yet, to create it |

If the number is not in your contacts we create it (within your plan's contact limit).

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/send \
  -H "Authorization: Bearer $BOTZO_API_KEY" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Idempotency-Key: order-1045-shipped" \
  -d '{
    "phone": "+966501234567",
    "message": "Your order 1045 is on its way 🚚 Arriving in two days.",
    "header": "Order update",
    "footer": "Rayhan Store",
    "buttons": [
      {"id": "track_1045", "title": "Track order"},
      {"id": "support", "title": "Talk to support"}
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
    message: 'Your order 1045 is on its way 🚚 Arriving in two days.',
    buttons: [{ id: 'track_1045', title: 'Track order' }],
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
        'message' => 'Your order 1045 is on its way 🚚 Arriving in two days.',
        'buttons' => [['id' => 'track_1045', 'title' => 'Track order']],
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
          "message": "Your order 1045 is on its way 🚚 Arriving in two days.",
          "buttons": [{"id": "track_1045", "title": "Track order"}]},
    timeout=30,
)
body = r.json()
if r.status_code != 200:
    print(body["code"], body["message"])
else:
    print(body["message_id"])
```

</div>

When the customer taps a button you receive a `message.received` event of type `interactive` with the `button.id` you set (for example `track_1045`), so you know what they chose. See [Webhooks](/developers/webhooks).

### Success response (200)

```json
{
  "statusCode": 200,
  "message_id": "wamid.HBgMOTY2NTAxMjM0NTY3FQIAERgSMEE2",
  "status": "sent",
  "data": { "success": true, "data": { "messaging_product": "whatsapp", "messages": [{ "id": "wamid.HBgMOTY2NTAxMjM0NTY3FQIAERgSMEE2" }], "chat": { "...": "..." } } }
}
```

| Field | Description |
| --- | --- |
| `message_id` | WhatsApp's id for the message. **Store it**: it tracks the status and appears in `message.status` events |
| `status` | Always `sent` on success (Meta accepted it). Delivered and read come later |
| `data` | WhatsApp's raw response and Botzo's record, kept for older integrations. **We don't recommend relying on it** |

### Failure response

When WhatsApp refuses the message you get a clear code, plus Meta's details in `meta_error`:

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

Failed messages also appear in the inbox, marked as failed. All codes are in [Errors](/developers/errors).

## Send a template

<span class="method post">POST</span> `/api/send/template`

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `phone` | string | yes | Customer number |
| `template.name` | string | yes | Template name as in `GET /api/templates` |
| `template.language.code` | string | yes | Template language: `ar`, `en`, `en_US`… as approved by Meta |
| `template.components` | array | depends on template | Placeholder values in Meta's format (below) |

`components` uses Meta's format exactly:

| Component | Shape |
| --- | --- |
| Body placeholders `{{1}}` `{{2}}` | `{"type": "body", "parameters": [{"type": "text", "text": "Sara"}, {"type": "text", "text": "1045"}]}` |
| Image header | `{"type": "header", "parameters": [{"type": "image", "image": {"link": "https://cdn.example.com/p.jpg"}}]}` |
| PDF header | `{"type": "header", "parameters": [{"type": "document", "document": {"link": "https://…/invoice.pdf", "filename": "invoice.pdf"}}]}` |
| Text header placeholder | `{"type": "header", "parameters": [{"type": "text", "text": "Today's offer"}]}` |
| URL button with a variable part | `{"type": "button", "sub_type": "url", "index": "0", "parameters": [{"type": "text", "text": "1045"}]}` |
| Quick-reply button | `{"type": "button", "sub_type": "quick_reply", "index": "1", "parameters": [{"type": "payload", "payload": "CONFIRM_1045"}]}` |
| Copy-code button | `{"type": "button", "sub_type": "copy_code", "index": "0", "parameters": [{"type": "coupon_code", "coupon_code": "SAVE20"}]}` |

The number and order of placeholders **must match the template** exactly, or you get `template_parameters_mismatch`.

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
          {"type": "text", "text": "Sara"},
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
      components: [{ type: 'body', parameters: [{ type: 'text', text: 'Sara' }, { type: 'text', text: '1045' }] }],
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
                ['type' => 'text', 'text' => 'Sara'],
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
            {"type": "text", "text": "Sara"}, {"type": "text", "text": "1045"}]}],
    },
})
```

</div>

The response has the same shape as sending text (`message_id` and `status`).

## Send an image or file

<span class="method post">POST</span> `/api/send/media`

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `phone` | string | yes | Customer number |
| `media_type` | string | yes | One of `image`, `video`, `audio`, `document` |
| `media_url` | string | yes | A **public https** link to the file. WhatsApp downloads it directly |
| `caption` | string | no | Text under an image, video or document (not audio), up to 1024 characters |
| `file_name` | string | no | File name the customer sees (matters for documents). Defaults to the end of the URL |

**URL rules:** starts with `https://`, opens without login, and is not an internal address (`localhost`, `10.x`, `192.168.x`). Anything else returns `400 validation_error`. We never open the link ourselves; WhatsApp downloads it.

| Type | Formats WhatsApp accepts | Max size |
| --- | --- | --- |
| `image` | JPEG, PNG | 5 MB |
| `video` | MP4, 3GP | 16 MB |
| `audio` | AAC, MP3, OGG (Opus), AMR, M4A | 16 MB |
| `document` | PDF, Word, Excel, PowerPoint, TXT… | 100 MB |

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/send/media \
  -H "Authorization: Bearer $BOTZO_API_KEY" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "phone": "+966501234567",
    "media_type": "document",
    "media_url": "https://cdn.example.com/invoices/1045.pdf",
    "file_name": "invoice-1045.pdf",
    "caption": "Invoice for order 1045"
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
    caption: 'Rose perfume, now in stock',
  }),
});
```

```php
Http::withToken(env('BOTZO_API_KEY'))->acceptJson()->post('{{BASE_URL}}/api/send/media', [
    'phone' => '+966501234567',
    'media_type' => 'document',
    'media_url' => 'https://cdn.example.com/invoices/1045.pdf',
    'file_name' => 'invoice-1045.pdf',
]);
```

```python
requests.post("{{BASE_URL}}/api/send/media", headers=HEADERS, json={
    "phone": "+966501234567",
    "media_type": "image",
    "media_url": "https://cdn.example.com/products/rose-perfume.jpg",
    "caption": "Rose perfume, now in stock",
})
```

</div>

If WhatsApp cannot download the file (private link, too large) you get `media_download_failed`.

## Message status

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

| `status` | Meaning |
| --- | --- |
| `sent` | WhatsApp's servers accepted it |
| `delivered` | It reached the customer's phone |
| `read` | The customer opened it (if they have read receipts on) |
| `failed` | It failed. The reason is in `error.code` and `error.message` |

The status only moves forward: `sent` → `delivered` → `read`. If it fails after sending (for example the customer changed their number) it becomes `failed` with the reason.

> **Better than polling:** turn on the `message.status` event in [Webhooks](/developers/webhooks) and get every change as it happens.

A message that is not in your organization returns `404 message_not_found`.

## Prevent duplicates (Idempotency-Key)

Networks are not perfect: your server sends the request, the customer gets the message, but the response gets lost, so your server retries and the customer gets it **twice**. The fix: send an `Idempotency-Key` header with a unique value per logical message:

```http
Idempotency-Key: order-1045-shipped
```

| Situation | What happens |
| --- | --- |
| First request with this key | Sent normally; we keep the result for 24 hours |
| Same key and same data again | **Nothing is sent**; you get the first response again, with the header `Idempotent-Replayed: true` |
| Same key, different data | `422 idempotency_key_reused` |
| The first request is still running | `409 idempotency_in_progress`; retry in a second |

- Works with `/api/send`, `/api/send/template` and `/api/send/media`.
- Keys are up to 100 characters, kept per organization for 24 hours.
- Server errors (5xx) and rate limits (429) are **not** kept, so retrying with the same key really sends.
- Use a value tied to the event (`order-1045-shipped`), not a new random value on each try.

## Tips

- **Retry smartly:** only on `429`, `502` and `5xx`, with growing waits (1, 2, 4 seconds…) and the same `Idempotency-Key`. Don't retry `422`: the cause won't change.
- **Timeouts:** at least 30 seconds; WhatsApp's servers are sometimes slow.
- **Many recipients:** to send the same template to hundreds of customers use [campaigns](/developers/resources) instead of a loop.
