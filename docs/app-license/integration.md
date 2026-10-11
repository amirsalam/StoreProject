# Integration guide (developers & releases)

How the installation license is built into **Digital Script Dragon Pro**, how to
configure it, and what to do before each release.

## Components (`app/Domain/AppLicense`)

| Class | Role |
|---|---|
| `AppLicenseClient` | HTTP client for `/api/v1/licenses/{activate,verify,deactivate}`; no redirects; errors classified definitive vs temporary |
| `InstallationIdentity` / `AppLicenseStateRepository` | Installation UUID + Ed25519 key pair; one `app_license_state` row (no `tenant_id`), secret key encrypted with `APP_KEY` |
| `RequestSigner` | Signs check-ins exactly as the server verifies them |
| `AppLicenseTokenVerifier` | Verifies tokens: signature, trusted `kid`, issuer, audience `storeproject`, domain, installation, activation, `nbf` |
| `AppLicenseEvaluator` | Local verdict per request: `not_activated`, `licensed`, `grace`, `unlicensed` (memoized) |
| `AppLicenseManager` | activate, deactivate, `refresh()` (check-in), `refreshIfDue()` |
| `EnsureAppLicensed` (middleware `app.licensed`) | Enforcement on selected routes; triggers due check-ins after the response |

UI: `Admin → Installation license` (`/admin/app-license`). Commands:
`app-license:status`, `app-license:verify [--if-due]`, `app-license:deactivate [--force]`.

## Configuration

`config/app_license.php` and `.env`:

| Variable | Default | Purpose |
|---|---|---|
| `APP_LICENSE_SERVER_URL` | — | License server base URL. HTTPS required outside `local`/`testing`. **Ship a default for customers** (see release checklist) |
| `APP_LICENSE_ISSUER` | `APP_LICENSE_SERVER_URL` | Expected token issuer; set only if it differs |
| `APP_LICENSE_MAX_GRACE_DAYS` | — | Optional cap on the offline grace; can only shorten the signed value |
| `APP_LICENSE_ENFORCE` | `true` | `false` is honored **only** when `APP_ENV` is `local` or `testing` |
| `APP_VERSION` | `1.0.0` | Reported to the license server |
| `APP_URL` | — | The domain the installation is licensed for (central domain; tenant subdomains and custom domains are covered) |

Shipped in the file (not `.env`):

- `public_keys` — production **public** keys, `kid => base64`. Only public keys:
  a test fails if anything else is pasted here.
- `development_public_keys` — trusted only in `local`/`testing`.
- `product` = `storeproject`, HTTP timeouts (connect 5 s, total 10 s, 1 retry on
  connection failure), clock tolerance 300 s.

## What is protected

| Requires the license | Always open |
|---|---|
| `admin/*` except `admin/app-license*` | Storefront, products, cart, blog, contact, docs, legal pages |
| `workspace/*` (vendor selling) | Login, logout, registration, password reset, 2FA, `settings/*` |
| Starting a purchase: `checkout` (show/store), PayPal pay, CMI redirect | Finishing a purchase: confirmation, PayPal return/cancel, CMI ok/fail/callback, Stripe webhook |
| `uploads/*` (product files) | Buyers' purchases, downloads, invoices |
| Sanctum business API (`api/v1/user`, `dashboard`, `notifications`) | Product-license API `api/v1/licenses/*`, `/up` |

`tests/Feature/AppLicense/AppLicenseEnforcementTest.php` compares the whole route table
with this map: **when you add a route, decide which side it belongs to**, or the test fails.

Unlicensed responses: admins → redirect to the activation page; other users → `503`
"temporarily unavailable"; API → `403 {"error":{"code":"app_license_required"}}`.
Grace keeps everything working and shows admins a banner.

## Check-ins

- Due when activated, not checked in the last hour, and (last success > 20 h ago **or**
  token expiring within 24 h): about daily, hourly retries after a failure.
- Triggers: the scheduler (`app-license:verify --if-due`, hourly at an `APP_KEY`-derived
  minute) **and** a licensed-area request (runs after the response, cache-locked) — so it
  works on hosts without cron.
- Server refuses the license (suspended/revoked/expired) → unlicensed immediately;
  activation released/replaced → must be activated again; network/5xx/rate limit/clock/
  unverifiable answer → keep the token, grace applies.

## Release checklist (vendor)

Before packaging a Dragon Pro release for customers:

- [ ] `config/app_license.php`: set the default server URL so customers need no extra
      setting, e.g. `'server_url' => env('APP_LICENSE_SERVER_URL', 'https://license.yourdomain.com')`.
- [ ] `public_keys` contains the server's **current** production public key — and the
      **next** one when a rotation is planned (`php artisan licenses:public-keys` on the
      server).
- [ ] `development_public_keys` contains only development keys (they are ignored in
      production anyway).
- [ ] `APP_VERSION` / version shown to the server updated.
- [ ] Customer documentation includes [activation.md](activation.md) and the cron line.
- [ ] Run the tests below.

## Testing

```bash
# unit/feature tests (pdo_sqlite loaded per command; Xdebug off avoids a known 3.3.2 crash)
php -d extension=pdo_sqlite -d extension=sqlite3 -d xdebug.mode=off vendor/bin/phpunit --filter=AppLicense

# end-to-end against a running license server (29 checks, temporary SQLite databases)
php -d xdebug.mode=off tests/E2E/app-license-lifecycle.php [server-path] [server-url]
```

The test suite runs with `APP_LICENSE_ENFORCE=false` (`phpunit.xml`); enforcement tests
switch it on explicitly. For local development without a license, set
`APP_LICENSE_ENFORCE=false` in `.env` (`APP_ENV=local` only), or activate with a key
from a development license server.
