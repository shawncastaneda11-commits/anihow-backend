# AniHow Build Pass 3: Enums

Eleven files under `app/Enums/`. Drop-in replacements plus new cases. No schema
dependency, so these run before the migration pass.

PHP is not available in this environment, so these are unlinted. Run
`vendor/bin/pint` and `php artisan about` after copying.

---

## The breaking change, first

`Permission::forRole()` is a `match ($role)` with three arms and no default.
Adding `Role::ContentEditor` to the `Role` enum without rewriting that match
throws `UnhandledMatchError` on the next call, which in practice means at seed
time or at first authorization check. Copy `Role.php` and `Permission.php`
together or not at all.

I kept the no-default match deliberately and documented why in the file. It is
the reason this failure is loud instead of silent, and a role that silently
receives no permissions is a much worse bug to find.

---

## Files

| File | Status |
|---|---|
| `Role.php` | Replaced. Adds `ContentEditor`, plus `panelRoles()`, `farmScopedRoles()`, `requiresFarm()`, `usesPanel()` |
| `Permission.php` | Replaced. 25 cases across 8 groups, all four roles mapped |
| `OrderStatus.php` | New. Replaces `ReservationStatus.php`, which is deleted |
| `OrderActor.php` | New. Replaces `ReservationActor.php`, which is deleted. Values unchanged |
| `NotificationType.php` | Replaced |
| `CancellationReason.php` | New |
| `FulfillmentPreference.php` | New |
| `TawadType.php` | New |
| `UserStatus.php` | New |
| `ArticleStatus.php` | New |
| `ArticleCategory.php` | New |
| `ListingUnit.php` | Untouched. Moves from `Listing` to `CropType` in the migration pass, but the enum itself is correct as written |

---

## Permission map changes

Your eleven permissions were module-shaped. `OverseeMarketplace` grants
everything a Super Admin does in one string, which works while one role holds
everything and stops working the moment a second panel role exists. The new set
is resource-shaped so Filament policies can gate per resource.

| Old | Becomes |
|---|---|
| `manage_accounts` | Kept, plus `approve_farmer_seller` and `suspend_accounts` split out |
| `create_farmer_seller` | `approve_farmer_seller`. The Super Admin approves registrations rather than creating sellers. |
| `oversee_marketplace` | Dissolved into `takedown_listings`, `view_all_orders`, `moderate_reviews`, `resolve_reports` |
| `oversee_listings` | `takedown_listings` |
| `oversee_crop_care` | `manage_all_articles` |
| `manage_own_listings` | Kept |
| `record_pos_sales` | Deleted with the POS module |
| `view_sales_analytics` | Splits three ways: `view_system_analytics`, `view_farm_analytics`, `view_own_analytics` |
| `manage_crop_care` | `manage_own_farm_articles`, and it moves off Farmer-Seller onto Content Editor |
| `browse_marketplace` | Kept, Buyer only |
| `reserve_produce` | `place_orders` |

Note the fourth row from the bottom. Farmer-Sellers currently hold
`manage_crop_care`. Under the v2 spec crop-care content belongs to the Content
Editor, so this is a revocation, not a rename. Any farmer-seller crop-care
screen in the Flutter app becomes read-only.

---

## `OrderStatus` against your `ReservationStatus`

Your state machine had four states and I have kept its exact shape:
`label()`, `isTerminal()`, `allowedNext()`, `canTransitionTo()`. The transition
table is the only structural change, plus three helpers the stock logic needs.

| Old | New |
|---|---|
| `Pending` | `Placed` |
| (none) | `Confirmed`, inserted between Placed and Ready |
| `ReadyForPickup` | `Ready`. Renamed because the state now covers seller delivery too, and "ready for pickup" would be wrong on half the orders. |
| `Completed`, `Cancelled` | Unchanged |

Added helpers, all three consumed by the stock service rather than by
controllers: `hasDeductedStock()`, `holdsStock()`, `isPriceLocked()`.

Cancellation remains reachable from every non-terminal state, as in your
original.

---

## Decisions log

| # | Decision |
|---|---|
| 20 | `cancelled_by` stays an actor enum rather than becoming a user foreign key. Pass 1 said FK; your existing shape is better, since an order has exactly two parties and the enum reads directly in the app. Pass 1 is superseded on this point. |
| 21 | `ListingUnit` keeps all seven cases including Gram and Liter. Pass 1 listed five. Yours wins. |
| 22 | `ReadyForPickup` renamed to `Ready`, because the state now spans both fulfillment preferences |
| 23 | Farmer-Sellers do not hold `browse_marketplace` or `place_orders`. One account, one role, so a farmer-seller cannot buy. Flagged below. |
| 24 | `Permission::forRole()` keeps its exhaustive no-default match, by design |
| 25 | `NotificationType::forOrderStatus()` added so the order state machine emits notifications without a second mapping table |

---

## Flags

**Flag 6. Farmer-Sellers cannot buy.** Strictly correct under one account, one
role, and that is how I have written the permissions. It also means a farmer who
wants to buy another farm's produce needs a second email address. Worth raising
with Cajigas, because a panel member will ask.

**Flag 7. `FloorPriceRaised` notification.** Added to cover the stranded-listing
case from Pass 1 decision 4. If you would rather the Super Admin sees stranded
listings only in the CMS, delete the case.

**Flag 8. `ReservationStatus` data migration.** Existing rows hold `pending` and
`ready_for_pickup`. Since the order data is being truncated, no value mapping is
needed. If you decide to keep the rows after all, `pending` maps to `placed` and
every such row is missing a `confirmed_at` that cannot be reconstructed.

---

## Still blocked

`database/migrations/`. Constraint and index names are needed for the renames.
`Category` to `crop_types` and `reservations` to `orders` both carry foreign
keys that have to be dropped by name before the rename and rebuilt after.
