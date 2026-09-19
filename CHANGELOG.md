# Changelog

## [0.9.24] — Level farmer app bar (2026-09-19)

My listings title, bell, and profile sit on one toolbar line with matching 40px controls.

### Files changed
- `mobile/lib/screens/farmer/farmer_shell.dart`
- `mobile/lib/widgets/app_header.dart`
- `mobile/lib/widgets/notification_bell.dart`
- `mobile/lib/widgets/profile_avatar_button.dart`
- `CHANGELOG.md`

## [0.9.23] — Mark all notifications read (2026-09-19)

Mark all read now clears the green dots immediately. The old FutureBuilder reload never rebuilt after the API call.

### Files changed
- `mobile/lib/screens/notifications/notifications_screen.dart`
- `mobile/lib/models/models.dart`
- `mobile/test/widget_test.dart`
- `CHANGELOG.md`

## [0.9.22] — Delete listing from Edit (2026-09-19)

Edit listing shows a trash icon in the app bar. Confirm, then DELETE `/farmer/listings/{id}` and return to My listings. New listing has no trash icon.

### Files changed
- `mobile/lib/screens/farmer/listing_form_screen.dart`
- `mobile/lib/services/api_client.dart`
- `CHANGELOG.md`

## [0.9.21] — Listing active pills (2026-09-19)

UI only. My listings uses Active / Inactive pills instead of switches. Tap still toggles the listing.

### Files changed
- `mobile/lib/widgets/listing_active_badge.dart`
- `mobile/lib/screens/farmer/listings_screen.dart`
- `CHANGELOG.md`

## [0.9.20] — Care Guide Details read-only (2026-09-19)

UI only. Details is a single cream-page card: photo, Detailed Instructions, crop type, and the saved body. No edit/FAB on this screen. Edit/delete stay on My Care Guides.

### Files changed
- `mobile/lib/screens/farmer/crop_care_detail_screen.dart`
- `mobile/lib/screens/farmer/crop_care_mine_screen.dart`
- `mobile/lib/widgets/care_guide_card.dart`
- `CHANGELOG.md`

## [0.9.19] — My Care Guides cards (2026-09-19)

UI only. My Care Guides lists real farmer guides as white cards: photo thumb, title, category pill, 3-line summary, View Details. FAB opens Add Care Guide.

### Files changed
- `mobile/lib/screens/farmer/crop_care_mine_screen.dart`
- `mobile/lib/screens/farmer/crop_care_screen.dart`
- `mobile/lib/widgets/care_guide_card.dart`
- `mobile/lib/theme/anihow_theme.dart`
- `CHANGELOG.md`

## [0.9.18] — Add Care Guide matches mock (2026-09-19)

Optional photo, Guide Title hint, category chips, and a large Care Instructions box. Photo is stored like listing images.

### Files changed
- `mobile/lib/screens/farmer/crop_care_form_screen.dart`
- `mobile/lib/screens/farmer/crop_care_detail_screen.dart`
- `mobile/lib/widgets/dashed_photo_box.dart`
- `mobile/lib/models/models.dart`
- `mobile/lib/services/api_client.dart`
- `app/Models/CropCareArticle.php`
- `app/Actions/CropCare/*`
- `app/Http/Requests/Api/CropCare/*`
- `app/Http/Controllers/Api/CropCare/CropCareArticleController.php`
- `app/Http/Resources/Api/CropCareArticleResource.php`
- `app/Filament/Resources/CropCareArticles/Schemas/CropCareArticleForm.php`
- `routes/api.php`
- `database/migrations/2026_09_19_053850_add_image_path_to_crop_care_articles_table.php`
- `tests/Feature/Api/CropCareApiTest.php`
- `CHANGELOG.md`

## [0.9.17] — Add Care Guide form trim (2026-09-19)

UI only. Target Stage and Timing/Frequency are not on the form or in the create/update payload. Care Instructions sits under Crop / Category and fills the space above Publish Care Guide.

### Files changed
- `mobile/lib/screens/farmer/crop_care_form_screen.dart`
- `mobile/lib/screens/farmer/crop_care_screen.dart`
- `CHANGELOG.md`

## [0.9.16] — Warm agricultural palette (2026-09-19)

UI only. Theme tokens and My listings use the beige/forest palette. Dark mode still uses the existing dark surfaces.

### Palette
- Screen `#F8F6F0`, cards `#FFFFFF` with a light shadow
- App bar, active tabs, price, FAB, active nav: forest `#1F5A3E`
- Titles `#1E2421`; inactive tabs `#6C757D`; inactive nav `#6F7872`
- In stock avatar `#7CD96C`, pill `#D9F5DF` / `#1C5635`
- Low stock avatar `#E88A83`, pill `#FCE3DE` / `#B94A3E`
- Switch on `#2E8B57`, off `#D6D1C7`; nav pill `#D7EADF`; badge `#E63946`

