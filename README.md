# Supplier Portal (Thesis 2026)

A small PHP + MariaDB supplier portal built to run locally on a LAMP stack (tested on XAMPP on Windows, designed to also run on Raspberry Pi). It supports the usual supplier workflows: login, company profile with logo, advertisement CRUD with admin approval, monthly invoicing with PDF export and payment tracking, visibility statistics for suppliers, and admin-wide reporting. Sensitive profile fields are encrypted at rest with libsodium.

The codebase is intentionally small. There is no framework — routing lives in `public/index.php`, controllers and services live under `app/lib`, and views are plain PHP templates in `app/pages`. Everything talks to MariaDB through PDO with prepared statements.

## Requirements

- PHP 8.1+ with the `sodium` and `pdo_mysql` extensions enabled
- MariaDB 10.4+ (MySQL 8 works too)
- Apache with mod_rewrite is not required — routing is done via `?page=` query parameters

On the XAMPP dev machine this was built on, `extension=sodium` needs to be uncommented in `C:\xampp\php\php.ini`.

## Directory layout

```
app/
  config/        config.example.php + config.local.php (gitignored)
  lib/           controllers, services, Crypto, Auth, PortalLogger, etc.
  pages/         view_*.php templates rendered via View.php
  scripts/       CLI entry points (backfill, test, benchmark, invoicing)
  storage/       logo uploads (outside the web root)
database/
  migrations/    001..009 — run in order
  seeds/         optional demo users + role seed
public/
  index.php      single entry point, routes by ?page=...
  portal.js      small JS helper
docs/            project specs (not source)
```

## Setup

1. **Enable sodium.** In `php.ini`, make sure `extension=sodium` is active. Confirm with `php -m | findstr sodium` on Windows or `php -m | grep sodium` elsewhere.

2. **Create the database.** Create an empty schema (default name in the example config is `gpp4_0_sv_accounting`, but anything works) and a user that can read/write/DDL it.

3. **Copy the config.**

   ```
   cp app/config/config.example.php app/config/config.local.php
   ```

   Fill in the DB credentials. Leave `crypto.enabled` as `false` for the first run if you just want to see the app boot; flip it to `true` once you have a key configured.

4. **Generate an encryption key** and export it:

   ```powershell
   C:\xampp\php\php.exe -r "echo rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=') . PHP_EOL;"
   ```

   Take the output and set it as `SUPPLIER_PORTAL_KEY_V1` in the environment Apache/PHP runs under. The key must never be committed; lose it and the encrypted supplier-profile data becomes unrecoverable.

5. **Run the migrations** in numeric order:

   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u YOUR_DB_USER -p YOUR_DB_NAME < database\migrations\001_portal_auth.sql
   C:\xampp\mysql\bin\mysql.exe -u YOUR_DB_USER -p YOUR_DB_NAME < database\migrations\002_ads.sql
   C:\xampp\mysql\bin\mysql.exe -u YOUR_DB_USER -p YOUR_DB_NAME < database\migrations\003_portal_security.sql
   C:\xampp\mysql\bin\mysql.exe -u YOUR_DB_USER -p YOUR_DB_NAME < database\migrations\004_supplier_phone_optional_codes.sql
   C:\xampp\mysql\bin\mysql.exe -u YOUR_DB_USER -p YOUR_DB_NAME < database\migrations\005_profile_encryption.sql
   C:\xampp\mysql\bin\mysql.exe -u YOUR_DB_USER -p YOUR_DB_NAME < database\migrations\006_invoicing.sql
   C:\xampp\mysql\bin\mysql.exe -u YOUR_DB_USER -p YOUR_DB_NAME < database\migrations\007_supplier_logos.sql
   C:\xampp\mysql\bin\mysql.exe -u YOUR_DB_USER -p YOUR_DB_NAME < database\migrations\008_portal_completion.sql
   C:\xampp\mysql\bin\mysql.exe -u YOUR_DB_USER -p YOUR_DB_NAME < database\migrations\009_pricing_completion.sql
   ```

6. **Seed a first login** (optional but useful for local work):

   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u YOUR_DB_USER -p YOUR_DB_NAME < database\seeds\001_portal_auth_seed.sql
   C:\xampp\mysql\bin\mysql.exe -u YOUR_DB_USER -p YOUR_DB_NAME < database\seeds\002_portal_demo_users_seed.sql
   ```

   This creates two accounts, both with password `password`:

   | Username | Role | Notes |
   |---|---|---|
   | `admintest` | ADMIN | full admin access |
   | `suppliertest` | SUPPLIER | linked to supplier id 23 — adjust in the seed if that id does not exist in your data |

   Change both passwords through the admin user management screen before doing anything real.

7. **Point a vhost at `public/`** (or drop the project under `htdocs` and visit `http://localhost/supplier-portal-thesis-2026/public/`). You should land on the home page. Click **Login** and sign in.

