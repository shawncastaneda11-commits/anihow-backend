# ALIGNMENT_REPORT

Read-only audit of the repository as it exists on disk. Reality only. Missing items are marked `NOT PRESENT` or `NOT IMPLEMENTED`. No recommendations.

## 1. Repo map

Workspace root: `C:\Users\My PC\OneDrive\Documents\New Anihow System`

### Top-level (two levels)

- `.ai/` — `NOT PRESENT`
- `.claude/` — `skills/`
- `.cursor/` — `skills/`
- `.github/` — `NOT PRESENT`
- `app/` — `Actions/`, `Enums/`, `Filament/`, `Http/`, `Mail/`, `Models/`, `Modules/`, `Notifications/`, `Policies/`, `Providers/`, `Support/`
- `bootstrap/` — `app.php`, `providers.php`, `cache/`
- `config/` — `anihow.php`, `app.php`, `auth.php`, `cache.php`, `database.php`, `filesystems.php`, `logging.php`, `mail.php`, `permission.php`, `queue.php`, `sanctum.php`, `services.php`, `session.php`, plus remaining Laravel defaults
- `database/` — `factories/`, `migrations/`, `seeders/`, `database.sqlite`
- `docs/` — `NOT PRESENT`
- `mobile/` — Flutter app: `android/`, `ios/`, `lib/`, `linux/`, `macos/`, `test/`, `web/`, `windows/`, `pubspec.yaml`, `analysis_options.yaml`
- `public/` — `index.php`, `favicon.ico`, `robots.txt`, `build/` (Vite), `storage/` (link)
- `resources/` — `css/`, `js/`, `views/`
- `routes/` — `api.php`, `console.php`, `web.php`
- `scripts/` — present
- `storage/` — `app/`, `framework/`, `logs/`
- `tests/` — `Feature/`, `Unit/`
- `vendor/` — Composer packages (not expanded)
- `AGENTS.md`, `CHANGELOG.md`, `CLAUDE.md`, `README.md`, `SYSTEM_OVERVIEW.md`, `.mcp.json`
- `artisan`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `phpunit.xml`, `pint.json`, `vite.config.js`, `railway.json`

### Frameworks and declared versions

From `composer.json`:

- PHP: `^8.3`
- `laravel/framework`: `^13.17`
- `filament/filament`: `~5.0`
- `laravel/sanctum`: `^4.3`
- `laravel/tinker`: `^3.0`
- `league/flysystem-aws-s3-v3`: `^3.35`
- `spatie/laravel-permission`: `^8.3`

From `mobile/pubspec.yaml`:

- Flutter SDK: `^3.13.3` (`environment.sdk`)
- App version: `1.0.0+1`
- `flutter`: sdk
- `flutter_lints`: `^6.0.0` (dev)

`package.json` exists (Vite frontend for Laravel). Exact Node package versions are not listed here; this section uses `composer.json` and `pubspec.yaml` only.

## 2. Database schema

Laravel `id()` is `bigint unsigned` auto-increment, not nullable. `timestamps()` are `created_at` / `updated_at` `timestamp` nullable. `foreignId(...)` is `bigint unsigned`. Unless noted, columns are not nullable.

### `0001_01_01_000000_create_users_table.php`

**Table `users`**

- `id` bigint unsigned PK
- `name` string not null
- `email` string unique not null
- `email_verified_at` timestamp nullable
- `password` string not null
- `remember_token` string nullable
- `created_at` timestamp nullable
- `updated_at` timestamp nullable

**Table `password_reset_tokens`**

- `email` string PK
- `token` string not null
- `created_at` timestamp nullable

**Table `sessions`**

- `id` string PK
- `user_id` bigint unsigned nullable, indexed (`foreignId()->nullable()->index()` — no `constrained()`, so **no FK constraint**)
- `ip_address` string(45) nullable
- `user_agent` text nullable
- `payload` longText not null
- `last_activity` integer not null, indexed

Foreign keys: none.

### `0001_01_01_000001_create_cache_table.php`

**Table `cache`**

- `key` string PK
- `value` mediumText not null
- `expiration` integer not null

**Table `cache_locks`**

- `key` string PK
- `owner` string not null
- `expiration` integer not null

Foreign keys: none.

### `0001_01_01_000002_create_jobs_table.php`

**Table `jobs`**

- `id` bigint unsigned PK
- `queue` string indexed not null
- `payload` longText not null
- `attempts` unsignedTinyInteger not null
- `reserved_at` unsignedInteger nullable
- `available_at` unsignedInteger not null
- `created_at` unsignedInteger not null

**Table `job_batches`**

- `id` string PK
- `name` string not null
- `total_jobs` integer not null
- `pending_jobs` integer not null
- `failed_jobs` integer not null
- `failed_job_ids` longText not null
- `options` mediumText nullable
- `cancelled_at` integer nullable
- `created_at` integer not null
- `finished_at` integer nullable

**Table `failed_jobs`**

- `id` bigint unsigned PK
- `uuid` string unique not null
- `connection` text not null
- `queue` text not null
- `payload` longText not null
- `exception` longText not null
- `failed_at` timestamp not null default current

Foreign keys: none.

### `2026_03_22_100000_create_permission_tables.php`

Uses `config('permission.table_names')`. `config/permission.php` has `'teams' => false`, so team columns are not added.

**Table `permissions`**

- `id` bigint unsigned PK (`config permission.primary_key` default `id`)
- `name` string not null
- `guard_name` string not null
- `created_at` / `updated_at` timestamp nullable
- unique (`name`, `guard_name`)

**Table `roles`**

- `id` bigint unsigned PK
- `name` string not null
- `guard_name` string not null
- `created_at` / `updated_at` timestamp nullable
- unique (`name`, `guard_name`)

**Table `model_has_permissions`**

- `permission_id` unsignedBigInteger not null
- `model_type` string not null
- `model_id` unsignedBigInteger not null (`column_names.model_morph_key` = `model_id`)
- index (`model_id`, `model_type`)
- PK (`permission_id`, `model_id`, `model_type`)
- FK `permission_id` → `permissions.id` `cascadeOnDelete()`

**Table `model_has_roles`**

- `role_id` unsignedBigInteger not null
- `model_type` string not null
- `model_id` unsignedBigInteger not null
- index (`model_id`, `model_type`)
- PK (`role_id`, `model_id`, `model_type`)
- FK `role_id` → `roles.id` `cascadeOnDelete()`

**Table `role_has_permissions`**

- `permission_id` unsignedBigInteger not null
- `role_id` unsignedBigInteger not null
- PK (`permission_id`, `role_id`)
- FK `permission_id` → `permissions.id` `cascadeOnDelete()`
- FK `role_id` → `roles.id` `cascadeOnDelete()`

### `2026_03_22_100100_create_personal_access_tokens_table.php`

**Table `personal_access_tokens`**

- `id` bigint unsigned PK
- `tokenable_type` string not null (`morphs('tokenable')`)
- `tokenable_id` bigint unsigned not null
- index (`tokenable_type`, `tokenable_id`)
- `name` text not null
- `token` string(64) unique not null
- `abilities` text nullable
- `expires_at` timestamp nullable, indexed
- `created_at` / `updated_at` timestamp nullable

