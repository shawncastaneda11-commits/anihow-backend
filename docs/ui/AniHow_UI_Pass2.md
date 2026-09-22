# AniHow UI Pass 2 — Money display

**Scope:** pesos / tawad presentation on Flutter order surfaces only. No backend edits.  
**Spec:** `docs/spec/AniHow_Project_Instructions_v3.3.md` §8 — pesos only; listed never overwritten; tawad its own line; three lines stay three.  
**Device:** Android emulator `emulator-5554`. Shots under `docs/ui/_pass2_preview/`.

---

## 0. Read-only API check

**Result: PASS — proceed with Flutter-only work.**

| Surface | Endpoint | Fields present |
| --- | --- | --- |
| Order | `GET /api/buyer/orders`, `GET /api/farmer/orders` (and show) via `OrderResource` | `subtotal`, `tawad_total`, `total` |
| Order line | `OrderItemResource` nested under `items` | `quantity`, `listed_price`, `line_subtotal`, `tawad_amount`, `line_total` (+ `unit`, `listing_name`) |

Flutter parsers: `OrderRecord` / `OrderItemRow` in `mobile/lib/models/models.dart` map those keys (`subtotal` → `listedTotal`, `tawad_total` → `tawadDisplay`, item `line_subtotal` / `listed_price` / `tawad_amount`).

Nothing missing; backend was not changed.

---

## Changelog

| File | Change | Reason |
| --- | --- | --- |
| `mobile/lib/theme/anihow_space.dart` | `AniHowMoney.peso` adds thousands separators (no `intl`; not in pubspec) | Spec example `₱1,250.00` |
| `mobile/lib/models/models.dart` | Import theme; `TawadRule.summary` uses `AniHowMoney.peso` | One peso formatter |
| `mobile/lib/screens/buyer/order_history_screen.dart` | Lone listed/total lines → order-level `PriceBreakdown` | Buyer’s only order view; three lines |
| `mobile/lib/screens/farmer/farmer_orders_screen.dart` | Detail: order-level `PriceBreakdown`; line `qty · listed unit → listed line_subtotal`; cash received stays separate. List card unchanged (total only) | Listed never overwritten by post-tawad |
| `mobile/test/widget_test.dart` | Assert `AniHowMoney.peso(1250) == '₱1,250.00'` | Guard thousands format |

**Untouched by design:** cart/checkout already used `PriceBreakdown`; money inputs keep `₱` prefix + raw typed digits; farmer list cards stay total-only.

---

## Screenshots (described)

| Surface | Shot | What shows |
| --- | --- | --- |
| Marketplace (buyer) | `01-buyer-market.png` | Listing card summary uses peso tawad (“₱20.00 off at 5 and above”) |
| Cart | `02-cart-light-f10.png` | Split banner “2 orders”; each seller group Listed / Tawad / Total (incl. Tawad ₱0.00) |
| Checkout | `03-checkout-light-f10.png` | Same three lines + listed unit; “Place 2 orders” |
| Orders placed | `04-placed-light-f10.png` | Split confirmed (`AH-260922-HLK8P` + `AH-260922-FGY4B`) with three lines each |
| Buyer order history | `05-order-history-light-f10.png` | Cards use `PriceBreakdown`; zero-tawad still shows Tawad ₱0.00 |
| Tawad form | `09-tawad-form-light-f10.png` | “Peso off…” rules only; `₱` prefix on amount; **no % / percent / pct** |
| Walk-in quote | `11-walkin-quote-light-f10.png` | Live quote Listed ₱150.00 / Tawad ₱20.00 / Total ₱130.00; amount field raw `130` with `₱` prefix |
| Walk-in confirm | `12-walkin-confirm-light-f10.png` | Dialog three lines + Amount received ₱130.00 |
| Farmer list (completed) | `13-farmer-orders-completed.png` | **Total only** on card (e.g. ₱130.00) |
| Farmer detail (app order) | `14-farmer-order-detail-light-f10.png` | Breakdown + `6 kg · ₱30.00 → ₱180.00` + Cash received |
| Farmer detail (walk-in) | `14b-walkin-order-detail-light-f10.png` | Listed ₱150.00 / Tawad ₱20.00 / Total ₱130.00; `5 kg · ₱30.00 → ₱150.00`; Cash received ₱130.00 |
| Dark / font 1.3 | `14c`–`14e`, `15-farmer-home-dark-f10.png` | Attempted via `cmd uimode night` + `font_scale` 1.3; see judgment calls |

---

## Listed − Tawad = Total

Checked every order returned by buyer and farmer list APIs before reseed: **0 mismatches**.

Examples verified on device / API:

| Order | Listed | Tawad | Total | OK |
| --- | ---: | ---: | ---: | --- |
| Buyer split A (`AH-260922-HLK8P`) | 150 | 20 | 130 | yes |
| Buyer split B (`AH-260922-FGY4B`) | 56 | 0 | 56 | yes |
| Walk-in (`AH-260922-EDS2Q`) | 150 | 20 | 130 | yes |
| Earlier farmer completed (`AH-260921-6LPRV`) | 180 | 20 | 160 | yes |

No order failed the identity.

---

## Checks

| Check | Result |
| --- | --- |
| Search `mobile/lib/` for `%` / `percent` / `pct` | **3 hits, none are money UI:** (1) modulo `% 3` in `AniHowMoney.peso`; (2–3) comments in `status_pill.dart` about “16% tint” contrast. No percentage tawad/price copy. |
| `flutter analyze` | **No issues found** |
| `flutter test` | **19 passed, 1 failed** |
| Known fail `cart_checkout_live_test` | **Failure message changed.** Was previously “expected 2 seller groups, got 1”. Now: `Expected: <201> Actual: <422>` on the second cart `POST` (live stock/validation against local DB). Not a Pass 2 display regression. |
| Reseed | `php artisan migrate:fresh --seed` then `php artisan db:seed --class=SmokeTestSeeder` → **26 passed, 0 failed** |

---

## Judgment calls

| Item | Decision | Reason |
| --- | --- | --- |
| Cart seeded via API for the two-seller + tawad qty, then cart/checkout/history driven on device | Acceptable | Same payloads the UI posts; ensures tawad condition (≥5 kg) and split without flaky multi-tap add-to-cart |
| Farmer list cards stay total-only | Left as instructed | List summary; breakdown lives on detail |
| Tawad ₱0.00 always shown | Kept | Spec: three lines stay three; hide-none |
| Line item shows `line_subtotal` (listed), never `line_total` | Deliberate | Spec: listed line total, not post-tawad |
| System night mode for dark shots | Weak / unreliable here | App Appearance was Light; system `uimode night` did not flip scaffold to dark on recreate. Font 1.3 applied via `font_scale`. Prefer in-app Appearance toggle in a later pass if dark money screenshots are required again |
| `intl` | Not added | Absent from pubspec; thousands implemented inline |

---

## Stop

Pass 2 complete. No further UI passes in this turn.
