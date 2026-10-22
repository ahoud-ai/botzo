# Limits and performance

## Request limits

| Limit | Value | Applies to |
| --- | --- | --- |
| API requests | 120 per minute | The whole organization, all secret keys together |
| Mochi public key | 60 per minute | Per device (IP address) |
| New Mochi conversations with a public key | 10 per hour | Per device (IP address) |
| Messages in a Mochi conversation | 30 per hour, at least 3 seconds apart | Per conversation |
| Mochi answers per day | Set by the organization (default 300) | All web and Mochi API conversations |
| Webhooks | Up to 3 URLs | Per organization |

Going over the per-minute limit returns:

```json
{
  "statusCode": 429,
  "code": "rate_limited",
  "message": "Too many API requests. Please retry shortly.",
  "request_id": "…"
}
```

with the headers `Retry-After` (seconds), `X-RateLimit-Limit` and `X-RateLimit-Remaining`. Wait for `Retry-After` before retrying.

## Plan limits

The organization's plan sets the messages per billing cycle, contacts, campaigns and auto-replies. Reaching any of them returns `403` with a code that says which (`message_limit_reached`, `contacts_limit_reached`…). Upgrade in Dashboard → Billing & subscription.

## WhatsApp (Meta) limits

Meta also limits your number itself, independently of Botzo:

- **New customers** you can start a conversation with by template in 24 hours (starting at 250 or 1,000, growing with your number's quality and volume).
- **Sending speed** per second (`meta_rate_limited`).
- **Consecutive messages to one customer** in a short time (`recipient_rate_limited`).

Your number's rating and current limits are in **WhatsApp Manager** in your Meta account.

## Expected speed

| Operation | Typical time | Notes |
| --- | --- | --- |
| Any request inside Botzo (no WhatsApp or AI) | under 50 ms | |
| Sending a WhatsApp message | 0.5 to 1.5 s | Mostly Meta's servers |
| Mochi answer (`wait=true`) | 2 to 5 s | AI time |
| Message to Mochi without waiting (`wait=false`) | under 100 ms | The answer follows seconds later |
| Webhook after a customer message | under 2 s | Once Meta tells us |
| Scheduled campaigns starting | within minutes of their time | Campaigns go out in batches |

### See where the time went: Server-Timing

Every API response has a `Server-Timing` header:

```http
Server-Timing: app;dur=1052.4, db;dur=12.7, token;dur=1.2, meta;dur=1010
```

| Value | Meaning |
| --- | --- |
| `app` | The whole request inside Botzo |
| `db` | Database |
| `meta` | Waiting for WhatsApp's servers |
| `token` | Preparing the WhatsApp connection |

In the example, 1010 of the 1052 ms were spent at Meta. For a slow request, `Server-Timing` and `request_id` are the first things we need from you.

## Performance tips

- **Reuse connections (keep-alive)** instead of opening a new one per request.
- **Timeouts:** 30 seconds for WhatsApp sends, 45 seconds for Mochi with `wait=true`.
- **Retries:** only on `429`, `5xx` and `502`, with growing waits (1, 2, 4, 8 s), and with an `Idempotency-Key` for sends.
- **Bulk sending:** use campaigns instead of a loop over `/api/send`; they handle pacing and WhatsApp limits for you.
- **Listen instead of polling:** webhooks save you from repeatedly asking about message statuses and agent replies.