### Files changed
- `mobile/lib/theme/anihow_theme.dart`
- `mobile/lib/screens/farmer/listings_screen.dart`
- `mobile/lib/widgets/produce_card.dart`
- `mobile/lib/widgets/profile_avatar_button.dart`
- `mobile/test/widget_test.dart`
- `CHANGELOG.md`

## [0.9.15] — Order status updates immediately (2026-09-19)

UI only. Mark ready and Complete pickup update the order in place and switch to Ready / Done. No pull-to-refresh needed.

### Files changed
- `mobile/lib/screens/farmer/reservations_screen.dart`
- `mobile/lib/models/models.dart`
- `mobile/lib/services/api_client.dart`
- `CHANGELOG.md`

## [0.9.14] — Crop-care detail cards (2026-09-19)

UI only. Category and Official chips stay at the top. Title and body each sit in their own card.

### Files changed
- `mobile/lib/screens/farmer/crop_care_detail_screen.dart`
- `CHANGELOG.md`

## [0.9.13] — Listing switches update immediately (2026-09-19)

UI only. Active/inactive switches on My listings flip at once and no longer wait for a pull-to-refresh. The switch is outside the card tap target so it does not open the editor.

### Files changed
- `mobile/lib/screens/farmer/listings_screen.dart`
- `mobile/lib/widgets/produce_card.dart`
- `mobile/lib/models/models.dart`
- `mobile/lib/services/api_client.dart`
- `CHANGELOG.md`

## [0.9.12] — Forest green color scheme (2026-09-19)

UI only. Palette lives on `AniHowColors`; theme + My listings consume those tokens. Dark mode still uses the existing dark surfaces.

### Palette
- App bar, tabs (active), price, FAB, active nav: forest `#1B4D3E`
- Screen `#F8F9FA`, cards `#FFFFFF`, borders `#E9ECEF`, titles `#1A1D20`
- Listing placeholder sage `#58A67D`, switch mint `#52B788`
- In stock `#DDF3E4` / `#1B4332`; Low stock `#FDE8E4` / `#C84B31`
- Unread badge `#E63946`; inactive tabs `#6C757D`; inactive nav `#8D99AE`

### Files changed
- `mobile/lib/theme/anihow_theme.dart`
- `mobile/lib/screens/farmer/listings_screen.dart`
- `mobile/lib/screens/farmer/farmer_shell.dart`
- `mobile/lib/screens/buyer/buyer_shell.dart`
- `mobile/lib/widgets/produce_card.dart`
- `mobile/lib/widgets/status_pill.dart`
- `mobile/lib/widgets/profile_avatar_button.dart`
- `mobile/lib/widgets/category_color.dart`
- `mobile/test/widget_test.dart`
- `CHANGELOG.md`

## [0.9.11] — Farmer shop profile polish (2026-09-19)

UI only. No backend changes. Uses existing farmer shop, listings, reservations, and POS endpoints.

### Layout
- Header is a left avatar + shop name, pin + location, and star + rating + review count when reviews exist
- One bordered stat row: **Listings** (active), **Sales** (completed reservations + POS), **Rating** (1 decimal). A cell is omitted when that value is not available
- Bio, location, and contact share one card. Contact is tap-to-call
- **Active listings** fill the lower half with existing `ProduceCard` rows
- **Edit shop profile** is an outlined secondary button. Settings gear stays in the app bar

### Data notes
- `GET /farmer/shop` has no listings payload. Active listings come from `GET /farmer/listings` (`is_active`). Buyer shop also requires an active category — that flag is not in the listing JSON, so it is not filtered here
- Sales = completed rows from `GET /farmer/reservations` + all rows from `GET /farmer/sales`. Omitted if either call fails or pagination is truncated after 20 pages
- Listings count omitted if listing pages are truncated. Rating omitted when `reviews_count` is 0
- Farmers cannot call `GET /buyer/shops/{id}` (buyer role)

### Files changed
- `mobile/lib/screens/profile/profile_screen.dart`
- `mobile/lib/screens/buyer/shop_profile_screen.dart`
- `mobile/lib/widgets/shop_profile_parts.dart`
- `mobile/lib/services/api_client.dart`
- `mobile/lib/theme/anihow_theme.dart`
- `CHANGELOG.md`

## [0.9.10] — Settings grouped + trimmed (2026-09-19)

UI + light local wiring. No new backend.

