# AniHow UI Pass 1 — Theme foundation

**Scope:** theme tokens, typography, shapes, and colour routing only. No screen layout or behaviour changes.  
**Palette rule:** no `AniHowColors` hex changed; no new hue; derived lightness shades only.  
**Device:** Android emulator `emulator-5554`, `font_scale` 1.0, density 420. Screenshots taken light and dark for the required screens.

---

## Changelog

| File | Change | Reason |
| --- | --- | --- |
| `mobile/pubspec.yaml` | Register `PlusJakartaSans` (400/500/600/700) from `assets/fonts/` | Theme fontFamily needs app-bundled faces |
| `mobile/lib/theme/anihow_theme.dart` | `fontFamily: PlusJakartaSans`; cards radius 16, no border, soft shadow `text` @ 10%; inputs radius 12, filled card, hairline / focused brand; filled + outlined `StadiumBorder`, `minimumSize` height 52, SemiBold labels; chips `StadiumBorder`; textTheme Bold / SemiBold / Regular from `AniHowSpace`; `navigationBarTheme` → `navBar` light / `darkCard` dark; remove unused `mint` + `categoryChip` | Pass 1 theme foundation (Pass 0 §C / §E) |
| `mobile/lib/screens/login_screen.dart` | Error text → `colorScheme.error` | Stop using fruit for errors |
| `mobile/lib/screens/register_screen.dart` | Error text → `colorScheme.error` | Same |
| `mobile/lib/widgets/app_header.dart` | Brand-surface text / icons → `colorScheme.onPrimary` (and 86% alpha where subtitle needed) | Theme routing; leave transparent alone |
| `mobile/lib/widgets/cart_icon_button.dart` | Icon colour → `onPrimary` when on brand | Theme routing |
| `mobile/lib/widgets/notification_bell.dart` | Icon → `onPrimary` | Theme routing |
| `mobile/lib/widgets/profile_avatar_button.dart` | Foreground → `onPrimary` | Theme routing |
| `mobile/lib/screens/notifications/notifications_screen.dart` | Mark-all foreground / disabled → `onPrimary` / 70% | Theme routing (was white / white70) |
| `mobile/lib/screens/buyer/listing_detail_screen.dart` | Brand-surface whites → `onPrimary` | Theme routing |
| `mobile/lib/widgets/produce_card.dart` | Brand-surface white → `onPrimary` | Theme routing |
| `mobile/lib/widgets/care_guide_card.dart` | Card colour → `Theme.cardTheme.color`; remove border; avatar FG → `onPrimary` | Dark mode + borderless cards |
| `mobile/lib/screens/farmer/crop_care_detail_screen.dart` | Scaffold / cards use Theme + `cardTheme.color`; no cream / card / cardBorder hardcodes; borders removed | Dark-mode readable crop care |
| `mobile/lib/widgets/status_pill.dart` | Order-status label uses same-hue HSL lightness tweak on 16% tint | ≥4.5:1 light; readable dark |

---

## Status pill contrast (order statuses)

Text on the **16% alpha tint** of the status colour, composited over the card surface (`#FFFFFF` light / `darkCard` `#1C2622` dark).  
**Before:** label colour = raw status token. **After:** same hue/saturation, lightness set in `status_pill.dart`.

| Status | Token | Light before | Light after | Dark before | Dark after |
| --- | --- | ---: | ---: | ---: | ---: |
| Placed | `pending` `#BA7517` | 3.10 | **4.59** | 3.44 | **4.61** |
| Confirmed | `sage` `#58A67D` | 2.52 | **4.56** | 4.12 | **4.57** |
| Ready | `ready` `#2E8B57` | 3.49 | **4.64** | 3.05 | **4.61** |
| Completed | `completed` `#378ADD` | 3.00 | **4.64** | 3.51 | **4.60** |
| Cancelled | `cancelled` `#888780` | 3.05 | **4.60** | 3.47 | **4.66** |

Solid stock chips (`inStock` / `lowStock` with fixed backgrounds) keep a dark shade of the status colour; they are not on the 16% tint path.

---

## Verification

| Check | Result |
| --- | --- |
| `flutter analyze` | **No issues found** |
| `flutter test` (full suite) | **19 passed, 1 failed** |
| Pass count vs pre-theme baseline | **Equal** (same 19 / 1). Failure is `cart_checkout_live_test` (“expected 2 seller groups, got 1”) — live API / smoke data, not theme |
| Offline unit/widget subset | Theme-invariant; palette hex test still passes |

### Emulator matrix (font scale 1.0)

| Screen | Light | Dark |
| --- | --- | --- |
| Login | Cream scaffold; brand header; radius-12 filled inputs; Stadium Sign in (h≈52); Plus Jakarta Sans | Same structure on dark background; readable fields and brand header |
| Marketplace | Cream list; radius-16 cards, soft shadow, no border; Stadium chips; light nav | Dark cards / surfaces; **nav bar is dark** (`darkCard`); Market tab indicator readable |
| Listing detail | Brand app bar with `onPrimary` icons; body follows theme | Same routing on dark scaffold |
| Buyer order history | Status pills with darker same-hue labels on tint | Pills remain readable on dark cards |
| Farmer incoming orders | Stadium Confirm / Cancel; Placed pill contrast OK; light nav | **Dark nav**; Placed pill readable; Stadium buttons |
| Crop care detail | Theme scaffold + borderless cards; body text readable | **Readable** white/off-white text on dark cards; no cream/border leftovers |
| Settings | Light Appearance segmented control; Stadium Log out | Dark selected; dark surfaces |

**Confirmed:** dark-mode bottom nav is dark (not `#FDFCFA`). Crop care detail is readable in dark mode.

---

## Judged / skipped

| Item | Decision | Reason |
| --- | --- | --- |
| `listing_active_badge.dart` still uses `Colors.white` on brand | Left as-is | Not in the Pass 1 routing file list; layout/badge behaviour out of scope |
| `AniHowColors.cardBorder` token kept | Left declared | Still referenced elsewhere (e.g. active badge); Pass 1 only removed borders on crop-care cards |
| Theme `onPrimary` / badge `textColor` / switch thumb still literal white inside `anihow_theme.dart` | Kept | Defining the scheme itself; not widget-level bypass of brand surfaces |
| `Colors.transparent` | Untouched | Explicit Pass 1 instruction |
| Binary emulator PNGs | Not committed under `docs/ui/` | Report describes captures; avoid repo bloat |
| Buyer login account | Used `smoke.buyer@anihow.local` | Local DB only had smoke buyer (not `ana.buyer@…`) |
| Live cart-split test failure | Not fixed | Pre-existing / data-state; equal before & after; not theme |
| Layout / spacing / peso / three-line price | Not touched | Later passes per Pass 0 |

---

## Stop

Pass 1 complete. No further UI passes in this turn.
