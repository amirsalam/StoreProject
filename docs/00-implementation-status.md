# Implementation Status — the DDDocs source-of-truth index

> **Purpose.** This is the master verification index for Documentation-Driven
> Development. Before implementing **anything**, consult this file to learn a
> module's real completion state, then read its architecture doc, then
> continue exactly where the code stopped. This file is kept synchronized
> with the code — when implementation changes, update the relevant row here
> and the module doc in the same change.

## How to read this

The 22 `*-architecture.md` docs are written in a **shipped-vs-planned**
format: each describes the *target* architecture and marks, in a "status
snapshot", what already ships versus what is planned. This index
aggregates those snapshots into one verifiable picture, **grounded in an
actual codebase survey** (models, migrations, services, controllers,
pages, tests) — not in the docs' aspirations. Last survey: **2026-06-28**
(post vendor-stores: **31 models, 43 migrations, 42 pages, 31 test files**,
22 architecture docs). The multi-vendor core now ships — see the
marketplace row + changelog.

- ✅ **Completed** — shipped with code + tests; do not rebuild, only improve (respect backward compatibility).
- 🟡 **Partial** — a real, working core ships; continue from where it stopped, do not duplicate.
- ❌ **Planned** — documented only; implement incrementally, dependency-order, one PR-sized feature at a time.

Completion % is a grounded estimate from code presence + test coverage,
not a precise metric. Local test execution is currently blocked
(`pdo_sqlite` not enabled in the local PHP); the suite runs in CI — so
"tests" below means *tests exist in the repo*, verified by file, not by a
local green run.

---

## Documentation structure reconciliation

The DDDocs master prompt envisions a **numbered** doc set
(`00-project-overview.md … 32-onboarding.md`). The repo currently uses
descriptive names (`<module>-architecture.md`). The mapping + the **gap**:

| Prompt # | Topic | This repo |
|---|---|---|
| 00 | project-overview | ⬅ *missing* (this file is the status index, not the overview) |
| 01 | architecture | ❌ missing (cross-cutting) |
| 02 | coding-standards | ❌ missing (partly in `CLAUDE.md`) |
| 03 | folder-structure | ❌ missing (partly in `CLAUDE.md`) |
| 04 | database | ❌ missing (per-module DB sections exist in each doc) |
| 05 | api-standards | 🟡 covered in `api-developer-portal-architecture.md` §25 |
| 06 | ui-design-system | ❌ missing (tokens live in code: `app.css`, shadcn) |
| 07 | security | 🟡 covered per-module + `audit-logs-architecture.md` |
| 08 | testing | 🟡 covered in `CLAUDE.md` + per-module test maps |
| 09 | deployment | ❌ missing |
| 10 | multi-tenancy | 🟡 covered across docs (no standalone doc) |
| 11 | authentication | 🟡 shipped; described in `audit-logs` §auth + onboarding §2 |
| 12 | dashboard | ✅ `dashboard-architecture.md` |
| 13 | projects | ✅ `projects-architecture.md` |
| 14 | tasks | ✅ `tasks-architecture.md` |
| 15 | notifications | ✅ `notifications-architecture.md` |
| 16 | billing | ✅ `billing-architecture.md` |
| 17 | ai | ✅ `ai-architecture.md` |
| 18 | analytics | ✅ `analytics-architecture.md` |
| 19 | automation | ✅ `workflow-automation-architecture.md` |
| 20 | marketplace | ✅ `marketplace-architecture.md` |
| 21 | mobile | ✅ `mobile-architecture.md` |
| 22 | crm | ✅ `crm-architecture.md` |
| 23 | messaging | ✅ `messaging-architecture.md` |
| 24 | support | ✅ `support-architecture.md` |
| 25 | file-manager | ✅ `file-manager-architecture.md` |
| 26 | knowledge-base | ✅ `knowledge-base-architecture.md` |
| 27 | admin-control-center | ✅ `admin-control-center-architecture.md` |
| 28 | white-label | ✅ `white-label-architecture.md` |
| 29 | audit-logs | ✅ `audit-logs-architecture.md` |
| 30 | api-developer-portal | ✅ `api-developer-portal-architecture.md` |
| 31 | affiliate | ✅ `affiliate-referral-architecture.md` |
| 32 | onboarding | ✅ `onboarding-architecture.md` |
| — | payments | ✅ `payments-architecture.md` (extra; underpins 16/31) |

