# FANN — Water Delivery CRM

Read this before exploring. It is the map of the platform; only dig into files
when you need detail this file doesn't cover.

## What it is

Bottled-water delivery business CRM for the Tajikistan market (currency **TJS**,
UI defaults to **Russian**). One Laravel app serves four audiences:

| Audience | Entry point | Notes |
|---|---|---|
| Clients | `/` landing, `/profile` (ClientHome) | order, track courier, see history |
| Back-office staff | `/admin/*` (Inertia pages) | full CRM |
| Couriers | `/api/v1/currier/*` (legacy app) | Sanctum tokens |
| Staff mobile app | `/api/v1/app/*` | same controllers as web, JSON-ified |

## Stack

- **Laravel 12** / PHP 8.2, **Inertia 2** + **Vue 3** (`<script setup>`, TS), **Tailwind 4**
- Auth: Fortify + Sanctum + **Spatie Permission**, passkeys (`spatie/laravel-passkeys`), 2FA, **phone OTP via Telegram**
- Realtime: **Reverb** (`BROADCAST_CONNECTION=reverb`), Echo via `composables/useEcho.ts`
- Queue: **Redis** + Horizon. Cache/session: database. DB: **SQLite** locally
- Routing in Vue is via **Wayfinder** — generated helpers in `resources/js/routes/**` and `resources/js/actions/**`. Import `{ index, show } from '@/routes/admin/orders'`; never hand-write URLs
- UI kit: reka-ui + local shadcn-style wrappers in `resources/js/components/ui/`
- Telephony: Asterisk AMI (click-to-call + `WebRTCDialer.vue`)
- Telegram bot lives in `packages/safarovitch/telegram-bot` (path repo, DefStudio Telegraph based)

Commands: `composer dev` (serve + queue + pail + ssr), `composer test` (Pest),
`npm run dev` / `build`, `npm run lint`, `npm run format`.
CI: `.github/workflows/{lint,tests}.yml`.

## Roles — the single most important concept

Defined in `App\Models\User` and seeded by `UserRolesSeeder`:

```
ADMIN_ROLES   = Admin, Staff manager, Product manager, Finance manager, Content manager
COURIER_ROLES = Currier, Currier manager      // note the misspelling "Currier" — it is load-bearing
plus: Client
```

Three route tiers, declared identically at the top of `routes/web.php` and `routes/mobile.php`:

- `$adminRoles`   — full back-office
- `$managerRoles` — admin + `Currier manager` (assignment / oversight)
- `$staffRoles`   — admin + `Currier manager` + `Currier` (delivery-scoped subset)

Helpers: `hasAdminAccess()`, `isStaff()`, `isCourierStaff()`, `isCourierOnly()`,
`isCurrierManager()`, `isClient()`, `isShell()`.

**`App\Support\UserAbilities::for($user)`** is the one source of truth for UI ability
flags (`manageOrders`, `deleteClients`, `viewAdminStats`, …). It feeds both
`HandleInertiaRequests` (web → `auth.can`) and `/api/v1/app/me` (mobile), so both
clients agree. Every flag mirrors a real server-side guard — hiding UI is never the
only protection.

**Courier scoping rule:** a plain courier must never see company-wide totals. E.g.
`pending_orders_count` in `HandleInertiaRequests` is scoped by `courier_id` for
`isCourierOnly()` users, and `MobileMenu` applies the same rule to its badge.

**Admin mode**: staff toggle a session flag via `POST /admin/switch-mode`
(`AdminModeController`); `adminMode` is shared to Inertia and Vue pages swap
between client routes and `/admin` routes based on it.

## Route files

- `routes/web.php` — landing, client area, and the whole `/admin` Inertia app
- `routes/mobile.php` — `/api/v1/app/*`; **mirrors the admin section of web.php**, reusing the same controllers. `MobileInertiaBridge` middleware converts Inertia responses/redirects to JSON. A route added to web.php is invisible to the app until mirrored here
- `routes/api.php` — public anonymized courier feed + legacy `/api/v1/currier/*` courier endpoints
- `routes/auth.php`, `routes/settings.php`, `routes/channels.php`
- Mobile navigation is **server-driven** from `App\Support\MobileMenu` via `/me` + `/menu`, so role/menu changes need no app release

## Domain model

