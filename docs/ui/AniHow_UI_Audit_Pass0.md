# AniHow UI audit, pass 0

Read-only. No files under `mobile/lib/`, `mobile/test/`, `mobile/android/`, or `mobile/pubspec.yaml` were edited. Spec source: `docs/spec/AniHow_Project_Instructions_v3.3.md`, sections 4, 5, 7, 8, 10, and 12.

Device pass: emulator-5554, 1080×2400 with density override 480 (360dp wide) and `font_scale` 1.3. Theme on device was Dark; Light was toggled once in Settings and captured. Smoke data was already loaded (`smoke.buyer@anihow.local`, `smoke.sellera@anihow.local`). The API on port 8000 was stopped to force the error state, then started again on `127.0.0.1:8000`. Density and font scale were restored to 420 and 1.0 after the pass.

Checkout with a line in the cart was not driven. Adding a cart line would write shared order-prep state. Cart was captured empty. The three-line widget on cart and checkout is cited from code.

Loading frames were not held. Data screens use `CircularProgressIndicator` (a spinner). No skeleton loader was found.

## Inventory

Role gate is `mobile/lib/main.dart` lines 86–94: no session goes to login; a farmer-seller goes to `FarmerShell`; a buyer goes to `BuyerShell`; any other role goes to `AdminGateScreen`.

| Screen | Path | Actor |
| --- | --- | --- |
| Login | `mobile/lib/screens/login_screen.dart` | Pre-login |
| Register | `mobile/lib/screens/register_screen.dart` | Pre-login |
| Admin gate | `mobile/lib/screens/admin_gate_screen.dart` | Other signed-in roles |
| Marketplace | `mobile/lib/screens/buyer/marketplace_screen.dart` | Buyer |
| Listing detail | `mobile/lib/screens/buyer/listing_detail_screen.dart` | Buyer |
| Cart | `mobile/lib/screens/buyer/cart_screen.dart` | Buyer |
| Checkout | `mobile/lib/screens/buyer/checkout_screen.dart` | Buyer |
| Order history | `mobile/lib/screens/buyer/order_history_screen.dart` | Buyer |
| Favorites | `mobile/lib/screens/buyer/favorites_screen.dart` | Buyer |
| Shop profile | `mobile/lib/screens/buyer/shop_profile_screen.dart` | Buyer |
| Profile | `mobile/lib/screens/profile/profile_screen.dart` | Buyer tab; farmer variant in the same area |
| Farmer profile | `mobile/lib/screens/profile/profile_screen.dart` (`FarmerProfileScreen`) | Farmer-seller |
| Settings | `mobile/lib/screens/profile/settings_screen.dart` | Both |
| Notifications | `mobile/lib/screens/notifications/notifications_screen.dart` | Both |
| My listings | `mobile/lib/screens/farmer/listings_screen.dart` | Farmer-seller |
| Listing form | `mobile/lib/screens/farmer/listing_form_screen.dart` | Farmer-seller |
| Tawad form | `mobile/lib/screens/farmer/tawad_form_screen.dart` | Farmer-seller |
| Walk-in sale | `mobile/lib/screens/farmer/walk_in_sale_screen.dart` | Farmer-seller |
| Incoming orders and order detail | `mobile/lib/screens/farmer/farmer_orders_screen.dart` | Farmer-seller |
| Crop care list | `mobile/lib/screens/farmer/crop_care_screen.dart` | Farmer-seller |
| Crop care category | `mobile/lib/screens/farmer/crop_care_category_screen.dart` | Farmer-seller |
| Crop care detail | `mobile/lib/screens/farmer/crop_care_detail_screen.dart` | Farmer-seller |
| Shop edit | `mobile/lib/screens/farmer/shop_edit_screen.dart` | Farmer-seller |
| Coming soon | `mobile/lib/screens/placeholders/coming_soon_placeholder.dart` | Unused placeholder |

Buyer tabs: Market, Orders, Favorites, Profile. Farmer tabs: Listings, Orders, Crop care. There is no cart, checkout, or marketplace tab on the farmer shell.