### Layout
- Section header + bordered card of rows: Appearance, Preferences, Account, About
- Row labels only (no subtitles). Change password uses a **Soon** tag. Email uses a **Verified** pill. About is `AniHow v1.0.0`
- Log out is a red outlined destructive button

### Wired
- Appearance Light/Dark/System (existing theme prefs)
- Notifications toggle — **local SharedPreferences only** (no user notification-preference column). Bell stops polling/badge when off
- Edit profile — farmer_seller only, opens existing shop profile
- Email Verified pill; Unverified still offers Resend (`POST /api/auth/email/verification-notification`)
- Log out

### Placeholder
- Language: static **English** (no app localization)
- Change password: disabled **Soon** (no logged-in change-password API)
- Help & contact, Terms & privacy: static copy screens

### Files changed
- `mobile/lib/screens/profile/settings_screen.dart`
- `mobile/lib/state/preferences_controller.dart`
- `mobile/lib/main.dart`
- `mobile/lib/widgets/notification_bell.dart`
- `CHANGELOG.md`

## [0.9.9] — Larger farmer type scale (2026-09-19)

UI only. Shared `AniHowSpace` / `AniHowTheme` type scale. No logic, color, or feature changes.

### Type scale
- `header` **20** (new) — green app-bar title, weight **500** (was title 16 / w700–w800)
- `headline` 18 → **20** — detail headings
- `name` 14 → **16** — card/list primary (`titleMedium`: produce name, buyer name, guide title)
- `title` **16** (unchanged) — `titleLarge`
- `body` / `meta` 13 → **14** — card secondary (price, unit, status meta)
- `label` **12** — floor for pills and small labels (nothing below 12)
- `tab` 14 → **15** — All / In stock / Low and Orders tabs
- `nav` **13** (new) — bottom nav labels (kept off `meta` so nav stays 12–13)

### Touch targets
- `tabHeight` 52 → **60**
- `thumb` **64** (was hardcoded 56) — listing thumbnails
- `avatar` **24** (was default 20–22) — list avatars
- `switchScale` **1.12** + padded switch tap target

### Files changed
- `mobile/lib/theme/anihow_space.dart`
- `mobile/lib/theme/anihow_theme.dart`
- `mobile/lib/widgets/app_header.dart`
- `mobile/lib/widgets/produce_card.dart`
- `mobile/lib/widgets/profile_avatar_button.dart`
- `mobile/lib/screens/farmer/listings_screen.dart`
- `mobile/lib/screens/farmer/crop_care_screen.dart`
- `mobile/lib/screens/farmer/crop_care_category_screen.dart`
- `mobile/lib/screens/buyer/reservation_detail_screen.dart`
- `mobile/test/widget_test.dart`
- `CHANGELOG.md`

## [0.9.8] — Farmer-authored crop-care guides (2026-09-19)

Adds farmer_seller authoring. Filament admin authoring is unchanged. Pickup-only.

### Backend
- Nullable `crop_care_articles.created_by` (user id). Null = official/admin guide
- Farmer endpoints (Sanctum + farmer_seller, JSON resources):
  - `POST /api/farmer/crop-care` — create (title, body, category_id)
  - `PUT/PATCH` and `DELETE /api/farmer/crop-care/{id}` — **own guides only**
  - `GET /api/farmer/crop-care/mine` — the caller's guides
- Read stays open: farmers still list/show all **active** guides (official + peers + own)
- Ownership is strict: farmers cannot update/delete official (`created_by` null) or another farmer's guide
- JSON adds `is_official`, `can_edit`, and `author` `{id,name,shop_name}` when authored

### App
- Crop-care home: FAB **Add guide** + **My guides** card
- Create/edit form: title, produce category, multiline body (`AniHowField` spacing)
- Author labels: **Official** badge vs farmer shop name
- My guides: list/edit/delete own tips; empty state “You haven't added a guide yet”

### Files changed
- `database/migrations/2026_09_18_232040_add_created_by_to_crop_care_articles_table.php`
- `app/Models/CropCareArticle.php`
- `app/Models/User.php`
- `app/Policies/CropCareArticlePolicy.php`
- `app/Actions/CropCare/CreateCropCareArticleAction.php`
- `app/Actions/CropCare/UpdateCropCareArticleAction.php`
- `app/Http/Requests/Api/CropCare/StoreCropCareArticleRequest.php`
- `app/Http/Requests/Api/CropCare/UpdateCropCareArticleRequest.php`
- `app/Http/Controllers/Api/CropCare/CropCareArticleController.php`
- `app/Http/Resources/Api/CropCareArticleResource.php`
- `database/factories/CropCareArticleFactory.php`
- `routes/api.php`
- `tests/Feature/Api/CropCareApiTest.php`
- `mobile/lib/models/models.dart`
- `mobile/lib/services/api_client.dart`
- `mobile/lib/widgets/crop_care_author_chip.dart`
- `mobile/lib/screens/farmer/crop_care_screen.dart`
- `mobile/lib/screens/farmer/crop_care_mine_screen.dart`
- `mobile/lib/screens/farmer/crop_care_form_screen.dart`
- `mobile/lib/screens/farmer/crop_care_category_screen.dart`
- `mobile/lib/screens/farmer/crop_care_detail_screen.dart`
- `CHANGELOG.md`