**Documentation gap:** the **foundational/cross-cutting docs 00–11 do not
exist as standalone files**, yet they describe systems that are *already
shipped* (architecture, multi-tenancy, auth, DB conventions, API
standards, security, testing). Under "documentation is the source of
truth", these should be backfilled so the truth is complete — see
[Recommended next step](#recommended-next-step).

---

## Master module matrix

Ordered by completion (most-shipped first). Risk = blast radius of changes;
Complexity = remaining build effort.

| # | Module | Doc | Status | % | Risk | Complexity |
|---|---|---|---|---|---|---|
| F | **Authentication & sessions** | (11) | ✅ | 95% | Med | Low |
| F | **Multi-tenancy** | (10) | ✅ | 90% | High | Low |
| F | **RBAC / permissions** | (10/27) | ✅ | 85% | High | Low |
| F | **Settings (profile/2FA/sessions/tokens)** | (11/30) | ✅ | 95% | Low | Low |
| 02 | **Payments (wallet/ledger/Money)** | payments | ✅ | 85% | High | Med |
| 12 | **Dashboard** | dashboard | ✅ | 80% | Low | Low |
| F | **i18n / localization** | (28) | ✅ | 80% | Low | Low |
| 06 | **Team / invitations** | (27) | 🟡 | 70% | Med | Low |
| 16 | **Billing / subscriptions** | billing | 🟡 | 60% | High | Med |
| 20 | **Marketplace** | marketplace | 🟡 | 72% | High | High |
| 15 | **Notifications** | notifications | 🟡 | 30% | Med | Med |
| 21 | **Mobile / responsive** | mobile | 🟡 | 35% | Low | Med |
| 13 | **Projects** | projects | 🟡 | 25% | Med | Med |
| 14 | **Tasks** | tasks | 🟡 | 25% | Med | Med |
| 27 | **Admin control center** | admin-control-center | 🟡 | 35% | Med | High |
| 29 | **Audit logs** | audit-logs | 🟡 | 25% | Med | Med |
| 18 | **Analytics** | analytics | 🟡 | 20% | Low | High |
| 30 | **API & developer portal** | api-developer-portal | 🟡 | 20% | Med | High |
| 28 | **White-label / branding** | white-label | 🟡 | 20% | Med | High |
| 32 | **Onboarding wizard** | onboarding | 🟡 | 20% | Low | Med |
| 26 | **Knowledge base** | knowledge-base | 🟡 | 10% | Low | Med |
| 25 | **File manager** | file-manager | 🟡 | 10% | Med | High |
| 31 | **Affiliate & referral** | affiliate-referral | 🟡 | 5% | High | High |
| 17 | **AI features** | ai | ❌ | 0% | Med | High |
| 19 | **Workflow automation** | workflow-automation | ❌ | 0% | Med | High |
| 22 | **CRM** | crm | ❌ | 0% | Low | High |
| 23 | **Messaging / chat** | messaging | ❌ | 0% | Med | High |
| 24 | **Support tickets** | support | ❌ | 0% | Low | Med |

(F = foundational, cross-cutting.)

---

## Per-module verification

### ✅ Authentication & sessions — 95%
- **Completed:** email register (`RegisteredUserController`, audited), login, logout, password reset/confirm, email verification (`VerifyEmailController` + `email_verified_at`), 2FA (`TwoFactorService` + challenge), social login (`SocialiteController` + `SocialAccount`), session management. Tests: Authentication, Registration, EmailVerification, PasswordReset, PasswordConfirmation, Socialite, TwoFactor.
- **Missing:** SSO/OIDC, phone verification, CAPTCHA, signup fraud checks.
- **Depends on:** —. **Improve, don't rebuild.**

### ✅ Multi-tenancy — 90%
- **Completed:** `tenants` + `tenant_user` pivot, `BelongsToTenant` trait + global scope, `add_tenant_id_to_business_tables`, `Tenant` (owner/users/roleOf/currentSubscription/plan), `TenantIsolationTest`.
- **Missing:** custom-domain resolution flow (column exists; verification/SSL planned — white-label §7), per-tenant data residency.
- **Depends on:** —. **High blast radius — change with care + tests.**

### ✅ RBAC / permissions — 85%
- **Completed:** Spatie `permission_tables`, `seed_roles_and_backfill_admins`, `HasRoles`, `EnsureUserIsAdmin`, role-aware dashboards.
- **Missing:** fine-grained policies per resource, scope ∩ RBAC for API (api doc §4), departments.

### ✅ Payments — 85%
- **Completed:** `Wallet` (cents + version), append-only `LedgerTransaction` (SIGN map, update-guard), `WalletService` (credit/debit/refund, locked, idempotent), `Money`, `OrderPaymentProcessor`, `ReconciliationService`, `webhook_events`, idempotency on payments. Tests: WalletService, OrderPaymentProcessor, Reconciliation.
- **Missing:** payout rails (bank/PayPal/Wise), multi-gateway beyond Stripe.
- **Depends on:** —. **The money spine — underpins billing, marketplace, affiliate.**

### ✅ Dashboard — 80%
- **Completed:** role-aware pages (super-admin/vendor/team/customer), `DashboardService`, `MetricsAggregator`, `daily_metrics`, `Api/V1/DashboardController`. Tests: DashboardService, MetricsAggregator, Dashboard.
- **Missing:** customizable widgets, saved layouts, real-time tiles.

### 🟡 Team / invitations — 70%
- **Completed:** `team_invitations`, `InviteMemberAction`, `AcceptInvitationAction`, `Workspace/TeamController` + page, `InvitationController` + accept page, `InvitationTest`.
- **Missing:** departments, bulk invite, per-resource permission editor.

### 🟡 Billing / subscriptions — 60%
- **Completed:** `Plan`, `Subscription`, `TenantSubscription` (TRIALING/ACTIVE/PAST_DUE), `Invoice`, `StripeGateway`, `BillingService`, `StripeWebhookProcessor` (+ `DuplicateWebhookException`), `add_stripe_columns_to_tenants`, `Workspace/Billing` + `Invoice` controllers + pages, `PlanGate` + `UsageReader`. Tests: StripeWebhook, PlanGate.
- **Missing:** dunning/retries, proration, usage-based billing, commission/payout to vendors, invoice PDF.
- **Depends on:** payments ✅, plans ✅.

### 🟡 Marketplace — 65%
- **Completed:** `categories`, `products` (+ admin CRUD with `StoreProductRequest` + `PlanGate` limit), `coupons` (`Coupon` model), `orders` + `order_items`, `licenses`, `downloads`, `reviews`, `wishlists`, storefront (`products/index`+`show`), cart (`CartService` + `CartController` + page). **Checkout vertical:** `CheckoutController` + `CheckoutService` (cart → order + items + pending `Payment` + Stripe PaymentIntent, server-side prices, coupon redemption with per-user/min-order/max-uses guards, $0-order fast-settle) + `FulfillOrder` listener on `PaymentCompleted` (issues `License`/`Download` idempotently) + `checkout/confirmation` page + i18n. **Stripe Elements card confirmation** (this PR): `checkout/index` rebuilt as a two-phase flow (billing → `@stripe/react-stripe-js` `PaymentElement` → `stripe.confirmPayment` with `return_url` = confirmation), `store()` content-negotiated to return the intent `client_secret` as JSON (redirect path unchanged → existing tests green), publishable key passed as a page prop, $0 orders skip the card step, no-key + processing + card-error states handled, 6 new i18n keys × 4 locales. Tests: ProductManagement, ProductPlanLimit, Cart, **Checkout** (11 cases — 3 new for the JSON intent contract).
- **Vendor stores (this PR):** `vendors` + `vendor_profiles` tables (tenant-scoped, soft-deletes), `Vendor`/`VendorProfile` models, `VendorService` (registerForUser/updateProfile/verify/suspend — DB transaction + `ActivityLog` audit + `VendorRegistered` event), `VendorPolicy` (owner/admin), `products.vendor_id` attribution, public storefront `StoreController` → `store/show` (profile header, logo/banner, verified badge, catalog grid, aggregate rating), `Workspace\VendorController` (open + manage own store) → `workspace/vendor/edit`, product→store backlink + `product.sold_by` i18n × 4 locales, `User::vendor()` relation, sidebar "My store" nav. Tests: `VendorStoreTest` (6) + `VendorProfileTest` (8). Verified: `php artisan migrate` (MySQL) green, smoke-test against real DB (register/unique-slug/attribution/profile/verify/suspend all pass), live HTTP `GET /store/{slug}` → 200 with correct data + 404 for unknown/inactive, `npm run build` + tsc + eslint clean.
- **Missing:** `vendor_stores` (theme/featured/SEO/custom domain), admin vendor approval/moderation (vendors self-activate today), vendor payouts (wire to the shipped wallet/ledger), product search/facets, ratings moderation, tax, webhook-lag "processing" affordance on the confirmation page.
- **Depends on:** payments ✅, billing 🟡, file-manager 🟡.

### 🟡 Notifications — 30%
- **Completed:** `notifications` table, `Notification` model, `NotificationService`, `Api/V1/NotificationController` (CRUD), `NotificationServiceTest`.
- **Missing:** multi-channel (email/SMS/push), templates, preferences, Reverb real-time, digest batching.

### 🟡 Mobile / responsive — 35%
- **Completed:** responsive UI across storefront/admin/settings, dark mode, RTL.
- **Missing:** PWA manifest/service worker, offline, install prompts, push.

### 🟡 Projects — 25%
- **Completed:** `projects` migration + `Project` model + `Workspace/ProjectController` + `workspace/projects` page.
- **Missing:** milestones, members, statuses/FSM, files, time tracking, project↔task wiring per the doc.

### 🟡 Tasks — 25%
- **Completed:** `tasks` migration + `Task` model + `Workspace/TaskController` + page.
- **Missing:** boards/columns, assignments, dependencies, comments, the doc's full task lifecycle.

### 🟡 Admin control center — 35%
- **Completed:** super-admin dashboard, `EnsureUserIsAdmin`, `Admin/{Product,Branding,User}Controller` + pages, `Setting` store. **Payment Gateways Management** (this PR): `PaymentGateway` model (encrypted credentials/webhook_secret), `payment_gateways` table, `config/payment_gateways.php` provider registry (18 providers, extensible), `PaymentGatewayService` (CRUD + toggle + setDefault + reorder + testConnection, all audited), `Admin\PaymentGatewayController` (full CRUD + custom actions, secrets redacted), `admin/payment-gateways/{index,create,edit,gateway-form}` pages, sidebar nav, `PaymentGatewayTest` (11 cases).
- **Missing:** cross-tenant governance, the aggregation/control overlays, security/compliance centers, gateway transaction statistics, live API credential ping.

### 🟡 Audit logs — 25%
- **Completed:** append-only `ActivityLog` (`UPDATED_AT=null`, `record()`), wired across auth + reconciliation, `settings/activity` page, `ActivityLogTest`.
- **Missing:** category taxonomy, `tenant_id` + subject morph, before/after snapshots, hash chain, partitioning, security detection, retention.

### 🟡 Analytics — 20%
- **Completed:** `daily_metrics` + `MetricsAggregator` (rollup foundation).
- **Missing:** event pipeline, BI dashboards, funnels, forecasting, exports.

### 🟡 API & developer portal — 20%
- **Completed:** `/api/v1` (user/dashboard/notifications), Sanctum tokens (`ApiTokenController`, scopes, shown-once, audited), `personal_access_tokens`.
- **Missing:** gateway middleware stack, OAuth2/OIDC, outbound webhooks, OpenAPI/SDKs, the portal, metering/monetization.

### 🟡 White-label / branding — 20%
- **Completed:** single-tenant `BrandingService` (title+logo, sanitize/optimize), admin branding page, CSS token system, `BrandLockup`, `BrandingTest`.
- **Missing:** per-tenant scoping, theme/typography/domain/email/PDF branding, feature tiers.

### 🟡 Onboarding wizard — 20%
- **Completed (as steps):** registration, tenant creation, plan/trial, branding, team invite, product publish all exist as standalone flows.
- **Missing:** the wizard state machine, event-driven checklist, activation score/TTFV, tours, sample data, AI assistant.

### 🟡 Knowledge base — 10%
- **Completed:** `BlogPost` model + migration.
- **Missing:** articles/spaces, search, RAG, help center, versioning.

### 🟡 File manager — 10%
- **Completed:** `downloads` + `licenses` (marketplace digital fulfillment), `BrandingService` upload pipeline (sanitize/optimize/signed URL).
- **Missing:** general file storage/folders, versioning, sharing, quotas, media library.

### 🟡 Affiliate & referral — 5%
- **Completed (primitives only):** the wallet/ledger/`Money`/reconciliation spine + `Coupon` model — i.e. the money rails a commission engine would use.
- **Missing:** everything affiliate-specific (affiliates, links, clicks, attribution, commission rules, payouts, fraud).

### ❌ AI / Automation / CRM / Messaging / Support — 0%
- Documented only. No models, services, or routes yet. Implement per dependency order when reached.

---

## Recommended next step

Per the methodology (read → verify → continue partial → implement missing,
in dependency order, PR-sized). **Update (2026-06-27):** the previously
recommended *marketplace checkout → payment* slice has **shipped**
(`c8d701b`), as has *Payment Gateways Management* (`9b87025`). The next
PR-sized increments, in leverage order:

1. ~~**Marketplace — Stripe Elements card-confirmation UI**~~ ✅ **SHIPPED
   2026-06-27** (`checkout/index` two-phase billing→`PaymentElement`→
   `confirmPayment`; content-negotiated `client_secret`; $0 fast-path).
   The end-to-end captured-revenue path is now closed on the shipped spine.

2. ~~**Marketplace — vendor stores/profiles**~~ ✅ **SHIPPED 2026-06-28**
   (`vendors` + `vendor_profiles`, `VendorService`/`VendorPolicy`,
   `products.vendor_id`, public `/store/{slug}`, `workspace/vendor`
   management). The multi-vendor core is in. **Next vendor increments, in
   order:** (a) **admin vendor approval/moderation** — vendors self-activate
   today; add the pending→approve workflow + an admin queue (small, gates
   §28 governance); (b) **vendor payouts** — wire vendor earnings to the
   shipped wallet/ledger (the affiliate doc's split logic applies); (c)
   `vendor_stores` (theme/featured/SEO/custom domain).

3. **Marketplace — confirmation "processing" affordance** *(tiny follow-up)*.
   After Stripe's redirect the webhook may lag; the confirmation page should
   show "payment processing" when the order is still `pending` with
   `redirect_status=succeeded`. A small, self-contained polish PR.

4. **Projects or Tasks vertical slice.** Self-contained, medium complexity,
   a clean full-stack vertical (FSM/statuses → members → comments) — good if
   a non-revenue domain slice is preferred.

5. **Documentation track — backfill foundational docs 00–11.** The
   source-of-truth is still incomplete: the cross-cutting docs describing
   the *already-shipped* architecture/tenancy/auth/DB/security/testing don't
   exist as standalone files. Low-risk; can run in parallel with any code PR.

**Recommendation:** with vendor stores shipped, take **increment 2a (admin
vendor approval/moderation)** next — it's small, closes the self-activation
gap, and is the prerequisite for trustworthy multi-vendor onboarding +
§28 governance. Then **2b (vendor payouts)** to complete the revenue loop
for sellers. Increment 3 (confirmation affordance) remains a clean tiny PR;
backfill foundational docs 00–11 in parallel.

> **Whichever track is chosen, the loop is the same:** read the module doc →
> confirm the rows above → implement the next PR-sized increment in
> dependency order with the full quality checklist (migration, validation,
> policy, service, events, tests, frontend) → update the module doc **and
> this index** → repeat.

---

## Changelog

| Date | Change |
|---|---|
| 2026-06-26 | Index created. Surveyed codebase (28 models, 39 migrations, 35 pages, 27 test files, 5 route files) and classified all modules. Identified the foundational-docs (00–11) gap. |
| 2026-06-26 | **Marketplace checkout vertical shipped** (45% → 55%): `CheckoutService` + `CheckoutController` + `FulfillOrder` listener on `PaymentCompleted` + checkout/confirmation pages + 4-locale i18n + `CheckoutTest` (8 cases). Wired the previously-disabled cart checkout button. Closes the cart→order→payment→digital-delivery gap on the shipped payments spine. |
| 2026-06-26 | **Payment Gateways Management shipped** (admin control center 25% → 35%): `payment_gateways` table + `PaymentGateway` model (encrypted credentials), `config/payment_gateways.php` registry (18 providers, config-only extensibility), `PaymentGatewayService` (CRUD/toggle/default/reorder/test, audited), `Admin\PaymentGatewayController` (secrets redacted), 4 React pages + sidebar nav + types, `PaymentGatewayTest` (11 cases). |
| 2026-06-27 | **Architecture doc set completed (22 docs).** Added white-label, audit-logs, api-developer-portal, affiliate-referral, onboarding architecture docs since index creation — every prompted module now has a shipped-vs-planned doc. |
| 2026-06-27 | **Index reconciled to DDDocs mode.** Re-surveyed code (29 models / 40 migrations / 40 pages / 29 tests / 22 docs). Corrected doc count (21 → 22). Refreshed "Recommended next step": the prior recommendation (marketplace checkout) has shipped, so the next PR-sized increment is now the **Stripe Elements card-confirmation UI** to close the captured-revenue path. Per-module rows re-verified accurate (no vendor/store model yet; no product search; no checkout card form yet). |
| 2026-06-27 | **Stripe Elements card-confirmation UI shipped** (marketplace 55% → 65%): `@stripe/stripe-js` + `@stripe/react-stripe-js`; `checkout/index` rebuilt two-phase (billing → `PaymentElement` → `confirmPayment` with `return_url`); `CheckoutController::store` content-negotiated to return the intent `client_secret` as JSON (legacy redirect path preserved → 8 existing tests stay green); publishable key surfaced as a page prop; $0 orders skip the card step; no-key/processing/card-error states; 6 i18n keys × 4 locales; 3 new `CheckoutTest` cases (JSON intent contract, 422 coupon, $0 null-secret) → 11 total. Verified: tsc (clean for checkout), eslint (clean), `npm run build` (green). Closes the end-to-end *captured-revenue* path on the shipped payments spine. Next: vendor stores. |
| 2026-06-28 | **DDDocs verification pass (no code change).** Re-ran the Step-2 survey against live code: counts identical to 2026-06-27 (29 models / 40 migrations / 40 pages / 29 tests / 22 docs), tree clean, no `Vendor`/`Store` model — every matrix row re-confirmed accurate. Commits since the last code-bearing change (`81c883c`) are documentation-only (the architecture-doc set). No completion %s moved. Recommended next increment stands: **marketplace vendor stores/profiles** (the multi-vendor gap). |
| 2026-06-28 | **Vendor stores shipped** (marketplace 65% → 72%; +2 models / +3 migrations / +2 pages / +2 tests → 31/43/42/31). Multi-vendor core: `vendors` + `vendor_profiles` (tenant-scoped, soft-deletes, `tenant_id` nullable per the business-table convention), `Vendor`/`VendorProfile` models, `VendorService` (register/updateProfile/verify/suspend — transaction + `ActivityLog` + `VendorRegistered` event), `VendorPolicy` (owner/admin), `products.vendor_id` attribution, public `StoreController` → `store/show`, `Workspace\VendorController` → `workspace/vendor/edit` (open + manage own store), `User::vendor()`, product→store backlink + `product.sold_by` i18n ×4, sidebar "My store". Verified: MySQL migrate green; real-DB smoke test (register/unique-slug/attribution/profile/verify/suspend) pass; live `GET /store/pixelforge` → 200 with correct data, unknown → 404; build + tsc + eslint clean. PHPUnit `VendorStoreTest` (6) + `VendorProfileTest` (8) written (run in CI — local `pdo_sqlite` absent). Next: admin vendor approval/moderation, then vendor payouts. |
| 2026-09-18 | **Fix: `FulfillOrder` ran twice per paid order** (no %s moved; +1 test file → 32). It was registered both explicitly (`Event::listen(PaymentCompleted::class, FulfillOrder::class)` in `AppServiceProvider::boot`) and by Laravel's default event discovery of `app/Listeners` — `event:list --event=PaymentCompleted` showed `FulfillOrder` *and* `FulfillOrder@handle`. Idempotency guards prevented double grants, but work was doubled and would double-grant if those guards regressed. Removed the explicit registration (discovery is the single wiring path; noted on the `FulfillOrder` docblock). Added `Payments\ListenerRegistrationTest` asserting exactly one `FulfillOrder` listener on `PaymentCompleted` (fails at 2 against the old provider). Verified: `event:list` → only `FulfillOrder@handle`; full PHPUnit suite green locally (235 tests); Pint clean. |
| 2026-09-19 | **About page shipped** (+1 page / +1 test file → 33 tests). The footer "About" link (`من نحن` / `À propos`) was a `#` placeholder with no page behind it. Added `Route::inertia('about', 'about')->name('about')` + `pages/about.tsx` on `StorefrontLayout` (hero, mission, values, product types — reusing the existing `product_types.*` keys — and a browse/sell CTA), a new `about.*` i18n block × 4 locales (en/fr/es/ar, RTL verified), and pointed the footer link at `route('about')`. `Marketplace\AboutPageTest` (3): guest render, Arabic locale/RTL, and en/fr/es/ar key parity for `about.*` (no-fallback lookup, so a missing locale block fails). Verified in browser: footer link → `/about` in fr and ar, no console errors, no horizontal overflow at 375px. Full PHPUnit suite green (238); Pint/ESLint/Prettier clean on new files. The other footer links (blog, contact, legal, docs, etc.) are still `#` placeholders. |
| 2026-09-21 | **Blog shipped** (+6 pages / +2 test files → 35; +2 controllers / +2 form requests). The footer "Blog" link (`المدونة`) was a `#` placeholder; the `blog_posts` table, `BlogPost` model and `BlogPostFactory` already existed (and `DatabaseSeeder` seeded 6+2 posts) but nothing was wired above them. Public: `BlogController` → `/blog` (published-only listing, search + JSON-column tag filter, paginated 9) and `/blog/{post:slug}` (404 for drafts *and* future-dated posts, view counter, tag-matched related posts); `blog/index` + `blog/show` pages on `StorefrontLayout`; `blog.*` i18n ×4 (RTL verified). Body is Markdown rendered server-side via `Str::markdown` with `html_input=strip` + `allow_unsafe_links=false`, so author content cannot inject markup — covered by a test asserting `<script>` and `javascript:` are stripped. Admin: `Admin\BlogPostController` resource (`admin/blog-posts`, except show) + `Store`/`UpdateBlogPostRequest` sharing a `NormalizesBlogPostInput` trait (slug defaulting, comma-or-array tags deduped, publish-date auto-fill on publish), 4 admin pages + "Blog" sidebar entry. Model gained `scopePublished()` / `isPublished()`. Tests: `Blog\BlogPageTest` (7) + `Admin\BlogPostManagementTest` (9). Verified: full suite green (254 tests, 1021 assertions); Pint/ESLint/Prettier/tsc clean on new files; `npm run build` green; browser — index lists 6 seeded posts, tag filter 6→4, post page renders with related posts, fr + ar/RTL, no console errors, no overflow at 375px. Remaining `#` placeholders: contact, legal (terms/privacy/license/refunds), docs, guides, api, status, changelog, roadmap. |