Shared widgets live under `mobile/lib/widgets/`: `price_breakdown.dart`, `status_pill.dart`, `produce_card.dart`, `care_guide_card.dart`, `app_header.dart`, `cart_icon_button.dart`, `notification_bell.dart`, `profile_avatar_button.dart`, `listing_active_badge.dart`, `dashed_photo_box.dart`, `category_color.dart`, `shop_profile_parts.dart`, `crop_care_author_chip.dart`.

Theme, type, and spacing:

- `ThemeData`, `ColorScheme`, and `AniHowColors` are in `mobile/lib/theme/anihow_theme.dart`.
- Spacing, the type scale, and peso formatting are in `mobile/lib/theme/anihow_space.dart` (`AniHowSpace`, `AniHowMoney`).

`PriceBreakdown` (`mobile/lib/widgets/price_breakdown.dart`) is the only shared three-line price widget. Cart, checkout, and the walk-in form use it. Order history and the farmer order list and detail do not.

## Section A. Spec checks

### 3a. Percentages — pass

No `%`, `percent`, or `pct` string exists under `mobile/lib/`. Tawad copy on cards is a peso amount (`₱20.00 off at 5 and above`). `mobile/lib/support/walk_in_quote.dart` divides a peso difference by quantity only to test the floor. That quotient is not shown.

### 3b. Three-line price — fail

`PriceBreakdown` prints `Listed`, `Tawad`, and `Total` through `AniHowMoney.peso` and does not strike through the listed price (`price_breakdown.dart` lines 24–30).

| Surface | Uses `PriceBreakdown` | What the user sees |
| --- | --- | --- |
| Cart | Yes, `cart_screen.dart` lines 152 and 166 | Not populated on device. Empty cart only. |
| Checkout | Yes, `checkout_screen.dart` lines 108, 115, 214, 221 | Not opened. Would require adding a cart line. |
| Walk-in, before record | Yes, `walk_in_sale_screen.dart` lines 280–285, only when `_quote != null` | Quantity left at 0, so the three lines were absent. The widget is the shared one. |
| Walk-in confirmation | Yes, `walk_in_sale_screen.dart` line 137 | Not re-recorded this pass. |
| Buyer order history | No | Total, then `Listed ₱…`. No tawad line. `order_history_screen.dart` lines 65–66. Device: `₱56.00` and `Listed ₱28.00` on the cancelled card; `₱160.00` on the completed card. |
| Farmer order list | No | Total only. Device: walk-in `₱130.00`, app order `₱160.00`. |
| Farmer order detail | No | Headline total, then `5 kg · listed ₱30.00` and the line total. No tawad line. `farmer_orders_screen.dart` lines 499–512. Device: `₱130.00`, `Cash received ₱130.00`, `5 kg · listed ₱30.00`, `₱130.00`. |

The listed price is not struck through and is not replaced by the discounted price. The fail is the missing tawad line on both order-detail surfaces. Listing cards show a one-line tawad summary, which is not a transaction total.

### 3c. Peso formatting — fail

`AniHowMoney.peso` is `₱` plus `toStringAsFixed(2)` (`anihow_space.dart` lines 33–35). It has no thousands separator. `TawadRule.summary` in `models.dart` repeats that shape. Form fields use a `₱ ` prefix on the listing form, tawad form, and walk-in amount field, and the typed value is not run through `peso`. Ratings use one decimal via `AniHowMoney.rating`, which is not money. Device examples: `₱28.00 / kg`, `₱30.00`, `₱130.00`, `₱160.00`.

### 3d. Farmer-seller cannot buy — pass

`main.dart` sends a farmer-seller only to `FarmerShell`. That shell’s tabs are Listings, Orders, and Crop care. No farmer screen references cart or checkout. On device the farmer chrome was My listings, Record walk-in sale, and the three tabs. No cart icon.

### 3e. Order statuses — pass

`StatusPill.order` maps exactly `placed`, `confirmed`, `ready`, `completed`, and `cancelled` (`status_pill.dart` lines 21–27). `no_show` is a cancellation reason labelled `No-show at handover` (`farmer_orders_screen.dart` line 26), and the cancel sheet says a no-show is not a separate status (line 655). Device: buyer history showed a Cancelled pill plus the text `No-show at handover`. Farmer orders showed five tabs, Placed through Cancelled. Notifications used the same status words.

