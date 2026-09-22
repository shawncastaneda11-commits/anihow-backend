# AniHow UI Pass 1C — Login and register layout

**Scope:** login and register layout only. Fields, validation, errors, and navigation unchanged.  
**Palette rule:** no `AniHowColors` hex changes; no new hues (header gradient end stays derived brand lightness from Pass 1B).  
**Shots:** `docs/ui/_pass1c_preview/`.

---

## Changelog

| File | Change | Reason |
| --- | --- | --- |
| `mobile/lib/widgets/auth_layout.dart` | **New.** Shared scrollable auth chrome: full-page brand→darker-brand gradient; ~34% header with faint circles, white 64dp / radius-20 logo tile + leaf, AniHow + tagline; sheet overlaps 28dp, top radius 28, card/`darkCard` fill to bottom; light status-bar icons; `resizeToAvoidBottomInset`; header brand block in `FittedBox(scaleDown)` | Pass 1C layout; one scrollable page; keyboard keeps focus + Sign in above IME |
| `mobile/lib/screens/login_screen.dart` | Replace fixed `AppHeader` + `Expanded` list with `AuthLayout`; keep Sign in / Welcome back, icons, errors, button, Create account link | Layout only |
| `mobile/lib/screens/register_screen.dart` | Same shell; title **Create account**; back via header `leading`; fields/Register button unchanged | Same chrome as login |

`AppHeader` left intact for marketplace / admin gate (non-auth).

---

## Screenshots (described)

Device `emulator-5554`. Matrix: light/dark × font 1.0 / 1.3 × login & register × top / bottom / password keyboard.

| Shot pattern | What shows |
| --- | --- |
| `login-*-top` | Header ~⅓, logo tile, sheet overlap, Sign in / Welcome back, fields, button, Create account; no empty band under sheet |
| `login-*-bottom` | Same (short form often fills without scrolling); confirms sheet to bottom |
| `login-*-keyboard` | Password focused; Sign in button remains above the soft keyboard |
| `register-*-top` | Same header; Create account; full name → confirm password + Register; back chevron on brand |
| `register-*-bottom` / `*-keyboard` | Scrolled / password field with keyboard; Register stays reachable |

Dark shots use app `ThemeMode.dark` (SharedPreferences `anihow_theme_mode=dark`); sheet is `darkCard`. Light sheet is white card on continuing brand gradient.

---

## Verification

| Check | Result |
| --- | --- |
| `flutter analyze` | **No issues found** |
| `flutter test` | **19 passed, 1 failed** |
| Known fail | `cart_checkout_live_test` — `Expected: <2> Actual: <1>` (seller groups / live data; not layout) |
| Overflow | Early header `RenderFlex overflowed by ~4–7px` fixed with `FittedBox`; **no `overflowed by` in the post-fix flutter run log** |
| Keyboard | Confirmed on login password: field + Sign in visible above IME |

---

## Judgment calls

| Item | Decision | Reason |
| --- | --- | --- |
| New `AuthLayout` vs extending `AppHeader` | New widget | Auth needs full-page gradient + overlapping sheet; marketplace header stays as-is |
| Header height `0.34 * viewport` after keyboard inset | Kept | With `resizeToAvoidBottomInset`, viewport shrinks so focused controls stay usable |
| Circle opacity `0.07` | Within 6–8% | Spec range; white on brand |
| Logo tile always `AniHowColors.card` (white) | Kept | Spec: white rounded tile; leaf stays brand |
| Dark mode via prefs, not only `cmd uimode` | Required | App had `anihow_theme_mode=light` locked; system night alone did not flip Flutter theme |
| Register muted subtitle | Omitted | Spec only asks for title “Create account”; no prior register subtitle |
| Screenshot binaries under `_pass1c_preview/` | Kept for this pass | Verification evidence; report describes them |

---

## Stop

Pass 1C complete. No further UI passes in this turn.
