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

There are exactly four roles:

1. **`super_admin`** — Filament web panel. Accounts, approvals, economic guardrails, moderation. Not self-registered.
2. **`content_editor`** — Filament web panel, scoped to one farm. Crop-care reference content and that farm's catalog participation. Not self-registered.
3. **`farmer_seller`** — mobile seller account. **Must not self-register.** Created only by `super_admin`. Manages own listings, incoming orders, tawad, and shop profile.
4. **`buyer`** — mobile buyer account. **Can self-register** with email + password. Browses the marketplace, checks out, tracks orders, and reviews completed handovers.

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

`migrate --seed` only creates roles and permissions. It does **not** create any login. Juan, Maria, Pedro, Ana, and Ben from earlier drafts are not seeded and will fail with "These credentials do not match our records."

Create the accounts you can actually sign in with:

```bash
php artisan db:seed --class=SuperAdminSeeder
php artisan db:seed --class=SmokeTestSeeder
```

### 4. Run

```bash
php artisan serve
php artisan reverb:start
php artisan queue:work
php artisan schedule:work
```

- `php artisan serve` — API + Filament. Without it, the app and `/admin` have nothing to talk to.
- `php artisan reverb:start` — live order chat. Without it, the app falls back to 8s polling.
- `php artisan queue:work` — queued emails (OTP, password reset, low-stock). Without it, those mails sit in the `jobs` table.
- `php artisan schedule:work` — scheduled farm announcements (later the stale-order sweep). Without it, a future `starts_at` never notifies.

- API: `http://localhost:8000/api`
- Filament admin: `http://localhost:8000/admin`
- Reverb (order chat): `ws://localhost:8080` — Android emulator uses `10.0.2.2:8080`

Settings → Help & contact opens the scripted FAQ bot (no LLM).

### Logins

Seeded after `SuperAdminSeeder` and `SmokeTestSeeder`. Password is `password` for every account unless you override `SUPER_ADMIN_PASSWORD` in `.env`.

#### Filament `/admin` (`http://localhost:8000/admin`)

| Role | Name | Email | Password |
| --- | --- | --- | --- |
| `super_admin` | AniHow Super Admin | `admin@anihow.local` | `password` |
| `content_editor` | Smoke Content Editor | `smoke.editor@anihow.local` | `password` |

`super_admin` email and password can be changed with `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD` before you run `SuperAdminSeeder`. The content editor belongs to Smoke Test Farm.

#### Android app

| Role | Name | Email | Password | Notes |
| --- | --- | --- | --- | --- |
| `farmer_seller` | Smoke Seller A | `smoke.sellera@anihow.local` | `password` | Shop: Aling Nena Produce. Farm: Smoke Test Farm. |
| `farmer_seller` | Smoke Seller B | `smoke.sellerb@anihow.local` | `password` | Shop: Mang Tonyo Farm. Farm: Smoke Test Farm. |
| `buyer` | Smoke Buyer | `smoke.buyer@anihow.local` | `password` | Email already verified. |

Do not use `juan@anihow.local`, `maria.santos@anihow.local`, `pedro.reyes@anihow.local`, `ana.buyer@anihow.local`, or `ben.buyer@anihow.local`. Those seeders are leftover from the pre-rebuild schema and are not called.

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
{ "email": "smoke.buyer@anihow.local" }
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

## Deploy on a VPS

Nginx + PHP-FPM serving `public/`. HTTPS is required.

### Supervisor

Two programs, both with autorestart. Same working directory and `.env` as the app.

`queue-work` — queued emails (OTP, password reset, low-stock):

```ini
[program:anihow-queue]
command=php artisan queue:work --sleep=1 --tries=3
directory=/path/to/anihow-backend
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/anihow-queue.log
```

`reverb` — live order chat:

```ini
[program:anihow-reverb]
command=php artisan reverb:start
directory=/path/to/anihow-backend
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/anihow-reverb.log
```

### Cron

```
* * * * * cd /path/to/anihow-backend && php artisan schedule:run >> /dev/null 2>&1
```

That ticks `announcements:notify-due` (and later the stale-order sweep).

### Reverb over Nginx

Prefer a dedicated subdomain (e.g. `ws.example.com`) whose server block proxies `location /` to `127.0.0.1:${REVERB_SERVER_PORT}` with the `Upgrade` / `Connection` headers. That way both the WebSocket path (`/app`) and Laravel's HTTP publish path (`/apps`) reach Reverb.

```nginx
server {
    listen 443 ssl;
    server_name ws.example.com;

    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_pass http://127.0.0.1:8080;
    }
}
```

On a shared domain instead, proxy **both** `/app` and `/apps` the same way:

```nginx
location /app {
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_pass http://127.0.0.1:8080;
}

location /apps {
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_pass http://127.0.0.1:8080;
}
```

`REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` in the **server** `.env` are what Laravel uses to publish to Reverb. Set them to the public wss endpoint (e.g. `ws.example.com` / `443` / `https`), not `localhost`. The Android app's endpoints are set at build time (see Flutter release build below), not from this `.env`.

### Post-deploy

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan reverb:restart
```

### Environment

- `APP_ENV=production` so smoke seeders never run
- `APP_DEBUG=false`
- `MAIL_*` for Gmail SMTP
- `RATE_LIMIT_AUTH` left at `5`

## Deploy on Railway

1. Create a Railway project and attach a **MySQL** plugin. Copy `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD` into `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
2. Add the env vars from the table above. Generate `APP_KEY` (`php artisan key:generate --show`) and set `APP_URL` to the public HTTPS URL.
3. Set `LISTING_DISK=s3` and fill AWS/R2 credentials. Railway's filesystem is ephemeral — do not use `public` disk in production.
4. Set `MAIL_MAILER=smtp` and real SMTP credentials. Keep `MAIL_FROM_ADDRESS` on a domain the provider allows.
5. Deploy this repo. `railway.json` runs `php artisan migrate --force` then `php artisan serve` on `$PORT`. Trust proxies and HTTPS are enabled in production.
6. Add a **second service** (same image/repo) with start command `php artisan queue:work --sleep=1 --tries=3` so verification, password reset, and reservation emails actually send.
7. Optional demo logins: `php artisan db:seed --class=SuperAdminSeeder` then `php artisan db:seed --class=SmokeTestSeeder`. Change `SUPER_ADMIN_PASSWORD` first. `php artisan db:seed` alone does not create users.

Worker service needs the same env vars (especially `APP_KEY`, database, and mail).

## Tests

```bash
php artisan test
```

### Flutter release build

Endpoints are compile-time `--dart-define` values. Omit them for emulator defaults (`10.0.2.2:8000`, Reverb `ws` on `8080`).

```bash
flutter build apk --release \
  --dart-define=API_BASE_URL=https://api.example.com \
  --dart-define=REVERB_HOST=ws.example.com \
  --dart-define=REVERB_PORT=443 \
  --dart-define=REVERB_SCHEME=wss \
  --dart-define=REVERB_APP_KEY=...
```

### Flutter testing

Everyday (skips live API tests):

```bash
flutter test
```

Live tests need a local API and smoke accounts. Local only:

```bash
php artisan migrate:fresh --seed
flutter test --tags live --run-skipped --concurrency=1
```

See [CHANGELOG.md](CHANGELOG.md) for what each phase added.