`User` ─┬─ `UserProfile` (type individual/company, region, credit_limit)
        ├─ `UserPhone[]`, `UserAddress[]` (label, address_line, lat/lng, is_default)
        ├─ `Wallet` (balance, TJS) ─ `Transaction[]` (polymorphic `reference`)
        ├─ `Order[]`, `Subscription[]`
        └─ `CurrierLocation[]` (`lastLocation()` = latestOfMany)

`Order` ─┬─ `OrderItem[]` (quantity, delivered_quantity, unit_price, is_gift)
         ├─ `returnedMaterials` (pivot `order_returned_materials`: quantity, deferred_quantity)
         ├─ `parentOrder` / `backorders` (self-referencing)
         └─ `financialRecords` (morphMany)

`Product` (JSON `name`/`description` → localized) ─ many-to-many `RawMaterial` (BOM,
`product_raw_material`). `RawMaterial.is_reusable` + `deposit_price` drive the bottle
deposit logic. `InventoryItem` is separate (equipment/assets, not stock).

### Order lifecycle

`OrderStatus`: pending → confirmed → in_production → ready → **accepted** →
**in_transit** → delivered, plus cancelled. `PaymentStatus`: unpaid / partial / paid.
Order numbers: `WF-{year}-{00001}` generated in `Order::boot()`; the year and
sequence follow the order's `created_at`, so a backdated order numbers into its
own year.

Non-obvious mechanics, all in `OrderController` (1.4k lines — the heart of the app):

- **`isAdminPath()`** distinguishes `/admin/*` and `/api/v1/app/admin/*` from the client-facing routes; the same controller method serves both, scoped differently. Client `/orders` is always filtered to `auth()->id()`
- **Partial delivery** (`applyPartialDelivery`): courier records per-line delivered counts at the delivered transition → stamps `delivered_quantity`, restores stock for shortfalls, optionally spawns a pending **backorder** child order, and recomputes `total_amount` preserving the original discount ratio
- **Reusable deposits** (`Order::reusableDepositSummary()`): expected reusable units come from the BOM; unreturned ones become a `deposit_charge`. "Deferred" = client will hand bottles back later; settled via `collectDeferred`
- **Money**: `grand_total = total_amount + deposit_charge`; `balance_due`, `overpaymentAmount()`, `reconcilePaymentStatus()` on the model. Payments go through `WalletService` (deposit/withdraw/pay/refund); `OrderAccountingService::syncPaymentRecord()` keeps a matching income `FinancialRecord` in sync and removes it if the order stops being paid
- **Gift items** (`is_gift`) are excluded from every total
- **Backdating**: full admins (ability `backdateOrders`) may post a `created_at` with a new order to record one taken earlier; `resolveCreatedAt()` ignores the field for anyone else and clamps future dates. `created_at`/`updated_at` are set on the model before `save()` — Eloquent only stamps them when they aren't already dirty

### Demand forecasting

`app/Services/Forecasting/` — seasonally-aware volume forecasting. The numbers
are all computed in PHP; Gemini is used only for text (sorting clients into
segments from free text, and writing the manager narrative). Tuning lives in
`config/forecasting.php`, not `.env`.

The model is a **seasonally-modulated Poisson rate per client**:

    expected orders on day d = baseRate x segmentIndex(month of d)^0.7

`baseRate` is de-seasonalised — orders divided by the *seasonal index summed
over the observed days*, not by elapsed days — so a client watched only over
summer does not look permanently thirsty. The exponent splits a seasonal swing
between ordering more often (0.7) and ordering more per visit (0.3); the two
must sum to 1 or the season gets counted twice.

Pieces:

- `ClientSegment` (native enum) — household / office / school / horeca / retail
  / fitness / medical / government / industrial / unknown. Each case carries a
  12-month **prior** curve, normalised to a mean of exactly 1.0. This is the
  demand-side classification and is *not* `ClientType` (individual/company): a
  school and an office are both "company" and behave oppositely in July
- `SegmentClassifier` — keyword rules over ru/tg/en text. **Rule order is
  load-bearing**: specific before generic, so "ООО Кафе Дилшод" lands as horeca
  rather than office. `AiSegmentClassifier` (Gemini) handles the leftovers