### 3f. Walk-in orders — pass

Device, farmer Completed tab and detail for `AH-260921-YRMKV`: status Completed, label Walk-in, guest name Aling Rosa, cash received `₱130.00`. No review prompt. `canBeReviewed` is what gates `Review unlocked for the buyer` (`farmer_orders_screen.dart` line 506) and `Ready to review` (`order_history_screen.dart` line 72). The walk-in form labels guest name optional (`Guest name (optional)`, hint `For your reference only`).

### 3g. Crop labels — fail

`CategoryItem.fromJson` reads `name` and never `label_fil` or `label_en` (`models.dart` lines 72–83). Marketplace chips, listing crops, and the crop-care article all show `name`. Device: chip and article crop line were `Kamatis (smoke test)`, not a Filipino label. Crop-care category chips are the English constants `Crop care` and `Pest management` (`models.dart` lines 706–708). Settings shows a Language row fixed to `English` (`settings_screen.dart` lines 58–61). That row does not present a second language. No other bilingual pair was found. The fail is that Filipino labels supplied by the API are not shown.

### 3h. Farmer-seller own figures — none in the app

Section 10 puts units sold, sales per period, best-selling produce, and average discount in the CMS. No screen under `mobile/lib/` calls an analytics endpoint or shows those four figures. Farmer profile, listings, and orders show operational data (stock, order totals), not those summaries.

## Section B. Screen findings

States below are what this pass actually showed, plus the code path for loading and error where a frame was not held. Severity is Spec breach, Broken, Rough, or Minor.

### Login — pre-login

Captured at 360dp, font 1.3, dark. Populated form. Empty, loading, and error were not separate states (the form does not fetch a list).

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| No defect recorded | Dark, font 1.3 | — | Email, password, Sign in, and Create a buyer account fit. Sign in bounds were 144px tall at density 480, which is 48dp. |

### Register — pre-login

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Not opened on device | — | — | Reachable from Login, `Create a buyer account`. Not exercised this pass. |

### Admin gate

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Not opened on device | — | — | `main.dart` line 94. Smoke buyer and farmer-seller never land here. |

### Marketplace — buyer

Populated with two kamatis listings. Search field held a no-match string and the list stayed populated. Loading is a spinner in code (`marketplace_screen.dart` line 160). Error is `Text` of `snapshot.error` with no retry (line 163).

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Filipino crop label not shown | Populated | Spec breach | Chip text `Kamatis (smoke test)`. See 3g. |
| Search text clipped inside the field | Font 1.3 | Rough | Typed string rendered as `zzzznozzzznomatchmatc` with the end cut off by the submit icon. |
| Typed query does not filter until submit | Populated | Minor | `onSubmitted` calls `_reload` (`marketplace_screen.dart` line 81). The list stayed on both listings while the field held a no-match string. |
| Cart icon and bell are under 48dp | Populated, dark | Rough | Bounds `[792,152][912,272]` and `[912,152][1032,272]`: 120px at density 480 is 40dp. |
| Bottom navigation stays a light panel | Dark | Rough | Nav background is `AniHowColors.navBar` `#FDFCFA` in both themes (`anihow_theme.dart` lines 203–204). Device: cream bar under a dark list. |

### Listing detail — buyer

Opened `Kamatis, bagong ani` (no tawad on that listing). Price line `₱28.00 / Kilogram (kg)`, `50 available`. Add to cart was not pressed.

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| No three-line block on a listing that has no tawad | Populated | — | Unit price only. Three-line rule applies where tawad is part of a transaction total, not on this unit line. |
| Add to cart and Add to favorites meet 48dp | Populated | — | Bounds height 144px at density 480. |

### Cart — buyer

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Empty state is a plain sentence, centered | Empty | — | `Your cart is empty.` Dark page, no light panel. |
| Populated three-line price not shown on device | Populated | — | Shared `PriceBreakdown` at `cart_screen.dart` lines 152 and 166. Cart was empty, so the lines were not on screen. |

