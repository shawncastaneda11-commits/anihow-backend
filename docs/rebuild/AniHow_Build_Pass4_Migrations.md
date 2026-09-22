# AniHow Build Pass 4: Migrations and Role Seeder

Fifteen migration files plus `RolePermissionSeeder`. Unlinted, no PHP in this
environment. Run Pint and `php artisan migrate:fresh --seed` after copying.

---

## The call: rewrite, do not layer

Your migrations are dated 18 and 19 September. The schema is two days old, there
is no production data, and the Railway instance is testing only. So the Pass 2
plan of renaming `categories` to `crop_types` and `reservations` to `orders`
through ALTER migrations is wasted work, and it leaves a migration history where
the reader has to replay fifteen edits to learn what a table contains. Cajigas
and the panel will both read this directory.

Pass 2 decisions 11 and 14 are superseded. The create migrations are rewritten
in place and the old ones are deleted.

This destroys existing data. Everything in the database right now is seeded
demo data or hand-entered test rows, so the cost is a reseed.

---

## Apply

```
# 1. Delete these files from database/migrations/
0001_01_01_000000_create_users_table.php          (replaced)
2026_09_18_225800_add_location_to_users_table.php (folded in)
2026_09_18_225810_create_categories_table.php     (becomes crop_types)
2026_09_18_225820_create_listings_table.php       (replaced)
2026_09_18_225830_create_crop_care_articles_table.php
2026_09_18_231000_create_reservations_table.php   (becomes orders)
2026_09_18_231010_create_reservation_items_table.php
2026_09_18_231020_create_sales_table.php          (POS, gone)
2026_09_18_231030_create_sale_items_table.php     (POS, gone)
2026_09_18_232000_add_shop_profile_to_users_table.php (folded in)
2026_09_18_232010_create_notifications_table.php  (renamed table)
2026_09_18_232020_create_reviews_table.php        (replaced)
2026_09_18_232030_create_favorites_table.php      (replaced)
2026_09_18_232040_add_created_by_to_crop_care_articles_table.php (folded in)
2026_09_19_053850_add_image_path_to_crop_care_articles_table.php (folded in)

# 2. Keep untouched
0001_01_01_000001_create_cache_table.php
0001_01_01_000002_create_jobs_table.php
2026_09_18_145004_create_permission_tables.php
2026_09_18_145005_create_personal_access_tokens_table.php

# 3. Copy the fifteen new files in, then
php artisan migrate:fresh --seed
```

`crop_type_id` on `farm_crop_type_overrides` restricts deletes only after `php artisan migrate:fresh`; until then the migration file and an already-migrated database disagree.

Four `add_*` migrations are folded into their create migrations. A two-day-old
project does not need an ALTER history.

---

## What changed against your schema

### `users`

| Change | Why |
|---|---|
| `is_active` boolean removed, `status` string added | A boolean cannot express pending. Today an unapproved farmer-seller is indistinguishable from a suspended one. |
| `approved_at`, `approved_by`, `suspended_at`, `suspension_reason` added | Approval is a Super Admin action with an audit trail |
| `farm_id` added, in a separate migration | Framework pins users to `0001_01_01`, and farms must exist first |
| `softDeletes` added | Orders hold `restrictOnDelete` on buyer and seller, so a hard delete of a user with order history fails outright |
| `location`, `shop_name`, `bio`, `contact` folded in | These are the storefront. Already built, no new table needed. |

`Listing::scopeMarketplaceVisible()` checks `farmerSeller.is_active`. That
column no longer exists. Change it to `status = 'active'`.

### `categories` becomes `crop_types`

Adds `label_en`, `label_fil`, `unit_of_measure`, `floor_price`, `max_discount`,
`created_by`. The floor price is the whole reason the Super Admin role exists
and your schema had no column for it.

### `listings`

`category_id` becomes `crop_type_id`. `name` becomes `title`. `unit` is dropped,
because unit of measure belongs to the taxonomy entry; two sellers listing the
same crop in different units makes units-sold meaningless. Adds `farm_id`,
`quantity_held`, `status`, the three takedown columns, and `softDeletes`.

