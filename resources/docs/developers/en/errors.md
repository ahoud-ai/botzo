# Errors

Every error has the same shape, a fitting HTTP status, and a stable `code` your program can rely on:

```json
{
  "statusCode": 422,
  "code": "outside_24h_window",
  "message": "(#131047) Re-engagement message",
  "request_id": "9fb64a88-f0f1-4a36-a1e8-0ba3eef81360"
}
```

- **Rely on `code`**, not on `message`; the text may change or be translated.
- `request_id` is also in the `X-Request-Id` header. Log it and send it to us when asking for help.
- WhatsApp errors include `meta_error` with Meta's original details (`code`, `subcode`, `message`, `details`).
- Validation errors include `errors`: the invalid fields and why.

## Should I retry?

| HTTP | Meaning | Retry? |
| --- | --- | --- |
| 400 | Invalid data | No. Fix the data |
| 401 | Missing or wrong key | No. Fix the key |
| 403 | Not allowed (permission, subscription, plan limit, WhatsApp account) | No, until the cause changes |
| 404 | Not found | No |
| 409 | A request with the same `Idempotency-Key` is still running | Yes, after a second |
| 422 | WhatsApp refused the message, or the data doesn't make sense | No, unless things change (e.g. the customer messaged you and the window opened) |
| 429 | Over a limit | Yes, with growing waits |
| 500 | Our error | Yes, shortly; send us the `request_id` if it repeats |
| 502 | Could not reach WhatsApp's servers | Yes, shortly |

## General codes

| HTTP | `code` | Cause | Fix |
| --- | --- | --- | --- |
| 400 | `validation_error` | Missing or malformed field; details in `errors` | Fix the listed fields |
| 401 | `bearer_token_missing` | No `Authorization` header | Send `Authorization: Bearer <key>` |
| 401 | `bearer_token_invalid` | Wrong, revoked or rotated key | Use the right key |
| 403 | `api_key_scope_denied` | The key's permissions don't allow this request | Use a key with the right permissions |
| 403 | `subscription_inactive` | The organization's subscription ended | Renew |
| 404 | `not_found` | The `uuid` is not in your organization | Check the id |
| 409 | `idempotency_in_progress` | Same key, request not finished | Wait and retry |
| 422 | `idempotency_key_reused` | Same `Idempotency-Key` with different data | Use a new key for each different message |
| 429 | `rate_limited` | Too many requests per minute | Wait, then retry |
| 500 | `server_error` | Unexpected error | Retry, and contact us with the `request_id` |

## Plan and account limits

| HTTP | `code` | Cause |
| --- | --- | --- |
| 403 | `message_limit_reached` | Message limit for the current billing cycle reached |
| 403 | `contacts_limit_reached` | Contact limit reached (and the number is new) |
| 403 | `campaign_limit_reached` | Campaign limit reached |
| 403 | `canned_replies_limit_reached` | Auto-reply limit reached |
| 403 | `whatsapp_not_connected` | No WhatsApp number connected |

## Sending messages (reasons from WhatsApp)

