# LGU Property Inventory Management System

PHP-based inventory application for Philippine LGUs: property items by office, PAR/ICS issuance, returns, unserviceable items, procurement-related prints, and COA-style physical count reports. Uses session auth, PDO for data access, REST-style JSON endpoints under `/api`, and **`theme/router.php`** (entered through the root **`router.php`** stub) for MVC-style routes.

**Base URL assumption in this repo:** `/lgu_invent/` (see `RewriteBase` in `.htaccess`).

---

## Features

| Area | Behaviour |
|------|-----------|
| **Inventory** | Items as SE/PPE (`property_items`), office linkage, classification from unit value thresholds in **`theme/config.php`** |
| **Issuance** | `issuance_records`; PAR vs ICS from category; printable forms |
| **Returns** | `return_records`; transfer / unserviceable flows |
| **Reports** | Inventory, COA-style physical counts, procurement printouts |
| **Forecast** | Admin **Planning → Forecast**: live 3-month demand + smart reorder text. Guide: **`docs/FORECASTING.md`** |
| **Auth** | `AuthController`, `User` model, `PermissionHelper`; session timeout (~1 hour) enforced in main shell |

---

## Requirements

- PHP 7.4+ (PDO, `pdo_mysql`, `json`, `mbstring`, sessions)
- MySQL 5.7+ / MariaDB 10.2+
- Apache with `AllowOverride All` and **mod_rewrite** enabled

---

## Quick start

1. Place the project under your docroot (e.g. `C:\xampp\htdocs\lgu_invent`).
2. Start Apache and MySQL.
3. **Database — pick one:**
   - **Automated:** open `http://localhost/lgu_invent/scripts/setup-database.php` (creates DB, core tables, sample offices).
   - **Import SQL:** create database `lgu_inventory` (utf8mb4), then import `database/lgu_inventory (1).sql`.
4. Edit **`theme/config.php`** (loaded via root **`bootstrap.php`**) if DB host/user/pass/name differ.
5. **Users:** Create accounts through **Register** on the login page (requires seeded `offices`), or insert users manually in MySQL. For local development only you may run **`scripts/setup-test-users.php`** — rotate passwords before any shared environment.
6. Open **`http://localhost/lgu_invent/`** — sign in; **admins** use the full dashboard, **department users** use **`/staff`** (procurement).

**Legacy URLs:** `/setup-database.php`, `/setup-test-users.php`, and root-level `print-*.php` still work via `.htaccess` internal rewrite to `scripts/` and `print/`.

---

## Important paths

| Path | Role |
|------|------|
| `bootstrap.php` | Defines **BASE_PATH** and loads **`theme/config.php`** (PDO, helpers, session start) |
| `theme/config.php` | Database credentials, thresholds, procurement print constants |
| `theme/router.php` | Routing for `/login`, `/staff`, `/dashboard`, logout, etc. |
| `theme/index.php` | Authenticated **admin** SPA shell (HTML + sidebar) |
| `theme/staff_portal.php` | **Department users** — procurement-only workspace (distinct UI per office theme) |
| `router.php` (root) | Thin entry — `require`s `theme/router.php` (used by `.htaccess`) |
| `index.php` (root) | Directory index — `require`s `router.php` (same rules as rewritten URLs; staff → `/staff`) |
| `theme/.htaccess` | Denies **direct** HTTP access to `theme/*.php` (files still load via PHP `require`) |
| `app/controllers/`, `app/models/`, `app/views/` | MVC layers |
| `api/*.php` | JSON endpoints (each loads `bootstrap.php`) |
| `scripts/` | One-time **setup-database.php**, **setup-test-users.php** |
| `print/` | **print-issuance.php**, **print-procurement.php**, **print-physical-count-report.php** |
| `assets/` | CSS/JS — main shell + **`staff-portal.css`** (`/lgu_invent/assets/…`) |
| `public/assets/` | Login/auth assets (e.g. `auth-modern.css`, `logo.png`) |

Logo for the modern login: `public/assets/logo.png`.

---

## API overview

Typical patterns (see each file for query/body specifics):

| Endpoint | Purpose |
|----------|---------|
| `GET/POST/PATCH … api/items.php` | Property items CRUD/filter |
| `api/offices.php` | Offices |
| `api/issuance.php` | Issuance records |
| `api/returns.php` | Returns |
| `api/unserviceable.php` | Unserviceable / disposal linkage |
| `api/dashboard.php` | Dashboard aggregates |
| Other `api/*.php` | Procurement, reorder helpers, etc. |

All endpoints expect a working DB connection; failures often return JSON with hints pointing at **`scripts/setup-database.php`**.

---

## Property classification & forms

- **SE (Semi-expendable):** unit value **below** the PPE threshold in **`theme/config.php`** — ICS-style handling.
- **PPE:** at or above threshold — PAR-style handling.
- Property numbers and form types are enforced in controllers/models and mirrored in issuance/print views.

---

## Print entry points

Opened in-browser (after login/session where required):

- `print/print-issuance.php?id={issuance_record_id}` — PAR or ICS layout.
- `print/print-procurement.php` — query params `type=pr|rfq|po|air` plus `id` (wired from procurement JS).
- `print/print-physical-count-report.php` — COA-style physical count; expects `category=SE|PPE` and optional filters.

---

## Security notes (production)

- Turn off verbose PHP errors (`theme/config.php` suppresses display for cleaner JSON callers).
- Change all seeded passwords immediately.
- Prefer HTTPS; restrict filesystem permissions; do not expose `scripts/` on public internet beyond initial provisioning if possible (IP allowlist, `.htpasswd`, or run SQL import only).

---

## Troubleshooting

- **Blank page / JSON parse errors:** ensure MySQL is running and **`theme/config.php`** matches your DB.
- **`Unexpected token '<'` in AJAX:** HTML error returned instead of JSON — DB down or fatal PHP error; check logs.
- **404 on routes:** verify `mod_rewrite` and `.htaccess`; `RewriteBase` must match your folder name (`/lgu_invent/`).
- **Stale links:** bookmarks to old root `setup-*.php` / `print-*.php` should still rewrite; prefer canonical URLs under `scripts/` and `print/`.

---

## Module summary (architecture)

- **Authentication:** `router.php` + `AuthController` + `app/models/User.php` + sessions; RBAC helpers in `app/helpers/PermissionHelper.php`; login UI `app/views/auth/login-modern.php`.
- **Inventory:** `Item` model / `ItemController`; items table with category, office, quantity, physical count fields; printable property card views under `app/views/items/`.
- **Issuance:** `Issuance` model + APIs; issuance print pipeline `includes/issuance_print_prepare.php` + PAR/ICS view partials.
- **Returns:** `ReturnController` / `ReturnModel`; links issuance and item rows.
- **Property card / PAR–ICS parity:** Formal print layouts reuse government-style tables in issuance and report views; authoritative schema is the shipped SQL (`database/lgu_inventory (1).sql`) plus any migrations you apply locally.

---

## License / use

Internal LGU use unless your organization defines otherwise.

**Version:** 2.x (PHP + API stack).
