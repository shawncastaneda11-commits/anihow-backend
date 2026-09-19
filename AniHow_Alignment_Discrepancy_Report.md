# AniHow — Code vs Spec Discrepancy Report

Audited: `ALIGNMENT_REPORT.md` (repo state) against `AniHow Project Instructions
v2 (Marketplace Rebuild)`.

Verdict: the build is a **competent implementation of the pre-restructure
system**. Roughly 60 percent of it survives. The gaps are concentrated in three
places: the Content Editor role, the Farm entity, and the crop taxonomy. All
three are schema-level, so they get cheaper the sooner they are fixed.

Severity key: **P0** blocks the spec and is schema-level. **P1** blocks the spec
but is application-level. **P2** is cosmetic, naming, or documentation.
**SPEC** means the code is fine and the instructions document should change.

---

## P0 — Schema-level, fix before more Filament resources are built

### P0-1. Content Editor role does not exist
`app/Enums/Role.php` has three cases: `super_admin`, `farmer_seller`, `buyer`.
The spec requires four actors. There is no Content Editor role, no seeded
permissions for it, no policy branch, and no Filament gating for it.

`User::canAccessPanel()` returns `is_active && isSuperAdmin()`, so the panel is
single-role by construction. Adding a second CMS role means reworking that gate
plus every policy, since policies currently test `isSuperAdmin()` directly
rather than permissions.

### P0-2. No Farm entity
There is no `farms` table and no `farm_id` anywhere. Shop identity lives as loose
columns on `users` (`shop_name`, `bio`, `contact`, `location`). The spec requires
Farm as a first-class entity, with Farmer-Sellers belonging to a farm and one
Content Editor scoped per farm. Without it, "one Content Editor per farm" cannot
be expressed at all.

### P0-3. Crop taxonomy is missing; `categories` is not it
`categories` holds `name`, `slug`, `is_active`. The spec's taxonomy entry must
hold: crop name, bilingual English and Filipino label, unit of measure, floor
price, maximum peso discount, and links to crop-care articles.

Consequence: `listings.name` and `listings.unit` are free text. Two farmers
selling tomatoes produce two unrelated strings. There is no shared vocabulary,
so bilingual labels, floor-price enforcement, and per-crop analytics all have
nothing to attach to. **This is the single most expensive gap.**

### P0-4. No floor price, no maximum discount
Neither field exists. `StoreListingRequest` validates `price_per_unit` only as
`numeric|min:0.01`. The Super Admin has no economic guardrail of any kind, which
is the governance justification for the role in the manuscript.

### P0-5. POS is fully built and must be removed
`sales`, `sale_items`, `SaleStatus::Recorded`, `SaleController` with four routes,
`CreatePosSaleAction`, `DeletePosSaleAction`, permission `record_pos_sales`, and
Flutter `PosScreen`. The spec removed POS entirely.

Judgment call: **delete it rather than leave it dormant.** A dead module in the
repo will surface in the code walkthrough and invite a question the manuscript
no longer answers.

### P0-6. Reservation model, not order model
`reservations` / `reservation_items` throughout, with `ReservationStatus` values
`pending`, `ready_for_pickup`, `completed`, `cancelled`. The spec requires
`Placed`, `Confirmed`, `Ready`, `Completed`, `Cancelled`.

The missing state is **Confirmed**, and it is not cosmetic:
- `CreateReservationAction` decrements stock at creation. The spec deducts at
  Confirmed.
- The farmer has no accept-or-decline gate. Transitions are `pending →
  ready_for_pickup` or `pending → cancelled`. The spec's confirm step, where
  tawad is verified and the pickup window is set, has nowhere to live.
- `OrderHistoryController` already calls them orders in the buyer API while the
  model says reservation. The vocabulary is already drifting.

### P0-7. Tawad absent entirely
No discount column, model, route, action, or validation rule. Line totals are
`quantity * unit_price`. The spec's Option C rules (flat peso off, peso off at a
minimum quantity), the Super Admin ceiling, and the floor-price floor validation
all need building from zero. `average discount given` has no data source.

---

## P1 — Application-level

### P1-1. Crop care is authored by farmers, not Content Editors
`crop_care_articles.created_by` points at the author; `CropCareArticlePolicy`
lets any FarmerSeller create, update, and delete their own articles. The spec
makes crop care Content Editor content, farm-scoped, **read-only** for
Farmer-Sellers and Buyers.

### P1-2. Buyers cannot read crop care at all
Every crop-care route sits behind `role:farmer_seller`. The report states the
buyer crop-care API is not present. The spec exposes it read-only to both app
roles.