Foreign keys: none (morph, not constrained).

### `2026_03_22_100200_add_profile_fields_to_users_table.php`

Alters `users`:

- `phone` string(32) nullable, unique, after `email`
- `is_active` boolean not null default `true`, after `phone`

### `2026_03_22_100300_create_categories_table.php`

**Table `categories`**

- `id` bigint unsigned PK
- `name` string not null
- `slug` string unique not null
- `is_active` boolean not null default `true`
- `created_at` / `updated_at` timestamp nullable

Foreign keys: none.

### `2026_03_22_100400_create_listings_table.php`

**Table `listings`**

- `id` bigint unsigned PK
- `farmer_seller_id` bigint unsigned not null
- `category_id` bigint unsigned not null
- `name` string not null
- `description` text nullable
- `unit` string not null
- `price_per_unit` decimal(12, 2) not null
- `quantity_available` decimal(12, 2) not null default `0`
- `harvested_at` date nullable
- `is_active` boolean not null default `true`
- `created_at` / `updated_at` timestamp nullable
- index (`farmer_seller_id`, `is_active`)
- index (`category_id`, `is_active`)
- FK `farmer_seller_id` → `users.id` `cascadeOnDelete()`
- FK `category_id` → `categories.id` `restrictOnDelete()`

### `2026_03_22_100500_create_crop_care_articles_table.php`

**Table `crop_care_articles`**

- `id` bigint unsigned PK
- `category_id` bigint unsigned nullable
- `title` string not null
- `body` text not null
- `is_active` boolean not null default `true`
- `created_at` / `updated_at` timestamp nullable
- FK `category_id` → `categories.id` `nullOnDelete()`

### `2026_03_22_100600_create_reservations_table.php`

**Table `reservations`**

- `id` bigint unsigned PK
- `buyer_id` bigint unsigned not null
- `farmer_seller_id` bigint unsigned not null
- `status` string not null
- `notes` text nullable
- `cancel_reason` text nullable
- `ready_at` timestamp nullable
- `completed_at` timestamp nullable
- `cancelled_at` timestamp nullable
- `created_at` / `updated_at` timestamp nullable
- index (`buyer_id`, `status`)
- index (`farmer_seller_id`, `status`)
- FK `buyer_id` → `users.id` `restrictOnDelete()`
- FK `farmer_seller_id` → `users.id` `restrictOnDelete()`

### `2026_03_22_100700_create_reservation_items_table.php`

**Table `reservation_items`**

- `id` bigint unsigned PK
- `reservation_id` bigint unsigned not null
- `listing_id` bigint unsigned not null
- `quantity` decimal(12, 2) not null
- `unit_price` decimal(12, 2) not null
- `line_total` decimal(12, 2) not null
- `created_at` / `updated_at` timestamp nullable
- FK `reservation_id` → `reservations.id` `cascadeOnDelete()`
- FK `listing_id` → `listings.id` `restrictOnDelete()`

### `2026_03_22_100800_create_sales_table.php`

**Table `sales`**

- `id` bigint unsigned PK
- `farmer_seller_id` bigint unsigned not null
- `status` string not null
- `notes` text nullable
- `created_at` / `updated_at` timestamp nullable
- index (`farmer_seller_id`, `created_at`)
- FK `farmer_seller_id` → `users.id` `restrictOnDelete()`

### `2026_03_22_100900_create_sale_items_table.php`

**Table `sale_items`**

- `id` bigint unsigned PK
- `sale_id` bigint unsigned not null
- `listing_id` bigint unsigned not null
- `quantity` decimal(12, 2) not null
- `unit_price` decimal(12, 2) not null
- `line_total` decimal(12, 2) not null
- `created_at` / `updated_at` timestamp nullable
- FK `sale_id` → `sales.id` `cascadeOnDelete()`
- FK `listing_id` → `listings.id` `restrictOnDelete()`

### `2026_03_22_101000_create_notifications_table.php`

**Table `notifications`**

- `id` bigint unsigned PK
- `user_id` bigint unsigned not null
- `type` string not null
- `title` string not null
- `body` text not null
- `related_type` string nullable (`nullableMorphs('related')`)
- `related_id` bigint unsigned nullable
- index (`related_type`, `related_id`)
- `read_at` timestamp nullable
- `created_at` / `updated_at` timestamp nullable
- index (`user_id`, `read_at`)
- FK `user_id` → `users.id` `cascadeOnDelete()`

### `2026_03_22_101100_create_reviews_table.php`

**Table `reviews`**

- `id` bigint unsigned PK
- `reservation_id` bigint unsigned unique not null
- `buyer_id` bigint unsigned not null
- `farmer_seller_id` bigint unsigned not null
- `rating` unsignedTinyInteger not null
- `comment` text nullable
- `created_at` / `updated_at` timestamp nullable
- unique (`buyer_id`, `reservation_id`)
- index (`farmer_seller_id`, `created_at`)
- FK `reservation_id` → `reservations.id` `restrictOnDelete()`
- FK `buyer_id` → `users.id` `restrictOnDelete()`
- FK `farmer_seller_id` → `users.id` `restrictOnDelete()`

### `2026_03_22_101200_create_favorites_table.php`

**Table `favorites`**

- `id` bigint unsigned PK
- `buyer_id` bigint unsigned not null
- `listing_id` bigint unsigned not null
- `created_at` / `updated_at` timestamp nullable
- unique (`buyer_id`, `listing_id`)
- FK `buyer_id` → `users.id` `cascadeOnDelete()`
- FK `listing_id` → `listings.id` `cascadeOnDelete()`

### `2026_04_04_120000_add_image_path_to_listings_table.php`

Alters `listings`:

- `image_path` string nullable after `harvested_at`

### `2026_04_10_090000_add_location_to_users_table.php`

Alters `users`:

- `location` string nullable after `phone`

### `2026_04_11_040000_add_shop_fields_to_users_table.php`

Alters `users`:

- `shop_name` string nullable after `name`
- `bio` text nullable after `location`
- `contact` string nullable after `bio`

### `2026_04_11_120000_add_created_by_to_crop_care_articles_table.php`

Alters `crop_care_articles`:

- `created_by` bigint unsigned nullable after `category_id`, indexed
- FK `created_by` → `users.id` `restrictOnDelete()`

### `2026_09_19_053850_add_image_path_to_crop_care_articles_table.php`

Alters `crop_care_articles`:

- `image_path` string nullable after `body`

### Tables in migrations with no `App\Models` class

`password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`, `personal_access_tokens`

Spatie `Role` / `Permission` exist as vendor models (`Spatie\Permission\Models\Role`, `Spatie\Permission\Models\Permission`), not under `app/Models`.

### `App\Models` with no matching migration

`NOT PRESENT` — every class in `app/Models` maps to a migrated table: `users`, `listings`, `categories`, `reservations`, `reservation_items`, `sales`, `sale_items`, `reviews`, `favorites`, `notifications`, `crop_care_articles`.

