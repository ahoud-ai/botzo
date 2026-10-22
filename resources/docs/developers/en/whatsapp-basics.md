# WhatsApp rules

WhatsApp (Meta) has strict rules for business messages. Knowing them before you code prevents most errors. Botzo applies these rules as they are, and tells you clearly why a message was refused.

## The 24-hour window

**The key rule:** when a customer messages you, a "customer service window" opens for **24 hours** from their last message.

| Situation | What you can send |
| --- | --- |
| Inside the window (the customer messaged you in the last 24 hours) | Anything: free text, buttons, images, files, templates |
| Outside the window (or a customer who never messaged you) | **Approved templates only** (`/api/send/template`) |

Sending free text outside the window returns:

```json
{
  "statusCode": 422,
  "code": "outside_24h_window",
  "message": "(#131047) Re-engagement message",
  "meta_error": {
    "code": 131047,
    "details": "Message failed to send because more than 24 hours have passed since the customer last replied to this number."
  }
}
```

**Fix:** send an approved template instead. When the customer replies to it, the window opens again.

> Every new message from the customer restarts the 24 hours. Your messages and your team's replies do not.

## Templates

A template is a pre-approved message with placeholders such as `{{1}}` and `{{2}}`:

```text
Hi {{1}}, your order {{2}} is on its way 🚚
```

- Create templates in the dashboard under **Message templates**. Meta usually approves them within minutes to hours.
- Each template has a **category**: `UTILITY` (orders, appointments), `MARKETING` (offers), `AUTHENTICATION` (verification codes). The category affects price and approval.
- Only `APPROVED` templates can be sent.
- Meta may pause marketing templates (`template_paused`) when many customers block or report them.

List your templates, their names and languages with `GET /api/templates`; how to send them is in [Sending WhatsApp messages](/developers/messages).

## Customer opt-in

Before sending templates, the customer must have **agreed** to receive WhatsApp messages from you, for example with a checkbox at checkout or sign-up. Meta watches block and report rates; messaging people who did not agree can lower your number's rating or get it restricted.

## Phone format

- International format with the country code: `+966501234567` (Saudi Arabia), `+201001234567` (Egypt).
- No leading zeros, spaces or dashes.
- The number must have a WhatsApp account, otherwise you get `recipient_unavailable`.

## Display name and number status

Before your number can send, Meta must approve its **display name**. Until then you get `display_name_not_approved` (Meta code `131037`). Fix it in **WhatsApp Manager → Phone numbers** in your Meta account, not in code.

| Botzo code | Cause | Fix |
| --- | --- | --- |
| `display_name_not_approved` | Display name not approved yet | Wait for Meta, or change the name to match the business |
| `meta_payment_issue` | No valid payment method in Meta | Add one in Meta Business → Billing |
| `whatsapp_account_restricted` | Account restricted for a policy violation | Check Meta Business Support |
| `whatsapp_token_expired` | The WhatsApp connection expired | Reconnect WhatsApp in Botzo settings |

## Sending speed

Meta limits messages per second and new customers per day based on your number's rating. Botzo paces sends for you, but under heavy load you may get `meta_rate_limited` or `recipient_rate_limited` (too many messages to the same customer). Wait and retry; see [Limits and performance](/developers/limits).

## Cost

Meta charges per conversation based on the template category and the customer's country, billed by Meta to the connected WhatsApp Business account. Replies inside the 24-hour window to customer messages are usually free. See Meta's pricing page for current rates.