### Checkout — buyer

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Not opened on device | — | — | `PriceBreakdown` at `checkout_screen.dart` lines 108, 115, 214, and 221. Opening it populated would require a cart write. |

### Order history — buyer

Populated with a cancelled order and a completed order. Empty copy in code is `No orders yet.` (line 46). Error is `Text` of `snapshot.error` with no retry (line 42). Loading is a spinner (line 39).

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Tawad line missing | Populated | Spec breach | Card shows total and `Listed ₱28.00`. No tawad line. `order_history_screen.dart` lines 65–66. |
| Text column collapses to one word per line | Font 1.3 | Broken | `Mang` / `Tonyo` / `Farm`, order number split as `AH-260921` / `-MQ2V6`, address `Manggaha` / `n, General` / `Trias`. `ListTile` plus a trailing status pill. |
| Second card clipped by the navigation bar | Font 1.3 | Broken | Completed card stopped at `AH-26092` / `1-6LPRV` / `₱160.00` under the bar. |
| No-show is a reason under Cancelled | Populated | — | Pass for 3e. Pill `Cancelled`, line `No-show at handover`. |
| Light navigation bar | Dark | Rough | Same `#FDFCFA` bar as Marketplace. |

### Favorites — buyer

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Empty state is present | Empty | — | `No favorites yet.` Centered on the dark page. |
| Light navigation bar | Dark | Rough | Same `#FDFCFA` bar. |

### Shop profile — buyer

Opened from the seller name on the first card (`Mang Tonyo Farm`). Pickup-only copy was visible. Not walked for font overflow.

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Not fully reviewed | Populated | — | Screen reached. Error path is `Text` of `snapshot.error` with no retry (`shop_profile_screen.dart` line 125). |

### Profile — buyer

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| No defect recorded on the identity block | Populated, dark | — | `Smoke Buyer`, `smoke.buyer@anihow.local`, role `buyer`, rows for Order history, Favorites, Settings. |
| Light navigation bar | Dark | Rough | Same `#FDFCFA` bar. |

### Settings — both

Captured in Dark and in Light at font 1.3.

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| `System` splits across two lines | Font 1.3, light and dark | Broken | Segment read `Syste` / `m` inside the appearance control. |
| `Terms & privacy` is clipped at the bottom of the first viewport | Font 1.3 | Rough | A line cut through the label. The row’s bounds ran to the screen edge. Log out was below the fold until scroll. |
| Language is a fixed English label | Populated | Minor | `settings_screen.dart` lines 58–61. It does not show Filipino, and it does not switch language. |
| Light mode itself is consistent | Light | — | Cream page `#F8F6F0`, white cards, green app bar. Toggled from the Light segment and captured. |

### Notifications — both

Populated, then error after the API was stopped.

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Error is a plain sentence and has no retry control | Error | Rough | `Cannot connect. Check your internet connection and try again.` Centered. `notifications_screen.dart` lines 141–142. `ApiException.toString` returns that message (`api_client.dart` lines 15 and 590). The raw Dio text is only `debugPrint` (line 587). |
| Loading is a spinner | Loading (code) | Minor | Line 139. Not held on a frame. |
| Populated list is readable at font 1.3 | Populated, dark | — | Titles `Order cancelled`, `Order completed`, `Order ready`, `Order confirmed` wrap on spaces, not mid-word. |

### My listings — farmer-seller

Populated All tab. Low tab empty.

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Empty state is present | Empty (Low) | — | `No low-stock listings.` |
| Tawad on the card is one summary line, and it wraps cleanly | Populated, font 1.3 | — | `₱20.00 off at` / `5 and above`. Not a transaction total. |
| No cart or buy control | Populated | — | Pass for 3d. Header is bell, avatar, and a create FAB. |
| Light navigation bar | Dark | Rough | Same `#FDFCFA` bar. Tabs: Listings, Orders, Crop care. |

### Listing form, tawad form, shop edit, farmer profile — farmer-seller

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Not opened on device | — | — | Farmer-only routes. Listing form error path prints `snapshot.error` with no retry (`listing_form_screen.dart` line 250). |

### Incoming orders — farmer-seller

