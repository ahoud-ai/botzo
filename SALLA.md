# Botzo on the Salla app store

Merchants install Botzo from the Salla app store and subscribe through Salla. The first release is
the website widget and Mochi; WhatsApp follows as a later update.

## Branches and where the code runs

| What | Where |
|---|---|
| Live server `~/botzo.net` | pulls `phase-0-hardening` |
| Salla work in progress | `feat/salla-app`, not on the server |
| `main` | never touched: a push triggers an FTP deploy |

Salla does not host a copy of Botzo. Installed stores talk to the same server, so a merge into
`phase-0-hardening` and a deploy is how Salla work goes live.

## How updates reach merchants

| Change | How it reaches merchants |
|---|---|
| Server code (replies, merchant panel, fixes) | On deploy, for every store, with no Salla review |
| Snippet code, listing text, scopes | After Salla reviews a new publish |

Stores keep the last approved snippet until Salla approves a new one, so server changes must keep
working with it. Larger updates get their own branch from `phase-0-hardening`.

## Settings

Set these in `.env` only, never in code or chat:

```
SALLA_CLIENT_ID=
SALLA_CLIENT_SECRET=
SALLA_WEBHOOK_SECRET=
```

Webhook URL for the app in the Salla Partners portal: `https://<domain>/api/salla/webhook`

## Install flow (phase 1)

| Salla event | What Botzo does |
|---|---|
| `app.store.authorize` | Creates the store and its Botzo account on first install, saves encrypted tokens |
| `app.uninstalled` | Deletes tokens at once, schedules conversations for deletion after 30 days |
| Anything else | Records the event name without its content, and answers 200 |

- A request without a valid `X-Salla-Signature` (HMAC-SHA256 of the raw body) or the secret in
  `Authorization` gets a 401 and is not recorded. An empty secret refuses everything.
- Salla retries a failed delivery three times, about five minutes apart. The same body is applied once.
- Late retries are compared with the event's `created_at`, so an old install cannot restore tokens
  after an uninstall and an old uninstall cannot remove the tokens of a newer install.
- Every store gets a new user and organization. Accounts are never joined by email: the login is a
  placeholder address and the store's email stays on `salla_stores`.

## Tokens

Access tokens last 14 days. Refresh tokens are single use, and using one twice revokes the store's
access and forces a reinstall. So `salla:refresh-tokens` (hourly) refreshes inside the store's cache
lock, re-reads the store first, and saves the new pair immediately.

A refresh that Salla refuses sets the status to `reauth_required` and is not retried until a new
`app.store.authorize` arrives. A refresh with no answer is logged as
`salla.token_refresh.no_response`, because Salla may have used the token anyway.

## Subscriptions and limits (phase 2)

Run `php artisan salla:setup-plans` once per environment. It creates four `inactive` rows in
`subscription_plans`, which keeps them off the public pricing page, and never overwrites existing rows.
Edit limits and prices on those rows: `ai_system_key_monthly_quota` is the monthly reply limit, and
`salla_plan_names` lists the Salla plan names that map to the plan. The values are placeholders until
pricing is decided.

| Salla event | What Botzo does |
|---|---|
| `app.trial.started` | Trial plan until the event's end date, or 7 days. A paying merchant is left alone |
| `app.trial.expired`, `app.trial.canceled` | Ends a running trial |
| `app.subscription.started`, `app.subscription.renewed` | Sets the plan by name and the paid period |
| `app.subscription.canceled` | Service continues to the paid end date |
| `app.subscription.expired` | Ends service now |

- Add-on events do not change the plan.
- An unknown plan name gives the Basic plan and logs `salla.plan.unknown_name`, so a paying merchant keeps service.
- An event older than the last applied one is ignored, and so is the end of a subscription other than the current one.
- A subscription event that arrives before the install event answers 500, so Salla retries it.
- Trials are saved as status `active` on the trial plan, because status `trial` falls under the site-wide
  `trial_addons` setting, which could switch the assistant off.
- `ai_limits_enforced` on the plan keeps the reply limit on even when `enable_ai_billing` is off site-wide.

The widget refuses requests (403) for a Salla store that is uninstalled or has no running subscription,
and Botzo's own checkout answers 403 for every Salla store. Organizations without a `salla_stores` row
are not affected.

**Not confirmed yet:** Salla refuses its subscriptions API while the app is in development, so the real
plan names and event fields have not been seen. Check them with a real subscription before publishing.

## Deleting data

`salla:purge` (daily) removes, for stores past `purge_after`: contacts, chats and their logs, notes and
tickets, widget sessions and voice calls, files customers sent, and the store's email. The account
stays so a reinstall returns to it. It also deletes webhook events older than 30 days.

Files that agents uploaded publicly are not deleted, only their database rows.

## Tests

- `tests/Feature/SallaAppInstallTest.php`
- `php -d memory_limit=1G vendor/bin/phpunit --filter SallaAppInstallTest`