## What's in the portal

### Admin

Logged in as an ADMIN user, the top nav exposes:

- **Suppliers** — list of all suppliers with user/ad counts. `+ Create supplier` opens a form that captures company data, contact person, address, phone, and an optional logo. New suppliers are created **inactive**; the admin reviews the profile and approves/activates them from the supplier detail page.
- **Users** — portal user management. Create or edit admin and supplier users, assign roles, link supplier users to a company, toggle active state, and reset passwords. Shown with filters for search, role, status, and supplier.
- **Ads Queue** — review advertisements submitted for approval. Approve, reject with a reason, or send back to draft. Status history is persisted.
- **Categories** — CRUD for ad categories.
- **Reports** — platform-wide dashboard with supplier/user/ad/invoice counts, visibility totals and CTR, top ads and top suppliers for the selected date range, plus the 20 most recent activity-log entries.
- **Invoices** — list of all invoices, filtering by status, supplier, and billing month. Per-invoice view shows line items, totals, status, and history, and lets the admin mark sent, record a payment, or delete a draft.
- **Pricing Rules** — CRUD for pricing rules. A rule combines a per-ad price, a monthly subscription fee, and an optional labelled service fee, each with a VAT rate and an effective-from date. Monthly invoice generation picks the most recent active rule for the billing month.
- **DB Test** and **Security Check** — developer tools. The security check page exposes encryption metadata (enabled flag, driver, active key id, number of configured keys) but never ciphertext or key material.

### Supplier

Logged in as a SUPPLIER user (linked to a supplier via `portal_users.supplier_id`), the nav shows:

- **My Profile** — edit company data, address, phone, upload/replace/remove logo.
- **Company Users** — manage additional users under the same supplier.
- **My Ads** — list of this supplier's ads, with create/edit/toggle/delete. Ads support a price model (fixed discount, layer discount, free gift, price list, custom offer) plus free-text offer details, a category, and an optional validity window. Ads are submitted for approval; approved ads can be toggled active.
- **Statistics** — impressions, clicks, CTR summary, per-ad breakdown, and a day/month bar chart over a selectable date range. Data comes from marketplace traffic.
- **My Invoices** — the supplier's own invoices only, with PDF download.

Suppliers never see other suppliers' data. All access checks run in the controllers through `Auth::requireRole()` and supplier-id ownership checks in the services.

### Public marketplace

The **Marketplace** page is accessible without login and lists every approved, active advertisement whose validity window covers today. Visitors can filter by search text and category, and open a detail page for each ad. Every marketplace page view records an impression per listed ad; every detail page view records a click. Those counters roll up into `ad_daily_stats` and feed the supplier and admin dashboards.

## Encryption at rest

Sensitive supplier-profile PII is encrypted before it hits MariaDB and decrypted on read. Auth data, ads, invoice metadata, routing, and joins stay in plaintext.

**Encrypted fields:**

- `suppliers.email`, `suppliers.unique_id`
- `persons.first_name`, `persons.last_name`, `persons.full_name`, `persons.email_address`
- `addresses.description`, `addresses.complement`, `addresses.city`, `addresses.region`, `addresses.postal_code`
- `phones_entities.country_prefix`, `phones_entities.area_code`, `phones_entities.phone_number`

The shared `persons`, `addresses`, and `phones_entities` tables are only encrypted for supplier-linked rows; unrelated rows stay untouched.

**Left in plaintext** (for compatibility, referential integrity, or because they are not PII):

- Primary keys, foreign keys, UUID link fields, timestamps
- `suppliers.supplier_name`, `suppliers.short_name`, `suppliers.country_code_ISO2`, `suppliers.homepage`, `addresses.website`
- Portal user credentials, auth tables, reset/verification tokens, roles and role mappings
- Ads, categories, ad status/activation history
- Invoice amounts, line items, status fields, numbering, billing periods, payment totals

Invoice supplier snapshots use the same crypto module so historical PDFs can still be rendered without exposing the data at rest.

### Crypto design

- Driver: libsodium `XChaCha20-Poly1305-IETF`
- Key size: 32 bytes
- Nonce: random 24-byte nonce per encryption
- Authenticated encryption with AEAD
- AAD: stable field-specific strings such as `suppliers.email` or `addresses.city`
- Envelope format: `enc:<key_id>:<base64url(nonce||ciphertext)>`

If encryption is enabled but the sodium extension, key id, or key material is missing or invalid, the app refuses to boot instead of silently storing plaintext.

### Key management

- Keys live outside the repo — environment variables or local config only.
- `crypto.active_key_id` picks the key used for new writes.
- Older key ids can stay configured temporarily so existing ciphertext still decrypts during a rotation.
- If the active key changes, older rows stay readable only while the previous key is still listed.
- If all configured keys are lost, encrypted profile data is unrecoverable — back keys up separately from code and database dumps.
- For this project, key rotation is a documented manual procedure; there is no automatic re-encryption worker.

