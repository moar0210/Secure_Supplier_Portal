# Supplier Portal (Thesis 2026)

A small PHP/MariaDB supplier portal that runs on a local LAMP stack. Built on XAMPP/Windows for development, targeted at Raspberry Pi for deployment. It handles supplier login, company profile with logo upload, ad CRUD with admin approval, monthly invoicing with PDF export, payment tracking, and per-supplier stats. Sensitive profile fields are encrypted at rest with libsodium.

No framework. Routing is a single `public/index.php` that dispatches on `?page=`. Controllers and services sit under `app/lib`, views are plain PHP in `app/pages`, and all DB access goes through PDO with prepared statements.

## Requirements

- PHP 8.1+ with `sodium`, `pdo_mysql`, and `curl` enabled
- MariaDB 10.4+ (MySQL 8 also works)
- Apache; `mod_rewrite` is not required

On XAMPP/Windows you may need to uncomment `extension=sodium` in `C:\xampp\php\php.ini`. Check with `php -m | findstr sodium` (or `grep` elsewhere).

## Directory layout

```
app/
  config/        config.example.php + config.local.php (gitignored)
  lib/           controllers, services, Crypto, Auth, PortalLogger, etc.
  pages/         view_*.php templates rendered via View.php
  scripts/       CLI: backfill, tests, benchmark, invoicing
  storage/       logo uploads and runtime caches (denied to direct HTTP)
database/
  migrations/    000..010, run in order
  seeds/         optional demo users + role seed
public/
  index.php      single entry point, routes by ?page=...
  portal.js      small JS helper
docs/            project specs (not source)
```

## Setup

1. **Enable sodium** and confirm with `php -m`.

2. **Create the database.** Any name works; a user with read/write/DDL on it is enough. The example config uses `gpp4_0_sv_accounting`.

3. **Copy the config:**
   ```
   cp app/config/config.example.php app/config/config.local.php
   ```
   Fill in DB credentials. `crypto.enabled` is `true` by default, so the app will refuse to boot until `SUPPLIER_PORTAL_KEY_V1` is set (next step).

4. **Generate an encryption key:**
   ```powershell
   C:\xampp\php\php.exe -r "echo rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=') . PHP_EOL;"
   ```
   Set the output as `SUPPLIER_PORTAL_KEY_V1` in the environment Apache/PHP runs under. Don't commit it. If you lose it, the encrypted profile data is gone for good.

5. **Run migrations 000-010 in numeric order:**
   ```powershell
   Get-ChildItem database\migrations\*.sql | Sort-Object Name | ForEach-Object {
     C:\xampp\mysql\bin\mysql.exe -u USER -p DB < $_.FullName
   }
   ```

6. **Seed demo accounts (optional):**
   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u USER -p DB < database\seeds\001_portal_auth_seed.sql
   C:\xampp\mysql\bin\mysql.exe -u USER -p DB < database\seeds\002_portal_demo_users_seed.sql
   ```
   You get `admintest` (ADMIN) and `suppliertest` (SUPPLIER, linked to supplier id 23). The demo seed creates supplier id 23 if it is missing, so a fresh database works without any manual supplier row. Both passwords are `password` and flagged `must_change_password = 1`.

7. **Point a vhost at `public/`** or drop the project under `htdocs` and visit `http://localhost/supplier-portal-thesis-2026/public/`. The repository includes Apache `.htaccess` deny rules for `app/`, `database/`, and `tmp/`; keep `AllowOverride` enabled if using the broader `htdocs` layout.

## What's in the portal

### Admin

- **Suppliers** — list with user/ad counts. New suppliers are created inactive; activate from the detail page after reviewing.
- **Users** — create/edit admins and supplier users, assign roles, link to a company, toggle active, reset passwords. Filters for search, role, status, and supplier.
- **Ads Queue** — approve, reject with a reason, or send back to draft. Status history is persisted.
- **Categories** — CRUD for ad categories.
- **Reports** — platform dashboard for a selectable date range: supplier/user/ad/invoice counts, visibility totals and CTR, top ads, top suppliers, and the 20 most recent activity-log entries.
- **Invoices** — filter by status, supplier, billing month. Per-invoice view shows lines, totals, status, and history. Mark sent, record a payment, or delete drafts.
- **Pricing Rules** — CRUD. A rule combines a per-ad price, a monthly subscription fee, and an optional labelled service fee, each with a VAT rate and an effective-from date. Monthly generation picks the most recent active rule for the billing month.
- **DB Test** and **Security Check** — developer tools. The security check shows encryption metadata (enabled flag, driver, active key id, configured key count) but never ciphertext or key material.

