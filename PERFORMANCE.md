# Performance

How Botzo is kept fast, how to measure it, and what is still open. Numbers below are from the
October 2026 audit (branch `perf/speed`).

## Targets

| Area | Target |
| --- | --- |
| Public pages (Lighthouse, mobile) | score 90+, LCP < 2.5 s, INP < 200 ms, CLS < 0.1 |
| Server response (TTFB) | < 600 ms |
| Dashboard pages | < 300 ms server time |
| Widget loader | < 15 KB gzip |
| AI reply | first visible text < 1.5 s where streaming works |

## Measuring

- **Pages and static files:** `bash scripts/perf-check.sh` (read-only GETs against https://botzo.net,
  or `BOTZO_URL=...`). Shows median TTFB, server time, size, compression and `Cache-Control`.
  Add `WIDGET_KEY=wgt_...` to include the widget config endpoint.
- **Lighthouse:** `npx lighthouse https://botzo.net/ --preset=perf --form-factor=mobile` (and
  `--preset=desktop`). Run 3 times and take the median; the server is far from most visitors, so
  network time varies.
- **Server timing:** API responses carry a `Server-Timing` header (`app`, `db`, `meta`, `token`,
  `broadcast`, `ai`). Open the browser's network tab, or `curl -sI`.
- **AI replies:** every automatic reply logs one `intellireply.reply.timing` line in the
  `intellireply` log channel: `prepare_ms`, `knowledge_ms`, `model_ms`, `send_ms`, `total_ms` and
  token counts. No message text is logged.
- **Queries per page:** in a local test database, count queries for a route as a logged-in owner
  (a `DB::listen` around `$kernel->handle()`), and look for the same SQL repeated.

## What was done

| Fix | Before | After |
| --- | --- | --- |
| Translations as static versioned files (`App\Support\TranslationAssets`) | `/translations/ar` 1,003 KB, uncompressed, no-cache on every page | 494 KB raw (~138 KB gzip), cached for a year |
| Hero background: WebP, only the active theme, desktop only | 2.96 MB on every device | 19 KB (dark) / 29 KB (light) on desktop, 0 on mobile |
| Vite preload helper kept out of the jspdf chunk | Home 1,763 KB JS, Pricing 1,050 KB | 1,331 KB / 617 KB (jspdf loads only on invoices) |
| 3D hero (three.js) lazy, wide screens only, paused when hidden | loaded on every device | not loaded on phones (home JS 886 KB on mobile) |
| Inbox chat background as WebP | 496 KB | 32 KB |
| Per-request memo for organization, team, subscription, plan, add-ons, tax rates (`App\Support\RequestMemo`) | `/chats` 181 queries, `/chats/{uuid}` 208, `/contacts` 145, `/dashboard` 132 | 33 / 46 / 27 / 43 |
| Unread badges in one `loadCount`, dashboard chart and campaign counts grouped | one query per contact / per day / per status | one query each |
| Widget: page views read `GET /api/widget/{key}/config` (browser-cached 5 min); the session opens with the first message or call | a session row per page view per new visitor | no row until the visitor writes |
| AI reply timing log | no per-step timing | `intellireply.reply.timing` |

Charts (`vue3-apexcharts`) were already loaded on demand, only on the dashboard.

### RequestMemo rules

`RequestMemo::remember($group, $key, fn)` keeps a value on the current request only. Groups are
cleared when their models change in the same request (see `AppServiceProvider::boot`):
Organization → `organization`, `organization_timezone`, `subscription`; Team → `team`,
`organization`; Subscription / SubscriptionPlan → `subscription`; Addon → `addon`;
TaxRate → `tax_rates`. Writes that bypass Eloquent (`DB::table(...)->update`) do not clear it, so
only memoize data that is written through models, or clear it yourself.

## Server and deploy

- `public/.htaccess`: LiteSpeed ignores the `SetEnvIfExpr` + `Header ... env=` rules, so per-path
  cache headers must use `<FilesMatch>` (as the `/i18n` rule does).
- Deploy should finish with `php artisan optimize` (config, routes, events and views cached);
  `php artisan optimize:clear` alone leaves Laravel reading every config file on each request.

## Open items

1. **Settings reads (about 8 queries per dashboard page).** Settings are written with
   `DB::table('settings')` in about 25 places (`SettingService`, `AddonController`,
   `LanguageController`, ...), which bypasses model events, so a cache across requests would go
   stale. Route all writes through one service that clears a cache, then serve reads from that
   cache. Deferred: pages are under 50 ms server time and the gain is small.
2. **Shared page props (about 6 queries on every page):** organization switcher, branch count,
   unread total, languages, plan usage. Each is a different number; caching them needs the same
   invalidation work as item 1.
3. **Remaining floor:** the Inbox list itself is about 6 queries (contacts, last messages, last
   inbound, tickets, unread counts) plus templates, contact fields and agents. The dashboard shows
   about 20 independent numbers. Going below ~20 queries on these pages would mean caching
   summaries, not removing waste.
4. Approved but pending (each needs its own review): self-hosted IBM Plex woff2 and dropping the
   Ping AR LT download, versioned widget files with long caching and pre-compression, database
   indexes after EXPLAIN on real data, Cloudflare in front of the site, and streaming AI replies.
