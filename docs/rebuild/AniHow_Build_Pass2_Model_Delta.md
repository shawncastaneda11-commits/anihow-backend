# AniHow Build Pass 2: Model Delta and Decisions

Read against the eleven models supplied. Companion to
`AniHow_Build_Pass1_Schema_and_Roles.md`.

Good news first. The code is cleaner than the alignment report implied. Snapshot
columns on `ReservationItem`, a real state machine on `ReservationStatus`, Spatie
on the `web` guard for both Filament and Sanctum, storage cleanup in model
`booted()` hooks. The structure is sound. What it encodes is the old system.

---

## 1. Four P0 findings

**1.1 There is no pending state for an account.** `User` carries
`is_active` as a boolean. Farmer-Seller registration requires Super Admin
approval, which means an account must be able to exist in a state that is
neither active nor suspended. A boolean cannot express it, so today an
unapproved farmer-seller is indistinguishable from a suspended one.

**1.2 Content Editors cannot log in.** `canAccessPanel()` returns
`$this->is_active && $this->hasRole(Role::SuperAdmin)`. The role does not exist
in the `Role` enum and the panel gate excludes it by construction.

**1.3 There is no farm.** No `farm_id` on `User`, `Listing`, `Reservation`, or
`CropCareArticle`. Every farm-scoped rule in the spec has nothing to scope to.

**1.4 There is no floor price.** `Category` holds `name`, `slug`,
`description`, `is_active` and nothing else. The economic guardrail that the
entire Super Admin role is built around has no column.

---

## 2. Model-by-model delta

### `Category` becomes `CropType`

Rename the model, the table, and every `category_id` foreign key to
`crop_type_id`. Then add:

`label_en`, `label_fil`, `unit_of_measure` (moved off `Listing`, see below),
`floor_price`, `max_discount`, `created_by`.

`name`, `slug`, `description`, `is_active` survive unchanged. `scopeActive()`
survives. The `listings()` relation survives under the new key name.

### `Listing`

| Current | Action |
|---|---|
| `farmer_seller_id` | Keep |
| `category_id` | Rename to `crop_type_id` |
| `name` | Rename to `title`. It stays free text, because under the taxonomy model this is the seller's own copy, not the crop identity. Crop identity now comes from `crop_type_id`. |
| `unit` | Drop. Unit of measure belongs to the taxonomy entry, not to each listing, or two sellers can list the same crop in different units and the units-sold figure becomes meaningless. |
| `price_per_unit` | Keep. Add validation against `crop_type.floor_price`. |
| `quantity_available` | Keep |
| `description`, `image_path`, `is_active` | Keep |
| | Add `farm_id`, `quantity_held`, `status`, `taken_down_at`, `taken_down_by`, `takedown_reason` |

`scopeMarketplaceVisible()` needs a fourth condition on
`status = published`, and the `farmerSeller` check changes from
`is_active = true` to `status = active`.

### `Reservation` becomes `Order`

Rename table and model. Do not drop and recreate: the rename keeps the `Review`
foreign key and the state machine class intact. Truncate the data.

Add: `order_number`, `farm_id`, `fulfillment_preference`, `fulfillment_note`,
`payment_method`, `subtotal`, `tawad_total`, `amount_received`, `confirmed_at`.

`total`, `notes`, `cancellation_reason`, `cancelled_by`, `ready_at`,
`completed_at`, `cancelled_at` all survive. `notes` and `fulfillment_note` are
different fields; keep both, `notes` is the seller's internal note.

`cancellation_reason` is currently a free string. Convert to the enum with
`buyer_cancelled`, `seller_declined`, `no_show`, `other`.

`assertCanTransitionTo()` survives as the entry point. The transition table
behind it is rewritten.

### `ReservationItem` becomes `OrderItem`

Already snapshots `listing_name`, `unit`, `unit_price`, `line_subtotal`. That is
exactly right and saves a pass.

Add: `crop_type_id` (snapshot the taxonomy link, or analytics breaks the moment
a listing is deleted), `tawad_rule_id`, `tawad_type`, `tawad_amount`,
`line_total`.

### `Review`

Rename `reservation_id` to `order_id`. Add `is_removed`, `removed_by`,
`removed_at` for Super Admin moderation. Confirm a unique index exists on the
order key; I cannot see indexes from the model.

### `CropCareArticle`

| Current | Action |
|---|---|
| `category_id` belongsTo | Replace with a many-to-many `article_crop_type` pivot. One article covers several crops. |
| `GENERAL_CATEGORY_ID = 0` | Retire. It exists to hold articles with no category; under the new model every article tags at least one crop type. |
| `isOfficial()`, `created_by === null` | Retire. There are no system-authored articles. Every article belongs to one farm and one Content Editor. |
| `is_active` | Replace with `status` draft or published, plus `published_at`. Content Editors need a draft state. |
| | Add `farm_id`, `category` (crop_care or pest_management), `slug`, `excerpt` |