No `shops` table. Shop fields live on `users`.

## 3. Models and relationships

No model sets `$guarded`. Fillable is PHP 8 `#[Fillable([...])]` where listed.

### `app/Models/User.php`

- Table: default `users`
- Fillable: `name`, `shop_name`, `email`, `phone`, `location`, `bio`, `contact`, `password`, `is_active`
- Hidden: `password`, `remember_token`
- Casts: `email_verified_at` => `datetime`, `password` => `hashed`, `is_active` => `boolean`
- Traits: `HasFactory`, `Notifiable`, `HasApiTokens`, `HasRoles`
- Relationships:
  - `listings()` HasMany `Listing` via `farmer_seller_id`
  - `buyerReservations()` HasMany `Reservation` via `buyer_id`
  - `farmerReservations()` HasMany `Reservation` via `farmer_seller_id`
  - `sales()` HasMany `Sale` via `farmer_seller_id`
  - `favorites()` HasMany `Favorite` via `buyer_id`
  - `buyerReviews()` HasMany `Review` via `buyer_id`
  - `receivedReviews()` HasMany `Review` via `farmer_seller_id`
  - `inAppNotifications()` HasMany `InAppNotification`
  - `cropCareArticles()` HasMany `CropCareArticle` via `created_by`

### `app/Models/Listing.php`

- Table: default `listings`
- Fillable: `farmer_seller_id`, `category_id`, `name`, `description`, `unit`, `price_per_unit`, `quantity_available`, `harvested_at`, `image_path`, `is_active`
- Casts: `price_per_unit` => `decimal:2`, `quantity_available` => `decimal:2`, `harvested_at` => `date`, `is_active` => `boolean`
- Appends: `image_url`
- Relationships:
  - `farmerSeller()` BelongsTo `User` via `farmer_seller_id`
  - `category()` BelongsTo `Category`
  - `favorites()` HasMany `Favorite`
- No `reservationItems()` / `saleItems()` methods (those tables are queried elsewhere).

### `app/Models/Category.php`

- Table: default `categories`
- Fillable: `name`, `slug`, `is_active`
- Casts: `is_active` => `boolean`
- Relationships:
  - `listings()` HasMany `Listing`
  - `cropCareArticles()` HasMany `CropCareArticle`

### `app/Models/Reservation.php`

- Table: default `reservations`
- Fillable: `buyer_id`, `farmer_seller_id`, `status`, `notes`, `cancel_reason`, `ready_at`, `completed_at`, `cancelled_at`
- Casts: `status` => `ReservationStatus`, `ready_at` / `completed_at` / `cancelled_at` => `datetime`
- Relationships:
  - `buyer()` BelongsTo `User` via `buyer_id`
  - `farmerSeller()` BelongsTo `User` via `farmer_seller_id`
  - `items()` HasMany `ReservationItem`
  - `review()` HasOne `Review`

### `app/Models/ReservationItem.php`

- Table: default `reservation_items`
- Fillable: `reservation_id`, `listing_id`, `quantity`, `unit_price`, `line_total`
- Casts: `quantity` / `unit_price` / `line_total` => `decimal:2`
- Relationships:
  - `reservation()` BelongsTo `Reservation`
  - `listing()` BelongsTo `Listing`

### `app/Models/Sale.php`

- Table: default `sales`
- Fillable: `farmer_seller_id`, `status`, `notes`
- Casts: `status` => `SaleStatus`
- Relationships:
  - `farmerSeller()` BelongsTo `User` via `farmer_seller_id`
  - `items()` HasMany `SaleItem`

### `app/Models/SaleItem.php`

- Table: default `sale_items`
- Fillable: `sale_id`, `listing_id`, `quantity`, `unit_price`, `line_total`
- Casts: `quantity` / `unit_price` / `line_total` => `decimal:2`
- Relationships:
  - `sale()` BelongsTo `Sale`
  - `listing()` BelongsTo `Listing`

### `app/Models/Review.php`

- Table: default `reviews`
- Fillable: `reservation_id`, `buyer_id`, `farmer_seller_id`, `rating`, `comment`
- Casts: `NOT PRESENT` (no `casts()` method)
- Relationships:
  - `reservation()` BelongsTo `Reservation`
  - `buyer()` BelongsTo `User` via `buyer_id`
  - `farmerSeller()` BelongsTo `User` via `farmer_seller_id`

### `app/Models/Favorite.php`

- Table: default `favorites`
- Fillable: `buyer_id`, `listing_id`
- Casts: `NOT PRESENT`
- Relationships:
  - `buyer()` BelongsTo `User` via `buyer_id`
  - `listing()` BelongsTo `Listing`

### `app/Models/InAppNotification.php`

- Table: `notifications` (`protected $table = 'notifications'`)
- Fillable: `user_id`, `type`, `title`, `body`, `related_type`, `related_id`, `read_at`
- Casts: `read_at` => `datetime`
- Relationships:
  - `user()` BelongsTo `User`
  - `related()` MorphTo

### `app/Models/CropCareArticle.php`

- Table: default `crop_care_articles`
- Fillable: `category_id`, `created_by`, `title`, `body`, `image_path`, `is_active`
- Casts: `is_active` => `boolean`
- Constant: `GENERAL_CATEGORY_ID = 0`
- Appends: `image_url`
- Relationships:
  - `category()` BelongsTo `Category`
  - `author()` BelongsTo `User` via `created_by`

## 4. Roles and permissions

### Spatie roles (enum + seeder)

`app/Enums/Role.php` string-backed cases:

- `super_admin`
- `farmer_seller`
- `buyer`

`database/seeders/RolePermissionSeeder.php` `Role::findOrCreate($role->value, 'web')` for every `Role` case.

`database/seeders/SuperAdminSeeder.php` `syncRoles(Role::SuperAdmin)` on the admin user. No permission strings assigned directly to that user.

Factories: `NOT PRESENT` (no role/permission factory).

`config/permission.php` table names and `'teams' => false`. No permission names listed in config.

### Spatie permissions (enum + seeder)

`app/Enums/Permission.php` string-backed cases:

- `manage_accounts`
- `create_farmer_seller`
- `oversee_marketplace`
- `oversee_listings`
- `oversee_crop_care`
- `manage_own_listings`
- `record_pos_sales`
- `view_sales_analytics`
- `manage_crop_care`
- `browse_marketplace`
- `reserve_produce`

`RolePermissionSeeder` `Permission::findOrCreate($permission->value, 'web')` then `syncPermissions` per role:

**`super_admin`:** all `Permission` cases (the full list above).

**`farmer_seller`:** `manage_own_listings`, `record_pos_sales`, `view_sales_analytics`, `manage_crop_care`.

**`buyer`:** `browse_marketplace`, `reserve_produce`.

API authorization in controllers uses `$this->authorize(...)` against policies and `role:buyer` / `role:farmer_seller` / `role:super_admin` middleware. Policy methods check `isBuyer()` / `isFarmerSeller()` / `isSuperAdmin()`, not `hasPermissionTo()`.

### Policies

