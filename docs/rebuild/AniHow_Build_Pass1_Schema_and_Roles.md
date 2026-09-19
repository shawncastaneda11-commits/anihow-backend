# AniHow Build Pass 1: Schema and Role Spec

Derived from `AniHow_Project_Instructions_v2_Marketplace_Rebuild.md`.
This is the target state. It is the reference the migration pass and the role
pass are written against, and the reference the alignment report is re-run
against.

Stack assumptions: Laravel 13.17, PHP 8.3, MySQL, Spatie Permission, Laravel
Sanctum, Filament 5 (single panel).

---

## 1. Tables to drop

| Table | Reason |
|---|---|
| `sales`, `sale_items` | Point-of-Sale module removed |
| `reservations`, `reservation_items` | Reservation model replaced by the order model |
| any crop cycle, growth phase, plot, or harvest table | Crop-cycle tracking removed |

Exact table names are yours to confirm; I am naming the concepts, not reading
your migrations.

Data in `reservations` is not migrated into `orders`. The reservation model has
no Confirmed state and no per-seller split, so a row-level carry-across would
produce orders that cannot be reconstructed. Truncate and reseed.

---

## 2. Tables to add or rebuild

### 2.1 `farms`

First-class entity. Everything farm-scoped hangs off this.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `name` | string(150) | |
| `slug` | string(160) unique | |
| `description` | text nullable | Content Editor maintained |
| `contact_person` | string(150) nullable | |
| `contact_number` | string(30) nullable | |
| `address` | string(255) nullable | |
| `barangay` | string(100) nullable | |
| `municipality` | string(100) nullable | |
| `pickup_point` | string(255) nullable | Free text, no geocoding |
| `cover_photo_path` | string nullable | |
| `is_active` | boolean default true | |
| timestamps | | |

Related: `farm_photos` (`id`, `farm_id`, `path`, `sort_order`).

### 2.2 `users`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `name` | string(150) | |
| `email` | string unique | |
| `phone` | string(30) nullable | |
| `password` | string | |
| `farm_id` | FK nullable, nullOnDelete | Required for farmer_seller and content_editor, null for super_admin and buyer |
| `status` | enum(`pending`,`active`,`suspended`) default `pending` | Buyers are created `active` |
| `approved_at` | timestamp nullable | |
| `approved_by` | FK users nullable | |
| `suspended_at` | timestamp nullable | |
| `suspension_reason` | string nullable | |
| timestamps, softDeletes | | |

Role is held by Spatie, not by a column on `users`. One account, one role: a
`SingleRole` guard enforces this on assignment (see Section 5).

Unique constraint: one Content Editor per farm. Enforced in application code
rather than in the schema, because the role lives in `model_has_roles`. A
`ContentEditorPolicy` check plus a validation rule on user creation covers it.

### 2.3 `crop_types` (the taxonomy)

System-wide. Not farm-scoped. This is the single most important structural
change from the current build, where listing names are free text.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `name` | string(100) | Canonical name |
| `label_en` | string(100) | Bilingual scope is crop labels only |
| `label_fil` | string(100) | |
| `slug` | string(120) unique | |
| `unit_of_measure` | enum(`kg`,`piece`,`bundle`,`sack`,`tray`) | Extend the enum only with adviser sign-off |
| `floor_price` | decimal(10,2) | Super Admin only |
| `max_discount` | decimal(10,2) default 0 | Super Admin only, peso amount |
| `is_active` | boolean default true | |
| `created_by` | FK users | |
| timestamps | | |

Check constraints: `floor_price > 0`, `max_discount >= 0`,
`max_discount < floor_price`. The third one is a judgment call, see Flag 3.