### Supplier

- **My Profile** — company data, address, phone, logo upload/replace/remove.
- **Company Users** — manage additional users under the same supplier.
- **My Ads** — list/create/edit/toggle/delete the supplier's own ads. Price model is one of: fixed discount, layer discount, free gift, price list, custom offer. Plus free-text offer details, a category, and an optional validity window. Ads are submitted for approval; once approved they can be toggled active.
- **Statistics** — impressions, clicks, CTR, per-ad breakdown, and a day/month bar chart over a selectable date range. Traffic comes from the Shop API.
- **My Invoices** — read-only, with PDF download.

Suppliers never see other suppliers' data. Access checks run in the controllers through `Auth::requireRole()`, and supplier-id ownership checks run in the services.

### Consumer-facing catalogue

The portal does not render ads to end consumers. That's the `hedvc.com` shop team's job. The spec mentions this only once: "This portal will be developed as a web application using PHP and MariaDB, which will be later connected in the site www.hedvc.com." Rendering approved ads for visitors lives in the shop frontend, not here.

What this portal does is the management side: supplier CRUD, the ad workflow (draft → pending → approved/rejected), pricing rules, invoicing, and statistics. Consumer traffic reads the data read-only through the JSON API below.

## Shop JSON API

A small read-only JSON API so the external shop frontend can consume approved ads without scraping. This is the optional "REST API for mobile app integration" from Section 9 of the spec, kept minimal: no writes, no auth endpoint, no user or invoice data.

All endpoints are `GET`, return `application/json`, and live under the same `?page=` entry point as the rest of the portal. Listing ads records an impression per ad; fetching a single ad records a click. Clients that only want to read (or poll without skewing stats) can pass `&track=0`. Tracking writes are also rate-limited per IP+User-Agent via `api.track_min_interval_seconds` (default 30s).

### Endpoints

| Method + route | Purpose |
|---|---|
| `GET /?page=api_shop_ads` | List approved, active, in-window ads. Optional `search=<text>`, `category_id=<int>`. Records impressions unless `track=0`. |
| `GET /?page=api_shop_ad&id=<int>` | Single ad detail. Records a click unless `track=0`. Returns `404` if missing or not publicly visible. |
| `GET /?page=api_shop_categories` | All ad categories. |
| `GET /?page=api_shop_supplier_logo&id=<int>` | Supplier logo binary (PNG/JPG/WebP) with the upload's MIME type. `404` if absent. |

### Example response

`GET /?page=api_shop_ads`:

```json
{
  "filters": { "search": "", "category_id": null },
  "count": 2,
  "ads": [
    {
      "id": 42,
      "title": "Spring wellness package",
      "description": "...",
      "category": { "id": 3, "name": "Health" },
      "supplier": {
        "id": 12,
        "name": "Example Clinic AB",
        "homepage": "https://example.com",
        "logo_url": "?page=api_shop_supplier_logo&id=12&v=2026-04-10%2012%3A04%3A55"
      },
      "price": { "model": "FIXED_DISCOUNT", "model_label": "Fixed discount", "text": "20% off" },
      "validity": { "from": "2026-04-01", "to": "2026-06-30" },
      "updated_at": "2026-04-10 12:04:55"
    }
  ]
}
```

Errors are always JSON:

```json
{ "error": { "status": 404, "message": "Advertisement not found." } }
```

### Consuming it

```js
const PORTAL = 'https://portal.example.com';

async function loadShop() {
  const res = await fetch(`${PORTAL}/?page=api_shop_ads&track=0`);
  const { ads } = await res.json();
  // render ads; each has .title, .description, .supplier.logo_url, .price.text, etc.
}

async function openAd(id) {
  const res = await fetch(`${PORTAL}/?page=api_shop_ad&id=${id}`);
  const { ad } = await res.json();
  // this one DOES record a click; call it when the user actually opens the detail view
}
```

`logo_url` is relative. Prefix with the portal origin, or set `portal.base_url` in `config.local.php` so the API returns absolute URLs.

### CORS

```php
'api' => [
    'cors_allowed_origins' => ['*'],                                 // public catalogue
    // 'cors_allowed_origins' => ['https://hedvc.com', 'https://www.hedvc.com'],
],
```

When a concrete list is set, only matching `Origin` headers are echoed in `Access-Control-Allow-Origin`, with `Vary: Origin` for caches. `OPTIONS` preflights are handled automatically.