`price_per_unit` keeps its name. It is accurate and it is all over your code.

### `reservations` becomes `orders`

Keeps `farmer_seller_id` rather than my Pass 1 `seller_id`. Your naming is
consistent across eleven models and one document should not break it.

Adds `order_number`, `farm_id`, `fulfillment_preference`, `fulfillment_note`,
`payment_method`, `subtotal`, `tawad_total`, `amount_received`, `confirmed_at`,
`cancellation_note`.

`cancellation_reason` narrows from `text` to `string`, since it is now an enum
value and the free-text part moved to `cancellation_note`.

### `reservation_items` becomes `order_items`

Your snapshot columns carry over unchanged: `listing_name`, `unit`, `quantity`,
`unit_price`, `line_subtotal`. Adds `crop_type_id`, the three tawad columns, and
`line_total`.

One behaviour change. `listing_id` moves from `restrictOnDelete` to nullable
`nullOnDelete`. Under restrict, a listing that has ever been ordered can never
be deleted, which turns every seller's delete button into an error message. The
snapshot columns exist precisely so the order survives the listing.

### `reviews`

`reservation_id` becomes `order_id`, unique index kept. Adds the four moderation
columns and a CHECK on rating, which your schema allowed to be 0 or 200.

### `notifications` becomes `in_app_notifications`

The old name is the table Laravel's own database notification channel claims.
Update `InAppNotification::$table`.

### Dropped

`sales`, `sale_items`.

---

## New tables

`farms`, `farm_photos`, `crop_types`, `listing_photos`, `tawad_rules`,
`cart_items`, `order_status_histories`, `reports`, `article_crop_type`.

---

## Seeder

`RolePermissionSeeder` reads `Permission::forRole()` and needs no maintenance of
its own. Two things it does that a naive version would not:

`syncPermissions()` rather than `givePermissionTo()`, so re-running after a
revocation actually revokes. The farmer-seller losing `manage_crop_care` depends
on this.

A sweep that deletes permission rows no longer in the enum, so `record_pos_sales`
and the other retired names cannot linger and keep granting access.

Register it in `DatabaseSeeder::run()` before any user seeder.

---

## Decisions log

| # | Decision |
|---|---|
| 26 | Migrations rewritten in place, not layered. Supersedes decisions 11 and 14. |
| 27 | Four `add_*` migrations folded into their create migrations |
| 28 | `farmer_seller_id` kept as the order column name, against Pass 1's `seller_id` |
| 29 | `price_per_unit` kept as the listing column name |
| 30 | `order_items.listing_id` becomes nullable `nullOnDelete`, against your `restrictOnDelete` |
| 31 | Enum-backed columns are `string` with a model cast, matching your existing convention rather than MySQL `ENUM` |
| 32 | CHECK constraints added via raw `DB::statement` on `crop_types`, `tawad_rules`, `reviews` |
| 33 | `users` and `listings` and `crop_care_articles` gain `softDeletes` |

---

## Flags

**Flag 9. CHECK constraint portability.** MySQL enforces CHECK from 8.0.16.
Older MySQL parses and ignores it silently. If Railway or the VPS runs anything
older, the FormRequest rules are the only guard, which is why they are not
optional. Confirm with `select version()`.

**Flag 10. One Content Editor per farm is not in the schema.** The role lives in
`model_has_roles`, so a unique index cannot express it. It needs a validation
rule on user creation plus a policy check. Easy to forget, and a second Content
Editor on one farm is exactly the kind of thing a panel member tries.

**Flag 11. `users.farm_id` nullability.** Null is correct for Super Admin and
Buyer and wrong for the other two. Not expressible as a column constraint
without a CHECK that reads the role out of a pivot table, which is not worth it.
Validation rule, and note it in the SRS.

---

## Next

Models. Eleven rewrites against this schema, plus seven new ones, plus the
`OrderStateMachine` service that Chapter 3 will diagram. Nothing further is
blocked; I have what I need.
