# Keys and security

Every API request carries a key in the `Authorization` header. The key's permissions decide what it can do, so pick the smallest one that does the job.

## Sending the key

```http
Authorization: Bearer YOUR_API_KEY
Accept: application/json
Content-Type: application/json
```

Never put the key in the URL (`?token=`) or the request body, only in the header.

## Key types

When you create a key in **Developer tools** you choose its permissions:

| Permission | What it can do | Where it lives | Example |
| --- | --- | --- | --- |
| **Full access** | The whole API: messages, contacts, groups, campaigns, templates and Mochi | Your server only | Full CRM integration |
| **Send messages only** | `POST /api/send`, `/api/send/template`, `/api/send/media`, plus `GET /api/messages/{id}`, `GET /api/templates` and `GET /api/verify` | Your server only | A store sending order updates |
| **Read only** | Any `GET` request (except Mochi); never sends or changes anything | Your server or a reporting tool | Internal dashboard |
| **Mochi only (server)** | Mochi conversations only; your server sets the user's identity (`user.id`) | Your server only | An app with its own backend |
| **Mochi public key (inside apps)** | Opens Mochi conversations only, with strict per-device limits. Starts with `pk_` | **Allowed** inside a mobile app or web front end | A mobile app without a backend |

Keys created before permissions existed keep **full access**, so no existing integration stops.

Using a key outside its permissions returns:

```json
{
  "statusCode": 403,
  "code": "api_key_scope_denied",
  "message": "This API key is not allowed to use this endpoint.",
  "request_id": "74159c04-8fc8-473a-aa26-d2e0d34484a0"
}
```

## Why a secret key never goes in a mobile app

Anyone can unpack an app (APK or IPA) and read the strings inside in minutes. With a secret key in there, anyone could send WhatsApp messages from your number, read your contacts, and use up your plan.

Two safe options:

1. **Best:** your app talks to **your server**, and your server talks to Botzo with the secret key. You stay in control of who sends what.
2. **No server:** use a **Mochi public key `pk_`**. It is designed to be seen: it can only open conversations with Mochi, each device gets its own conversation with a token nobody else knows, and it is limited per device. It cannot send WhatsApp messages or read any data.

## Key lifecycle

| Action | Where | What happens |
| --- | --- | --- |
| Create | Developer tools → Generate API key | The key is shown **once**. We only store a hash (HMAC) and cannot show it again |
| Rotate | Rotate button next to the key | A new key with the same permissions is issued, and **the old one stops at once** |
| Revoke | Revoke button | The key stops at once, for good |

Each card shows the key's last 4 characters and when it was last used, so you know which key runs where.

> **If a key leaks:** rotate it in the dashboard right away, then put the new key on your server. The old one stops the same second.

## Authentication errors

| Status | `code` | Cause and fix |
| --- | --- | --- |
| 401 | `bearer_token_missing` | No `Authorization` header. Use `Bearer <key>` |
| 401 | `bearer_token_invalid` | Wrong, revoked or rotated key. Copy the key again |
| 403 | `api_key_scope_denied` | Valid key without permission for this request. Use a key with the right permissions |
| 403 | `subscription_inactive` | The organization's subscription ended. Renew it in the dashboard |

## Security tips

- Keep keys in environment variables or a secrets manager, never in code or Git.
- Use one key per system (store, CRM, app) with a clear name, so you can revoke one without touching the others.
- Pick the smallest permission. A store that only sends updates needs "Send messages only".
- Always verify webhook **signatures** before trusting them: see [Webhooks](/developers/webhooks).