### What the API deliberately does not do

No writes, no auth, no user or invoice data. No pagination (catalogue volume is small in the thesis scope; add `limit`/`offset` if it grows). No rate limiting on the reads; in production, put the portal behind a reverse proxy.

## Encryption at rest

Sensitive supplier-profile PII is encrypted before it hits MariaDB and decrypted on read. Auth data, ads, invoice metadata, routing, and joins stay in plaintext.

**Encrypted:**

- `suppliers.email`, `suppliers.unique_id`
- `persons.first_name`, `persons.last_name`, `persons.full_name`, `persons.email_address`
- `addresses.description`, `addresses.complement`, `addresses.city`, `addresses.region`, `addresses.postal_code`
- `phones_entities.country_prefix`, `phones_entities.area_code`, `phones_entities.phone_number`

The shared `persons`, `addresses`, and `phones_entities` tables are only encrypted for supplier-linked rows; unrelated rows stay untouched.

**Plaintext** (compatibility, referential integrity, or not PII): primary keys, foreign keys, UUID link fields, timestamps, `suppliers.supplier_name/short_name/country_code_ISO2/homepage`, `addresses.website`, portal user credentials and auth tables, ads and categories, invoice amounts/lines/status/numbering.

Invoice supplier snapshots use the same crypto module, so historical PDFs still render without exposing the data at rest.

### Crypto design

- Driver: libsodium `XChaCha20-Poly1305-IETF` (AEAD)
- Key size: 32 bytes; nonce: random 24 bytes per encryption
- AAD: stable field-specific strings like `suppliers.email` or `addresses.city`
- Envelope: `enc:<key_id>:<base64url(nonce||ciphertext)>`

If encryption is enabled but sodium, the key id, or the key material is missing or invalid, the app refuses to boot instead of silently writing plaintext.

### Keys

Keys live outside the repo; environment variables or local config only. `crypto.active_key_id` picks the key used for new writes. Older key ids can stay configured during a rotation so existing ciphertext still decrypts. If the active key changes, older rows are only readable while their key is still listed. Lose every configured key and the encrypted data is unrecoverable, so back them up separately from code and database dumps. Rotation is a manual, documented procedure; there is no automatic re-encryption worker.

### Backfilling plaintext rows

If the DB already contains supplier data in plaintext (e.g. after importing the initial SQL dump):

```powershell
C:\xampp\php\php.exe -d extension=sodium app\scripts\backfill_profile_encryption.php
```

Idempotent: it skips `NULL`/empty values, detects already-encrypted values, and fails loudly on bad config or ciphertext. Safe to re-run.

## CLI tools

### Monthly invoice generation

```powershell
C:\xampp\php\php.exe app\scripts\generate_monthly_invoices.php 2026-04
```

Optional second argument is the admin user id to attribute the run to; if omitted, the script picks the first active admin.

Idempotent for drafts: re-running for the same month updates existing drafts instead of duplicating them, and removes drafts that have no billable ads (unless the pricing rule has a subscription or service fee, in which case the draft stays alive with the fixed-fee lines).

### Verification scripts

Four end-to-end scripts under `app/scripts`. Each one creates the rows it needs, runs assertions, prints PASS/FAIL per check, and cleans up after itself. Safe to run against the same DB the app uses.

Encryption round-trip + read/write paths:
```powershell
C:\xampp\php\php.exe -d extension=sodium app\scripts\test_profile_encryption.php
```
Covers encrypt/decrypt, tamper detection, wrong-key failure, already-encrypted detection, null/empty handling, backfill idempotence, and the supplier profile read/write paths with encrypted DB values.

Invoicing:
```powershell
C:\xampp\php\php.exe app\scripts\test_invoicing.php
```
Covers monthly draft generation, idempotent regeneration, stale draft cleanup, recurring subscription and service-fee lines, SENT transition, manual payment recording, and PDF generation.

Portal completion (supplier lifecycle, users, shop-API visibility, stats, invoice deletion):
```powershell
C:\xampp\php\php.exe app\scripts\test_portal_completion.php
```
Covers creating a supplier through the service, approval toggling, supplier-scoped company user CRUD, admin-side portal user CRUD, shop-API visibility for approved active ads, impression/click aggregation, the admin report payload, portal activity log persistence, and draft invoice deletion.

