# AniHow API

Laravel REST API for a farmers' digital market hub in General Trias, Cavite.

Mobile clients are Flutter (Android). The single `super_admin` uses the Filament web panel at `/admin`.

Current scope is **Phase 5**: Phases 1–4 product APIs plus production hardening (email verification, password reset, queued notification email, rate limits, S3 listing images, Railway-ready config). Push notifications and descriptive-analytics summaries are not built yet.

## Stack

| Package | Version (installed) |
| --- | --- |
| PHP | 8.3+ (developed on 8.4) |
| Laravel | 13.x |
| MySQL | 8.x (application database) |
| Laravel Sanctum | 4.x (token auth for Flutter) |
| Spatie laravel-permission | 8.x |
| Filament | 5.x |

PHPUnit tests use in-memory SQLite (`phpunit.xml`).

## Roles

There are exactly three roles:

1. **`super_admin`** — one admin, Filament web panel. Manages all accounts, creates/verifies `farmer_seller` accounts, oversees marketplace, listings, and crop-care articles. Seeded; not self-registered.
2. **`farmer_seller`** — combined farmer + seller mobile account. **Must not self-register.** Created only by `super_admin`. Manages own listings, incoming reservations, walk-in POS sales, shop profile, and read-only crop-care articles.
3. **`buyer`** — separate mobile account. **Can self-register** with email + password. Browses the marketplace and shops, places reservations, reviews completed pickups, and keeps favorites.

## Out of scope

- Crop-cycle / growth tracking (removed)
- Push notifications (`TODO(push-notifications)` — in-app + queued email are live)
- Descriptive-analytics sales summary (deferred; reservation and POS totals are stored)

## Setup

### 1. Requirements

- PHP 8.3+ with extensions: `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `zip`
- Composer 2
- MySQL 8

### 2. Install

```bash
composer install
copy .env.example .env   # Windows
php artisan key:generate
php artisan storage:link
```

Listing images use `LISTING_DISK` (`public` locally → `storage/app/public/listings` after `storage:link`; `s3` on Railway).

### 3. Database

Create an empty MySQL database, then point `.env` at it:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=anihow
DB_USERNAME=root
DB_PASSWORD=
```

```sql
CREATE DATABASE anihow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate --seed
```

That seeds:

- Roles + permissions (`super_admin`, `farmer_seller`, `buyer`)
- One super admin
- Categories (vegetables, fruit, grains, root crops, herbs)
- Three sample farmer-sellers in General Trias, Cavite
- Sample listings (including one inactive listing)
- Sample crop-care articles
- Two sample buyers
- Sample reservations (pending, ready for pickup, completed, cancelled) and walk-in POS sales
- Shop profiles, favorites, a sample review, and in-app notifications (including a low-stock notice)

### 4. Run

```bash
php artisan serve
```

- API: `http://localhost:8000/api`
- Filament admin: `http://localhost:8000/admin`

### Super admin (seeded)

```ini
SUPER_ADMIN_EMAIL=admin@anihow.local
SUPER_ADMIN_PASSWORD=password
```

### Sample farmer-sellers (password: `password`)

| Name | Email | Location | Shop |
| --- | --- | --- | --- |
| Juan Dela Cruz | `juan@anihow.local` | San Francisco, General Trias, Cavite | Juan's Farm Stall |
| Maria Santos | `maria.santos@anihow.local` | Tejero, General Trias, Cavite | Santos Fruit Corner |
| Pedro Reyes | `pedro.reyes@anihow.local` | Navarro, General Trias, Cavite | Reyes Root Crops |

### Sample buyers (password: `password`)

| Name | Email |
| --- | --- |
| Ana Reyes | `ana.buyer@anihow.local` |
| Ben Cruz | `ben.buyer@anihow.local` |

## Auth API (Sanctum)

Send `Accept: application/json`. After login/register, send `Authorization: Bearer {token}`.

Buyer **writes** (create/cancel reservation, review, add/remove favorite) require a verified email (`403` with `"Your email address is not verified."`). Browse endpoints work before verification. Farmer-seller accounts created by admin are already verified.

| Method | Path | Who | Purpose |
| --- | --- | --- | --- |
| POST | `/api/auth/register` | guest | Buyer self-registration (sends verification email) |
| POST | `/api/auth/login` | guest | Login for all roles |
| POST | `/api/auth/logout` | authenticated | Revoke current token |
| GET | `/api/auth/user` | authenticated | Current user |
| GET | `/api/auth/email/verify/{id}/{hash}` | signed link | Verify buyer email |
| POST | `/api/auth/email/verification-notification` | authenticated | Resend verification email |
| POST | `/api/auth/forgot-password` | guest | Request password reset email |
| POST | `/api/auth/reset-password` | guest | `{ email, token, password, password_confirmation }` |
| POST | `/api/admin/farmer-sellers` | `super_admin` | Create a pre-verified farmer-seller |

Auth routes are throttled (`RATE_LIMIT_AUTH`, default 5/min). The rest of the API uses `RATE_LIMIT_API` (default 60/min).