- `SeasonalityService` — serves priors while history is thin, and measures the
  real curve once ≥13 months exist (`min_months_for_learning`), blending the
  two by shrinkage. One formula covers both regimes: at zero observations it
  reduces exactly to the prior. Measurement is ratio-to-moving-average over
  *units per enrolled client* (clients who ordered in the trailing 12 months) —
  dividing by clients who actually ordered would hide the whole school effect,
  since schools do not order less in July, they stop
- `ClientDemandModel` / `ClientDemandProfile` — per-client rates. Sparse
  clients are **shrunk toward their segment mean, never excluded**; dropping
  occasional buyers makes the aggregate structurally low. Churn is measured in
  *expected orders missed*, not elapsed days, so it is automatically lenient
  during a segment's off-season
- `DemandForecastService` — aggregates. Committed orders and active
  subscriptions are known demand and are reported separately; **subscribed
  clients are removed from the statistical model** so their contract and their
  history are not both counted. The P10–P90 band comes from summing per-client
  variance, so a day made of three large clients is correctly reported as less
  predictable than one made of forty small ones
- `ProcurementForecastService` — units → BOM → raw materials. For reusable
  containers only the measured *leakage* has to be bought, not the throughput;
  deferred returns count as returned (a timing difference, not a loss)
- `ForecastAccuracyService` — the feedback loop, and the thing that actually
  makes the forecast precise. `forecast:snapshot` stores each vintage nightly,
  `forecast:reconcile` scores it the next night, and the measured bias is fed
  back, clamped to ±`bias_max_adjustment`. Scored with WAPE, not MAPE, so one
  quiet 2-bottle day cannot post a 400% error and dominate
- `RoutePlanner` — capacitated nearest-neighbour clustering, seeded from the
  *furthest* stop so remote deliveries anchor a route instead of being stranded
  on the last one. Deliberately a heuristic, not an optimal VRP solve. Stops
  without coordinates are returned in their own bucket, never silently dropped

Commands (all scheduled in `routes/console.php`): `forecast:classify-segments
[--ai]`, `forecast:recompute-seasonality [--show]`, `forecast:snapshot`,
`forecast:reconcile [--report]`.

Gotchas:

- A seasonality row or client segment set by a human is stored with
  `source = manual` and **recomputation must never overwrite it**
- Seasonal indices are multiplicative around 1.0 and every curve is
  renormalised to average exactly 1.0 across the year. Breaking that invariant
  silently inflates or deflates annual totals
- Aggregate totals are summed from the raw grid, never from the rounded day
  rows — a client contributing 0.03 bottles/day rounds to 0.0 every day, and
  summing after rounding erases the entire long tail
- Over a horizon, **variances add, standard deviations do not**. Summing daily
  P10s overstates a 30-day band by roughly 5x

#### The production plan

`/admin/production` is the one page non-technical staff use every morning, so
it deliberately shows one big number and no statistics vocabulary. It is also
where the forecast stops being advice and becomes a decision, which makes its
arithmetic worth understanding:

    fill = needed that day − what is already filled and ready

Demand for a day comes from three sources, and they are kept visibly separate
because two are facts and one is an estimate: orders already placed for that
date, subscription schedules not yet turned into orders, and the statistical
forecast for everyone else.

`production_runs` is a small ledger with two row types — `production` (staff
filled this many) and `count` (staff physically counted this many, which
becomes the new anchor and supersedes all earlier arithmetic). Ready stock is
*derived* from it rather than stored, because a warehouse balance always
drifts and re-counting must be the easy fix. This exists because the app had no
production step at all: `OrderController` decrements `products.quantity` when
an order is placed and nothing ever puts it back, and on the 19L product
`manage_stock` is off, so that column is stale and unusable.

Gotchas:

- **Round to nearest, never up.** Rounding the forecast tail up turns 0.2
  predicted bottles into a whole bottle every day, telling staff to "fill 1"
  all week and over-producing the thing the page exists to prevent. The
  fractional deficit carries forward in stock until it is a real bottle
- Gifts **are** counted here, unlike everywhere else in the app: a free bottle
  is still a bottle somebody has to fill
- Short deliveries subtract `delivered_quantity`, not `quantity` — bottles that
  came back on the van never left stock
- `Subscription::occurrencesBetween()` is shared with the demand forecast on
  purpose. The generator advances `next_delivery_at` **past** the delivery it
  just created, so an order and a schedule date never describe the same
  delivery — filtering out subscription-generated orders would silently drop
  them. Both services include the orders and skip schedule dates that already
  have one
