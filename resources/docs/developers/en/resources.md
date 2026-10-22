# Contacts, templates and campaigns

These requests connect Botzo to your system (store, CRM, booking system): add customers and organize them in groups, list your templates, and launch a campaign to a whole group.

Required permission: **Full access** (reading also works with "Read only").

## Lists and pages

All list requests (`GET`) are paginated:

| Parameter | Default | Description |
| --- | --- | --- |
| `page` | 1 | Page number |
| `per_page` | 10 | Items per page, up to 100 |

They return:

```json
{
  "data": [ { "...": "..." } ],
  "links": { "first": "…?page=1", "last": "…?page=3", "prev": null, "next": "…?page=2" },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 10, "total": 27 }
}
```

To walk through everything: start at `page=1` and continue while `links.next` is not `null`.

## Contacts

| Request | Description |
| --- | --- |
| <span class="method get">GET</span> `/api/contacts` | List contacts |
| <span class="method post">POST</span> `/api/contacts` | Add a contact |
| <span class="method put">PUT</span> `/api/contacts/{uuid}` | Update a contact |
| <span class="method delete">DELETE</span> `/api/contacts/{uuid}` | Delete a contact |

### Fields (add and update)

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `first_name` | string | yes when adding | First name |
| `last_name` | string | no | Last name |
| `phone` | string | yes | Valid international number, **unique** in your organization |
| `email` | string | no | Email |
| `group` | array | no | Group `uuid`s to join: `["6f1c…", "a92b…"]` |
| `metadata` | object | no | Your own fields, such as `{"city": "Riyadh", "tier": "gold"}`. Usable in campaign placeholders |

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/contacts \
  -H "Authorization: Bearer $BOTZO_API_KEY" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "first_name": "Sara",
    "last_name": "Ali",
    "phone": "+966501234567",
    "email": "sara@example.com",
    "metadata": {"city": "Riyadh", "tier": "gold"}
  }'
```

```js
const res = await fetch('{{BASE_URL}}/api/contacts', {
  method: 'POST',
  headers: { Authorization: `Bearer ${process.env.BOTZO_API_KEY}`, Accept: 'application/json', 'Content-Type': 'application/json' },
  body: JSON.stringify({ first_name: 'Sara', phone: '+966501234567', metadata: { city: 'Riyadh' } }),
});
const { id } = await res.json(); // the contact's uuid
```

```php
$id = Http::withToken(env('BOTZO_API_KEY'))->acceptJson()
    ->post('{{BASE_URL}}/api/contacts', ['first_name' => 'Sara', 'phone' => '+966501234567'])
    ->json('id');
```

```python
r = requests.post("{{BASE_URL}}/api/contacts", headers=HEADERS,
                  json={"first_name": "Sara", "phone": "+966501234567"})
contact_uuid = r.json()["id"]
```

</div>

Response (add, update or delete):

```json
{
  "statusCode": 200,
  "id": "dd0d957b-9f2c-4bcb-aa3a-a1a17388ff11",
  "message": "Request processed successfully"
}
```

An item from `GET /api/contacts`:

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
  "metadata": "{\"city\":\"Riyadh\"}",
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

> `reply_context.reply_window_open` tells you whether the 24-hour window is open with this customer right now, so you know in advance whether to send text or a template.

| Error | Cause |
| --- | --- |
| `400 validation_error` with `errors.phone` | Invalid number, or it already exists |
| `403 contacts_limit_reached` | Your plan's contact limit is reached |
| `404 not_found` | The `uuid` is not in your organization |

## Groups

| Request | Description |
| --- | --- |
| <span class="method get">GET</span> `/api/contact-groups` | List groups |
| <span class="method post">POST</span> `/api/contact-groups` | Create a group: `{"name": "VIP customers"}` (unique name) |
| <span class="method put">PUT</span> `/api/contact-groups/{uuid}` | Rename |
| <span class="method delete">DELETE</span> `/api/contact-groups/{uuid}` | Delete the group (the contacts stay) |

The response matches contacts: `{"statusCode": 200, "id": "<uuid>", "message": "..."}`. To put a customer in a group, send `group` when adding or updating them.

## Auto-replies

Fixed replies Botzo sends when a customer's message contains a keyword.

| Request | Description |
| --- | --- |
| <span class="method get">GET</span> `/api/canned-replies` | List |
| <span class="method post">POST</span> `/api/canned-replies` | Add |
| <span class="method put">PUT</span> `/api/canned-replies/{uuid}` | Update |
| <span class="method delete">DELETE</span> `/api/canned-replies/{uuid}` | Delete |

| Field | Values | Description |
| --- | --- | --- |
| `name` | text | Internal name |
| `trigger` | text | The keyword, such as `hours` |
| `match_criteria` | `exact match` or `contains` | The message equals the keyword, or contains it |
| `response_type` | `text`, `image` or `audio` | Reply type |
| `response` | text or file | The text, or an uploaded file (`multipart/form-data`) for image and audio |

```bash
curl -X POST {{BASE_URL}}/api/canned-replies \
  -H "Authorization: Bearer $BOTZO_API_KEY" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name": "Opening hours", "trigger": "hours", "match_criteria": "contains", "response_type": "text", "response": "We are open every day from 9am to 11pm."}'