Placed tab empty. Completed tab populated with the walk-in and the app order.

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Tawad line missing on the order card | Populated | Spec breach | Cards show `₱130.00` and `₱160.00` only. See 3b. |
| Text column collapses and the next card is clipped | Font 1.3 | Broken | `AH-260921-` / `YRMKV`, `Buyer picks` / `up`. The Smoke Buyer card is cut off by the navigation bar at `AH-260921-`. |
| Cancelled tab label is clipped | Font 1.3 | Rough | Fifth tab rendered as `Cancell` at the right edge. Placed was off the left edge until the bar was scrolled. |
| Empty state is present | Empty (Placed) | — | `No placed orders.` |
| Walk-in is Completed, named, and not a review prompt | Populated | — | Pass for 3f. `Aling Rosa`, `Walk-in`, `Completed`. |
| Light navigation bar | Dark | Rough | Same `#FDFCFA` bar. |

### Farmer order detail — farmer-seller

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Tawad line missing | Populated | Spec breach | `₱130.00`, `Cash received ₱130.00`, `5 kg · listed ₱30.00`, `₱130.00`. `farmer_orders_screen.dart` lines 499–512. |
| No review prompt on the walk-in | Populated | — | Pass for 3f. Status pill `Completed`, subtitle `Walk-in`. |
| Detail body is full width at font 1.3 | Font 1.3, dark | — | Unlike the list card, the detail lines do not collapse to one word per line. Large empty region below the line item. |

### Walk-in sale — farmer-seller

Opened from Incoming orders. Quantity left at 0. Sale was not recorded.

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Three lines appear only after a quote exists | Quantity 0 | — | `if (_quote != null)` wraps `PriceBreakdown` (`walk_in_sale_screen.dart` lines 280–285). At quantity 0 the lines were absent, which matches that condition. |
| Guest name is optional | Populated form | — | Label `Guest name (optional)`. Pass for 3f. |
| Note field sits against the pinned Record sale button | Font 1.3 | Minor | The note box is partly visible above the button. Record sale itself is full width. |
| Error path has no retry | Error (code) | Rough | `walk_in_sale_screen.dart` line 182, `Text('$_error')`. Not re-opened after the API stop. |

### Crop care list — farmer-seller

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Buyers cannot open crop care | Buyer shell | Spec breach | Spec changelog: a buyer has no farm and must read published crop-care (`AniHow_Project_Instructions_v3.3.md` around the section 6 clarification). The only in-app list is `CropCareScreen` inside `FarmerShell`. |
| Empty copy exists in code | Empty (code) | — | `No crop-care articles yet.` (`crop_care_screen.dart` line 103). Device list was populated. |
| Article card is readable at font 1.3 | Populated, dark | — | `Watching for pests on kamatis` and the summary wrap on spaces. |

### Crop care category — farmer-seller

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Not opened on device | — | — | Filters on the list go to this screen. Error path is `Text` of `snapshot.error` (`crop_care_category_screen.dart` line 50). |

### Crop care detail — farmer-seller

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Light page in dark mode, and the summary is nearly unreadable | Dark | Broken | `backgroundColor: AniHowColors.cream` (`crop_care_detail_screen.dart` line 40). Device: cream page, white card, pale summary text, dark body text under the divider. |
| App bar title is ellipsized | Font 1.3 | Rough | `Watching for pests on k...` |
| Crop shown is the taxonomy name, not a Filipino label | Populated | Spec breach | Card line `Kamatis (smoke test)`. See 3g. |

### Coming soon

| Issue | State | Severity | Evidence |
| --- | --- | --- | --- |
| Not opened on device | — | — | Placeholder widget. Not on the buyer or farmer tab bars. |

## Section C. Cross-cutting patterns

Theme bypasses. No `Color(0x…)` hex exists outside `anihow_theme.dart`. Widgets that skip `ColorScheme` and paint `Colors.white`, `Colors.white70`, or `Colors.transparent` directly:

