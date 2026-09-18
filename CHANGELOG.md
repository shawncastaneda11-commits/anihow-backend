# Changelog

## [0.5.0] — Phase 5 production hardening (2026-09-18)

### Auth

- Buyer self-register implements `MustVerifyEmail` and queues a verification email
- Signed `GET /api/auth/email/verify/{id}/{hash}` + `POST /api/auth/email/verification-notification`
- Buyer writes (create/cancel reservation, review, favorite mutate) require `verified` middleware
- Farmer-seller accounts created by admin stay pre-verified
- Password reset API for all roles: `POST /api/auth/forgot-password`, `POST /api/auth/reset-password` (queued mail; unknown emails do not leak accounts)

### Notifications

- In-app records unchanged
- Queued email is an additional channel on reservation created, reservation status change, and low stock (verified addresses only)
- `TODO(push-notifications)` remains — no push provider

### Abuse protection

- `throttle:auth` (default 5/min) on login, register, password reset, resend verification
- `throttle:api` (default 60/min) on the API group
- Write-endpoint ownership audit: listings, reservations, POS, shop, reviews, favorites already enforced via role middleware + policies; no gaps found to relax

### Storage / Railway

- `LISTING_DISK` (`public` local fallback, `s3` in production) for listing images
- Trust proxies, `FORCE_HTTPS` in production, CORS from `CORS_ALLOWED_ORIGINS`
- Security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS when HTTPS)
- API exceptions always JSON (`401` / `403` / `404` / `422` / `429` / `500`)
- Complete `.env.example` + `railway.json` start command

### Explicitly still deferred

- Push notifications
- Descriptive-analytics sales summary
- Crop-cycle / growth tracking

## [0.4.0] — Phase 4 crop-care mobile + trust/usability (2026-09-18)

### Crop-care mobile (read-only)

- Farmer-seller `GET /api/farmer/crop-care` (optional `category_id`, `search` on title/body)
- `GET /api/farmer/crop-care/{article}` — active admin-authored articles only
- No create/edit from mobile

### In-app notifications

- `notifications` table: `user_id`, `type`, `title`, `body`, `related_id`/`related_type`, `read_at`
- Fired on new reservation (farmer-seller), status change (buyer), and low stock (farmer-seller when `quantity_available` crosses below `LOW_STOCK_THRESHOLD`, default 5)
- `GET /api/notifications`, `GET /api/notifications/unread-count`
- `PATCH /api/notifications/{notification}/read`, `POST /api/notifications/read-all`
- TODO(email-push-notifications): in-app records only — no email or push

### Reviews

- `reviews`: buyer, farmer-seller, reservation (unique), rating 1–5, comment
- Buyer may review only a **completed** reservation, **once**
- `POST /api/buyer/reviews`
- `GET /api/buyer/shops/{farmerSeller}/reviews` (+ average rating)

### Shop profiles

- Users: `shop_name`, `bio`, `contact` (`location` already existed)
- `GET/PATCH /api/farmer/shop`
- `GET /api/buyer/shops` and `GET /api/buyer/shops/{farmerSeller}` (active listings + average rating)

### Buyer convenience

- `GET /api/buyer/orders` — completed and cancelled reservations with items
- `GET /api/buyer/orders/{reservation}/receipt` — receipt-style breakdown
- `favorites` (`buyer_id` + `listing_id`): `GET/POST /api/buyer/favorites`, `DELETE /api/buyer/favorites/{listing}`

### Seed / admin

- Sample shop profiles, favorites, one completed-reservation review, extra POS sale that crosses the low-stock threshold (so notifications already created by reservation/POS actions have real rows)
- Filament Accounts form includes shop fields

### Explicitly not in this phase

- Email / push notifications
- Descriptive-analytics sales summary
- Crop-cycle / growth tracking
- Email verification and security hardening (TODO hooks unchanged)

## [0.3.0] — Phase 3 reservations + POS (2026-09-18)

### Data

- `reservations`: `buyer_id`, `farmer_seller_id`, `status`, `total`, `notes`, cancellation fields, `ready_at` / `completed_at` / `cancelled_at`
- `reservation_items`: `listing_id` (Phase 2 listings; there is no separate products table), name/unit snapshots, `quantity`, `unit_price` captured at reservation time, `line_subtotal`
- `sales` + `sale_items`: walk-in POS, **not** tied to a buyer account; same line snapshots + `total`
- One reservation = listings from **one** farmer-seller only
- Seeders: two sample buyers, sample reservations (pending / ready / completed / cancelled), sample POS sales via the same action classes so stock stays consistent

### Reservation status

