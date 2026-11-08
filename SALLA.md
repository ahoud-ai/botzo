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

## Widget in the store (phase 3)

The snippet is the same for every store. Salla's editor accepts plain JavaScript only, not HTML or
`<script>` tags, and its preview does not run the code, so test on the store itself. A saved snippet
reached an installed store within seconds, with no reinstall.

```js
(function(){var s=document.createElement('script');s.src='https://botzo.net/salla-widget.js';s.async=true;document.head.appendChild(s)})();
```

1. `public/salla-widget.js`, built from `resources/widget/salla-loader.js` by `npm run build:widget`, reads
   `store.id` from `salla.config`.
2. It asks `GET /api/salla/widget/{store id}` for the widget key.
3. It adds the usual `public/widget.js` with that key.

- Stores keep the snippet Salla approved, so `salla-widget.js` and the key route must keep working with it.
- `app.store.authorize` creates the widget and assistant once. A reinstall keeps the key and the merchant's edits.
  The widget's only allowed domain is the host of the store's domain in Salla.
- The key route answers 403 for an unknown store, an uninstalled store, a store with no running subscription,
  a disabled widget, or another domain.
- Salla sends no event when a store changes its domain. An unknown domain makes Botzo ask Salla again, at most
  once an hour per store, and the allowed domain follows the answer.
- New widgets use the `store` look: a light panel in the store's color with Mochi on its top edge. The color is the
  widget's `accent` when set, else `theme.color.primary` from `salla.config`. It lives in `storeStyles` in
  `resources/widget/widget.js`, behaves as the Mochi theme, and is not offered in the admin's theme list.
- New assistants use Botzo's AI key and the texts in `database/data/salla_store_assistant.php`. They have no
  order or shipping data yet and are told not to guess.
- `daily_reply_limit` stays at the table default of 300 per store until pricing is decided; a plan with more than
  300 replies a day needs a higher value.

**Known limit:** browsers send only the host of the page, and some stores share a host (`demostore.salla.sa`,
and probably `salla.sa`), so a store on the same host could use another store's key, and a script outside a
browser can fake the header anyway. Conversations stay private to each visitor's token. The exposure is
another store's reply allowance, capped by its daily and monthly limits.

**Before release:** check that the server's `addons` table has `AI Assistant` turned on and the server can
reach `accounts.salla.sa`, and that Salla's review accepts loading a script from Botzo's server.

## Selling from the chat (phase 5)

`salla:sync-products` (every five minutes) copies the products of new stores and of stores not synced in the last
hour into `salla_products`. Products gone from Salla are removed only after every page was read. Prices on a card
can therefore be up to an hour old; the cart and checkout always use Salla's current price.

1. For each visitor message, `SallaShoppingAssistant` searches the catalog and gives the matches to the model,
   with the products the visitor was last shown.
2. The model ends its reply with `[[PRODUCT:id]]`, at most three. Cards are built from the catalog of the visitor's
   own store, so an invented id shows nothing.
3. The card calls `salla.cart.addItem` in the visitor's browser, which needs no extra scope. Salla refuses the call
   without a choice for each option even when the option says `required: false`, so the card asks for them first.
4. "Checkout and pay" calls `salla.cart.submit`, which takes guests to login and then to `pay.salla.sa`. Botzo never
   sees card details.

Bookings, donations, options with no list of values and multi-choice options open the product page instead.

## Shipping, offers and orders

The same command copies shipping zones and active special offers into `salla_stores.store_facts`, and the assistant
receives them with each message. A list Salla does not return keeps its last copy.

- **Coupons are left out on purpose:** a code can be private to some customers.
- **Policies:** Salla has no route for the store's policy pages, so they wait for a field in the merchant panel.

"Where is my order?" shows a form (`[[CARD:order]]`) that posts to `POST /api/widget/{key}/order-status`:

- Both the order number and the last nine digits of the phone on the order must match. Any mismatch gets the same answer.
- Five tries per conversation an hour, and 60 lookups a minute per store.
- The answer holds the status and a tracking link only, and the phone is not stored in the conversation.

**Scopes needed (read only):** settings, customers, orders, carts, branches, categories, brands, products, special
offers, shipping, marketing, reviews, store settings. Carts, branches, brands, marketing and reviews are not used yet;
remove any still unused before publishing, because Salla's review and merchants read this list.

**Not confirmed yet:** a real tracking link, a real special offer, the inside of the checkout page (the demo store is
in maintenance mode), and a price changed in Salla reaching the cart.

## Deleting data

`salla:purge` (daily) removes, for stores past `purge_after`: contacts, chats and their logs, notes and
tickets, widget sessions and voice calls, files customers sent, and the store's email. The account
stays so a reinstall returns to it. It also deletes webhook events older than 30 days.

Files that agents uploaded publicly are not deleted, only their database rows.

## Tests

- `tests/Feature/SallaAppInstallTest.php`
- `php -d memory_limit=1G vendor/bin/phpunit --filter SallaAppInstallTest`