| File | Lines |
| --- | --- |
| `mobile/lib/widgets/cart_icon_button.dart` | 8 |
| `mobile/lib/widgets/notification_bell.dart` | 105 |
| `mobile/lib/widgets/app_header.dart` | 50, 56, 67, 89, 102 |
| `mobile/lib/screens/buyer/listing_detail_screen.dart` | 142, 154 |
| `mobile/lib/widgets/listing_active_badge.dart` | 18 |
| `mobile/lib/widgets/care_guide_card.dart` | 34, 47 |
| `mobile/lib/screens/notifications/notifications_screen.dart` | 125, 126 |
| `mobile/lib/widgets/profile_avatar_button.dart` | 28, 64 |
| `mobile/lib/widgets/produce_card.dart` | 191 |
| `mobile/lib/widgets/dashed_photo_box.dart` | 36 |

`AniHowColors.mint` (`#2E8B57`) and `AniHowColors.categoryChip` (`#D1E7DD`) are declared and not referenced. `mint` duplicates `switchOn` and `ready`.

`navigationBarTheme.backgroundColor` is `AniHowColors.navBar` (`#FDFCFA`) for both light and dark (`anihow_theme.dart` lines 203–204). Every tab shell shows that light bar in dark mode.

`TextStyle(` outside `anihow_theme.dart` (counts). These set weight and size directly, usually from `AniHowSpace`, instead of `textTheme`:

| File | `TextStyle(` count |
| --- | --- |
| `shop_profile_screen.dart` | 9 |
| `app_header.dart` | 4 |
| `listing_detail_screen.dart` | 3 |
| `notifications_screen.dart` | 3 |
| `profile_screen.dart` | 3 |
| `shop_profile_parts.dart` | 3 |
| `coming_soon_placeholder.dart` | 1 |
| `crop_care_detail_screen.dart` | 1 |
| `login_screen.dart` | 1 |
| `register_screen.dart` | 1 |
| `settings_screen.dart` | 1 |
| `tawad_form_screen.dart` | 1 |
| `care_guide_card.dart` | 1 |
| `listing_active_badge.dart` | 1 |
| `produce_card.dart` | 1 |
| `profile_avatar_button.dart` | 1 |
| `status_pill.dart` | 1 |

`fontSize:` outside the theme file follows the same files (shop profile 9, app header 4, listing detail 3, notifications 3, profile 3, shop profile parts 3, and one each on care guide, author chip, login, register, settings, badge, avatar, status pill, produce card, crop care detail, coming soon). Sizes point at `AniHowSpace` rather than a raw number, but they do not go through `textTheme`.

`EdgeInsets` with a raw number outside the spacing constants: `crop_care_detail_screen.dart` line 87, `EdgeInsets.all(16)`, which happens to equal `AniHowSpace.screen`. Other `EdgeInsets` uses pass `AniHowSpace` values. The theme file itself uses `EdgeInsets.symmetric(horizontal: 8, vertical: 8)` on tabs (line 193) and `EdgeInsets.zero` on cards.

Duplicated pieces:

- Three-line price exists once (`PriceBreakdown`) and is skipped by both order histories.
- Peso formatting exists as `AniHowMoney.peso` and again as `TawadRule.summary`, plus raw `₱ ` prefixes on inputs.
- Empty, loading, and error UI are copied per screen. Loading is always a centered spinner. Error is always a centered `Text` of the message. There is no shared empty, loading, or error widget, and no retry button. Some lists wrap a successful body in `RefreshIndicator`; the error branch does not.

Order-list layout: buyer history and farmer orders both put a long `Column` in a `ListTile` subtitle beside an avatar and a `StatusPill`. At font 1.3 on 360dp that column becomes a few characters wide and the next card slides under the navigation bar.

## Section D. Counts

The light navigation bar is one pattern, counted once, even though section B notes it on each tab shell. Missing tawad on the farmer list and the farmer detail is one spec breach.

