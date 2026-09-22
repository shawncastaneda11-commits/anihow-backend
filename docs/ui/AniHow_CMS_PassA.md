# AniHow CMS Pass A — Best-selling produce widget

**Scope:** presentation of the best-selling produce dashboard widget only.  
**Not changed:** `AnalyticsService::bestSelling()` (or any figure computation / scoping).  
**Shots:** `docs/ui/_cms_pass_a/`.

---

## 1. What was wrong

| Finding | Detail |
| --- | --- |
| Widget | `App\Filament\Widgets\BestSellingTable` extended plain `Filament\Widgets\Widget` |
| View | `resources/views/filament/widgets/best-selling-table.blade.php` — custom Blade with a **raw HTML `<table>`** and Tailwind utility classes (`grid`, `md:grid-cols-2`, `text-gray-500`, `border-gray-100`, …) |
| Theme | Panel has **no compiled custom Tailwind theme** for those utilities, so week/month blocks and borders did not reliably pick up Filament styling |
| Layout | Two stacked period blocks side by side, not one table with a period filter |
| Money | Sales rendered as `PHP {{ number_format(...) }}` |
| Empty copy | “No completed orders yet.” (not period-aware) |
| Rank | Absent |

---

## Changelog

| File | Change | Reason |
| --- | --- | --- |
| `app/Filament/Widgets/BestSellingTable.php` | Rebuild as `TableWidget` with Chart-style `$filter` (`week` / `month`, default `week`), `records()` from `AnalyticsService::bestSelling`, columns Rank / Crop / Units / Sales, empty heading, full width | Filament-owned table + same filter pattern as Units sold |
| `resources/views/filament/widgets/best-selling-table.blade.php` | Wrap `$this->table` in `x-filament::section` with `afterHeader` select (`wire:model.live="filter"`) matching chart widgets | Filter UI without raw Tailwind grids |

`AnalyticsService`, `ScopedAnalytics`, and other widgets were not modified.

---

## Figures before / after

Same smoke seed state (one completed order): **identical**.

| Viewer | Period | Rank | Crop | Units | Sales (revenue) |
| --- | --- | ---: | --- | --- | ---: |
| Super Admin | week | 1 | Kamatis (smoke test) | 6 kg | 160 |
| Super Admin | month | 1 | Kamatis (smoke test) | 6 kg | 160 |
| Content Editor (farm 1) | week | 1 | Kamatis (smoke test) | 6 kg | 160 |
| Content Editor (farm 1) | month | 1 | Kamatis (smoke test) | 6 kg | 160 |

UI shows Units as `6.00 kg` and Sales as `₱160.00`. Computation path unchanged.

---

## Scoping

Unchanged and confirmed:

- `ScopedAnalytics::canView()` still gates Super Admin (`view_system_analytics`) and Content Editor (`view_farm_analytics`).
- `AnalyticsService::scope()` still: system-wide for Super Admin; `orders.farm_id` for farm analytics; own seller otherwise.
- Editor dashboard still shows farm-scoped widgets (narrower nav; same farm figures here because smoke data is one farm).

---

## Money / “PHP” elsewhere (report only)

This widget now uses `₱` + thousands + 2 decimals. Other CMS places still show **PHP** (not changed this pass):

| Place | Example |
| --- | --- |
| `SalesOverviewWidget` | Sales / Average tawad stats |
| `SalesPerPeriodChart` legend | “Sales (PHP)” |
| Farm override helpers / validation messages | “system floor is PHP …” |
| Listing table “below floor” badge | “Below floor of PHP …” |

---

## Verification

| Check | Result |
| --- | --- |
| Figures week/month before = after | **Match** (table above) |
| `php artisan test` | **94 passed / 94** (suite is larger than the older “85 of 85” baseline; all green) |
| `php artisan db:seed --class=SmokeTestSeeder` | **26 passed / 26** |
| Pint on dirty PHP | Passed |
| Screenshots | `admin-light.png`, `admin-dark.png`, `editor-light.png`, `editor-dark.png` — widget shows one table, This week filter, Rank/Crop/Units/Sales, `₱160.00` |

---

## Judgment calls

| Item | Decision | Reason |
| --- | --- | --- |
| `TableWidget` + custom `records()` instead of Eloquent query | Chosen | Aggregates are not a clean Eloquent model query; Filament 5 custom data API fits without changing analytics |
| Chart-style filter on a custom widget view (not table `SelectFilter`) | Chosen | Matches Units-sold dropdown in the section header |
| Rank added only in the presentation map | Safe | Does not alter `bestSelling()` ordering or values |
| Test count 94 vs requested 85 | Reported as-is | Suite grew; all tests pass |
| Left other “PHP” strings alone | Per instructions | Pass A is this widget only |

---

## Stop

CMS Pass A complete. No further CMS passes in this turn.