Authentication flows (forced rotation, password reset, admin vs self-service password changes):
```powershell
C:\xampp\php\php.exe app\scripts\test_auth_flows.php
```
Requires migration 010 (`portal_users.must_change_password` column). Covers: new users must rotate their initial password, token-based reset clears the forced-rotation flag, admin-issued password changes re-flag the target user, self-service changes do not trigger another forced rotation.

### Benchmark

```powershell
C:\xampp\php\php.exe -d extension=sodium app\scripts\benchmark_profile_encryption.php
```

Wall-clock latency and memory for repeated encrypt/decrypt, profile reads, and profile writes. Creates temporary supplier-linked rows and cleans them up. CPU analysis belongs in the evaluation discussion, not the app itself.

## Migrations at a glance

| File | Summary |
|---|---|
| `000_legacy_core_schema.sql` | Minimal supplier, address, contact, phone, and address-link tables required for a clean install without an external legacy dump |
| `001_portal_auth.sql` | Portal user, role, user_role tables; sessions and reset tokens |
| `002_ads.sql` | Ads, categories, ad status history |
| `003_portal_security.sql` | Lockout counters, failed-login tracking, security columns |
| `004_supplier_phone_optional_codes.sql` | Makes phone country prefix/area code optional |
| `005_profile_encryption.sql` | Widens encrypted columns, switches phone fields to string, drops the legacy phone uniqueness index (AEAD ciphertext is random) |
| `006_invoicing.sql` | Pricing rules, invoices, lines, payments, status history, numbering sequences, ad activation history |
| `007_supplier_logos.sql` | Logo metadata (files served only through controlled PHP routes) |
| `008_portal_completion.sql` | Re-asserts encrypted phone sizes, adds `portal_activity_logs` and `ad_daily_stats` |
| `009_pricing_completion.sql` | Adds `ads.price_model_type`, pricing-rule subscription/service fees, invoice line type/code, drops the ad-level unique index in favour of `line_code` |
| `010_handover_hardening.sql` | Converts `verification_token` to InnoDB, adds `portal_users.must_change_password` for forced rotation |

## Notes and gotchas

- Validation runs on plaintext input before encryption; decryption happens right after a DB read. Views never see ciphertext.
- Existing plaintext rows still load correctly until the backfill is run, so enabling encryption on an existing DB does not break reads.
- The admin Security Check page only shows safe metadata; no keys, ciphertext, or decrypted data.
- The repo does not commit a real encryption key. `app/config/config.local.php` is gitignored.
- Logo uploads go under `app/storage` and are served only through controlled PHP routes. In the fallback XAMPP `htdocs` layout, Apache `.htaccess` files deny direct HTTP access to the raw storage folder. PNG/JPG/WebP, up to 2 MB. File type is validated by both MIME and image-data inspection.
- Vendored TCPDF lives in `app/vendor/tcpdf`; the current handoff copy is 6.11.2. See `app/vendor/README.md` before updating it.
- Activity-log writes go to both `error_log` and the `portal_activity_logs` table when the table is reachable. If the table is missing (e.g. before migration 008), the logger silently falls back to `error_log` only.
- All mutating forms include a CSRF token via `Csrf::input()` and verify it through `Csrf::verifyOrFail()`.
- Sessions are cookie-based with `HttpOnly` + `SameSite=Strict` set in `SecurityBootstrap.php`. The `secure` flag is set automatically when the portal runs over HTTPS.
- Password reset has no mail transport. By default (`auth.password_reset_reveal_link = false`) the reset link is **not** shown in the browser — the form only confirms that a request was created. To retrieve the one-time link, run the CLI helper from the server:
  ```
  C:\xampp\php\php.exe app\scripts\create_password_reset_link.php <username-or-email>
  ```
  For a local offline demo you can opt in to showing the link in the browser by setting `auth.password_reset_reveal_link = true` in `config.local.php`; the link will then only be revealed when the request originates from `127.0.0.1` / `::1`. A real deployment should wire up SMTP instead. The token flow itself is production-shaped: sha256-hashed token, 60-minute expiry, single-use.
- Seeded demo accounts ship with `must_change_password = 1`. The portal redirects them to `?page=change_password` on first login and blocks every other route until a new password is chosen. Do the same for any account you create via SQL.
- Shop API CORS defaults to an empty allowlist (deny cross-origin). Set `api.cors_allowed_origins` to a concrete list or `["*"]` to reopen.
- Shop API impression/click tracking is rate-limited per IP + User-Agent via `api.track_min_interval_seconds` (default 30s) to prevent stat inflation.