`app/Policies/CategoryPolicy.php`: `NOT PRESENT`

#### `app/Policies/ListingPolicy.php`

- `viewAny`: SuperAdmin, or FarmerSeller, or Buyer with `is_active`.
- `view`: SuperAdmin; or FarmerSeller who `isOwnedBy`; or Buyer if listing `marketplaceVisible()`.
- `create`: SuperAdmin or FarmerSeller.
- `update`: SuperAdmin or FarmerSeller who `isOwnedBy`.
- `delete`: SuperAdmin or FarmerSeller who `isOwnedBy`.

#### `app/Policies/ReservationPolicy.php`

- `viewAny`: SuperAdmin, FarmerSeller, or Buyer.
- `view`: SuperAdmin; or FarmerSeller `isOwnedByFarmer`; or Buyer `isOwnedByBuyer`.
- `create`: Buyer.
- `cancelAsBuyer`: Buyer and `isOwnedByBuyer`.
- `manageAsFarmer`: FarmerSeller and `isOwnedByFarmer`.

#### `app/Policies/SalePolicy.php`

- `viewAny`: SuperAdmin or FarmerSeller.
- `view`: SuperAdmin or FarmerSeller who `isOwnedBy`.
- `create`: FarmerSeller.
- `delete`: FarmerSeller who `isOwnedBy`.

#### `app/Policies/CropCareArticlePolicy.php`

- `viewAny`: SuperAdmin or FarmerSeller.
- `view`: SuperAdmin; or FarmerSeller if `is_active` or `created_by` is the user.
- `create`: SuperAdmin or FarmerSeller.
- `update`: SuperAdmin or FarmerSeller who `isOwnedBy`.
- `delete`: SuperAdmin or FarmerSeller who `isOwnedBy`.

#### `app/Policies/FavoritePolicy.php`

- `viewAny`: Buyer.
- `create`: Buyer.
- `delete`: Buyer who `isOwnedBy`.

#### `app/Policies/ReviewPolicy.php`

- `create`: Buyer.

#### `app/Policies/InAppNotificationPolicy.php`

- `viewAny`: authenticated user (any logged-in user).
- `view`: `isOwnedBy`.
- `update`: `isOwnedBy`.

## 5. Filament panel

### Panel provider

`app/Providers/Filament/AdminPanelProvider.php`

- Panel id: `admin`
- Path: `admin`
- Login: enabled (`->login()`)
- `discoverResources` in `app/Filament/Resources`
- `discoverPages` in `app/Filament/Pages`
- Pages registered: `\Filament\Pages\Dashboard::class`
- `discoverWidgets` in `app/Filament/Widgets`
- Widgets registered: `\Filament\Widgets\AccountWidget::class`
- Middleware: `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`, `AuthenticateSession`, `ShareErrorsFromSession`, `VerifyCsrfToken`, `SubstituteBindings`, `DisableBladeIconComponents`, `DispatchServingFilamentEvent`
- Auth middleware: `Authenticate`

`app/Filament/Pages/`: empty (0 PHP files). Custom Filament pages: `NOT PRESENT`.

`app/Filament/Widgets/`: empty (0 PHP files). Custom Filament widgets: `NOT PRESENT`.

Panel access gate: `User::canAccessPanel(Panel $panel): bool` returns `$this->is_active && $this->isSuperAdmin()`.

No resource class overrides `canViewAny` or `shouldRegisterNavigation`. No `authorize()` calls inside resource PHP besides Filament defaults.

**Roles that can reach Filament resources:** only `super_admin` with `is_active = true`. `farmer_seller` and `buyer` cannot access the panel.

### Resources

| Resource | Path | Model | Pages | Navigation label |
|---|---|---|---|---|
| `UserResource` | `app/Filament/Resources/Users/UserResource.php` | `User` | `ListUsers`, `CreateUser`, `ViewUser`, `EditUser` | Accounts |
| `CategoryResource` | `app/Filament/Resources/Categories/CategoryResource.php` | `Category` | `ListCategories`, `CreateCategory`, `EditCategory` | (default Categories) |
| `ListingResource` | `app/Filament/Resources/Listings/ListingResource.php` | `Listing` | `ListListings`, `CreateListing`, `ViewListing`, `EditListing` | (default Listings) |
| `CropCareArticleResource` | `app/Filament/Resources/CropCareArticles/CropCareArticleResource.php` | `CropCareArticle` | `ListCropCareArticles`, `CreateCropCareArticle`, `ViewCropCareArticle`, `EditCropCareArticle` | (slug `crop-care-articles`) |

`CreateUser::mutateFormDataBeforeCreate` sets `email_verified_at` to `now()`. Form roles: `farmer_seller`, `buyer` only (not `super_admin`).

Other Filament resources (Reservation, Sale, Review, Favorite, Notification): `NOT PRESENT`.

## 6. API surface

Laravel `bootstrap/app.php` registers `api:` prefix `/api`, prepends `ForceJsonResponse`, appends `throttleApi()` on the `api` middleware group. `auth:sanctum` and `role:...` are as written in `routes/api.php`.

Commented-out routes in `routes/api.php` / `routes/web.php`: `NOT PRESENT`.

### `routes/web.php`

| Method | URI | Middleware | Action | Name |
|---|---|---|---|---|
| GET | `/` | `web` | closure returning `view('welcome')` | `NOT PRESENT` |

### `routes/api.php`

Default middleware group: `api` (JSON + throttle). Additional middleware listed per group.

#### Auth (no `auth:sanctum`)

| Method | URI | Extra middleware | Controller@action | Name |
|---|---|---|---|---|
| POST | `/api/auth/register` | none | `RegisterController` `__invoke` | `auth.register` |
| POST | `/api/auth/login` | none | `LoginController` `__invoke` | `auth.login` |
| POST | `/api/auth/forgot-password` | none | `ForgotPasswordController` `__invoke` | `auth.password.forgot` |
| POST | `/api/auth/reset-password` | none | `ResetPasswordController` `__invoke` | `auth.password.reset` |
| GET | `/api/auth/email/verify/{id}/{hash}` | `signed` | `VerifyEmailController` `__invoke` | `verification.verify` |

#### Auth (`auth:sanctum`)

| Method | URI | Extra middleware | Controller@action | Name |
|---|---|---|---|---|
| POST | `/api/auth/email/verification-notification` | `auth:sanctum`, `throttle:6,1` | `ResendVerificationController` `__invoke` | `verification.send` |
| GET | `/api/auth/me` | `auth:sanctum` | `MeController` `__invoke` | `auth.me` |
| POST | `/api/auth/logout` | `auth:sanctum` | `LogoutController` `__invoke` | `auth.logout` |

#### Admin (`auth:sanctum`, `role:super_admin`)

| Method | URI | Controller@action | Name |
|---|---|---|---|
| POST | `/api/admin/farmer-sellers` | `CreateFarmerSellerController` `__invoke` | `admin.farmer-sellers.store` |

#### Marketplace (`auth:sanctum`, `role:buyer`)