### Verify email

The verification link is a signed API URL. Opening it marks the buyer verified. Flutter can open it in a browser or intercept it.

### Reset password

```json
POST /api/auth/forgot-password
{ "email": "ana.buyer@anihow.local" }
```

The email contains `FRONTEND_URL/reset-password?token=...&email=...`. The app then POSTs `/api/auth/reset-password`. Unknown emails still return 200 so accounts are not enumerated.

Farmer-seller create/register bodies may include optional `location` (shown on marketplace product details).

## Marketplace + listings (Phase 2)

| Method | Path | Who | Purpose |
| --- | --- | --- | --- |
| GET | `/api/categories` | authenticated | Active categories |
| GET | `/api/farmer/listings` | `farmer_seller` | Own listings only |
| POST | `/api/farmer/listings` | `farmer_seller` | Create listing (`multipart/form-data` if attaching `image`) |
| GET | `/api/farmer/listings/{listing}` | `farmer_seller` | Show own listing |
| PUT/PATCH/POST | `/api/farmer/listings/{listing}` | `farmer_seller` | Update own listing (POST is for multipart image updates) |
| DELETE | `/api/farmer/listings/{listing}` | `farmer_seller` | Delete own listing |
| PATCH | `/api/farmer/listings/{listing}/active` | `farmer_seller` | Toggle or set `is_active` |
| GET | `/api/buyer/marketplace` | `buyer` | Browse active listings (`category_id`, `search`) |
| GET | `/api/buyer/marketplace/{listing}` | `buyer` | Product detail (seller name + location) |

A farmer-seller cannot read, update, or delete another farmer's listing (403). Buyers only see active listings from active sellers (inactive listings 404). Produce is stored as **listings** (`listing_id`); there is no separate products table.

### Create listing (farmer)

`multipart/form-data`:

- `name`, `category_id`, `unit` (`kg`, `g`, `piece`, `bundle`, `sack`, `tray`, `liter`)
- `price_per_unit`, `quantity_available`
- `description` (optional), `is_active` (optional), `image` (optional, max 2 MB)

### Browse marketplace (buyer)

```
GET /api/buyer/marketplace?category_id=1&search=tomato
```

## Reservations (Phase 3)

A reservation holds items from **one** farmer-seller only. Status is `pending` → `ready_for_pickup` → `completed`, plus `cancelled`. Jumps are rejected. Stock decrements when the reservation is created and is restored on cancellation.

| Method | Path | Who | Purpose |
| --- | --- | --- | --- |
| GET | `/api/buyer/reservations` | `buyer` | Own reservations |
| POST | `/api/buyer/reservations` | `buyer` | Create from marketplace listings |
| GET | `/api/buyer/reservations/{reservation}` | `buyer` | Show own reservation |
| PATCH | `/api/buyer/reservations/{reservation}/cancel` | `buyer` | Cancel while `pending` |
| GET | `/api/farmer/reservations` | `farmer_seller` | Incoming reservations |
| GET | `/api/farmer/reservations/{reservation}` | `farmer_seller` | Show incoming reservation |
| PATCH | `/api/farmer/reservations/{reservation}/ready` | `farmer_seller` | `pending` → `ready_for_pickup` |
| PATCH | `/api/farmer/reservations/{reservation}/complete` | `farmer_seller` | `ready_for_pickup` → `completed` |
| PATCH | `/api/farmer/reservations/{reservation}/cancel` | `farmer_seller` | Cancel `pending` or `ready_for_pickup` (`reason` required) |

### Create reservation (buyer)

```json
{
  "notes": "Pickup Saturday morning",
  "items": [
    { "listing_id": 1, "quantity": 2 }
  ]
}
```

`unit_price` is captured from the listing at reservation time. Overselling and mixed-seller carts return 422.

## POS / walk-in sales (Phase 3)

POS sales are **separate** records from reservations. They are not tied to a buyer account. Stock decrements in a transaction.

| Method | Path | Who | Purpose |
| --- | --- | --- | --- |
| GET | `/api/farmer/sales` | `farmer_seller` | Own walk-in sales |
| POST | `/api/farmer/sales` | `farmer_seller` | Record a walk-in sale |
| GET | `/api/farmer/sales/{sale}` | `farmer_seller` | Show own sale |

```json
{
  "notes": "Walk-in cash",
  "items": [
    { "listing_id": 1, "quantity": 1.5 }
  ]
}
```

A farmer-seller can only sell their own listings (inactive listings are allowed at the stall). Overselling returns 422.

## Crop-care mobile (Phase 4)

Admin-authored articles only. Farmer-sellers can browse and search; they cannot create or edit from the app.

| Method | Path | Who | Purpose |
| --- | --- | --- | --- |
| GET | `/api/farmer/crop-care` | `farmer_seller` | List articles (`category_id`, `search`) |
| GET | `/api/farmer/crop-care/{article}` | `farmer_seller` | View one active article |

## In-app notifications (Phase 4)

