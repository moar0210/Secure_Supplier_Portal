# CLAUDE.md

## Project
Secure Supplier Portal — PHP 8+ / MariaDB web app in a local LAMP stack (Apache).
Custom MVC architecture (no framework). Offline-first, evaluated on Raspberry Pi.
Client: HEDVC AB (healthtech — health data management for Universal Health Coverage).

## Stack
- Backend: PHP 8+
- Database: MariaDB (PDO with prepared statements only)
- Frontend: HTML, CSS, vanilla JS (minimal)
- Server: Apache
- Encryption: PHP sodium (AEAD), key loaded from ENV
- PDF: TBD (must be Pi-viable — evaluate memory/CPU before choosing)
- Version control: Git

## Architecture (MVC)

## Hard Rules — Never Violate
- **Prepared statements only.** No string concatenation in SQL. Ever.
- **Output escaping on every variable** rendered in views (`htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`).
- **CSRF token** on every state-changing form. Validate server-side.
- **Secure sessions:** HttpOnly, SameSite=Strict, regenerate ID on login, enforce timeout.
- **RBAC check** on every protected route (Admin vs Supplier). Supplier sees only own data.
- **No CDNs, no external APIs, no cloud dependencies.** Everything bundled locally.
- **No heavy frameworks** (no Laravel, no Symfony, no Composer packages that pull large dependency trees).
- **Input validation and sanitization** on all user inputs before processing.

## Code Conventions
- PHP files: strict types (`declare(strict_types=1);`).
- Naming: PascalCase for classes, camelCase for methods/variables, snake_case for DB columns.
- Controllers return views or redirects — no business logic in controllers.
- Models handle DB queries (always via prepared statements) and return typed data.
- Views are plain PHP templates — no logic beyond loops/conditionals for display.
- One class per file. Filename matches class name.
- Meaningful commit messages: `feat(auth): add CSRF token validation`.

## Security Checklist (Reference)
- SQL injection: prepared statements (PDO::prepare + bindParam)
- XSS: htmlspecialchars on all output
- CSRF: per-session token, validated on POST/PUT/DELETE
- Password hashing: password_hash(PASSWORD_ARGON2ID) or PASSWORD_BCRYPT
- Session fixation: session_regenerate_id(true) on login
- File uploads (logo): validate MIME type, restrict size, store outside web root
- Encryption at rest: sodium_crypto_aead_xchacha20poly1305_ietf_encrypt for sensitive columns
- Key handling: load from ENV/config, never hardcode, document rotation strategy

## Modules (in priority order)
1. Authentication & RBAC (login/logout, sessions, role-based access)
2. Supplier Profile (company CRUD, logo upload, audit log)
3. Ads & Listings (supplier CRUD, admin approve/reject, tenant isolation)
4. Invoicing (monthly generation, status flow: Draft→Sent→Paid→Overdue, VAT calc)
5. PDF Export (invoice download)
6. Payment Tracking (manual admin confirmation, no gateway)
7. Encryption at Rest (sensitive columns, AEAD, benchmarks)

Statistics dashboard is OUT OF SCOPE (bonus only if time remains).

## Database Notes
- Schema derived from existing HEDVC SQL dump — only portal-relevant tables used.
- All sensitive columns (contact info, addresses) encrypted at application level.
- Index strategy: cover login queries, invoice listing, ad filtering.
- Use foreign keys and constraints. Cascade deletes only where explicitly safe.

## Pi Considerations
- Keep queries efficient — avoid N+1, use JOINs with proper indexes.
- Measure encryption overhead and document results.
- PDF lib must work within ~1GB RAM constraint.
- Test with realistic data volume (hundreds of suppliers, thousands of invoices).

## Reference Docs
See `docs/` directory for:
- Project specification (Supplier_Portal_2025_12_28.docx)
- Extended description with WBS and hour estimates
- Thesis structure requirements (Kau format)
- Roadmap and phase exit criteria