| Method | URI | Controller@action | Name |
|---|---|---|---|
| GET | `/api/marketplace` | `MarketplaceController@index` | `marketplace.index` |
| GET | `/api/marketplace/{listing}` | `MarketplaceController@show` | `marketplace.show` |
| GET | `/api/categories` | `CategoryController@index` | `categories.index` |

#### Buyer reservations (`auth:sanctum`, `role:buyer`)

| Method | URI | Controller@action | Name |
|---|---|---|---|
| GET | `/api/buyer/reservations` | `BuyerReservationController@index` | `buyer.reservations.index` |
| POST | `/api/buyer/reservations` | `BuyerReservationController@store` | `buyer.reservations.store` |
| GET | `/api/buyer/reservations/{reservation}` | `BuyerReservationController@show` | `buyer.reservations.show` |
| PATCH | `/api/buyer/reservations/{reservation}/cancel` | `BuyerReservationController@cancel` | `buyer.reservations.cancel` |

#### Buyer shop / orders / reviews / favorites (`auth:sanctum`, `role:buyer`)

| Method | URI | Controller@action | Name |
|---|---|---|---|
| GET | `/api/buyer/shops/{seller}` | `BuyerShopController@show` | `buyer.shops.show` |
| GET | `/api/buyer/shops/{seller}/reviews` | `BuyerShopController@reviews` | `buyer.shops.reviews` |
| GET | `/api/buyer/orders` | `OrderHistoryController@index` | `buyer.orders.index` |
| GET | `/api/buyer/orders/{reservation}` | `OrderHistoryController@show` | `buyer.orders.show` |
| GET | `/api/buyer/orders/{reservation}/receipt` | `OrderHistoryController@receipt` | `buyer.orders.receipt` |
| POST | `/api/buyer/reviews` | `ReviewController@store` | `buyer.reviews.store` |
| GET | `/api/buyer/favorites` | `FavoriteController@index` | `buyer.favorites.index` |
| POST | `/api/buyer/favorites` | `FavoriteController@store` | `buyer.favorites.store` |
| DELETE | `/api/buyer/favorites/{listing}` | `FavoriteController@destroy` | `buyer.favorites.destroy` |

#### Farmer listings (`auth:sanctum`, `role:farmer_seller`)

| Method | URI | Controller@action | Name |
|---|---|---|---|
| GET | `/api/farmer/listings` | `ListingController@index` | `farmer.listings.index` |
| POST | `/api/farmer/listings` | `ListingController@store` | `farmer.listings.store` |
| GET | `/api/farmer/listings/{listing}` | `ListingController@show` | `farmer.listings.show` |
| PUT | `/api/farmer/listings/{listing}` | `ListingController@update` | `farmer.listings.update` |
| PATCH | `/api/farmer/listings/{listing}` | `ListingController@update` | `farmer.listings.update` |
| DELETE | `/api/farmer/listings/{listing}` | `ListingController@destroy` | `farmer.listings.destroy` |
| PATCH | `/api/farmer/listings/{listing}/active` | `ToggleListingActiveController` `__invoke` | `farmer.listings.active` |

#### Farmer reservations (`auth:sanctum`, `role:farmer_seller`)

| Method | URI | Controller@action | Name |
|---|---|---|---|
| GET | `/api/farmer/reservations` | `FarmerReservationController@index` | `farmer.reservations.index` |
| GET | `/api/farmer/reservations/{reservation}` | `FarmerReservationController@show` | `farmer.reservations.show` |
| PATCH | `/api/farmer/reservations/{reservation}/ready` | `FarmerReservationController@markReady` | `farmer.reservations.ready` |
| PATCH | `/api/farmer/reservations/{reservation}/complete` | `FarmerReservationController@complete` | `farmer.reservations.complete` |
| PATCH | `/api/farmer/reservations/{reservation}/cancel` | `FarmerReservationController@cancel` | `farmer.reservations.cancel` |

#### Farmer POS (`auth:sanctum`, `role:farmer_seller`)

| Method | URI | Controller@action | Name |
|---|---|---|---|
| GET | `/api/farmer/sales` | `SaleController@index` | `farmer.sales.index` |
| POST | `/api/farmer/sales` | `SaleController@store` | `farmer.sales.store` |
| GET | `/api/farmer/sales/{sale}` | `SaleController@show` | `farmer.sales.show` |
| DELETE | `/api/farmer/sales/{sale}` | `SaleController@destroy` | `farmer.sales.destroy` |

#### Farmer shop (`auth:sanctum`, `role:farmer_seller`)

| Method | URI | Controller@action | Name |
|---|---|---|---|
| GET | `/api/farmer/shop` | `FarmerShopController@show` | `farmer.shop.show` |
| PUT | `/api/farmer/shop` | `FarmerShopController@update` | `farmer.shop.update` |
| PATCH | `/api/farmer/shop` | `FarmerShopController@update` | `farmer.shop.update` |

#### Crop care (`auth:sanctum`, `role:farmer_seller`)

| Method | URI | Controller@action | Name |
|---|---|---|---|
| GET | `/api/farmer/crop-care/categories` | `CropCareArticleController@categories` | `farmer.crop-care.categories` |
| GET | `/api/farmer/crop-care` | `CropCareArticleController@index` | `farmer.crop-care.index` |
| GET | `/api/farmer/crop-care/mine` | `CropCareArticleController@mine` | `farmer.crop-care.mine` |
| POST | `/api/farmer/crop-care` | `CropCareArticleController@store` | `farmer.crop-care.store` |
| GET | `/api/farmer/crop-care/{article}` | `CropCareArticleController@show` | `farmer.crop-care.show` |
| PUT | `/api/farmer/crop-care/{article}` | `CropCareArticleController@update` | `farmer.crop-care.update` |
| PATCH | `/api/farmer/crop-care/{article}` | `CropCareArticleController@update` | `farmer.crop-care.update` |
| DELETE | `/api/farmer/crop-care/{article}` | `CropCareArticleController@destroy` | `farmer.crop-care.destroy` |

#### Notifications (`auth:sanctum`)

| Method | URI | Controller@action | Name |
|---|---|---|---|
| GET | `/api/notifications` | `NotificationController@index` | `notifications.index` |
| GET | `/api/notifications/unread-count` | `NotificationController@unreadCount` | `notifications.unread-count` |
| PATCH | `/api/notifications/{notification}/read` | `NotificationController@markRead` | `notifications.read` |
| POST | `/api/notifications/read-all` | `NotificationController@markAllRead` | `notifications.read-all` |

### Health (not in `api.php` / `web.php`)

`bootstrap/app.php` `withHealthChecks('/up')` — GET `/up`.

Filament login and panel routes are registered by Filament under `/admin`, not in `routes/web.php`.

## 7. Core flows, as implemented

### a. User registration and approval, per role

**Buyer**