## Backfill for existing plaintext rows

If the database already contains supplier data in plaintext (for example, after importing the initial SQL dump), the backfill script walks the encrypted fields and migrates existing values in place:

```powershell
C:\xampp\php\php.exe -d extension=sodium app\scripts\backfill_profile_encryption.php
```

It is idempotent: skips `NULL`/empty values, detects and skips already-encrypted values, and fails loudly if the config or ciphertext is invalid. Safe to re-run.

## CLI: monthly invoice generation

For scheduled billing outside the web UI:

```powershell
C:\xampp\php\php.exe app\scripts\generate_monthly_invoices.php 2026-04
```

An optional second argument is the portal admin user id to attribute the run to. If it is omitted, the script picks the first active admin account.

Generation is idempotent for drafts: re-running the command for the same month updates existing drafts instead of duplicating them, and removes drafts that no longer have any billable ads (unless the pricing rule has a subscription or service fee, in which case the draft stays alive with the fixed-fee lines).

## Verification scripts

There are three end-to-end scripts under `app/scripts`. Each one creates the rows it needs, runs assertions, prints PASS/FAIL per check, and cleans up after itself. They are safe to run against the same database the app uses.

**Encryption round-trip + read/write paths:**

```powershell
C:\xampp\php\php.exe -d extension=sodium app\scripts\test_profile_encryption.php
```

Covers encryption/decryption, tamper detection, wrong-key failure, already-encrypted detection, null/empty handling, backfill idempotence, and the supplier profile read/write paths with encrypted DB values.

**Invoicing:**

```powershell
C:\xampp\php\php.exe app\scripts\test_invoicing.php
```

Covers monthly draft generation, idempotent regeneration, stale draft cleanup, recurring subscription and service-fee lines, SENT transition, manual payment recording, and PDF generation.

**Portal completion (supplier lifecycle, users, marketplace, stats, invoice deletion):**

```powershell
C:\xampp\php\php.exe app\scripts\test_portal_completion.php
```

Covers creating a supplier through the service, approval toggling, supplier-scoped company user CRUD, admin-side portal user CRUD, marketplace visibility for approved active ads, impression/click aggregation, the admin report payload, portal activity log persistence, and draft invoice deletion.

## Benchmark

```powershell
C:\xampp\php\php.exe -d extension=sodium app\scripts\benchmark_profile_encryption.php
```

Measures wall-clock latency and memory for repeated field-level encrypt/decrypt, supplier profile reads, and supplier profile writes. It creates temporary supplier-linked benchmark rows and cleans them up. It does not try to do CPU analysis — that belongs in the evaluation discussion, not in the app.

## What each migration does

| File | Summary |
|---|---|
| `001_portal_auth.sql` | Portal user, role, and user_role tables; sessions and reset tokens |
| `002_ads.sql` | Ads, categories, ad status history |
| `003_portal_security.sql` | Lockout counters, failed-login tracking, security-related columns |
| `004_supplier_phone_optional_codes.sql` | Makes phone country prefix/area code optional |
| `005_profile_encryption.sql` | Widens encrypted columns, switches phone fields to string, drops the legacy phone uniqueness index (AEAD ciphertext is random) |
| `006_invoicing.sql` | Pricing rules, invoices, invoice lines, payments, status history, numbering sequences, ad activation history |
| `007_supplier_logos.sql` | Logo metadata table (files stored outside the web root) |
| `008_portal_completion.sql` | Re-asserts encrypted phone column sizes, adds `portal_activity_logs`, adds `ad_daily_stats` |
| `009_pricing_completion.sql` | Adds `ads.price_model_type`, `pricing_rules.subscription_fee` / `optional_service_fee` / `service_fee_label`, invoice line type/code, drops the ad-level unique index in favour of line_code |

## Notes and gotchas

- Validation runs on plaintext input before encryption; decryption happens right after a DB read. Views never see ciphertext.
- Existing plaintext rows still load correctly until the backfill is run, so enabling encryption on an existing DB does not break reads.
- The admin security check page only exposes safe encryption metadata. It never displays keys, ciphertext, or decrypted supplier data.
- The repo does not commit a real encryption key. `app/config/config.local.php` is gitignored.
- Logo uploads are saved in `app/storage` (outside the web root). Allowed types: PNG, JPG, WebP, up to 2 MB. File type is validated both by MIME and by reading image data.
- Activity log writes go to both `error_log` and the `portal_activity_logs` table when the table is reachable. If the table is missing (e.g. before migration 008) the logger silently falls back to `error_log` only.
- All mutating forms include a CSRF token via `Csrf::input()` and verify it through `Csrf::verifyOrFail()`.
- Sessions are cookie-based with `HttpOnly` + `SameSite=Lax` set in `SecurityBootstrap.php`. Set `session.cookie_secure=1` in `php.ini` once the portal is served over HTTPS.