### 2.4 `listings`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `user_id` | FK users, cascade | The farmer-seller |
| `farm_id` | FK farms | Denormalized from the seller for scoped queries |
| `crop_type_id` | FK crop_types, restrictOnDelete | |
| `title` | string(150) | Seller's own copy, not the taxonomy name |
| `description` | text nullable | |
| `price` | decimal(10,2) | Validated `>= crop_type.floor_price` |
| `quantity_available` | decimal(10,2) | |
| `quantity_held` | decimal(10,2) default 0 | See Flag 1 |
| `is_available` | boolean default true | Seller's manual on and off switch |
| `status` | enum(`published`,`taken_down`,`archived`) default `published` | Auto-publish on create |
| `taken_down_at` | timestamp nullable | |
| `taken_down_by` | FK users nullable | |
| `takedown_reason` | string nullable | |
| timestamps, softDeletes | | |

Sellable quantity is `quantity_available - quantity_held`. Never let a listing
be ordered beyond that.

Related: `listing_photos` (`id`, `listing_id`, `path`, `sort_order`).

### 2.5 `tawad_rules`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `listing_id` | FK listings, cascade | |
| `type` | enum(`flat`,`min_quantity`) | Exactly two, never a third |
| `discount_amount` | decimal(10,2) | Pesos, never a percentage |
| `min_quantity` | decimal(10,2) nullable | Required when `type = min_quantity`, null otherwise |
| `is_active` | boolean default true | |
| `ended_at` | timestamp nullable | Seller may end a rule |
| timestamps | | |

One active rule per listing. Enforce with a partial uniqueness check in
application code, since MySQL has no filtered unique index.

**Validation, applied at rule creation and again at checkout:**

1. `discount_amount <= crop_type.max_discount`
2. Floor check, by type:
   - `flat`: `listing.price - discount_amount >= crop_type.floor_price`
     (worst case is an order of one unit)
   - `min_quantity`: `((listing.price * min_quantity) - discount_amount) / min_quantity >= crop_type.floor_price`
3. `min_quantity > 0` when the type requires it

### 2.6 `cart_items`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `user_id` | FK users, cascade | The buyer |
| `listing_id` | FK listings, cascade | |
| `quantity` | decimal(10,2) | |
| timestamps | | |

Unique on (`user_id`, `listing_id`). No `carts` table; the buyer is the cart.
The cart spans farms and sellers freely. The split happens at checkout.

### 2.7 `orders`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `order_number` | string(20) unique | Human readable, shown at handover |
| `buyer_id` | FK users, restrictOnDelete | |
| `seller_id` | FK users, restrictOnDelete | The farmer-seller |
| `farm_id` | FK farms | Denormalized for farm-scoped analytics |
| `status` | enum(`placed`,`confirmed`,`ready`,`completed`,`cancelled`) default `placed` | |
| `fulfillment_preference` | enum(`buyer_pickup`,`seller_delivers`) | |
| `fulfillment_note` | text nullable | Agreed time and place, free text |
| `payment_method` | enum(`cash_on_handover`) default `cash_on_handover` | Single value by design, kept as an enum so the constraint is visible |
| `subtotal` | decimal(12,2) | Sum of line subtotals before tawad |
| `tawad_total` | decimal(12,2) default 0 | |
| `total` | decimal(12,2) | `subtotal - tawad_total` |
| `amount_received` | decimal(12,2) nullable | Recorded by the seller at Completed |
| `confirmed_at` | timestamp nullable | |
| `ready_at` | timestamp nullable | |
| `completed_at` | timestamp nullable | |
| `cancelled_at` | timestamp nullable | |
| `cancelled_by` | FK users nullable | |
| `cancellation_reason` | enum(`buyer_cancelled`,`seller_declined`,`no_show`,`other`) nullable | See Flag 2 |
| `cancellation_note` | string nullable | |
| timestamps | | |

Index on (`seller_id`, `status`), (`buyer_id`, `status`), (`farm_id`, `completed_at`).

### 2.8 `order_items`