- Enum: `pending` → `ready_for_pickup` → `completed`, plus `cancelled`
- Invalid jumps are rejected (422)
- Buyer may cancel only while `pending`
- Farmer-seller may cancel `pending` or `ready_for_pickup` with a required reason
- `quantity_available` decrements on create (transaction + `lockForUpdate`); restored on cancel; overselling is rejected

### Buyer API

- `GET/POST /api/buyer/reservations`
- `GET /api/buyer/reservations/{reservation}`
- `PATCH /api/buyer/reservations/{reservation}/cancel`

### Farmer-seller API

- `GET /api/farmer/reservations`
- `GET /api/farmer/reservations/{reservation}`
- `PATCH /api/farmer/reservations/{reservation}/ready`
- `PATCH /api/farmer/reservations/{reservation}/complete`
- `PATCH /api/farmer/reservations/{reservation}/cancel` (`reason` required)

### POS API (separate from reservations)

- `GET/POST /api/farmer/sales`
- `GET /api/farmer/sales/{sale}`
- Own listings only; stock decremented in a transaction

### Explicitly not in this phase

- Descriptive-analytics sales summary endpoint and admin analytics view (records are stored so this can be added later without a schema change)
- Crop-cycle / growth tracking
- Email verification and security hardening (TODO hook unchanged)

## [0.2.0] — Phase 2 marketplace + listings (2026-09-18)

### Data

- `users.location` (nullable) for farmer-seller marketplace display
- `categories` (name, slug, description, is_active)
- `listings` owned by `farmer_seller_id`: name, category, unit, price_per_unit, quantity_available, description, image_path, is_active
- `crop_care_articles` (title, body, optional category, is_active)
- Seeders: categories, three General Trias farmer-sellers, sample listings, crop-care articles

### Farmer listing API (own records only)

- `GET/POST /api/farmer/listings`
- `GET/PUT/PATCH/POST /api/farmer/listings/{listing}` (POST for multipart image updates)
- `DELETE /api/farmer/listings/{listing}`
- `PATCH /api/farmer/listings/{listing}/active`
- Ownership enforced with `ListingPolicy` (403 if not owner)
- Single listing image on the local `public` disk

### Buyer marketplace API

- `GET /api/categories` (authenticated)
- `GET /api/buyer/marketplace` — active listings, `category_id` + `search` filters
- `GET /api/buyer/marketplace/{listing}` — detail with seller name + location
- Inactive listings and listings from inactive sellers are hidden (404 on show)

### Filament

- Categories, Listings, and Crop care resources for `super_admin`
- Accounts form includes `location`

### Explicitly not in this phase

- Reservations / orders
- POS and descriptive-analytics sales summary
- Crop-cycle / growth tracking
- Email verification and security hardening (TODO hook unchanged)

## [0.1.0] — Phase 1 foundation (2026-09-18)

Scaffolded only. Marketplace, listings, POS, and analytics are **not** implemented.

### Installed

- Laravel 13 application skeleton
- Laravel Sanctum 4 (API token auth for Flutter)
- Spatie laravel-permission 8 (roles + permissions)
- Filament 5 admin panel at `/admin` (super_admin only)

### Accounts

- `users` migration: `name`, `email`, `phone` (nullable), `password`, `is_active`, `email_verified_at` (kept as a future verification hook)
- Spatie roles: `super_admin`, `farmer_seller`, `buyer`
- Permission seeder aligned to later modules (marketplace, listings, POS, crop-care)
- Super admin seeder (`SUPER_ADMIN_*` env vars)

### Auth API

- `POST /api/auth/register` — buyer self-register (email + password). Farmer-sellers cannot self-register.
- `POST /api/auth/login` — all roles; rejects inactive accounts
- `POST /api/auth/logout` — revoke current Sanctum token
- `GET /api/auth/user` — current user (`UserResource`)
- `POST /api/admin/farmer-sellers` — super_admin creates a farmer_seller
- JSON responses, API Resources, thin controllers + action classes
- Spatie `role` middleware aliases in `bootstrap/app.php`; API route groups in `routes/api.php`

### Filament

- Admin panel branded AniHow, login only (no public registration)
- Accounts resource: create/edit users, assign `farmer_seller` or `buyer`, toggle `is_active`

### Explicitly deferred / stubbed

- `TODO(email-verification)` hook on buyer registration (no mail, no `MustVerifyEmail`)
- Security hardening deferred
- Empty module notes: `app/Modules/Marketplace`, `app/Modules/Listings`, `app/Modules/Pos`
- Empty API controller folders for those modules
- Empty `farmer` and `buyer` route groups reserved for Phase 2
- Crop-cycle / growth tracking will not be added
