# Supplier Portal Thesis 2026

Lightweight PHP + MariaDB supplier portal built for local/offline-first use. The current implementation uses a simple MVC-ish structure with PDO, role-based access, CSRF protection, supplier profile management, advertisement CRUD, and admin review tools.

## Scope

This repository implements the practical thesis interpretation of profile encryption:

- Sensitive supplier-profile PII is encrypted at rest at the application layer.
- Auth data, ads, routing, joins, ownership checks, and admin review stay compatible with the existing schema.
- This phase does not add invoicing, payment tracking, or PDF export.
- This phase does not attempt blanket encryption of every database column in the legacy schema.

## Encrypted At Rest

These supplier-profile fields are encrypted before being written to MariaDB and decrypted after reads:

- `suppliers.email`
- `suppliers.unique_id`
- `persons.first_name`
- `persons.last_name`
- `persons.full_name`
- `persons.email_address`
- `addresses.description`
- `addresses.complement`
- `addresses.city`
- `addresses.region`
- `addresses.postal_code`
- `phones_entities.country_prefix`
- `phones_entities.area_code`
- `phones_entities.phone_number`

The backfill script intentionally targets supplier-linked rows only for the shared `persons`, `addresses`, and `phones_entities` tables.

## Left In Plaintext

These values remain plaintext to preserve compatibility and scope:

- Primary keys, foreign keys, UUID link fields, timestamps
- `suppliers.supplier_name`
- `suppliers.short_name`
- `suppliers.country_code_ISO2`
- `suppliers.homepage`
- `addresses.website`
- Portal user credentials and auth tables
- Reset and verification tokens
- Roles and role mappings
- Ads, categories, and ad status history
- Anything related to invoices, payments, or PDF export

## Encryption Design

- Driver: libsodium `XChaCha20-Poly1305-IETF`
- Key size: 32 bytes
- Nonce: random 24-byte nonce per encryption
- Authenticated encryption: AEAD
- AAD: stable field-specific strings such as `suppliers.email` and `addresses.city`
- Envelope format: `enc:<key_id>:<base64url(nonce||ciphertext)>`

If encryption is enabled but the sodium extension, key id, or key material is missing or invalid, the application fails clearly during bootstrap.

## Configuration

1. Enable the sodium extension in the PHP runtime used by the app.
   On the XAMPP setup used during development, uncomment `extension=sodium` in `C:\xampp\php\php.ini`.
2. Copy [app/config/config.example.php](/C:/xampp/htdocs/supplier-portal-thesis-2026/app/config/config.example.php) to `app/config/config.local.php` if you have not already done so.
3. Set `crypto.enabled` to `true` when you are ready to enforce encryption.
4. Provide the active key through an environment variable.
5. When `crypto.enabled` is `false`, new sensitive profile writes are not protected at rest.

Example config:

```php
'crypto' => [
    'enabled' => true,
    'active_key_id' => 'v1',
    'keys' => [
        'v1' => getenv('SUPPLIER_PORTAL_KEY_V1') ?: '',
    ],
],
```

Recommended key generation command:

```powershell
C:\xampp\php\php.exe -r "echo rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=') . PHP_EOL;"
```

Then export that value as `SUPPLIER_PORTAL_KEY_V1` in the shell or environment used by Apache/PHP.

## Key Management Strategy

- Store keys outside the repository, through environment variables or local config only.
- Select the active write key with `crypto.active_key_id`.
- New writes always use the active key.
- Older key ids may stay configured temporarily so existing ciphertext can still decrypt during rotation.
- If you change the active key, older data stays decryptable only while the previous key remains configured.
- If all configured keys are lost, encrypted supplier-profile data is unrecoverable.
- Back up the encryption key separately from the application code and the database backup.
- Keep the key in a secure offline or admin-controlled location, never in Git.
- For this thesis project, key rotation is a documented operational procedure. Automatic full re-encryption is not required.

## Migration

Run the new migration after the previous ones:

```powershell
C:\xampp\mysql\bin\mysql.exe -u YOUR_DB_USER -p YOUR_DB_NAME < database\migrations\005_profile_encryption.sql
```

What the migration does:

- Widens encrypted target columns so versioned ciphertext fits safely
- Changes supplier phone code/number fields to string columns
- Updates generated columns that would otherwise be too small for ciphertext
- Removes the legacy phone uniqueness index that no longer makes sense with randomized AEAD ciphertext

## Backfill Existing Plaintext Rows

The backfill is idempotent and safe to run multiple times. It:

- skips `NULL` and empty values
- validates and skips already encrypted values
- encrypts legacy plaintext values
- reports counts per table/column
- fails loudly if config or ciphertext is invalid

Run:

```powershell
C:\xampp\php\php.exe -d extension=sodium app\scripts\backfill_profile_encryption.php
```

## Verification Script

The verification script covers:

- encryption/decryption round trips
- tamper detection
- wrong or missing key failures
- encrypted value detection
- null/empty handling
- backfill idempotence
- supplier profile read/write behavior with encrypted database values

Run:

```powershell
C:\xampp\php\php.exe -d extension=sodium app\scripts\test_profile_encryption.php
```

The script inserts temporary supplier-linked test rows and cleans them up afterward.

## Benchmark Script

The benchmark script measures:

- repeated field-level encrypt/decrypt operations
- repeated supplier profile read path calls
- repeated supplier profile update path calls
- wall-clock latency
- memory usage and peak memory

Run:

```powershell
C:\xampp\php\php.exe -d extension=sodium app\scripts\benchmark_profile_encryption.php
```

It prints real timings from the current environment, creates temporary supplier-linked benchmark rows, and cleans them up afterward.
It currently reports wall-clock latency plus memory usage and peak memory.
CPU-level analysis belongs in the thesis evaluation discussion, not in the runtime app itself.

## Admin Security Check

The admin security check page now exposes safe encryption metadata only:

- encryption enabled
- crypto driver
- active key id
- number of configured keys

It never shows actual keys, ciphertext, or decrypted supplier data.

## Notes

- Supplier profile validation still runs on plaintext input before encryption.
- Decryption happens only after database reads.
- Existing plaintext rows still load correctly until backfill is run.
- The repository does not commit a real encryption key.