1. `POST /api/auth/register` → `RegisterController::__invoke` → `RegisterBuyerAction::handle`.
2. Creates `User` with `is_active` true, `email_verified_at` null, hashed password.
3. `assignRole(Role::Buyer)`.
4. `sendEmailVerificationNotification()` (mail `VerifyEmail` via Laravel notification).
5. Returns Sanctum token from `createToken($deviceName)`.
6. Email verify: signed `GET /api/auth/email/verify/{id}/{hash}` → `VerifyEmailController` marks `email_verified_at`.
7. Resend: `POST /api/auth/email/verification-notification` → `ResendVerificationController`.
8. There is no admin approval step for buyers. Inactive users fail login (`LoginUserAction` requires `is_active`).

**Farmer seller**

1. Public self-register as farmer: `NOT IMPLEMENTED`.
2. SuperAdmin API: `POST /api/admin/farmer-sellers` → `CreateFarmerSellerController::__invoke` → `CreateFarmerSellerAction::handle`.
3. Creates user with `email_verified_at` = `now()`, `is_active` true, `assignRole(Role::FarmerSeller)`.
4. Filament `CreateUser` can create a user and assign `farmer_seller` or `buyer`, and sets `email_verified_at` now. That is an admin-created account, not a public register flow.

**Super admin**

1. Public register: `NOT IMPLEMENTED`.
2. `SuperAdminSeeder::run` `updateOrCreate` email `env('SUPER_ADMIN_EMAIL', 'admin@anihow.local')`, `syncRoles(Role::SuperAdmin)`, `is_active` true, `email_verified_at` now.

**Login (any seeded/created active user)**

`LoginController::__invoke` → `LoginUserAction::handle`: `Auth::attempt`, require `is_active`, `createToken`, return user + token.

Password reset: `ForgotPasswordController` / `ResetPasswordController` (Laravel password broker). Flutter client does not call these.

### b. Creating a listing

1. Farmer `POST /api/farmer/listings` → `ListingController@store` → `CreateListingAction::handle`.
2. `$farmer->listings()->create([...])` with validated fields; `is_active` default true unless provided; optional `image` stored via listing image helper.
3. Filament `CreateListing` uses `ListingForm` and creates `Listing` in the panel.
4. Toggle: `PATCH /api/farmer/listings/{listing}/active` → `ToggleListingActiveController` → `ToggleListingActiveAction::handle`.
5. Update: `ListingController@update` → `UpdateListingAction`.
6. Delete: `ListingController@destroy` → `DeleteListingAction` (blocked if reservation_items or sale_items exist for the listing).

### c. Browsing / searching listings

1. Buyer `GET /api/marketplace` → `MarketplaceController@index`.
2. Scope `Listing::marketplaceVisible()`: listing `is_active`, farmer `is_active`, category `is_active`.
3. Optional `search`: `name` `LIKE %term%`.
4. Optional `category_id`.
5. Optional `sort`: `price_asc`, `price_desc`, `freshest`, `availability` (else latest).
6. Paginated. `GET /api/marketplace/{listing}` → `MarketplaceController@show` (same visibility).
7. `GET /api/categories` → `CategoryController@index` active categories.
8. Farmer `GET /api/farmer/listings` lists that farmer’s listings (not marketplace scope).

### d. Cart and checkout

**NOT IMPLEMENTED.** No cart table, model, route, or checkout action.

Buyer `ListingDetailScreen._reserve` posts a single `listing_id` + `quantity` to `POST /api/buyer/reservations` (`ApiClient.createReservation`). That is reservation create, not a cart.

`CreateReservationAction::handle` accepts multiple `items` in one request (`StoreReservationRequest`), requires all listings to belong to the same farmer, locks rows, decrements stock, creates one `Reservation` with `status` `pending`.

### e. Order lifecycle and every status value used

Marketplace “orders” are `Reservation` records. Walk-in POS uses `Sale`.

**Reservation statuses (verbatim DB/API values from `ReservationStatus`):**

- `pending`
- `ready_for_pickup`
- `completed`
- `cancelled`

Transitions (`Reservation::assertCanTransitionTo`):

- `pending` → `ready_for_pickup` (`MarkReservationReadyAction::handle`: sets `ready_at`)
- `pending` → `cancelled` (`CancelReservationAction::handle`: sets `cancelled_at`, `cancel_reason`, restores stock)
- `ready_for_pickup` → `completed` (`CompleteReservationAction::handle`: sets `completed_at`)
- `ready_for_pickup` → `cancelled` (same cancel action)

Buyer cancel: `BuyerReservationController@cancel` with `ReservationActor::Buyer`.
Farmer cancel: `FarmerReservationController@cancel` with `ReservationActor::FarmerSeller` (`reason` required).

**Sale statuses (verbatim from `SaleStatus`):**

- `recorded`

`CreatePosSaleAction` creates sales with `SaleStatus::Recorded`. `DeletePosSaleAction::handle` restores listing stock then `$sale->delete()`.

No other order/sale status strings exist in enums.

### f. Discount, promo, or price-reduction logic

**NOT IMPLEMENTED.** No discount/promo columns, models, routes, or FormRequest rules. Line totals are `quantity * unit_price` (`ListingStock` / reservation and sale item create).

### g. Fulfillment / delivery / pickup handling

Delivery: **NOT IMPLEMENTED** (no delivery table, courier, address shipping, or tracking).

Pickup, as coded:

1. Reservation created `pending`.
2. Farmer `PATCH .../ready` → `ready_for_pickup` + `ready_at` (`MarkReservationReadyAction::handle`); in-app notify buyer (`InAppNotifier::reservationStatusChanged`).
3. Farmer `PATCH .../complete` → `completed` + `completed_at` (`CompleteReservationAction::handle`).
4. Cancel restores quantity (`CancelReservationAction`).
5. User `location` / `contact` / `shop_name` are profile fields, not a fulfillment workflow.

Walk-in POS is immediate `recorded` sale with stock decrement (`CreatePosSaleAction`).

### h. Payment handling of any kind

**NOT IMPLEMENTED.** No payment gateway, payment table, paid/unpaid flag, or charge action. `price_per_unit` / `unit_price` / `line_total` are stored amounts only.

### i. Reviews

1. Buyer `POST /api/buyer/reviews` → `ReviewController@store` → `CreateReviewAction::handle`.
2. `StoreReviewRequest`: `reservation_id` required, exists, unique in `reviews`; `rating` required integer min 1 max 5; `comment` nullable string max 2000.
3. Policy `ReviewPolicy@create`: buyer.
4. Action requires reservation `completed`, owned by the buyer, one review per reservation.
5. `GET /api/buyer/shops/{seller}/reviews` → `BuyerShopController@reviews` paginated.
6. Shop show includes aggregate rating from `receivedReviews`.

### j. Analytics, reporting, or dashboard aggregation

Dedicated analytics API: **NOT IMPLEMENTED.** Permission `view_sales_analytics` is seeded and never checked in app code.

What exists:

- Filament `Dashboard` + `AccountWidget` only. No custom chart widgets.
- `BuyerShopController@show` computes shop rating from reviews (average / count).
- `CropCareArticleController@categories` `withCount` active articles as `tips_count`.
- Flutter `FarmerProfileScreen` loads paged listings, reservations, and sales and displays counts from those responses (client-side, first page of each paged call).
- `NotificationController@unreadCount` returns an integer.