In-app records plus queued email (no push). Created when a buyer places a reservation (farmer), when reservation status changes (buyer), and when listing stock crosses below `LOW_STOCK_THRESHOLD` (farmer). `MAIL_MAILER=log` locally; SMTP in production.

| Method | Path | Who | Purpose |
| --- | --- | --- | --- |
| GET | `/api/notifications` | authenticated | My notifications |
| GET | `/api/notifications/unread-count` | authenticated | Unread count |
| PATCH | `/api/notifications/{notification}/read` | authenticated | Mark one as read |
| POST | `/api/notifications/read-all` | authenticated | Mark all as read |

## Shop profiles, reviews, favorites (Phase 4)

| Method | Path | Who | Purpose |
| --- | --- | --- | --- |
| GET | `/api/farmer/shop` | `farmer_seller` | Own shop profile |
| PUT/PATCH | `/api/farmer/shop` | `farmer_seller` | Update `shop_name`, `bio`, `location`, `contact` |
| GET | `/api/buyer/shops` | `buyer` | Browse farmer-seller shops |
| GET | `/api/buyer/shops/{farmerSeller}` | `buyer` | Shop profile + active listings + average rating |
| GET | `/api/buyer/shops/{farmerSeller}/reviews` | `buyer` | Reviews + average rating |
| POST | `/api/buyer/reviews` | `buyer` | Review a **completed** reservation (once) |
| GET | `/api/buyer/favorites` | `buyer` | My favorite listings |
| POST | `/api/buyer/favorites` | `buyer` | `{ "listing_id": 1 }` |
| DELETE | `/api/buyer/favorites/{listing}` | `buyer` | Remove a favorite |

### Create review (buyer)

```json
{
  "reservation_id": 1,
  "rating": 5,
  "comment": "Fresh kamote, easy pickup."
}
```

## Order history (Phase 4)

| Method | Path | Who | Purpose |
| --- | --- | --- | --- |
| GET | `/api/buyer/orders` | `buyer` | Completed and cancelled reservations with items |
| GET | `/api/buyer/orders/{reservation}/receipt` | `buyer` | Receipt-style line items + total |

## Filament

`/admin` is limited to active `super_admin` users. Resources:

- **Accounts** — users, including farmer-seller `location`, `shop_name`, `bio`, `contact`
- **Categories** — marketplace categories
- **Listings** — all produce listings
- **Crop care** — title, body, optional category (not crop-cycle tracking; mobile read API is Phase 4)

## Environment

Copy `.env.example`. Important variables:

| Variable | Local | Railway / production |
| --- | --- | --- |
| `APP_KEY` | `php artisan key:generate` | generate once, store as secret |
| `APP_URL` | `http://localhost:8000` | `https://<your-app>.up.railway.app` |
| `APP_ENV` / `APP_DEBUG` | `local` / `true` | `production` / `false` |
| `FORCE_HTTPS` | `false` | `true` (also implied when `APP_ENV=production`) |
| `FRONTEND_URL` | same as app or Flutter deep-link base | password-reset landing URL |
| `DB_*` | MySQL 8 | Railway MySQL plugin |
| `MAIL_MAILER` | `log` | `smtp` |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_FROM_ADDRESS` | unused with `log` | your SMTP provider |
| `LISTING_DISK` | `public` | `s3` |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` / `AWS_BUCKET` | empty | S3-compatible bucket (R2, Tigris, AWS) |
| `AWS_ENDPOINT` / `AWS_URL` / `AWS_USE_PATH_STYLE_ENDPOINT` | empty | set for R2 / MinIO-style APIs |
| `QUEUE_CONNECTION` | `database` (run `queue:work`) | `database` + a worker service |
| `CORS_ALLOWED_ORIGINS` | `*` | your Flutter web origin(s), comma-separated |
| `RATE_LIMIT_AUTH` / `RATE_LIMIT_API` | `5` / `60` | same unless you need to tune |
| `SUPER_ADMIN_*` | seed credentials | change the password |

## Deploy on Railway

1. Create a Railway project and attach a **MySQL** plugin. Copy `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD` into `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
2. Add the env vars from the table above. Generate `APP_KEY` (`php artisan key:generate --show`) and set `APP_URL` to the public HTTPS URL.
3. Set `LISTING_DISK=s3` and fill AWS/R2 credentials. Railway's filesystem is ephemeral — do not use `public` disk in production.
4. Set `MAIL_MAILER=smtp` and real SMTP credentials. Keep `MAIL_FROM_ADDRESS` on a domain the provider allows.
5. Deploy this repo. `railway.json` runs `php artisan migrate --force` then `php artisan serve` on `$PORT`. Trust proxies and HTTPS are enabled in production.
6. Add a **second service** (same image/repo) with start command `php artisan queue:work --sleep=1 --tries=3` so verification, password reset, and reservation emails actually send.
7. Optional: `php artisan db:seed` once for demo data. Change `SUPER_ADMIN_PASSWORD` first.

Worker service needs the same env vars (especially `APP_KEY`, database, and mail).

## Tests

```bash
php artisan test
```

See [CHANGELOG.md](CHANGELOG.md) for what each phase added.