| Severity | Count | Items |
| --- | --- | --- |
| Spec breach | 5 | Buyer order history has no tawad line. Farmer order list and detail have no tawad line. Peso format has no thousands separator. Filipino crop labels are not shown. Buyers have no route to published crop care. |
| Broken | 4 | Order-history column collapse at font 1.3. Farmer order-card collapse and clip. Settings `System` splits to `Syste` / `m`. Crop-care detail is a light page in dark mode and its summary is unreadable. |
| Rough | 7 | Light navigation bar in dark mode. Cancelled tab clipped. Crop-care title ellipsized. Terms row clipped. Search field clipped. Cart and bell targets at 40dp. Error copy with no retry. |
| Minor | 4 | Language row fixed to English. Search filters only on submit. Loading is a spinner and there is no shared empty, loading, or error widget. Walk-in note field sits tight against the pinned button. |
| Total | 20 | |

## Section E. Colour palette

### ColorScheme and ThemeData

`ColorScheme.fromSeed` (`anihow_theme.dart` lines 84–93) pins these roles. Error, tertiary, and the container roles are generated from the seed and have no source hex in the project.

| Hex | Role |
| --- | --- |
| `#1F5A3E` | `primary` and `secondary` (seed, `brand`, `deepGreen`). App bar, filled and outlined buttons, FAB, focused input, selected tab and nav icon. |
| `#FFFFFF` | `onPrimary`, `onSecondary`, app bar foreground, badge text, button labels, switch thumb. |
| `#F8F6F0` | Light `surface`, scaffold, and canvas (`cream`). |
| `#1E2421` | Light `onSurface` (`text`). |
| `#121A17` | Dark `surface`, scaffold, and canvas (`darkBackground`). |
| `#F3F0E8` | Dark `onSurface` (`darkText`). |

Also set on `ThemeData`, not only on `ColorScheme`:

| Hex | Role |
| --- | --- |
| `#FFFFFF` / `#1C2622` | Card, light / dark. |
| `#E8E4DA` / `#2E3A34` | Divider and input border, light / dark. |
| `#E63946` | Badge. |
| `#2E8B57` / `#D6D1C7` | Switch track on / off. |
| `#6C757D` | Unselected tab (`muted`). |
| `#FDFCFA` | Navigation bar, both brightness values. |
| `#D7EADF` | Navigation indicator. |
| `#6F7872` | Inactive navigation icon and label. |
| `#1F5A3E` at 16% | Selected chip. |
| `#1E2421` at 10% | Card shadow. |
| On-surface at 70% | `labelSmall`. |

### Status and category tokens in `AniHowColors` (not ColorScheme roles)

| Hex | Name | Role |
| --- | --- | --- |
| `#BA7517` | `pending`, `root` | Placed. |
| `#58A67D` | `sage` | Confirmed. |
| `#2E8B57` | `ready`, `switchOn`, `mint` | Ready. `mint` is unused. |
| `#378ADD` | `completed` | Completed. |
| `#888780` | `cancelled` | Cancelled. |
| `#1C5635` | `inStock` | In-stock label. |
| `#D9F5DF` | `inStockBg` | In-stock chip background. |
| `#7CD96C` | `inStockAvatar` | In-stock placeholder. |
| `#B94A3E` | `lowStock` | Low-stock label. |
| `#FCE3DE` | `lowStockBg` | Low-stock chip background. |
| `#E88A83` | `lowStockAvatar` | Low-stock placeholder. |
| `#639922` | `leafy` | Category. |
| `#D85A30` | `fruit` | Category. |
| `#7F77DD` | `eggplant` | Category. |
| `#3A7A58` | `avatarOnBrand` | Avatar on the green header. |
| `#E2E8F0` | `photoPlaceholder` | Empty photo. |
| `#E5E7EB` | `cardBorder` | Card border. |
| `#D1E7DD` | `categoryChip` | Declared, unused. |
| `#FFFFFF` | `card` | Light card. |
| `#F8F6F0` | `cream` | Light scaffold. Also forced as the crop-care detail background in dark mode. |

### Colours used outside ThemeData

No extra hex. The call sites in section C use `Colors.white`, `Colors.white70`, or `Colors.transparent`, or they read `AniHowColors` directly. The one that ignores dark mode is `crop_care_detail_screen.dart` line 40, `AniHowColors.cream`.

## Device restoration

Density override was cleared and font scale set back to 1.0. The API is running again at `http://127.0.0.1:8000`. In-app theme was left on Dark, which is how Settings was found at the start of the pass.