### k. Crop care / reference content

1. Farmer `GET /api/farmer/crop-care` → `CropCareArticleController@index` (`CropCareIndexRequest`): active articles, optional `search`, optional `category_id` including `0` (`GENERAL_CATEGORY_ID`) for `category_id` null.
2. `GET .../categories` → categories that have active articles, plus virtual `{id: 0, name: General, slug: general}` when uncategorized count > 0.
3. `GET .../mine` → author’s articles paginated.
4. `POST` → `CreateCropCareArticleAction::handle` (`is_active` true, optional image via `SyncCropCareImage`).
5. `PUT/PATCH` → `UpdateCropCareArticleAction`.
6. `DELETE` → destroy after `CropCareArticlePolicy@delete`.
7. Filament `CropCareArticleResource` CRUD.
8. Buyer crop-care API: **NOT PRESENT**.

## 8. Enums and constants

### `app/Enums/Role.php`

- `SuperAdmin` = `super_admin`
- `FarmerSeller` = `farmer_seller`
- `Buyer` = `buyer`

### `app/Enums/Permission.php`

- `ManageAccounts` = `manage_accounts`
- `CreateFarmerSeller` = `create_farmer_seller`
- `OverseeMarketplace` = `oversee_marketplace`
- `OverseeListings` = `oversee_listings`
- `OverseeCropCare` = `oversee_crop_care`
- `ManageOwnListings` = `manage_own_listings`
- `RecordPosSales` = `record_pos_sales`
- `ViewSalesAnalytics` = `view_sales_analytics`
- `ManageCropCare` = `manage_crop_care`
- `BrowseMarketplace` = `browse_marketplace`
- `ReserveProduce` = `reserve_produce`

### `app/Enums/ReservationStatus.php`

- `Pending` = `pending`
- `ReadyForPickup` = `ready_for_pickup`
- `Completed` = `completed`
- `Cancelled` = `cancelled`

### `app/Enums/SaleStatus.php`

- `Recorded` = `recorded`

### `app/Enums/ReservationActor.php`

- `Buyer` = `buyer`
- `FarmerSeller` = `farmer_seller`

### `app/Enums/NotificationType.php`

- `ReservationCreated` = `reservation_created`
- `ReservationStatusChanged` = `reservation_status_changed`
- `LowStock` = `low_stock`

### Model / config constants

- `CropCareArticle::GENERAL_CATEGORY_ID` = `0`
- `config/anihow.php` `low_stock_threshold` default `5` (`env('LOW_STOCK_THRESHOLD', 5)`)
- `config/anihow.php` `listing_disk` default `public`
- Marketplace sort strings: `price_asc`, `price_desc`, `freshest`, `availability`
- Login default `device_name`: `mobile` (`LoginController`)
- SuperAdmin seeder defaults: email `admin@anihow.local`, name `AniHow Super Admin`, password `password`

### Flutter hardcoded role strings (`mobile/lib/models/models.dart` `UserAccount`)

- `super_admin`, `farmer_seller`, `buyer`

### Flutter reservation status strings (`status_pill.dart` / models)

- `pending`, `ready_for_pickup`, `completed`, `cancelled`

## 9. Validation rules

Only rules that mention price, quantity, discount, or stock. Discount: `NOT PRESENT` in any FormRequest.

### `app/Http/Requests/Api/Listings/StoreListingRequest.php`

- `'price_per_unit' => ['required', 'numeric', 'min:0.01']`
- `'quantity_available' => ['required', 'numeric', 'min:0']`

### `app/Http/Requests/Api/Listings/UpdateListingRequest.php`

- `'price_per_unit' => ['sometimes', 'required', 'numeric', 'min:0.01']`
- `'quantity_available' => ['sometimes', 'required', 'numeric', 'min:0']`

### `app/Http/Requests/Api/Reservations/StoreReservationRequest.php`

- `'items.*.quantity' => ['required', 'numeric', 'min:0.01']`

### `app/Http/Requests/Api/Pos/StoreSaleRequest.php`

- `'items.*.quantity' => ['required', 'numeric', 'min:0.01']`

### Inline / support (not FormRequest)

`app/Support/ListingStock.php` `decrement`:

- Throws `ValidationException` with message `Not enough stock for "{$listing->name}".` when `bccomp((string) $listing->quantity_available, $quantity, 2) < 0`.

### Filament `app/Filament/Resources/Listings/Schemas/ListingForm.php`

- `price_per_unit`: `numeric()->minValue(0.01)`
- `quantity_available`: `numeric()->minValue(0)`

No other FormRequest contains `price`, `quantity`, `discount`, or `stock` keys.

## 10. Flutter app (if present)

Present at `mobile/`. Navigator `MaterialPageRoute` only. Named routes: `NOT PRESENT`.

Role routing in `mobile/lib/main.dart`: `UserAccount.isFarmerSeller` → `FarmerShell`; `isBuyer` → `BuyerShell`; else `AdminGateScreen`. Unauthenticated → `LoginScreen`.