- The materials figure ("covers N more bottles") is stock left *beyond orders
  already taken*, because raw materials are decremented when an order is
  placed, not when a bottle is filled

### Other subsystems

- **Subscriptions** — `SubscriptionService` + `subscriptions:generate` command, scheduled every 5 min (`routes/console.php`); frequency weekly/biweekly/monthly/custom with a time slot
- **Production plan** — `/admin/production`, `ProductionPlanService`. The daily
  operational page: pick a date or range, get one number ("fill 240"). See
  **Demand forecasting** above
- **Forecasts** — two views over one model, under `/admin/forecasts/*`:
  - `ForecastController` — the per-client calendar: who is likely to order on a
    given day, and what basket (trend, churn flags, expected value). Can create
    the order.
  - `DemandForecastController` + `App\Services\Forecasting\*` — the aggregate
    volume view: expected bottles per day/segment/product with a P10–P90 band,
    plus what to buy. `RoutePlanController` turns a day's demand into vehicle
    runs. See **Demand forecasting** above.
- **Financial records** — polymorphic income/expense ledger; `/admin/financial-records/export` uses the dependency-free `App\Support\XlsxWriter`
- **Notifications** — `OrderCreated` / `OrderDelivered` / `OrderStatusUpdated` events → Telegram group broadcast (`TelegramNotifier`) + per-user inbox fan-out (`StaffNotifier`) readable at `/api/v1/app/notifications`
- **Auth via Telegram OTP** — `TelegramOtpService`: phone → deep link `t.me/<bot>?start=login_<token>` → bot DMs the code → verify. Requires `CACHE_STORE` = redis or database. Setup: `php artisan app:setup-telegram-bot`, diagnose with `app:diagnose-telegram-bot`
- **Shell users** — walk-in clients get an auto-created `User` with `claimed_at = null` (`isShell()`); a later self-registration on the same phone/email adopts the row instead of being rejected
- **Maps** — MapLibre GL + OpenFreeMap vector tiles, no API key. `MapChooser.vue` opens Yandex/Google/OSM. Live tracking: `GET /me/active-tracking` and the public throttled feed `/api/v1/public/curriers/locations`

## Frontend conventions

- Pages in `resources/js/pages/**` mapped 1:1 to `Inertia::render('orders/Index')`
- `AppLayout.vue` → sidebar/header layouts; `BottomNav.vue` for mobile. Mobile-first throughout
- **i18n is client-side**: `composables/useI18n.ts` with `resources/js/i18n/ru.ts`, keyed by the English string. Default locale is **ru**; a missing key falls back to English. `useLandingI18n.ts` is the landing-page variant — don't mix them up (an earlier bug used the wrong one on the courier dashboard)
- Shared Inertia props: `auth.user`, `auth.can`, `adminMode`, `flash`, `currency`, `pending_orders_count`, `asterisk`, `otpFlow`
- Other composables: `useTableSort`, `useToast`, `useOrderNotifications`, `useEcho`, `useAppearance` (dark mode), `useTwoFactorAuth`

## House style

- PHP indentation is inconsistent across the repo (2-space in the older controllers/models, 4-space in newer services). **Match the file you are editing.**
- Non-obvious decisions are documented in block comments at the top of the class or route group — read them, and keep that habit when adding logic
- Enums: older ones extend `BenSampo\Enum` (`OrderStatus`, `PaymentStatus`, …), newer ones are native PHP enums (`SubscriptionFrequency`, `DeliveryTimeSlot`). Check before calling `::getValues()` vs `::cases()`
- Tests are Pest; coverage is currently auth/settings/starter-kit only — the business logic above is untested, so verify changes by hand
- Spelling: **Currier** (role, model `CurrierLocation`, routes) is intentional/legacy. `courier_id`, `courier_locations` table, and `Order::courier()` use the correct spelling. Both exist; don't "fix" either

## Gotchas

- Adding an admin route means editing **both** `routes/web.php` and `routes/mobile.php`
- Adding a permission means editing `UserAbilities` (and `MobileMenu` if it needs a menu entry) *in addition to* the route middleware
- `products.currency` once had an erroneous unique index — dropped in a migration; don't reintroduce it
- The root `debug_ami.log` (26 MB) and `debug_ami_raw.php` are Asterisk debugging leftovers, not part of the app