Everything is snapshotted. An order confirmed at a price keeps that price even
if the listing or the rule changes afterwards.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `order_id` | FK orders, cascade | |
| `listing_id` | FK listings, nullOnDelete | Reference only |
| `crop_type_id` | FK crop_types, restrictOnDelete | Analytics group by this, so it must survive |
| `listing_title` | string(150) | Snapshot |
| `unit_of_measure` | string(20) | Snapshot |
| `unit_price` | decimal(10,2) | Snapshot, the listed price |
| `quantity` | decimal(10,2) | |
| `line_subtotal` | decimal(12,2) | `unit_price * quantity` |
| `tawad_rule_id` | FK tawad_rules, nullOnDelete | |
| `tawad_type` | string(20) nullable | Snapshot |
| `tawad_amount` | decimal(10,2) default 0 | Snapshot |
| `line_total` | decimal(12,2) | `line_subtotal - tawad_amount` |

The three lines the buyer sees (listed price, tawad, final total) come straight
off these columns. The listed price is never overwritten.

### 2.9 `order_status_histories`

| Column | Type |
|---|---|
| `id` | bigint unsigned PK |
| `order_id` | FK orders, cascade |
| `from_status` | string(20) nullable |
| `to_status` | string(20) |
| `changed_by` | FK users |
| `note` | string nullable |
| `created_at` | timestamp |

### 2.10 `reviews`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `order_id` | FK orders, unique, cascade | One review per order, and no order means no review |
| `buyer_id` | FK users | |
| `seller_id` | FK users | |
| `rating` | tinyint unsigned | 1 to 5 |
| `comment` | text nullable | |
| `is_removed` | boolean default false | Super Admin moderation, soft removal |
| `removed_by` | FK users nullable | |
| `removed_at` | timestamp nullable | |
| timestamps | | |

Creation guard: the parent order must be `completed`.

### 2.11 `articles`

Farm-scoped crop-care and pest-management reference.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `farm_id` | FK farms, cascade | |
| `author_id` | FK users | The Content Editor |
| `title` | string(200) | |
| `slug` | string(220) | Unique per farm |
| `excerpt` | string(300) nullable | |
| `body` | longtext | |
| `cover_photo_path` | string nullable | |
| `category` | enum(`crop_care`,`pest_management`) | |
| `status` | enum(`draft`,`published`) default `draft` | |
| `published_at` | timestamp nullable | |
| timestamps, softDeletes | | |

Pivot `article_crop_type` (`article_id`, `crop_type_id`). Articles tag to the
shared taxonomy even though the article itself belongs to one farm.

### 2.12 `reports`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `reporter_id` | FK users | |
| `reportable_type` / `reportable_id` | morphs | Listing, Review, or User |
| `reason` | string(255) | |
| `status` | enum(`open`,`resolved`,`dismissed`) default `open` | |
| `resolved_by` | FK users nullable | |
| `resolution_note` | string nullable | |
| timestamps | | |

---

## 3. Order state machine

| From | To | Actor | Side effects |
|---|---|---|---|
| (new) | `placed` | Buyer | `quantity_held` increases per line |
| `placed` | `confirmed` | Seller | `quantity_available` decreases, `quantity_held` decreases, prices locked |
| `placed` | `cancelled` | Buyer or Seller | `quantity_held` decreases, nothing deducted |
| `confirmed` | `ready` | Seller | None |
| `confirmed` | `cancelled` | Seller | `quantity_available` restored |
| `ready` | `completed` | Seller | `amount_received` required |
| `ready` | `cancelled` | Seller | `quantity_available` restored, reason typically `no_show` |
| `completed` | (terminal) | | Review unlocks |
| `cancelled` | (terminal) | | |

Every transition writes an `order_status_histories` row. No transition is
allowed out of a terminal state. Implement as a single `OrderStateMachine`
service, not as scattered controller logic, because Chapter 3 will need one
diagram that matches one class.

---

## 4. Validation choke points

Floor price and discount ceiling are checked in three places. All three are
required; the first two are convenience, the third is the guarantee.

1. Listing create and update: `price >= crop_type.floor_price`
2. Tawad rule create and update: ceiling check plus the floor check in 2.5
3. Checkout: recompute every line against the live `crop_types` row before the
   order is written, and again before `confirmed`

