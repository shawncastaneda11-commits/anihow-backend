# AniHow Diagnostic D1

**Mode:** read-only. No code or tests changed for this diagnostic.  
**Audit baseline commit:** `86a6182` (`UI audit pass 0`).  
**Note:** `HEAD` is currently that same commit; UI/CMS work and the Laravel test growth below live in the **working tree** (uncommitted). Strict `git diff 86a6182..HEAD` is empty; evidence uses **working tree vs `86a6182`**.

---

## 1. `cart_checkout_live_test` and 422

### Run on reseeded database

Commands:

1. `php artisan migrate:fresh --seed`
2. `php artisan db:seed --class=SmokeTestSeeder`
3. Same request sequence as `mobile/test/cart_checkout_live_test.dart` against `http://127.0.0.1:8000/api`
4. `flutter test test/cart_checkout_live_test.dart`

**Result on clean reseed: no 422.** Both cart `POST`s returned **201**; checkout returned **201** with **2** orders; Flutter live test **passed**.

Request bodies used (unchanged client shapes):

```json
{"listing_id":2,"quantity":"6"}
{"listing_id":1,"quantity":"2"}
{"fulfillment_preference":"buyer_pickup","fulfillment_note":"Pass 8 multi-seller split."}
```

### 422 body (when live stock is exhausted — matches Pass 2 failure mode)

Pass 2 recorded `Expected: <201> Actual: <422>` on the **second** cart `POST`. That is reproducible when sellable stock for the second listing is gone after prior live checkouts (not after a fresh reseed).

Captured validation response:

```json
{"message":"Only -3.00 available.","errors":{"quantity":["Only -3.00 available."]}}
```

(HTTP **422**; field `quantity`.)

### Flutter request-body builders since audit

| File | Diff vs `86a6182` | Formatted money in payload? |
| --- | --- | --- |
| `mobile/lib/services/cart_requests.dart` | **None** (blob hash identical) | No |
| `mobile/lib/services/tawad_requests.dart` | **None** | No |
| `mobile/lib/services/api_client.dart` | **None** | No — still posts raw `quantity` / `amount_received` / `discountAmount` strings |
| `mobile/lib/screens/buyer/cart_screen.dart` | Not in request path for checkout body | Display only |
| `mobile/lib/screens/buyer/checkout_screen.dart` | No payload change from Pass 2 money | Display only |
| `mobile/lib/screens/farmer/walk_in_sale_screen.dart` | Walk-in gate / UI; still `amountReceived: _amountReceived.text.trim()` | Display uses `AniHowMoney.peso`; **request does not** |
| `mobile/lib/screens/farmer/tawad_form_screen.dart` | Pass 2 display/`prefixText` only | `discountAmount: amount` from text field, not `summary` |
| `mobile/lib/screens/farmer/listing_form_screen.dart` | `prefixText: '₱ '` decoration only | `price_per_unit: _price.text.trim()` |
| `mobile/lib/models/models.dart` | `TawadRule.summary` now calls `AniHowMoney.peso` | **Display getter only** — never sent on the wire |

`AniHowMoney.peso`, `TawadRule.summary`, and `₱`/comma formatting do **not** reach cart, checkout, order-complete, tawad-save, or walk-in request maps.

### Cause

**Reseed / live DB state** (pre-existing flaky live dependency), **not** a Pass 2 regression.

- Pass 2 only changed **display** formatting (`AniHowMoney.peso` / `TawadRule.summary`).
- Request builders are byte-identical to the audit commit.
- Clean reseed → live test green; dirty stock → 422 on `quantity` as above.
- No Pass 2 line injects formatted money into a request payload.

---

## 2. Backend test count

### Strict `git diff 86a6182..HEAD --stat -- tests/`

**Empty** — `HEAD` == `86a6182`.

### Working tree vs audit (`git diff 86a6182 --stat -- tests/` + untracked)

| Path | Change | Methods added | Pass / origin |
| --- | --- | --- | --- |
| `tests/Feature/Api/FarmPriceGuardTest.php` | Modified (+155 lines) | `test_a_raised_farm_floor_flags_stranded_listings_without_blocking_or_repricing`; `test_a_content_editor_can_write_an_override_on_their_own_farm`; `test_a_content_editor_cannot_write_an_override_on_another_farm`; `test_a_super_admin_can_write_an_override_on_any_farm`; `test_neither_role_can_loosen_a_farm_guard` | **Not** UI Pass 1/1b/1c/2. Uncommitted Change A / farm-guard follow-on (base file from Change A pass 2D `4ce57fc`) |
| `tests/Feature/Api/WalkInSaleTest.php` | Modified (+139 lines) | `test_a_completed_walk_in_cannot_be_transitioned`; `test_a_walk_in_skips_a_tawad_that_would_drop_the_unit_below_the_effective_floor`; `test_an_actor_without_record_walk_in_sales_cannot_record_one`; `test_the_four_analytics_summaries_count_a_completed_walk_in` | **Not** UI passes. Uncommitted Change B / walk-in follow-on (base file from Change B pass B3 `da795e8`) |
| `tests/Feature/Api/AuthApiTest.php` | Modified (assertions only) | **0** new methods — permission asserts on existing login tests | Same farm/walk-in permission work; not a UI pass |
| `tests/Feature/FarmIsolationTest.php` | **Untracked** new file | 8 methods (`test_a_content_editor_cannot_read_another_farms_articles_in_the_panel`, … `test_a_super_admin_sees_both_farms_system_wide`) | **Not** UI Pass 1–2. Present when CMS Pass A reported **94** |

Arithmetic: **77** (audit) + **5** (FarmPriceGuard) + **4** (WalkInSale) + **8** (FarmIsolation) = **94**.

### Was the count 85 or 94 at the audit commit?

**Neither.**

| Claim | Actual |
| --- | --- |
| At audit commit `86a6182` | **77** `public function test_*` methods across 17 files under `tests/` (counted from `git show 86a6182:…` blobs; no `#\[Test\]` / data providers) |
| **85** | Older documented baseline in `docs/spec/AniHow_Project_Instructions_v3.3.md` (“85 of 85”), **not** the audit commit tree |
| **94** | Current working tree (`php artisan test --list-tests` = 94), as reported in CMS Pass A — **after** the uncommitted additions above |

---

## 3. FittedBox in Pass 1C

| Location | Wraps | Shrinks below font scale 1.3? |
| --- | --- | --- |
| `mobile/lib/widgets/auth_layout.dart` **:89** | `FittedBox(fit: BoxFit.scaleDown)` → `Column` (64dp logo tile + “AniHow” + “Farmers' market hub”) | **Yes, when constrained.** `scaleDown` may reduce the whole column (including text painted at `AniHowSpace.headline` / `body`) so the effective size is below what `MediaQuery.textScaler` at 1.3 would otherwise draw, if the ~34% header slot is too short. If the slot is tall enough, no shrink. |

**Only one FittedBox was added in Pass 1C** (new `auth_layout.dart`).

Out of scope for Pass 1C (listed so it is not confused): `mobile/lib/screens/farmer/crop_care_screen.dart` segment labels use `FittedBox(scaleDown)` from **Pass 1B**, not 1C.

---

## Stop

Diagnostic D1 complete. No code changes.