`excerpt()` computes a summary at read time. Keep the method, and keep the
stored `excerpt` column nullable so a Content Editor can override it. The method
becomes the fallback.

### `User`

Add `farm_id`, `status`, `approved_at`, `approved_by`, `suspended_at`,
`suspension_reason`. Drop `is_active` once `status` is backfilled.

`shop_name`, `bio`, `contact`, `location` already exist. That is the storefront,
already built. Pass 1 treated the storefront as a view over listings; it is
actually these columns plus a listings query. No new table needed.

`canAccessPanel()` becomes `status === active && hasAnyRole([SuperAdmin, ContentEditor])`.

Add `isContentEditor()`. Retire `sales()`, `buyerReservations()` and
`incomingReservations()` become `orders()` and `incomingOrders()`.

### `Sale`, `SaleItem`

Drop both, with their tables, factories, enums, policies, controllers, and
Filament resources. The POS module is gone.

---

## 3. Two things in the code that are not in the spec

**`Favorite`.** A buyer wishlist. Not mentioned anywhere in the v2 spec.
Decision: keep it. It is standard marketplace furniture, it costs one table, it
is already built and working, and Shopee has it. It gets one line in Section 1.3
under Module A rather than a module of its own. Flagging because it is a scope
addition and the spec is the authority.

**`InAppNotification`.** Also unmentioned. Decision: keep. Order status changes
are useless to a buyer who has to poll for them.

One defect here regardless: the model points at a table named `notifications`,
which is the table name Laravel's own database notification channel expects. The
moment anyone calls `Notification::send()` with the database channel, the two
collide. Rename the table to `in_app_notifications`.

---

## 4. Enums

| Enum | Action |
|---|---|
| `Role` | Add `ContentEditor` |
| `ReservationStatus` | Becomes `OrderStatus`. Add `Confirmed` between Placed and Ready. Rewrite `canTransitionTo()` against the Pass 1 transition table. |
| `ReservationActor` | Becomes `OrderActor` |
| `ListingUnit` | Moves from `Listing` to `CropType`. Enum itself unchanged. |
| `NotificationType` | Add order status cases, drop any reservation and sale cases |
| new `UserStatus` | `pending`, `active`, `suspended` |
| new `TawadType` | `flat`, `min_quantity` |
| new `FulfillmentPreference` | `buyer_pickup`, `seller_delivers` |
| new `CancellationReason` | `buyer_cancelled`, `seller_declined`, `no_show`, `other` |
| new `ArticleStatus` | `draft`, `published` |
| new `ArticleCategory` | `crop_care`, `pest_management` |

---

## 5. New models with no current counterpart

`Farm`, `TawadRule`, `CartItem`, `OrderStatusHistory`, `Report`,
`ListingPhoto`, `FarmPhoto`.

On photos: `Listing` currently holds a single `image_path`. Decision: keep it as
the cover image and add `listing_photos` for the rest. That avoids touching
`ListingStorage`, the `booted()` cleanup hook, and every app screen that reads
`imageUrl()`.

---

## 6. Decisions log

| # | Decision |
|---|---|
| 11 | `Category` is renamed to `CropType` rather than replaced. Preserves data and foreign keys. |
| 12 | `listings.name` survives as `title`, free text, seller-owned. Crop identity moves to `crop_type_id`. |
| 13 | `unit` moves from `Listing` to `CropType`. One crop, one unit, system-wide. |
| 14 | `reservations` is renamed to `orders`, not dropped. Data truncated, structure preserved. |
| 15 | `Favorite` accepted into scope. One line in 1.3, not a module. |
| 16 | `InAppNotification` accepted into scope. Table renamed to `in_app_notifications`. |
| 17 | `CropCareArticle::isOfficial()` and `GENERAL_CATEGORY_ID` retired. All articles are farm-owned. |
| 18 | Storefront is the existing `User` columns plus a listings query. No new table. |
| 19 | `listings.image_path` stays as the cover image; `listing_photos` holds the rest. |

---

## 7. Still blocked

Three things, and the first is the one that matters:

1. **`app/Enums/`**, specifically `ReservationStatus`. It already has
   `canTransitionTo()`. I am rewriting that method and I want to match its
   shape rather than impose a different one.
2. **`database/migrations/`**. Nullability, precision, index names, and foreign
   key constraint names. Renames and drops need the constraint names or the
   migration fails at runtime rather than at review.
3. **The Spatie seeder**, if one exists. Existing permission names decide
   whether the Pass 1 grid is a rename or a rewrite.

With those three the next deliverable is the migration set plus the role seeder,
runnable.