| HTTP | `code` | Meta code | Cause | Fix |
| --- | --- | --- | --- | --- |
| 422 | `outside_24h_window` | 131047 | More than 24 hours since the customer's last message | Send a template |
| 422 | `recipient_unavailable` | 131026 | The number has no WhatsApp, or can't receive | Check the number |
| 422 | `recipient_is_sender` | 131021 | Sending to your own number | Use another number |
| 422 | `display_name_not_approved` | 131037 | Meta hasn't approved the display name | WhatsApp Manager |
| 422 | `meta_payment_issue` | 131042 | No payment method in the Meta account | Add one in Meta Business |
| 422 | `meta_ecosystem_limited` | 131049 | Meta held the message to protect the customer's experience (too many marketing messages) | Send fewer marketing messages to this customer |
| 422 | `unsupported_message_type` | 131051 | Unsupported message type | |
| 422 | `media_download_failed` | 131052 | WhatsApp couldn't download the file | Make sure the link is public and the size and format are accepted |
| 422 | `media_upload_failed` | 131053 | File upload failed | Try another file |
| 422 | `template_parameters_mismatch` | 132000 | Placeholder count doesn't match the template | Match the template's `{{n}}` count |
| 422 | `template_not_found` | 132001 | No template with this name or language, or not approved | Check `GET /api/templates` |
| 422 | `template_text_too_long` | 132005 | Text too long after filling placeholders | Shorten the values |
| 422 | `template_policy_violation` | 132007 | Template breaks Meta's policies | Edit the template |
| 422 | `template_parameters_invalid` | 132012 | Wrong placeholder format (e.g. image instead of text) | Match the placeholder type |
| 422 | `template_paused` | 132015 | Meta paused the template for low quality | Improve it or use another |
| 422 | `template_disabled` | 132016 | Meta disabled the template | Create a new one |
| 422 | `invalid_request` | 100 | Invalid parameter | See `meta_error.details` |
| 422 | `meta_rejected` | other | Refused for another reason | Details in `meta_error` |
| 429 | `meta_rate_limited` | 130429 | Over the number's sending rate | Wait and retry |
| 429 | `meta_spam_rate_limited` | 131048 | Meta limited sending due to blocks or reports | Review your message quality |
| 429 | `recipient_rate_limited` | 131056 | Too many messages to the same customer in a short time | Wait before messaging them again |
| 403 | `whatsapp_account_locked` | 131031 | The WhatsApp Business account is locked | Meta Business Support |
| 403 | `whatsapp_account_restricted` | 368 | Account temporarily restricted | Meta Business Support |
| 401 | `whatsapp_token_expired` | 190 | The WhatsApp connection expired | Reconnect WhatsApp in settings |
| 502 | `meta_unreachable` | — | Couldn't reach WhatsApp's servers | Retry shortly |
| 404 | `contact_not_found` | — | The contact isn't in your organization | |

## Messages and campaigns

| HTTP | `code` | Cause |
| --- | --- | --- |
| 404 | `message_not_found` | The `message_id` isn't in your organization |
| 404 | `template_not_found` | Wrong template `uuid` in a campaign |
| 404 | `contact_group_not_found` | Wrong group `uuid` in a campaign |
| 422 | `campaign_audience_empty` | The group has no contacts |

## Mochi

| HTTP | `code` | Cause |
| --- | --- | --- |
| 403 | `assistant_not_active` | The AI assistant is off for the organization |
| 404 | `conversation_not_found` | No such conversation, or a missing or wrong device token (public key) |
| 429 | `too_many_conversations` | Too many new conversations from one device (public key) |
| 429 | `too_many_messages` | Messages too fast or too many in one conversation |

## Example: handling errors in code

<div class="code-tabs">

```js
async function sendWhatsApp(payload, idempotencyKey, attempt = 1) {
  const res = await fetch('{{BASE_URL}}/api/send', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${process.env.BOTZO_API_KEY}`,
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'Idempotency-Key': idempotencyKey,
    },
    body: JSON.stringify(payload),
  });
  const body = await res.json();
  if (res.ok) return body.message_id;

  const retryable = [409, 429, 500, 502, 503].includes(res.status);
  if (retryable && attempt < 5) {
    await new Promise((r) => setTimeout(r, 2 ** attempt * 1000));
    return sendWhatsApp(payload, idempotencyKey, attempt + 1);
  }
  if (body.code === 'outside_24h_window') {
    return sendTemplateInstead(payload.phone);
  }
  throw new Error(`Botzo ${body.code} (${body.request_id}): ${body.message}`);
}
```

```php
function sendWhatsApp(array $payload, string $idempotencyKey): ?string
{
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $response = Http::withToken(env('BOTZO_API_KEY'))
            ->acceptJson()
            ->timeout(30)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('{{BASE_URL}}/api/send', $payload);

        if ($response->successful()) {
            return $response->json('message_id');
        }
        if (! in_array($response->status(), [409, 429, 500, 502, 503], true)) {
            logger()->warning('Botzo send failed', $response->json());
            return null;
        }
        sleep(2 ** $attempt);
    }
    return null;
}
```

</div>