The Super Admin can lower a floor price or a max discount at any time, which can
strand an existing listing or rule. Judgment call: raising a floor above an
existing listing price flags the listing for the Super Admin rather than
silently rejecting it or auto-raising the seller's price. Auto-raising a
farmer's price is not something the system should ever do.

---

## 5. Roles and permissions (Spatie)

Four roles, one guard (`web`). Sanctum resolves the same User model, so
`$user->can()` works identically in the API and in Filament.

`super_admin`, `content_editor`, `farmer_seller`, `buyer`.

**One account, one role.** Enforce in a `UserObserver` or a dedicated
`AssignsSingleRole` action: assigning a role calls `syncRoles()`, never
`assignRole()`. Never expose a multi-select role field in Filament.

### Permission set

| Permission | super_admin | content_editor | farmer_seller | buyer |
|---|---|---|---|---|
| `user.create` | yes | | | |
| `user.approve` | yes | | | |
| `user.suspend` | yes | | | |
| `user.delete` | yes | | | |
| `user.viewAny` | yes | own farm, read only | | |
| `farm.create` | yes | | | |
| `farm.update` | yes | own farm | | |
| `farm.delete` | yes | | | |
| `crop_type.manage` | yes | | | |
| `crop_type.set_pricing` | yes | | | |
| `listing.create` | | | own | |
| `listing.update` | | | own | |
| `listing.takedown` | yes | | | |
| `listing.viewAny` | yes | | own only | published only |
| `tawad.manage` | | | own listings | |
| `order.viewAny` | yes | | own | own |
| `order.advance` | | | own | |
| `order.cancel` | | | own | own, before confirmed |
| `review.create` | | | | own completed orders |
| `review.remove` | yes | | | |
| `article.manage` | yes | own farm | | |
| `article.view` | yes | yes | yes | yes |
| `report.resolve` | yes | | | |
| `report.create` | | | yes | yes |
| `analytics.view.system` | yes | | | |
| `analytics.view.farm` | yes | own farm | | |
| `analytics.view.own` | | | own | |
| `export.generate` | yes | | | |

`content_editor` holds no pricing, no moderation, and no account permission.
That absence is the point of the role and the first thing the panel will probe.

### Filament panel access

One panel. `canAccessPanel()` returns true for `super_admin` and
`content_editor` only, and false for `farmer_seller` and `buyer`. Every resource
is policy-gated; `content_editor` resources additionally scope their Eloquent
query to `auth()->user()->farm_id`. Global scoping is not optional here, because
a missing `where` on a farm-scoped resource is a cross-farm data leak and the
adviser will test exactly that.

---

## 6. Flags

**Flag 1. Stock held versus deducted.** The spec says stock deducts at Confirmed
and that cancellation before Confirmed releases stock. Both sentences are only
true together if stock is held at Placed and deducted at Confirmed. Hence
`quantity_held`. If you would rather stock move only at Confirmed, then a Placed
order can oversell and two buyers can place orders against the same last kilo.
I went with holding. Confirm.

**Flag 2. `no_show` as a cancellation reason.** Same call I flagged in the
manuscript draft, now expressed in the schema: no-show is a
`cancellation_reason` on a Cancelled order, not a sixth status. The manuscript
and the schema have to agree, so one confirmation settles both.

**Flag 3. `max_discount < floor_price` as a check constraint.** Not stated in
the spec. It closes a gap where a max discount set above the floor makes the
per-rule floor check the only thing standing between a crop type and a free
kilo. Cheap to add, easy to defend, and removable if you disagree.

**Flag 4. Article uniqueness.** Slug is unique per farm, not globally, so two
farms can both publish "Pest Management for Kamatis". That is correct under
farm-scoped content, but it means article URLs need the farm slug in the path.

---

## 7. What I need to write the migration pass

The schema above is greenfield-correct. Turning it into migrations that run
against your repo without breaking it needs your current state:

- `database/migrations/` contents, or `php artisan schema:dump` output
- `app/Models/` listing
- the current Spatie seeder, if there is one

Send those and the next deliverable is the migration set plus the role seeder,
ready to run.
