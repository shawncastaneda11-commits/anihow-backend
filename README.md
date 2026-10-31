# AniHow

[![CI](https://github.com/shawncastaneda11-commits/anihow-backend/actions/workflows/ci.yml/badge.svg)](https://github.com/shawncastaneda11-commits/anihow-backend/actions/workflows/ci.yml)

A digital market hub for farmers in Cavite. Buyers order fresh produce and value-added products from partner farms; farmer-sellers manage listings, harvests, orders and payments; farm staff and the administrator run everything from a web CMS.

This repository holds all three parts:

- **API and CMS**: Laravel 13 REST API with a Filament 5 admin panel at `/admin`.
- **Mobile app**: Flutter (Android) in [`mobile/`](mobile).
- **Server scripts**: VPS provisioning, deploy and backup in [`scripts/vps/`](scripts/vps), plus Windows helper scripts in [`scripts/`](scripts).

Live server: `https://anihow.tech` (API at `/api`, CMS at `/admin`).

## Features

**Buyer (mobile app)**

- Sign up with a strong password and accept the Terms of Service and Privacy Policy; existing users accept again when the terms change.
- Email verification by 6-digit code; new buyers also verify a government ID (reviewed by an admin, photos deleted after review) before their first order or reservation.
- Marketplace with fresh and value-added categories, search, filters and a daily "fair mix" order; farm pages, shops, favorites and farm updates.
- Cart split by seller, checkout with pickup or delivery, cash or online payment with the seller's QR code and payment proof.
- Reservations for upcoming harvests (paid first, refunds on a shortfall), order tracking, reviews and reports.
- Chats with sellers (photos and PDFs), notifications with categories, push notifications, Filipino or English, light or dark mode, data download and account deletion.

**Farmer-seller (mobile app)**

- Accounts are created by the Super Admin with a temporary password that must be changed at first sign-in.
- Listings with a stepped form, stock bar and filters; harvest records, add or remove stock, stock history and production cost.
- Incoming orders, payment checks, refunds, walk-in sales, tawad (discount) rules and reservations.
- My Sales with date ranges, sales and harvest charts; crop-care guides, farm news and buyer chats.

**Content Editor (CMS, one farm)**

- My Farm profile (logo, cover, map pin, contact, pickup point, farm feature switches), farm photos, price guards and seller crop lists.
- Crop types, crop-care articles, announcements, FAQ answers and the farm's analytics dashboard with a yearly CSV.

**Super Admin (CMS)**

- Accounts (create, suspend, reactivate, temporary passwords), ID verifications, account deletion requests.
- Farms, crop types, listings, orders, payments, reservations, reviews, reports, moderation and analytics exports.

## Stack

| Part | Version |
| --- | --- |
| PHP | 8.4 |
| Laravel | 13 |
| Filament | 5 |
| Laravel Sanctum | 4 (token auth for the app) |
| Laravel Reverb | live chat and order updates |
| Spatie laravel-permission | 8 (roles and permissions) |
| Database | MySQL 8 in production, SQLite for local work and tests |
| Flutter | Android app in `mobile/` |
| Firebase Cloud Messaging | push notifications |

## Roles

1. **`super_admin`**: full CMS access. Not self-registered.
2. **`content_editor`**: CMS access for one farm. Created by the Super Admin; one per farm.
3. **`farmer_seller`**: mobile seller account. Created by the Super Admin only.
4. **`buyer`**: mobile buyer account. Signs up in the app.

## Run it locally (Windows)

Local testing uses its own database, so nothing you do reaches the live server.

### 1. Requirements

- PHP 8.4 with these extensions enabled in `php.ini`: `sodium`, `gd`, `intl`, `mbstring`, `fileinfo`, `zip`, `curl`, `openssl`, `pdo_sqlite` (or `pdo_mysql`). Check with `php -m`.
- Composer 2
- Flutter, Android Studio and an Android emulator

### 2. Install

```powershell
composer install
copy .env.example .env
php artisan key:generate
php artisan storage:link
```

For SQLite, set `DB_CONNECTION=sqlite` in `.env` and remove the other `DB_*` lines. For MySQL, create an empty `anihow` database and fill in `DB_*`.

```powershell
php artisan migrate
```

### 3. Load test data

```powershell
php artisan anihow:test-fixtures
```

This creates the [test accounts](#test-accounts), the client farms and the listings, orders, reservations and reports the test instruments use. It is safe to run again: anything that exists is left alone.

Other commands:

| Command | What it does |
| --- | --- |
| `php artisan anihow:test-fixtures --dry-run` | Shows what would be created, changes nothing |
| `php artisan anihow:reset-accounts --execute` | Removes every account and its data except Super Admins, and keeps the three client farms. Run without `--execute` to preview |
| `php artisan anihow:demo-data` | Six months of demo history for the client farms (accounts end in `@demo.anihow.local`) |

On the production server these commands refuse to run unless you add `--force`.

### 4. Start the server

Run each command in its own terminal tab:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
php artisan reverb:start
php artisan queue:work
php artisan schedule:work
```

| Process | Needed for |
| --- | --- |
| `serve` | The API and the CMS at `http://127.0.0.1:8000/admin` |
| `reverb:start` | Live chat and order updates |
| `queue:work` | Notifications, emails and other background jobs |
| `schedule:work` | Payment deadlines, harvest reminders, opening reservations and other timed tasks |

`scripts\start-anihow-all.cmd` starts the server, Reverb, the CMS and the emulator in one step. It does not start `queue:work` or `schedule:work`.

### 5. Run the app on the emulator

Uninstall any release build first. A release build talks to the live server and has the same package name:

```powershell
& "$env:LOCALAPPDATA\Android\Sdk\platform-tools\adb.exe" uninstall com.anihow.anihow
```

Then build a debug build. A debug build talks to your computer (`10.0.2.2:8000` from the emulator).

If the project folder name has spaces or brackets, the Android build fails with "is not recognized as an internal or external command". Map the folder to a spare drive letter first:

```powershell
subst X: "C:\path\to\anihow-backend"
$env:PUB_CACHE = "X:\mobile\.dart_tool\apk-pub-cache"
cd X:\mobile
flutter pub get
flutter run -d emulator-5554
```

When you are done, remove the drive with `subst X: /D`.

### Local differences

- Email codes are shown in the app instead of being emailed (`MAIL_MAILER=log`).
- Lock-screen push notifications do not arrive; in-app notifications work.

## Test accounts

Created by `php artisan anihow:test-fixtures`. All of them share one test password, which is kept out of this repository; ask the project lead. Accounts created during testing must follow the password rules: 8+ characters, upper and lower case, a number and a symbol.

Outside production the same command also creates Sta. Clara Farm, a test farm with an address but no map pin, and sets the crop floor prices and maximum tawad the test cases expect (Pechay, Kamatis, Kalabasa, Talong, Okra). In production it skips both.

| Email | Role | Notes |
| --- | --- | --- |
| `admin01@gmail.com` | Super Admin | CMS |
| `editor01@gmail.com` | Content Editor | CMS, PYAP Manggahan Chapter |
| `susp03@gmail.com` | Content Editor | Suspended, no farm |
| `kuyajun@gmail.com` | Farmer-seller | Kuya Jun Harvest, accepts online payment |
| `alingnena@gmail.com` | Farmer-seller | Aling Nena Produce, cash only, sells the value-added Tuyong mangga |
| `susp02@gmail.com` | Farmer-seller | Suspended |
| `buyer01@gmail.com` | Buyer | Has completed and pending orders |
| `buyer02@gmail.com` | Buyer | Has reservations and reports |
| `delete01@gmail.com` | Buyer | Has a pending account deletion request |
| `susp01@gmail.com` | Buyer | Suspended |

Accounts made before ID verification existed, including all of these, are never blocked from ordering; only new sign-ups must verify an ID.

## Build the Android app

From the project root on Windows:

```powershell
.\scripts\build-anihow-apk.cmd -Server vps
& "$env:LOCALAPPDATA\Android\Sdk\platform-tools\adb.exe" install -r dist\AniHow-vps.apk
```

The server address and Reverb settings come from `mobile/dart_defines/<server>.json`. A release build without them shows a "not connected to a server" screen. The build script handles folder names with spaces or brackets on its own.

Push notifications need `mobile/android/app/google-services.json` from Firebase. It is ignored by git; never commit it.

## Production (VPS)

The live server is an Ubuntu VPS running Nginx, PHP-FPM 8.4, MySQL 8 and Supervisor (queue worker and Reverb). Scripts in `scripts/vps/`:

| Script | Purpose |
| --- | --- |
| `provision.sh` | One-time server setup: packages, MySQL database, Nginx, Supervisor, cron |
| `deploy.sh` | Pulls `main`, installs, migrates, caches config and routes, restarts the queue and Reverb |
| `backup.sh` | Nightly MySQL dump |
| `staging-setup.sh` | One-time setup of the test copy (see below) |
| `deploy-staging.sh` | Puts `main` or any branch on the test copy |

Deploy after merging to `main`:

```bash
ssh anihow@<server> "bash /var/www/anihow/scripts/vps/deploy.sh"
```

Scheduled tasks (cron runs `php artisan schedule:run` every minute):

| Command | Purpose |
| --- | --- |
| `announcements:notify-due` | Sends scheduled farm announcements |
| `reservations:open-due` | Turns reservations into orders when a listing opens |
| `listings:harvest-upkeep` | Harvest reminders and expired stock |
| `orders:sweep-stale` | Cleans up stale orders |
| `payments:upkeep` | Payment deadlines, reminders and expiry |
| `payments:purge-proof-files` | Deletes old payment proof screenshots |
| `sanctum:prune-expired` | Removes expired sign-in tokens |

Push notifications on the server need the Firebase service-account key at the path set in `FIREBASE_CREDENTIALS`. Keep it outside the web root and out of git.

## Configuration

Copy `.env.example` to `.env`. The variables that matter most:

| Variable | Purpose |
| --- | --- |
| `APP_URL`, `APP_ENV`, `APP_DEBUG` | Site address and mode (`production` / `false` on the server) |
| `DB_*` | Database connection |
| `MAIL_*` | Gmail SMTP on the server; `MAIL_MAILER=log` locally |
| `QUEUE_CONNECTION` | `database`, with a queue worker running |
| `REVERB_*` | Live chat server; on the server, the public `wss` address |
| `LISTING_DISK` | Where listing photos are stored |
| `FIREBASE_CREDENTIALS` | Path to the Firebase service-account key for push |
| `TERMS_VERSION` | Raise it when the Terms of Service or Privacy Policy change; app users accept again |
| `REQUIRE_ID_VERIFICATION` | `true` makes new buyers verify an ID before ordering |
| `RATE_LIMIT_AUTH`, `RATE_LIMIT_API` | Requests per minute for sign-in and for the rest of the API |
| `ANIHOW_SELLER_HELP_*` | Contact details shown on the "Want to be a seller?" screen |
| `SUPER_ADMIN_*` | Account created by `SuperAdminSeeder`; set your own password |

Never commit `.env`, `google-services.json` or the Firebase key.

## Test copy (staging)

`https://staging.anihow.tech` is a second copy of AniHow on the same VPS for testing. It has its own folder (`/var/www/anihow-staging`), its own database (`anihow_staging`, whose user cannot read the live database), its own Reverb, queue worker and scheduled tasks, and `APP_ENV=staging`. Real email and push notifications work, like on the live site. Nothing done there reaches the live site or the pilot users.

The matching phone app is **AniHow Staging** (`com.anihow.anihow.staging`). It installs next to the live AniHow app.

### Set it up once

1. Add a DNS `A` record for `staging` pointing at the VPS address, and wait until it resolves.
2. In Firebase, add an Android app with the package name `com.anihow.anihow.staging`, then download `google-services.json` again into `mobile/android/app/`. The file now covers both apps.
3. On the VPS, as root:

   ```bash
   bash /var/www/anihow/scripts/vps/staging-setup.sh staging.anihow.tech
   ```

   It creates the database, copies the live `.env` with the staging values swapped in (nothing is printed), sets up HTTPS, the queue worker, Reverb and the cron line, and fills the empty database with `anihow:demo-data` and `anihow:test-fixtures`. Running it again is safe.

### Use it

```bash
# Put main, or a pull request branch before merging, on staging:
ssh anihow@<server> "bash /var/www/anihow-staging/scripts/vps/deploy-staging.sh"
ssh anihow@<server> "bash /var/www/anihow-staging/scripts/vps/deploy-staging.sh my-branch"

# Start the test data over (no --force needed on staging):
ssh anihow@<server> "cd /var/www/anihow-staging && php artisan anihow:reset-accounts --execute && php artisan anihow:test-fixtures"
```

Build and install the staging app on Windows:

```powershell
.\scripts\build-anihow-apk.cmd -Server staging
& "$env:LOCALAPPDATA\Android\Sdk\platform-tools\adb.exe" install -r dist\AniHow-staging.apk
```

## API

All endpoints are under `/api`. Send `Accept: application/json`, and after signing in send `Authorization: Bearer {token}`.

| Prefix | Who |
| --- | --- |
| `/api/auth` | Sign up, sign in, email verification, password reset, terms, ID verification, profile |
| `/api/buyer` | Cart, checkout, orders, reservations, payments, favorites, reviews |
| `/api/farmer` | Listings, stock, harvests, orders, payments, walk-in sales, analytics |
| `/api/admin` | Admin-only actions used outside the CMS |
| other `/api` routes | Marketplace, farms, shops, chats, notifications, reports, FAQ |

List every route with:

```bash
php artisan route:list --path=api
```

## Tests

```bash
php artisan test            # PHP tests (in-memory SQLite)
vendor/bin/pint --test      # PHP formatting
cd mobile
flutter analyze
flutter test
```

GitHub Actions runs all of these on every pull request.