## [0.9.7] — Center app-bar titles (2026-09-19)

UI only. No backend, auth, or navigation-bar changes.

Green header titles are centered on every screen that uses this header. Leading back arrows and trailing bell / avatar / settings stay on the edges.

- `AniHowTheme` `AppBarTheme.centerTitle`: `false` → `true` (buyer tabs, pushed screens, crop-care category/detail, settings, shop, listings form, etc.)
- `AppHeader` bar layout: title centered in the full header; trailing actions stay right (farmer My listings / Incoming orders / Walk-in POS / Crop care, plus Marketplace)

Bottom nav labels and icons are unchanged.

### Files changed
- `mobile/lib/theme/anihow_theme.dart`
- `mobile/lib/widgets/app_header.dart`
- `CHANGELOG.md`

## [0.9.6] — Crop-care by category (2026-09-19)

Pickup-only. Tip bodies were not rewritten. Grouping only.

### Backend
- Reused existing nullable `crop_care_articles.category_id` (no model redesign)
- `GET /api/farmer/crop-care/categories` — categories that have **active** tips, plus `tips_count`
- Uncategorized tips (`category_id` null) appear under virtual **General** (`id` 0, slug `general`); empty categories are omitted
- `GET /api/farmer/crop-care?category_id=0` lists General tips; each tip still belongs to one category only
- Index JSON: `excerpt` (first line), **no** `body`; show still returns full `body`
- Filament crop-care: null category labeled General

### App
- Crop-care home is a 2-column category grid (icon + color + name + tip count)
- Category opens a scannable list: numbered marker, title, one-line excerpt, chevron
- Tip detail loads `GET /farmer/crop-care/{id}`: category chip, headline, body typography; optional Tip:/Note: callout. No invented sections.

### Files changed
- `app/Models/CropCareArticle.php`
- `app/Http/Controllers/Api/CropCare/CropCareArticleController.php`
- `app/Http/Requests/Api/CropCare/CropCareIndexRequest.php`
- `app/Http/Resources/Api/CropCareArticleResource.php`
- `app/Http/Resources/Api/CropCareCategoryResource.php`
- `app/Filament/Resources/CropCareArticles/Schemas/CropCareArticleForm.php`
- `app/Filament/Resources/CropCareArticles/Tables/CropCareArticlesTable.php`
- `routes/api.php`
- `database/factories/CropCareArticleFactory.php`
- `tests/Feature/Api/CropCareApiTest.php`
- `mobile/lib/screens/farmer/crop_care_screen.dart`
- `mobile/lib/screens/farmer/crop_care_category_screen.dart`
- `mobile/lib/screens/farmer/crop_care_detail_screen.dart`
- `mobile/lib/models/models.dart`
- `mobile/lib/services/api_client.dart`
- `mobile/lib/widgets/category_color.dart`
- `CHANGELOG.md`

## [0.9.5] — Health-check follow-up: bugs + safe polish (2026-09-19)

Pickup-only. No API contract, role, or delivery changes.

### Job 3 fixes (A + B only)

- Listing delete: 422 when the listing is on a reservation or sale (avoids FK 500 and deleting the photo first)
- Listing resource: compute seller average/count once per listing
- Unverified-email banner OK now dismisses
- `mounted` checks after awaits on listings, orders, favorites, POS picker, photo picker
- POS listing bind swallows fetch errors (FutureBuilder already shows them)
- Notification “Mark all read” catches API errors
- Listing detail image uses `errorBuilder` like listing cards
- Pull-to-refresh waits for the reload future
- 401 clears the stored token
- Notification bell unsubscribes before resubscribing on dependency change

### Files changed

