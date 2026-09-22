# AniHow UI Pass 1B — Login, crop care filter, dark accent contrast

**Scope:** dark accent contrast, login/register FreshCart-style header, crop care segmented filter. No behaviour changes.  
**Palette rule:** no `AniHowColors` hex changed; no new hue; darker brand for the header gradient is a derived lightness only.

---

## Changelog

| File | Change | Reason |
| --- | --- | --- |
| `mobile/lib/theme/anihow_theme.dart` | Dark: `textButtonTheme` foreground and selected NavigationBar icon/label use `AniHowColors.sage`; light still uses brand | Brand on dark surfaces failed WCAG; sage clears 4.5:1 |
| `mobile/lib/widgets/app_header.dart` | `brandMark` header: full-width, behind status bar (content padded by top inset), vertical brand → darker-brand gradient, bottom radius 28; optional `leading` | FreshCart-style auth chrome |
| `mobile/lib/screens/login_screen.dart` | Headline “Sign in” + muted “Welcome back”; email mail prefix, password lock prefix; keep visibility toggle, fields, errors, navigation | Pass 1B login layout |
| `mobile/lib/screens/register_screen.dart` | Same brand header as login; back via header `leading`; fields/validation unchanged | Consistency with login chrome |
| `mobile/lib/screens/farmer/crop_care_screen.dart` | Horizontal chips → full-width `SegmentedButton` (All / Crop care / Pest management), `showSelectedIcon: false`, list horizontal padding; labels in `FittedBox(scaleDown)` | Equal-width filter; no clip at font scale 1.3 |

---

## Dark accent contrast

Surfaces: text button on `darkBackground` `#121A17`; selected nav label/icon on `darkCard` `#1C2622`.

| Pairing | Before (brand `#1F5A3E`) | After (sage `#58A67D`) |
| --- | ---: | ---: |
| TextButton on dark background | 2.18 | **6.04** |
| Selected nav label/icon on dark nav | 1.92 | **5.31** |

Both after ratios ≥ 4.5:1. Light theme accents unchanged (still brand).

---

## Verification

| Check | Result |
| --- | --- |
| `flutter analyze` | **No issues found** |
| `flutter test` | **19 passed, 1 failed** (unchanged count) |
| Known failure | `test/cart_checkout_live_test.dart` — *multi-seller cart splits at checkout through client request shapes* (live API / seller-group count; not theme) |
| Note | One earlier suite run also hit a transient `tawad_ui_live_test` **429**; re-run settled at 19/1 with only the cart-checkout failure |

### Emulator matrix

Device `emulator-5554`, density 420. Screens: login, register, crop care list, admin gate (`admin@anihow.local` → `AdminGateScreen`).

| Screen | Light 1.0 | Dark 1.0 | Light 1.3 | Dark 1.3 |
| --- | --- | --- | --- | --- |
| Login | Full-bleed gradient header, Sign in / Welcome back, mail+lock icons | Same; Create account link in sage | Same, larger type | Same |
| Register | Same header + back; fields unchanged | Same | Same | Same |
| Crop care | Segmented All / Crop care / Pest management | Sage selected nav; segments readable | **Pest management** scales down, no clip | Same |
| Admin gate | Super admin + Filament URL + Log out | Same structure on dark | Readable | Readable |

**Confirmed:** dark selected nav uses sage; crop care “Pest management” does not clip at font scale 1.3 on ~360dp width.

---

## Judgment calls

| Item | Decision | Reason |
| --- | --- | --- |
| Header gradient end colour | HSL lightness × 0.55 of brand (not a named palette token) | Derived shade of brand; no new hue / no hex edit on existing tokens |
| Register back control | `IconButton` in header `leading` | Replacing `AppBar` removed the automatic back; needed to keep navigation usable |
| SegmentedButton `All` value | Sentinel `__all__` mapped to `category: null` | Same API filter behaviour as before |
| Label fit at 1.3 | `FittedBox(BoxFit.scaleDown)` on every segment label | Meets “scale down rather than change the text” without clipping |
| Admin gate chrome | Left as simple `AppHeader` (non-`brandMark`) | Pass 1B only applied FreshCart header to login/register |
| Screenshot binaries | Not committed under `docs/ui/` | Captured for verification only |

---

## Stop

Pass 1B complete. No further UI passes in this turn.