| Screen class | File | Role | API endpoints called |
|---|---|---|---|
| `LoginScreen` | `screens/login_screen.dart` | unauthenticated | `POST /auth/login` |
| `RegisterScreen` | `screens/register_screen.dart` | unauthenticated (buyer) | `POST /auth/register` |
| `AdminGateScreen` | `screens/admin_gate_screen.dart` | `super_admin` (and any non-farmer/non-buyer) | none |
| `BuyerShell` | `screens/buyer/buyer_shell.dart` | `buyer` | hosts tabs; verify banner uses `POST /auth/email/verification-notification` |
| `MarketplaceScreen` | `screens/buyer/marketplace_screen.dart` | `buyer` | `GET /marketplace`, `GET /categories` |
| `ListingDetailScreen` | `screens/buyer/listing_detail_screen.dart` | `buyer` | `GET /marketplace/{id}`, `POST /buyer/reservations`, `POST /buyer/favorites` |
| `BuyerReservationsScreen` | `screens/buyer/reservations_screen.dart` | `buyer` | `GET /buyer/reservations` |
| `ReservationDetailScreen` | `screens/buyer/reservation_detail_screen.dart` | `buyer` | `GET /buyer/reservations/{id}`, `PATCH /buyer/reservations/{id}/cancel`, `POST /buyer/reviews` |
| `FavoritesScreen` | `screens/buyer/favorites_screen.dart` | `buyer` | `GET /buyer/favorites`, `DELETE /buyer/favorites/{listing}` |
| `ShopProfileScreen` | `screens/buyer/shop_profile_screen.dart` | `buyer` | `GET /buyer/shops/{seller}`, `GET /buyer/shops/{seller}/reviews` |
| `OrderHistoryScreen` | `screens/buyer/order_history_screen.dart` | `buyer` | `GET /buyer/orders` |
| `ProfileScreen` | `screens/profile/profile_screen.dart` | `buyer` (also opened from farmer via different class) | `GET /auth/me` (via auth), `POST /auth/logout` |
| `FarmerShell` | `screens/farmer/farmer_shell.dart` | `farmer_seller` | hosts tabs |
| `FarmerListingsScreen` | `screens/farmer/listings_screen.dart` | `farmer_seller` | `GET /farmer/listings` (paged), `PATCH /farmer/listings/{id}/active` |
| `ListingFormScreen` | `screens/farmer/listing_form_screen.dart` | `farmer_seller` | `GET /categories`, `GET /farmer/listings/{id}` (edit), `POST /farmer/listings`, `PUT /farmer/listings/{id}`, `DELETE /farmer/listings/{id}` |
| `FarmerReservationsScreen` | `screens/farmer/reservations_screen.dart` | `farmer_seller` | `GET /farmer/reservations` (paged), `PATCH .../ready`, `PATCH .../complete` |
| `PosScreen` | `screens/farmer/pos_screen.dart` | `farmer_seller` | `GET /farmer/listings`, `GET /farmer/sales` (paged), `POST /farmer/sales`, `DELETE /farmer/sales/{id}` |
| `CropCareScreen` | `screens/farmer/crop_care_screen.dart` | `farmer_seller` | `GET /farmer/crop-care`, `GET /farmer/crop-care/categories` |
| `CropCareMineScreen` | `screens/farmer/crop_care_mine_screen.dart` | `farmer_seller` | `GET /farmer/crop-care/mine`, `DELETE /farmer/crop-care/{id}` |
| `CropCareFormScreen` | `screens/farmer/crop_care_form_screen.dart` | `farmer_seller` | `POST /farmer/crop-care`, `PUT /farmer/crop-care/{id}` |
| `CropCareDetailScreen` | `screens/farmer/crop_care_detail_screen.dart` | `farmer_seller` | `GET /farmer/crop-care/{id}` |
| `FarmerProfileScreen` | `screens/farmer/farmer_profile_screen.dart` | `farmer_seller` | `GET /farmer/shop`, `GET /farmer/listings` (paged), `GET /farmer/reservations` (paged), `GET /farmer/sales` (paged) |
| `ShopEditScreen` | `screens/farmer/shop_edit_screen.dart` | `farmer_seller` | `GET /farmer/shop`, `PUT /farmer/shop` |
| `NotificationsScreen` | `screens/notifications/notifications_screen.dart` | buyer or farmer | `GET /notifications`, `PATCH /notifications/{id}/read`, `POST /notifications/read-all` |
| `SettingsScreen` | `screens/profile/settings_screen.dart` | buyer or farmer | `POST /auth/email/verification-notification`, `POST /auth/logout`; theme/prefs are local |

`NotificationBellButton` (`widgets/notification_bell.dart`): `GET /notifications/unread-count`.

`AuthController.restoreSession`: `GET /auth/me`.

`url_launcher` `tel:` is used from profile / shop / reservation detail; that is not an API endpoint.

API methods on `ApiClient` with **no screen caller** in `mobile/lib`:

- `GET /farmer/reservations/{id}` — no `farmerReservation(id)` method
- `GET /farmer/sales/{id}` — no client method
- `GET /buyer/orders/{id}` — no client method
- `GET /buyer/orders/{id}/receipt` — no client method
- `PATCH /farmer/reservations/{id}/cancel` — no client method
- `POST /auth/forgot-password` — no client method
- `POST /auth/reset-password` — no client method
- `POST /admin/farmer-sellers` — no client method

## 11. Third-party packages

Framework packages (`laravel/framework`, `filament/filament`, Flutter SDK) are listed in section 1. Below: other declared packages and observed use.

### `composer.json` require (non-framework)

- `laravel/sanctum` `^4.3` — API tokens (`HasApiTokens`, `auth:sanctum`, `personal_access_tokens`).
- `laravel/tinker` `^3.0` — REPL; not referenced by app HTTP code.
- `league/flysystem-aws-s3-v3` `^3.35` — S3 disk for listing/crop-care images when `LISTING_DISK=s3`.
- `spatie/laravel-permission` `^8.3` — `HasRoles`, role middleware, `roles` / `permissions` tables, `RolePermissionSeeder`.

### `composer.json` require-dev

- `fakerphp/faker` — factories/seeders.
- `laravel/boost` `^2.9` — AI/dev tooling (Boost MCP).
- `laravel/pail` `^1.2.5` — log viewer CLI.
- `laravel/pao` `^1.0.4` — Laravel PaO dev package.
- `laravel/pint` `^1.27` — PHP formatter.
- `mockery/mockery` `^1.6` — test doubles.
- `nunomaduro/collision` `^8.9.3` — test/CLI error output.
- `phpunit/phpunit` `^12.5.12` — test runner.

### `mobile/pubspec.yaml` (non-SDK)

- `cupertino_icons` `^1.0.8` — Cupertino icon font (declared; Material icons also used).
- `dio` `^5.11.1` — HTTP client in `ApiClient`.
- `provider` `^6.1.5+1` — `AuthController`, `ThemeController`, `PreferencesController`.
- `flutter_secure_storage` `^11.2.0` — Sanctum token storage.
- `shared_preferences` `^2.5.5` — theme / preferences persistence.
- `image_picker` `^1.2.0` — listing and crop-care photo pickers.
- `url_launcher` `^6.3.2` — `tel:` launch from shop/profile/reservation UI.
- `flutter_lints` `^6.0.0` (dev) — analyzer lints.

## 12. Anything unfinished

### TODO comments

- `app/Support/InAppNotifier.php`: `TODO(push-notifications)` — push not sent; in-app rows are written.
- `app/Support/InAppNotifier.php`: `TODO(email-notifications)` — comment notes mailables exist (`ReservationCreatedMail`, `ReservationStatusChangedMail`, `LowStockMail`) but that path is not invoked from the notifier.
- `app/Modules/Notifications/README.md`: same push TODO.

Grep of `TODO` / `FIXME` / `XXX` / `hack` in `app/` and `mobile/lib` besides the above: no additional application TODOs.

### Stubbed / placeholder

- `tests/Unit/ExampleTest.php` — default PHPUnit example (`assertTrue(true)`).
- `resources/views/welcome.blade.php` — stock Laravel welcome for `GET /`.
- `AdminGateScreen` — static “use the web admin panel” message; no admin API usage.

### Empty controllers

- `app/Http/Controllers/Controller.php` is an abstract base with `AuthorizesRequests` only. All API controllers listed in section 6 have actions.

### Commented-out routes

`NOT PRESENT` in `routes/api.php` and `routes/web.php`.

### Unused models

All `app/Models` classes are referenced by controllers, policies, or actions.

Permission `view_sales_analytics` and most Spatie permission strings are unused in policies/controllers (roles are used instead).

### Filament empty directories

- `app/Filament/Pages/` — no custom pages.
- `app/Filament/Widgets/` — no custom widgets.

### Flutter vs API gaps (API exists, app does not call)

Farmer reservation show, farmer reservation cancel, farmer sale show, buyer order show, buyer order receipt, forgot/reset password, admin create farmer-seller.

### Cart / checkout / payment / discount / delivery

`NOT IMPLEMENTED` (see section 7).