- `app/Actions/Listings/DeleteListingAction.php`
- `app/Http/Resources/Api/ListingResource.php`
- `tests/Feature/Api/FarmerListingApiTest.php`
- `mobile/lib/screens/buyer/buyer_shell.dart`
- `mobile/lib/screens/buyer/marketplace_screen.dart`
- `mobile/lib/screens/buyer/favorites_screen.dart`
- `mobile/lib/screens/buyer/reservations_screen.dart`
- `mobile/lib/screens/buyer/shop_profile_screen.dart`
- `mobile/lib/screens/buyer/listing_detail_screen.dart`
- `mobile/lib/screens/farmer/listings_screen.dart`
- `mobile/lib/screens/farmer/reservations_screen.dart`
- `mobile/lib/screens/farmer/listing_form_screen.dart`
- `mobile/lib/screens/farmer/pos_screen.dart`
- `mobile/lib/screens/notifications/notifications_screen.dart`
- `mobile/lib/state/auth_controller.dart`
- `mobile/lib/widgets/notification_bell.dart`
- `CHANGELOG.md`

## [0.9.4] — Trim helper chrome text (2026-09-19)

UI-only. No backend, auth, or logic changes.

| Screen | Removed |
| --- | --- |
| Login | Location “General Trias, Cavite”; second tagline; “Welcome back”; “Sign in as a buyer or farmer-seller.” Header is now AniHow + “Farmers' market hub” with a centered leaf mark. |
| Marketplace | Greeting “Hello, {name}”; subtitle “Fresh harvest from General Trias”. Header is now “Marketplace”. |
| Super admin gate | Header subtitle “Use the Filament website, not this app” (body URL line kept). |
| Walk-in POS | Already gone: “Record a sale at the stall”. |
| Crop-care list | Already gone: “Admin-authored crop guidance”. |
| My listings | Already gone: “Tap a listing to edit · toggle to show in market”; farmer own-listing rows already hide shop name (`showSeller: false`). |
| Incoming orders / buyer reservations | Already gone: any subtitle under the header. |

Login still has Email, Password (show/hide), Sign in, and “Create a buyer account”, spaced with `AniHowSpace` (screen 16, field 14, section 20).

### Files changed

- `mobile/lib/widgets/app_header.dart`
- `mobile/lib/screens/login_screen.dart`
- `mobile/lib/screens/buyer/marketplace_screen.dart`
- `mobile/lib/screens/admin_gate_screen.dart`
- `CHANGELOG.md`

## [0.9.3] — PART 4 shop discovery + reviews (2026-09-19)

Pickup-only. **This release is PART 4 only.** Average ratings display to 1 decimal (e.g. 4.5).

### Audit (already there vs added)

| Area | Already there (Phase 4 + Parts 1–3) | Added / used in PART 4 |
| --- | --- | --- |
| `reviews` table | Yes. Gated `POST /api/buyer/reviews` (own completed reservation, once, 1–5 stars) | Unchanged create path |
| Shop profiles | `shop_name`, `location`, `contact`, `bio` | Buyer shop screen uses them |
| `GET /api/buyer/shops` | Active farmer shops, paginated | Unused in app (discovery is tap-seller) |
| `GET /api/buyer/shops/{farmerSeller}` | Shop + marketplace-visible listings + avg | Nested listing cards now include seller avg; rating is 1 decimal |
| `GET /api/buyer/shops/{farmerSeller}/reviews` | Paginated latest reviews + avg | App “Show more”; 1-decimal avg; newest `id` tie-break |
| Listing cards | Star + rating from PART 1 (farmer-level) | Same format (`4.5`); tap seller name → shop |
| Product detail | Seller name/location only | Tappable farmer rating → shop profile |
| Delivery / courier / fee | None | Still none. Contact is tap-to-call for stall pickup |

### Endpoint change

**No new routes.** Existing buyer shop endpoints now return `average_rating` as a 1-decimal string (`4.0`, `4.5`). Shop show nested listings include the seller relation so cards match marketplace ratings. Inactive or non-farmer shop ids stay 404. Buyers may view any active farmer shop; only the author gating on `POST /api/buyer/reviews` applies to creating.

### Files changed

- `app/Models/User.php`
- `app/Http/Resources/Api/ShopProfileResource.php`
- `app/Http/Resources/Api/ListingResource.php`
- `app/Http/Controllers/Api/Shop/BuyerShopController.php`
- `app/Http/Controllers/Api/Favorites/FavoriteController.php`
- `tests/Feature/Api/ReviewShopFavoriteOrderApiTest.php`
- `tests/Feature/Api/MarketplaceApiTest.php`
- `mobile/lib/models/models.dart`
- `mobile/lib/services/api_client.dart`
- `mobile/lib/theme/anihow_space.dart`
- `mobile/lib/widgets/produce_card.dart`
- `mobile/lib/screens/buyer/shop_profile_screen.dart`
- `mobile/lib/screens/buyer/listing_detail_screen.dart`
- `mobile/lib/screens/buyer/marketplace_screen.dart`
- `mobile/lib/screens/buyer/favorites_screen.dart`
- `mobile/lib/screens/buyer/reservation_detail_screen.dart`
- `mobile/test/widget_test.dart`
- `CHANGELOG.md`