### P1-3. No cart, no checkout, no per-seller order split
`CreateReservationAction` accepts multiple items and enforces one farmer per
reservation, which is the right constraint, but there is no cart and no split at
checkout. The Flutter `ListingDetailScreen` posts one listing at a time. The
Shopee model in the spec needs the cart layer.

### P1-4. No fulfillment preference
The spec requires a pickup-versus-seller-delivers choice with a note field.
Only a generic `notes` column exists, and the status name `ready_for_pickup`
hardcodes pickup as the only path.

### P1-5. Cash amount received is not recorded
`CompleteReservationAction` sets `completed_at` only. The spec records the amount
received at Completed. Without it the sales figures are derived from listed
prices rather than from what actually changed hands.

### P1-6. Descriptive analytics not implemented
Filament has `Dashboard` plus the stock `AccountWidget`. No custom widgets, no
charts. `app/Filament/Widgets/` is empty. Permission `view_sales_analytics` is
seeded and never checked anywhere. All four required summaries (units sold per
crop, sales per period, best-selling produce, average discount) are missing.

### P1-7. Super Admin cannot govern the marketplace from the CMS
Filament resources exist for `User`, `Category`, `Listing`, `CropCareArticle`
only. There is no resource for Reservation, Sale, or Review. The spec gives the
Super Admin the order ledger, review removal, and report resolution. Currently
none of that is reachable in the panel.

### P1-8. Spatie Permission is installed but unused
Policies and middleware test `isSuperAdmin()` / `isFarmerSeller()` / `isBuyer()`
directly. Permissions are seeded and never checked. This works, but it makes the
package hard to defend in the tech-stack justification, and it is the mechanism
the spec names for separating Super Admin from Content Editor on one panel.

### P1-9. Permission names encode the dead model
`record_pos_sales` and `reserve_produce` name features the spec removed. Rename
with the rest of the vocabulary pass.

---

## P2 — Minor

- **P2-1.** `listings.harvested_at` reads as a crop-cycle remnant. Harmless as a
  freshness field, but rename or document it so no one reads it as growth
  tracking.
- **P2-2.** `favorites` is not in the spec's module list. Keep it, it is a good
  marketplace feature, but it has to appear in Section 1.3 or it is an
  undocumented feature at defense.
- **P2-3.** Notifications likewise. Real, working, undocumented.
- **P2-4.** No farmer-seller approval state. The Super Admin creates the account
  outright via `POST /api/admin/farmer-sellers`. The spec says "approved", which
  implies an application. Decide whether approval is a workflow or just
  admin-creates.
- **P2-5.** Several API endpoints have no Flutter caller: farmer reservation
  show and cancel, buyer order show and receipt, forgot and reset password,
  admin create farmer-seller. Dead surface at demo time.
- **P2-6.** `AdminGateScreen` is a static message. Fine, but it is the only thing
  a Super Admin sees in the app.
- **P2-7.** Super admin seeder password is `password`. Change before any public
  deployment.

---

## SPEC — where the instructions document should move instead

- **S-1. Filament version.** `composer.json` pins `filament/filament ~5.0` and
  `laravel/framework ^13.17`. The instructions lock "Filament 4" in Ch3. The
  code is newer and correct. **Update the spec to Filament 5**, and check the
  Ch3 System Development wording in the same pass.
- **S-2. Email verification and password reset** are built and are not in the
  spec. They are additive and defensible. Add them to Section 1.3 rather than
  removing them.
- **S-3. Review rating is 1 to 5.** This is the marketplace star rating and does
  not conflict with the four-point Likert evaluation instrument. No change
  needed; noting it so nobody "fixes" it later.

---

## Recommended order of work

1. **Schema pass, one migration set:** add `farms`; add `crop_types` as the real
   taxonomy with bilingual labels, unit, floor price, and max discount; add
   `farm_id` to users and `crop_type_id` to listings; drop `sales` and
   `sale_items`; rename `reservations` to `orders` and add `confirmed_at`,
   `fulfillment_preference`, `amount_received`, and the discount columns.
2. **Role pass:** add `content_editor`, move policies from role checks to Spatie
   permissions, rework `canAccessPanel`.
3. **Order lifecycle pass:** insert Confirmed, move the stock decrement to it,
   add the farmer accept-or-decline endpoint.
4. **Tawad pass:** discount rules on listings, ceiling validation, floor
   validation at rule creation and at checkout.
5. **Cart and checkout pass.**
6. **Crop care ownership pass:** reassign to Content Editor, farm-scope it,
   expose read-only to buyers.
7. **Analytics pass:** four Filament widgets.
8. **Vocabulary pass:** reservation to order everywhere, permission renames,
   Flutter strings, then delete dead endpoints.

Steps 1 and 2 are the ones that get more expensive every day. Do them first.