```

## Templates

<span class="method get">GET</span> `/api/templates`

```json
{
  "data": [
    {
      "uuid": "1d3f9a0e-…",
      "name": "order_shipped",
      "metadata": "{\"name\":\"order_shipped\",\"language\":\"ar\",\"status\":\"APPROVED\",\"category\":\"UTILITY\",\"components\":[{\"type\":\"BODY\",\"text\":\"Hi {{1}}, your order {{2}} is on its way.\"}]}",
      "updated_at": "2026-10-22 21:23"
    }
  ],
  "links": { "...": "..." },
  "meta": { "...": "..." }
}
```

`metadata` is a JSON string with the full template definition as in Meta: language, status, category, components and placeholders. Use its `name` and language when [sending a template](/developers/messages). Templates are created and edited in the dashboard.

## Campaigns

<span class="method post">POST</span> `/api/campaigns`

Sends one template to everyone in a group (or all contacts), now or at a set time. Botzo sends in batches within WhatsApp's limits and tracks each customer's result in the dashboard.

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `template` | string | yes | Template `uuid` from `GET /api/templates` |
| `contacts` | string | yes | Group `uuid`, or `all` for all contacts |
| `name` | string | no | Campaign name (defaults to "API Campaign" with the date) |
| `skip_schedule` | boolean | no | `true` (default) to send now, `false` to schedule |
| `time` | string | when scheduling | Send time **in your organization's timezone**, such as `2026-11-01 10:00` |
| `header` | object | depends on template | Header placeholders |
| `body` | object | depends on template | Body placeholders |
| `buttons` | array | depends on template | Button placeholders |

### Campaign placeholders

Because a campaign goes to many customers, each placeholder is either **the same for everyone** or **filled from each customer's data**:

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

| `selection` | `value` | Result |
| --- | --- | --- |
| `static` | any text | The same text for everyone |
| `dynamic` | `first name`, `last name`, `name`, `phone`, `email` | From the customer's data |
| `dynamic` | `field:city` (any key in `metadata`) | From your own fields on the customer |

Image header: `"header": {"parameters": [{"type": "IMAGE", "value": "https://cdn.example.com/offer.jpg"}]}` (or `VIDEO`, `DOCUMENT`).

URL button with a variable part: `"buttons": [{"type": "URL", "parameters": [{"type": "static", "value": "summer"}]}]`. Button types: `URL`, `QUICK_REPLY`, `COPY_CODE`; inside parameters, `type` is `static` or `dynamic`.

<div class="code-tabs">

```bash
curl -X POST {{BASE_URL}}/api/campaigns \
  -H "Authorization: Bearer $BOTZO_API_KEY" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "name": "Weekend offer",
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

Response:

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

| Error | Cause |
| --- | --- |
| `404 template_not_found` | Wrong template `uuid` |
| `404 contact_group_not_found` | Wrong group `uuid` |
| `422 campaign_audience_empty` | The group is empty |
| `403 campaign_limit_reached` | Your plan's campaign limit is reached |
| `403 whatsapp_not_connected` | No WhatsApp number connected yet |

> **Campaign pace:** messages go out in batches every few minutes, and a scheduled campaign may start a few minutes after its time. Each customer's result (sent or failed, and why) is in the dashboard under Campaigns.