## [0.9.2] — PART 3 buyer order tracking (pickup only) (2026-09-19)

Pickup-only. **This release is PART 3 only.** Part 4 (shop reviews + browse by farmer) is not started.

### Audit (already there vs added)

| Area | Already there (Phase 4) | Added / used in PART 3 |
| --- | --- | --- |
| `GET /api/buyer/reservations` | Own list with items + seller, paginated | Loads `review`; seller JSON includes stall pickup fields |
| `GET /api/buyer/reservations/{id}` | Show with items + seller, ownership policy | Same path. Adds shop_name, location, contact, `can_review`, `review` |
| `PATCH /api/buyer/reservations/{id}/cancel` | Buyer, pending only | App calls it from the detail screen |
| `POST /api/buyer/reviews` | 1–5 stars + comment; own completed reservation once | App “Rate this farmer” sheet; hidden after review |
| Delivery / courier / fee | None | Still none. Stall block is labeled **Pickup at the stall** |

### Endpoint change

No new routes. `GET /api/buyer/reservations/{reservation}` (and the list) now include:

- `seller.shop_name`, `seller.location`, `seller.contact` (stall pickup)
- line `quantity` / `unit_price` / `line_subtotal` and `total` as 2-decimal strings
- `can_review` (completed + owned + no review yet)
- `review` when loaded

### Files changed

- `app/Http/Resources/Api/ReservationResource.php`
- `app/Http/Resources/Api/ReservationItemResource.php`
- `app/Http/Controllers/Api/Reservations/BuyerReservationController.php`
- `tests/Feature/Api/ReservationApiTest.php`
- `mobile/lib/models/models.dart`
- `mobile/lib/services/api_client.dart`
- `mobile/lib/support/relative_time.dart`
- `mobile/lib/screens/buyer/reservations_screen.dart`
- `mobile/lib/screens/buyer/reservation_detail_screen.dart`
- `mobile/lib/screens/notifications/notifications_screen.dart`
- `mobile/pubspec.yaml` (`url_launcher` for tap-to-call)
- `mobile/android/app/src/main/AndroidManifest.xml`
- `CHANGELOG.md`

## [0.9.1] — PART 2 in-app notification loop (2026-09-19)

Pickup-only. **This release is PART 2 only.** Part 3 (buyer order tracking) and Part 4 are not started.

### Audit (already there vs added)

| Area | Already there (Phase 4) | Added / fixed in PART 2 |
| --- | --- | --- |
| Table `notifications` | Yes (`type`, `title`, `body`, morph `related`, `read_at`) | — |
| `InAppNotifier` | create → farmer; ready/complete → buyer; low-stock → farmer | Peso amounts in bodies to 2 decimals; cancel notifies the **other** party |
| Cancel hook | Always notified the **buyer** | Buyer cancel → farmer; farmer cancel → buyer |
| Endpoints | `GET /api/notifications` (newest first, paginated), `GET /api/notifications/unread-count`, `PATCH /api/notifications/{id}/read`, `POST /api/notifications/read-all` | **No new routes.** Ownership via policy `isOwnedBy` |
| JSON `related_type` | Eloquent FQCN (`App\Models\Reservation`) | Aliased to `reservation` \| `listing` for deep-link |
| Push / extra email | TODO(push); Phase 5 queued mail already exists | Left the TODO hooks; no push, no new email product |
| App inbox | Settings screen only, no badge, no deep-link | Bell + unread badge, poll on focus, list, empty state, deep-link |

### Endpoints (unchanged paths)

- `GET /api/notifications` — own inbox, newest first, paginated
- `GET /api/notifications/unread-count`
- `PATCH /api/notifications/{inAppNotification}/read`
- `POST /api/notifications/read-all`

JSON now includes `related_id` and `related_type` as `reservation` or `listing`.

### Files changed

- `app/Actions/Reservations/CancelReservationAction.php`
- `app/Support/InAppNotifier.php`
- `app/Http/Resources/Api/InAppNotificationResource.php`
- `tests/Feature/Api/NotificationApiTest.php`
- `mobile/lib/models/models.dart`
- `mobile/lib/services/api_client.dart`
- `mobile/lib/main.dart`
- `mobile/lib/navigation/route_observer.dart`
- `mobile/lib/widgets/notification_bell.dart`
- `mobile/lib/screens/notifications/notifications_screen.dart`
- `mobile/lib/screens/farmer/farmer_shell.dart`
- `mobile/lib/screens/buyer/buyer_shell.dart`
- `mobile/lib/screens/buyer/marketplace_screen.dart`
- `mobile/lib/screens/profile/settings_screen.dart`
- `CHANGELOG.md`

Reservation **detail** is PART 3 — taps currently open the closest list (or the farmer listing form for low-stock).

## [0.9.0] — PART 1 buyer discovery (2026-09-19)

Pickup-only market hub. **This release is PART 1 only.** Parts 2–4 are not started.

### Audit (already there vs added)

| Area | Already there | Added in PART 1 |
| --- | --- | --- |
| Marketplace search `?search=` | Yes (name `like`) | — |
| Category filter `?category_id=` | Yes | — |
| Sort | No (always `latest`) | `sort=price_asc\|price_desc\|freshest\|availability` |
| Listing ratings | Reviews exist on the **farmer** (`reviews.farmer_seller_id`), not per listing. Shop profiles already expose `average_rating` | Marketplace listing JSON now includes seller avg + count |
| Notifications | Table + CRUD + notifier on create/ready/complete/cancel/low-stock | **Not in PART 1** (inbox/badge still Settings-only; cancel always notifies the **buyer**) |
| Buyer reservations | Status tabs + pending cancel | **Not in PART 1** (no pickup detail / review prompt) |
| Shops | `GET /buyer/shops/{id}` with listings + reviews | **Not in PART 1** (no tap-seller shop screen) |

Freshest uses `created_at` — there is no harvest-date column.

### Endpoint change

- `GET /api/buyer/marketplace?sort=` — optional, validated. Default `freshest`.
- Listing resource: `average_rating`, `reviews_count` (from the seller’s reviews). No new route.

### Files changed

- `app/Http/Requests/Api/Marketplace/MarketplaceIndexRequest.php`
- `app/Http/Controllers/Api/Marketplace/MarketplaceController.php`
- `app/Http/Resources/Api/ListingResource.php`
- `tests/Feature/Api/MarketplaceApiTest.php`
- `mobile/lib/models/models.dart`
- `mobile/lib/services/api_client.dart`
- `mobile/lib/screens/buyer/marketplace_screen.dart`
- `mobile/lib/widgets/produce_card.dart`
- `CHANGELOG.md`

## [0.8.1] — Type scale, even order tabs, crop-care article (2026-09-19)

UI-only. Shared type scale bumped one step: headers 16, names 14, body/price 13, tabs 14 / 52px tall.

- Listings **All / In stock / Low** uses the larger tab labels and taller touch target
- Orders tabs fill the full width (`isScrollable: false`). Completed shortened to **Done** so four labels stay readable at 14sp: Pending / Ready / Done / Cancelled
- Crop-care detail is an article: category chip, 18sp title, Admin + estimated read time, 16px padding, 1.6 line-height, lead paragraph. Tip:/Note: sentences get a soft green callout; no fake sections

### Files changed

- `mobile/lib/theme/anihow_space.dart`, `mobile/lib/theme/anihow_theme.dart`
- `mobile/lib/screens/farmer/listings_screen.dart`
- `mobile/lib/screens/farmer/reservations_screen.dart`
- `mobile/lib/screens/buyer/reservations_screen.dart`
- `mobile/lib/screens/farmer/crop_care_screen.dart`
- `mobile/lib/widgets/produce_card.dart`
- `mobile/test/widget_test.dart`
- `CHANGELOG.md`

## [0.8.0] — Flutter layout polish (2026-09-19)

Layout + spacing pass only. No Laravel backend changes.

### Part A — Shared spacing + type

- Added `mobile/lib/theme/anihow_space.dart`
  - Screen padding 16, card gap 12, section gap 20, card pad 12, radius 12
  - Type: titles 15, body 13, labels/meta 11–12
  - Form: 4 between label and field, 14 between fields
  - `AniHowMoney.peso` rounds every displayed amount to 2 decimals
- Theme radius and text scale now read from those constants (light + dark)

### Part B — Tabs

- Farmer listings: TabBar **All / In stock / Low** (client-side; Low = qty > 0 and < 5)
- Incoming orders + buyer reservations: TabBar **Pending / Ready / Completed / Cancelled** (client-side; `GET /farmer/reservations` and `GET /buyer/reservations` have no status query)

### Part C — Cut redundant text

- Removed farmer header helpers (“Tap a listing…”, “Record a sale…”, “Admin-authored…”)
- Farmer’s own listing rows no longer repeat the shop name

### Part D — Walk-in POS rebuild

- Produce selector card (thumbnail + name + unit price), quantity stepper, live TOTAL (2 decimals)
- **Record sale** → existing `POST /api/farmer/sales`
- **Today at the stall** from existing `GET /api/farmer/sales`, filtered client-side by `created_at`

### Part E — New-listing form

- Dashed **Add photo** box → existing `image` multipart field (`POST /api/farmer/listings`, `POST /api/farmer/listings/{id}` for update with photo)
- Labels above fields; Category+Unit and Price+Quantity on one row; full-width **Save listing** pinned at the bottom

### Files added

- `mobile/lib/theme/anihow_space.dart`
- `mobile/lib/widgets/form_label.dart`
- `mobile/lib/widgets/dashed_photo_box.dart`

### Files changed

- `mobile/lib/theme/anihow_theme.dart`
- `mobile/lib/models/models.dart` (`SaleRecord.createdAt` + items, listing `isInStock`)
- `mobile/lib/services/api_client.dart` (`farmerSales()`, multipart listing image)
- `mobile/lib/widgets/{produce_card,app_header,status_pill}.dart`
- Farmer: `farmer_shell`, `listings_screen`, `reservations_screen`, `pos_screen`, `listing_form_screen`, `crop_care_screen`
- Buyer: `reservations_screen`, `marketplace_screen`, `listing_detail_screen`, `favorites_screen`, `order_history_screen`
- Shared: login, register, profile, settings, admin_gate
- `mobile/pubspec.yaml` (`image_picker`)
- `mobile/test/widget_test.dart`

### Not faked

- Reservation status filter stays client-side (no status query on those endpoints)
- Today’s POS list uses `GET /api/farmer/sales` first page + `created_at` (no invented “today” query)

## [0.7.0] — Flutter design system + Profile/Settings (2026-09-19)

### Design system

- Added `mobile/lib/theme/anihow_theme.dart` (light + dark ThemeData, brand green #1D9E75)
- Reusable widgets:
  - `widgets/produce_card.dart`
  - `widgets/category_color.dart` (maps Vegetables/Herbs → leafy, Fruit → fruit, Root/Grains → root, eggplant → eggplant)
  - `widgets/status_pill.dart`
  - `widgets/app_header.dart`
  - `widgets/primary_button.dart`
  - `widgets/profile_avatar_button.dart`
- Applied across login, marketplace, listing detail, buyer reservations, farmer listings, incoming reservations, POS, crop-care
- Theme mode (light / dark / system) stored locally with `shared_preferences`

### Profile + Settings

- Buyer Profile tab: name, email, shortcuts to Order history (`GET /api/buyer/orders`) and Favorites
- Farmer-seller app-bar avatar → shop profile (`GET/PATCH /api/farmer/shop`) with edit form
- Shared Settings: theme toggle, in-app notifications list (`GET /api/notifications` + mark read), logout, About AniHow
- Email verification resend wired (`POST /api/auth/email/verification-notification`)
- Change password disabled with “coming soon” — no authenticated change-password endpoint exists (only forgot/reset)

### Files added

- `mobile/lib/theme/anihow_theme.dart`
- `mobile/lib/widgets/{produce_card,category_color,status_pill,app_header,primary_button,profile_avatar_button}.dart`
- `mobile/lib/state/theme_controller.dart`
- `mobile/lib/screens/profile/{profile_screen,settings_screen}.dart`
- `mobile/lib/screens/buyer/order_history_screen.dart`

### Files changed

- `mobile/lib/main.dart`, `models/models.dart`, `services/api_client.dart`
- Login, register, buyer/farmer shells and list screens
- `mobile/pubspec.yaml` (`shared_preferences`)
- `CHANGELOG.md`

## [0.6.0] — Flutter mobile app for buyer and farmer_seller (2026-09-18)

### Included in the mobile app (`mobile/`)

- Login for all roles; buyer self-register; Sanctum token in secure storage as `Bearer`
- Role routing: buyer home, farmer-seller home, super_admin gate (use Filament website)
- Buyer: marketplace browse + search, listing detail, reserve for pickup, my reservations (status + cancel pending), favorites
- Farmer-seller: my listings (list, create, edit, toggle active), incoming reservations (mark ready / complete), walk-in POS sale, crop-care browse
- API base URL in one file: `mobile/lib/config/api_config.dart` (`http://10.0.2.2:8000/api` on Android; `http://127.0.0.1:8000/api` on Windows/Edge)

### Deferred / not in the app UI

- Separate “accept reservation” action — API has no accept endpoint; pending → mark ready is the farmer action
- Reviews UI, shop profile edit, order-history/receipt screen, in-app notifications inbox
- Descriptive-analytics sales summary, crop-cycle / growth tracking, push notifications
- Listing image upload from the phone (create/edit are JSON fields only)

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
