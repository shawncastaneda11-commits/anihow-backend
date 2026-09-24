# AniHow Capstone: Project Instructions (v3.5, Manuscript Re-audit)

Supersedes v3.4 and, through it, v3.3, v3.2, v3.1, v3, and the v2 Marketplace
Rebuild instructions in full. Where this document conflicts with any earlier
instruction set, working note, or prior manuscript section, THIS DOCUMENT WINS.
v2 through v3.4 are historical reference only.

Status: system built and feature-complete for the study; manuscript in re-audit
against the v3.5 build. Revised 24 September 2026 for the post-v3.5 hardening
pass (see "Post-v3.5 addendum" at the end); no module or actor changed.

---

## 1. Your role

You are assisting with the manuscript for AniHow, a marketplace-first capstone
system that is **built and running**. The prose describes the system in the
present tense because it exists.

**The manuscript is written from zero.** No section of the Project 1 manuscript
is carried across as a starting draft, and no prior prose is adapted. Your job is
structural design, fresh drafting against this specification, consistency
auditing, and source verification. Every structural decision is logged; nothing
is changed silently.

---

## 2. Build state

The system is implemented across three surfaces: a Laravel API (four roles), a
Filament CMS (policy-gated resources for two roles on one panel), and a Flutter
Android application. Development happens on the branch
`cursor/cloud-agent-1790189470926-52vq6`; this document describes that branch as
of commit `efa1419`.

**Test totals (confirmed runs, 24 September 2026).**

| Layer | Result |
|---|---|
| Full `php artisan test` suite | **215 tests, 1,538 assertions** |
| SmokeTestSeeder (service-level, runs separately) | **26 of 26** checks |
| Multi-farm isolation (inside the PHP total) | 8 checks, 138 assertions |
| Flutter `flutter test` (default run, Flutter 3.47.4) | **63 passed**; 3 live API suites skipped by design |
| Flutter live suites (`--tags live --run-skipped --concurrency=1`) | 3 of 3 against a seeded local API |
| GitHub Actions CI (every push) | Pint `--test`, full PHPUnit suite, `flutter analyze`, `flutter test`; green on `efa1419` |

The PHP feature suite covers: authentication and OTP verification, change
password, the client request contract, crop-care reads (farmer-sellers only),
buyer and farmer order resources, the order state machine, checkout and the
per-seller split, tawad creation and validation, seller isolation, farm price
guards, walk-in recording, marketplace search, takedown and restore
notification, order chat, the FAQ bot and its CMS management and moderation,
the farm page, farm announcements, farmer-seller analytics, stale-order
reminders and auto-cancellation, Super Admin CSV exports, and the data-subject
rights (correct, export, request deletion, anonymisation), shop favorites,
the buyer-visible cancellation note, the demo seeder, image variants and
thumbnails, and orphaned-file pruning.

The overlap between the smoke and feature layers is deliberate: the seeder tests
the services, the feature suite tests the routes, and a policy or FormRequest can
fail while the service beneath it stays correct.

**Seeding.** `php artisan migrate:fresh --seed` seeds roles and permissions and
the FAQ system answers in every environment, and seeds the SmokeTestSeeder
fixtures and listing images only when `APP_ENV` is `local` or `testing`. The
smoke accounts therefore never reach production.

**Release builds.** The Android release APK takes its API and WebSocket
endpoints at build time (`--dart-define`). A release build without a valid
`https` API and `wss` Reverb endpoint refuses to start and shows a "not connected
to a server" screen instead of silently pointing at the emulator address.

**Not yet done, and outstanding before the system is a system rather than a
build:** it has run only against seeder fixtures, never against a real farm's
data; it is not deployed; the app has run on the emulator and a development
device only. Section 14 names the VPS as the deployment target and lists the four
server processes it needs. Gmail SMTP is wired and works once the sending
account's app password is set; until then local mail stays on `log`.

Chapter 4 and Chapter 5 are not part of this manuscript. See Section 13.

---

## 3. Canonical identity

- **Title, verbatim:** "AniHow: A Digital Market Hub for Farmers in Cavite
  Implementing Descriptive Analytics" (lowercase "for" in prose; the title page
  renders in all caps, which is correct).
- **Team:** Alimangohan, Castaneda, Lagazo, Mallari, Matro.
  **Adviser:** Mr. Joven B. Cajigas.
- **Program:** BS Information Technology (Web and Mobile Technology), LPU-Cavite.
- **Client:** partner farm(s) in Cavite, each represented in the system by its
  own Content Editor. **PYAP (Pag-asa Youth Association of the Philippines)**
  Manggahan Chapter, General Trias, Cavite, adopted through LPU-Cavite COSEL, is
  the pilot client and the sole evaluated partner (decision 21). The multi-farm
  structure is a design capability, verified by the isolation tests.
- **Post-turnover administration:** the LPU-Cavite Information and
  Communications Technology Department holds the Super Admin role after handover
  (as drafted in Chapter 3; decision 22).
- **Farmer term:** "member-farmer" is retired along with the communal-plot
  framing. Use **"farmer-seller"** for the selling actor and "farmer" in general
  prose.

---

## 4. Actor model (four actors, locked)

| Actor | Surface | Definition |
|---|---|---|
| **Super Admin** | CMS (web) | System owner. Accounts, approvals, economic guardrails, moderation, analytics, exports, account-deletion processing. Sole holder of destructive power. |
| **Content Editor** | CMS (web) | Representative of a partner farm, scoped to that farm. Owns the farm's crop-care articles, farm profile and gallery, announcements, and farm FAQ answers, and tightens the farm's price guards. No account power, no moderation power, no listing-price power. |
| **Farmer-Seller** | Android app | Approved member of a partner farm. Operates a storefront, creates listings, sets tawad rules, confirms and fulfils orders, chats with buyers about their orders, records walk-in sales, reads their own sales figures. |
| **Buyer** | Android app | Open registration. Browses, orders, chats with the seller about an order, arranges handover, reviews. |

**Account rules.** One account, one role. There is no dual-account model and no
role switching. **A farmer-seller therefore cannot buy.** Buyer registration is
open and self-service and is verified by OTP. **Farmer-seller accounts are
created by the Super Admin** (in the CMS or through the admin API) for approved
members of a partner farm, and are treated as verified on creation; a pending
account is approved with a farm membership check. Content Editor accounts are
created by the Super Admin.

**Removed roles:** Seller as a separate account type, PYAP Admin, COSEL Auditor,
Finance Manager (never existed; guard against reintroduction).

---

## 5. Who handles who

**Super Admin governs:**
- Creates, approves, suspends, and deletes all accounts including Content Editors
- Sets the **system floor price per crop type** and the **system maximum peso
  discount per crop type** on the shared taxonomy entry. These two system fields
  are Super Admin's alone, and no override any role sets can escape them.
- Writes farm price overrides on any farm, held to the same tighten-only rule as
  a Content Editor (keeps a farm's guards reachable when its editor is absent).
- Takes down listings (required reason, seller notified) and restores them
  (seller notified), resolves reports, removes reviews, suspends users.
- **Moderates but never writes** listings, crop-care content, and farm FAQ
  answers. For a farm FAQ answer, moderation means hide or delete: the Super
  Admin can hide a farm answer (the farm falls back to the system answer, and
  the farm's Content Editor is notified) but cannot edit its text. Only the
  Super Admin can unhide it.
- Writes the **system-wide FAQ answers** (buyer-facing and farmer-seller-facing).
- May post announcements for any farm.
- Reads order chat threads, read-only, in the CMS. Never posts into a thread.
- Reads the full order ledger and all descriptive dashboards.
- **Owns exports:** the only role that can download the order ledger CSV and the
  analytics CSV. Every export is recorded in an audit log.
- **Processes account-deletion requests:** approves (anonymises the account) or
  rejects with a note.
- Owns the crop taxonomy structure.

**Content Editor governs, within their own farm only:**
- Crop-care and pest-management reference articles: write, edit, tag to crop
  types, publish, unpublish.
- Farm profile: description, contact person, contact number, pickup point, cover
  photo, and **gallery photos**. Everything but the contact number is shown
  publicly on the farm page; the contact number is visible only to that farm's
  farmer-sellers and the Super Admin.
- **Farm announcements:** members-only or public, optional start and end time,
  optional pin. Active announcements notify the farm's active farmer-sellers once;
  public ones also appear on the farm page. Buyers are never notified.
- **Farm FAQ answers:** add or override answers for the farm's farmer-sellers
  only. Can read the system answers (read-only) to see what they override.
  Cannot write system answers, buyer-facing answers, or another farm's answers,
  and cannot unhide an answer the Super Admin hid.
- Their farm's roster view (read-only; approvals stay with Super Admin).
- **Farm-scoped price guards, tighten-only.** May raise the farm floor above the
  system floor and lower the farm discount ceiling below the system maximum;
  never the reverse. Effective floor = higher of the two; effective ceiling =
  lower of the two. Both overrides optional; default to the system value.
- **Cannot** set or override another farm's values, cannot set a listing price,
  cannot moderate, cannot export, cannot touch other farms' content.

**Why tighten-only.** The taxonomy is shared, so a floor that any farm could
lower would stop being a floor for every other farm using that entry. Bounded
upward, farms differ from each other while one Super Admin number still fences
them all in.

**Farmer-Seller governs, within their own storefront only:**
- Listings: crop type, own photos, description, own price at or above the farm's
  effective floor, quantity, availability; can hide and show a listing.
- Tawad rules on own listings, within the farm's effective discount ceiling.
- Own orders: confirm, decline, mark ready, mark completed, cancel with reason.
- Recording of walk-in sales, Section 7.
- **Order chat** with the buyer on each of their app orders.
- **My Sales:** their own descriptive figures in the app, Section 10.
- Reads their farm's announcements and the farm page; reads crop-care articles.
- Cannot see another farmer-seller's listings, orders, or figures.
- Sees, read-only, their partner farm (profile) and the crop's effective floor
  (new-listing form, labelled as set by their farm).

**Buyer:**
- Browses, searches (crop type, storefront name, produce keyword), carts, orders,
  cancels before confirmation.
- Browses all storefronts through a shops directory; opens a storefront's farm
  page from the storefront.
- **Order chat** with the seller on each of their orders.
- Reviews a farmer-seller only after a completed order with that farmer-seller.

**Every app user (buyer or farmer-seller)** can view and correct their own
profile (name, phone, location; email is the verified login and is not editable
in the app), change their password, download their own data, request deletion of
their account, use the scripted FAQ, and switch the app between English and
Filipino.

**Nobody handles money.** Cash changes hands in person. The system records that
it did. No gateway, no wallet, no escrow, no proceeds division.

---

## 6. Data structure spine

- **Farm** is a first-class entity. Farmer-Sellers belong to a farm. Content
  Editors are scoped to a farm. One Content Editor per farm. A farm has gallery
  photos (`farm_photos`, with captions), announcements (`farm_announcements`),
  and may have farm FAQ answers (`faq_entries` rows with its `farm_id`).
- **Crop taxonomy** is shared and system-wide. A taxonomy entry holds: crop name,
  English and Filipino label, unit of measure, system floor price, system
  maximum peso discount, and links to crop-care articles.
- **Farm price overrides** live in `farm_crop_type_overrides`, one row per farm
  per taxonomy entry, nullable farm floor and nullable farm ceiling. Missing row
  or null column means the system value applies. `farm_id` cascades on delete,
  `crop_type_id` restricts, the pair is unique. Tighten-only is enforced in the
  application.
- **Listing** is the farmer-seller's own object under a taxonomy entry (Shopee
  model: the taxonomy is a category tree, not a product list).
- **Order** carries its source (app or walk-in), status history, a nullable
  buyer (null for walk-in), and a `reminder_sent_at` stamp used by the stale-order
  sweep. **Order messages** (`order_messages`) belong to an order.
- **Crop-care articles** are farm-scoped in authorship, not in read access. Read
  access is farmer-seller-only and system-wide; the author farm is shown.
  **Buyers have no crop-care access.**
- **FAQ entries** (`faq_entries`): system rows (`farm_id` null) and farm rows.
  Each row has an intent key, the roles it serves, English and Filipino label
  and answer, keywords, an editor-controlled active flag, and Super Admin
  moderation fields (`moderated_at`, `moderated_by`). A row is live only when it
  is active and not moderated.
- **Export logs** (`export_logs`): who exported what, with which filters and how
  many rows.
- **Account deletion requests** (`account_deletion_requests`): pending, completed,
  or rejected, with who processed it and when.
- **Multi-farm isolation is verified.** Each farm's overrides, listings, orders,
  figures, farm-scoped analytics, article writes and moderation, photos,
  announcements and FAQ answers are private to that farm. The Super Admin reads
  across all farms. Published articles and a farm's public page remain readable
  across farms by design.

---

## 7. Marketplace flow (the system spine)

Reference model: Shopee and Lazada, minus payments and minus logistics.

1. Farmer-Seller creates a listing under a crop taxonomy entry. Price validated at
   or above the farm's effective floor, which is shown on the form. Auto-publishes.
2. Super Admin holds **takedown** power, not pre-approval power.
3. Buyer browses by crop type, by storefront (shops directory or tapping a
   seller), or by search. Opens a storefront, its farm page, or a listing. Adds
   to cart.
4. Checkout **splits the cart into one order per farmer-seller.** Tawad applies
   automatically where its condition is met. The cart shows a breakdown per
   seller-order plus one cart-wide summary.
5. Buyer selects a fulfillment preference: **buyer pickup** or **seller
   delivers**, with a note. No courier, no tracking, no fee, no route.
6. Payment is **cash on handover**, recorded not processed.
7. Status: **Placed, Confirmed, Ready, Completed, Cancelled.** Farmer-seller
   advances the status; the buyer may cancel only while Placed. **Stock is held
   at Placed and deducted at Confirmed.** Cancelling before Confirmed releases the
   hold; cancelling at or after Confirmed restores the deducted stock.
8. **A no-show is a cancellation reason, not a sixth status.**
9. Handover is face to face. Farmer-seller marks Completed and records the amount
   received.
10. Review unlocks at Completed, one per order. No order, no review.

**Order chat.** Each app order has a text thread between its buyer and its
farmer-seller, used to arrange the handover time and place. It is delivered live
over a WebSocket (Laravel Reverb), with the app falling back to checking every
eight seconds if the connection drops. A cancelled order's thread becomes
read-only; walk-in orders have no thread (there is no buyer account). **Chat is
coordination, not negotiation:** the price and the tawad are fixed by the listing
and cannot be changed in chat (the FAQ says so to buyers).

**Stale orders.** An app order left at Placed does not hold stock forever. After
**12 hours** the farmer-seller receives one in-app reminder. After **48 hours**
the system cancels it with the reason **"seller unresponsive"**, which releases
the held stock and notifies the buyer. This is the system acting as an actor
(`OrderActor::System`) through the same order state machine; it is a
cancellation reason, not a new status. Walk-in, Confirmed and Ready orders are
never touched. Both thresholds are configuration values.

**Walk-in sales.** A buyer without the app can still buy. The Farmer-Seller
records the sale in the Android app as an ordinary order against an existing
listing, with no buyer account and an optional free-text buyer name. Crop type,
unit and price come from the catalog, tawad applies on the same rules, stock
deducts. Consequences to state in Chapter 3:

- A walk-in order is created directly at **Completed** (the one exception to
  stock deducting at Confirmed).
- **No buyer account means no review** and **no chat**.
- The walk-in path lives in the **Android application**, not the CMS.

This is a second way into one order ledger, not a second sales surface and not a
sixth module.

---

## 8. Tawad (locked as a seller-set discount rule)

**Definition to state once, in the Project Description sub-section of Background
and Rationale, and nowhere else in Chapter 1:** tawad in AniHow is implemented as
a seller-published peso discount rule rather than live negotiation, because the
system records transactions but does not mediate them.

- Peso amounts only. **No percentage input anywhere.**
- Exactly two rule types: flat peso off per order; peso off at a minimum
  quantity. One active rule per listing.
- Applies automatically at checkout. Buyer sees listed price, tawad, final total.
- Farm effective ceiling bounds the discount; `max_discount < floor_price` is a
  database CHECK on the system pair.
- Discounted unit price never falls below the effective floor: checked at rule
  creation, at checkout, and on a walk-in sale.
- Confirmed orders keep the price they were confirmed at.
- **Order chat does not change this.** There is no bargaining channel; the chat
  cannot alter a price.
- Feeds the "average discount given" summary.

Definition of Terms carries a plain operational definition with no rationale.

---

## 9. Crop-care reference

CMS-managed articles, written by each farm's Content Editor, tagged to shared
taxonomy entries, **read-only in the app for Farmer-Sellers only**. A
farmer-seller reads published articles from all farms, each showing its author
farm. Reference content only: not a tracker, scheduler, decision engine, or
prerequisite for selling.

---

## 10. Descriptive analytics

Descriptive only. Sourced entirely from the system's own listings and recorded
orders. **Analytics count completed orders only**, which includes walk-ins.

Core figures (the four the manuscript names):
- Units sold per crop type
- Sales per period
- Best-selling produce (by week and by month)
- Average discount given

Supporting summaries shown alongside them (still descriptive): completed order
count, units sold, gross sales, and the walk-in versus app share of completed
orders.

**Where they appear.**
- **CMS dashboard:** Super Admin system-wide; Content Editor scoped to their farm.
- **My Sales (Aking Benta), Android app:** a farmer-seller's own figures only, for
  the last 7 or 30 calendar days. Every figure on the screen uses the same
  window, and the window is printed on screen, so the per-period bars add up to
  the gross sales total.
- **Exports:** the Super Admin can download the analytics as one CSV (four
  labelled sections, chosen date range), computed by the same service as the
  dashboard, and the order ledger as a CSV that follows the table's filters.
  Exports never include emails, phone numbers or chat, text cells are protected
  against spreadsheet formula injection, and every export is logged.

**Time.** All days and windows are Philippine time (`Asia/Manila`).

---

## 11. Permanently removed, never reintroduce

- Crop-cycle and growth-phase tracking, plot scoping, harvest-readiness gating
- Point-of-Sale as a separate module and a separate Sales Summary module (the
  summary half lives in Descriptive Analytics; the recording half is the walk-in
  path in Module B)
- The three-account model and any dual or switchable account
- PYAP Admin role, COSEL Auditor role
- Growth-phase posting prerequisite, owner-acceptance handshake, consignment
- Any farming prerequisite or checklist as a condition of selling
- Price analytics; moving-average price-trend classification; PSA OpenSTAT;
  forecasting; predictive analytics
- AI, IoT, sensors, predictive technology of any kind. **The FAQ helper is a
  scripted keyword matcher, not an AI chatbot, and must never be described as
  one.**
- Live price negotiation in any form, including through order chat

**Hard boundaries:** no payment gateway, no courier or logistics integration, no
proceeds division, no percentage discounts.

---

## 12. Retained constraints

- **Bilingual scope (revised, decision 12):** the app interface can be switched
  between English and Filipino; crop labels are bilingual on the taxonomy entry;
  the FAQ answers exist in both languages. User-written content (listings,
  articles, announcements, chat) is shown as written and is not translated. The
  CMS is English only.
- **Data Privacy Act of 2012 (RA 10173):** "designed in accordance with", never
  "complies with". Implemented measures:
  - role-restricted access and per-farm isolation;
  - users can **view and correct** their profile, **download** their own data
    (JSON, rate-limited), and **request deletion**;
  - deletion is processed by the Super Admin and is carried out as
    **anonymisation**, because orders must stay in the ledger: personal fields are
    cleared or replaced, logins revoked, listings deactivated, cart, favourites and
    notifications removed, and the account soft-deleted; orders, reviews and chat
    remain, shown as "Deleted user";
  - deletion is refused while the user has open orders;
  - a farm's contact number is not shown to buyers;
  - exports exclude contact data and chat, and are logged.
- **Evaluation:** ISO/IEC 25010:2023, four characteristics: functional
  suitability, usability, reliability, performance efficiency.
- **Instrument:** four-point Likert scale (Highly Acceptable 3.50 to 4.00, Fairly
  Acceptable 2.50 to 3.49, Acceptable 1.50 to 2.49, Unacceptable 1.00 to 1.49).
  Our scale stands (decision 4).

---

## 13. Manuscript scope and structure

**This manuscript is Chapters 1 to 3 plus the abstract and front matter.** No
Chapter 4, no Chapter 5.

**Headings are unnumbered, bold, and left-aligned**, under a centred chapter label
and centred chapter title. Section numbers appear in filenames and conversation
only.

### Chapter 1: INTRODUCTION

```
Background and Rationale of the Study
Objectives of the Study
Significance of the Study
Scope and Limitation
```

Background and Rationale integrates Introduction, Project Context and Project
Description. Project Description runs as an italic-bold sub-section and carries
the five module names verbatim. They are the golden thread.

- A. Marketplace and Storefront Module
- B. Order and Fulfillment Coordination Module
- C. Crop-Care and Pest-Management Reference Module
- D. Descriptive Analytics and Reporting Module
- E. Web-based Content Management System

**Where the v3.5 features sit (so none reads as a sixth module):**

| Feature | Module |
|---|---|
| Farm page (profile, pickup point, gallery) | A |
| Order chat; stale-order reminder and auto-cancellation | B |
| My Sales for farmer-sellers; Super Admin exports | D |
| Farm announcements; FAQ answers and their moderation; account-deletion processing; export log | E (authored and governed in the CMS; announcements and FAQ are read in the app) |
| Filipino interface; profile correction; data download; deletion request; change password | Account settings in the app. Cross-cutting, described under Scope and the Data Privacy paragraph, **not a module** |

**Objectives** keep the FOUR-objective pattern (Design, Create/Develop, Test,
Evaluate) with lettered sub-items. Do not reintroduce six objectives.

**Significance precedes Scope.** **Scope and Limitation** is the outline's
heading, singular, with italic-bold ***Scope of the Study*** and ***Limitations
of the Study***. Chapter 1 targets 8 to 10 pages (decision 2).

### Chapter 2: REVIEW OF RELATED LITERATURE

```
Main topics (as many as the literature warrants)
Theoretical Framework          (requires a figure)
Conceptual Framework           (requires a figure)
Definition of Terms
```

Definition of Terms lives in Chapter 2. Theoretical Framework: DeLone and McLean
IS Success Model (2003). Conceptual Framework: Input-Process-Output, drawn as a
figure. All sources verified from scratch.

### Chapter 3: METHOD

```
Research Design
Research Locale
Sampling Technique
Respondents of the Study
Research Instrument
Data Gathering Procedure
Data Analysis
Ethical Considerations
  (includes a Declaration of AI Use)
Software Development Methodology
System / Network Architecture and Design
System Development
Testing Procedure
Evaluation Procedure
Implementation Plan
```

Participants: internal actors by total enumeration; buyers as a mixed pool
(community and external, with a stated recruitment method and target N); a
separate IT-expert group. The Declaration of AI Use is filled in, not deleted.

### Abstract

Project 1 style: no Results, Discussion or Conclusions. 250 to 300 words, block
format, 1.50 spacing, three to five keywords, the relevant UN SDG named in the
abstract and keywords. Written last.

---

## 14. Tech stack (locked)

Flutter (Android only), Laravel REST API, Laravel Sanctum, Spatie Permission,
MySQL, Filament CMS (web, separate from the app), **Laravel Reverb** (WebSocket
server for order chat). Deployment to a VPS; Railway is testing only.

**Versions (canonical; stated once in Chapter 3 System Development, mirrored in
the SRS):**

| Component | Version |
|---|---|
| Laravel | 13.32.0 |
| PHP | 8.4.25 |
| Filament | 5.8.2 |
| Laravel Sanctum | 4.3.3 |
| Spatie Laravel-Permission | 8.3.0 |
| Laravel Reverb | 1.12.0 |
| Dart SDK constraint | ^3.13.3 |
| Flutter | 3.47.4 |
| MySQL | [TEAM INPUT: from the VPS] |

**Server processes on the VPS (all four are required):**
1. Nginx + PHP-FPM serving the Laravel application over HTTPS.
2. **Queue worker** (`queue:work`, under Supervisor): sends queued email, namely
   password resets, low-stock alerts, and deletion notices. OTP codes do NOT go
   through the queue (see below).
3. **Reverb** (`reverb:start`, under Supervisor), proxied as `wss` on its own
   subdomain: live order chat.
4. **Scheduler** (cron running `schedule:run` every minute): delivers scheduled
   farm announcements (every five minutes) and runs the stale-order sweep
   (hourly).

**Email verification:** OTP-only, 6-digit code, hashed in cache, 10-minute
expiry, in-app verification screen. Mail goes out through Gmail SMTP (app
password on the sending account). The OTP is sent synchronously (`notifyNow`)
within the registration or resend request, so it does not depend on the queue
worker. `log` in local development, where the code is also returned in the API
response only in `local`/`testing` when mail is unconfigured; in production an
unconfigured mailer returns 503 and the code is never exposed.

**Time zone:** `Asia/Manila` for the application, analytics, and schedules.

**Role implementation:** Super Admin and Content Editor are two Spatie roles on
**one** Filament panel, policy-gated per resource.

**Version handling:** Chapter 1 and Chapter 2 prose is version-less. Versions
appear in exactly one place, Chapter 3 System Development.

**Justification framework.** Every tool choice defends against four criteria:
Android-first on low-cost phones, zero cost, well-documented for a student team,
maintainable after handover. Reverb meets all four (first-party Laravel package,
free, self-hosted on the same VPS). Development process: Iterative and
Incremental, anchored by two documented adviser validation sessions.

---

## 15. Sources of truth (priority order)

1. **This document**, the governing spec. Kept in the repository at
   `docs/spec/AniHow_Project_Instructions_v3.5.md`; the branch history is the
   evidence for every "built" claim.
2. **`2026-2027-CRD-FULL-MANUSCRIPT-CCS.docx`**, authority for **structure and
   format**, Chapters 1 to 3 only. Where it conflicts with this spec on system
   behaviour, this spec wins; on structure or format, it wins.
3. **Workbook PDFs**, section checklists and expected length.

**Archived. Never a source of prose, structure, or system behaviour:**
MANUSCRIPT-FINAL.docx, SRS v1.3, the Project 1 diagram set, the Project 1 defense
assets, the "Capstone-Project-1-Manuscript-Outline-and-Format" document.
The README's older phase sections (Reservations, POS) are also historical and
describe removed features.

**Never use at all:** Capstone_Capsule.pdf.

---

## 16. Format spec

Times New Roman 12pt; double spacing (abstract at 1.50); one-inch margins; page
numbers bottom right; APA 7; **bold, left-aligned, unnumbered** section headings
under centred chapter labels; ring-bound; deliver .docx; diagrams SVG or PNG.
Table and figure notes use the label "Note" at 9pt, italicised, single spaced.

---

## 17. Working rules

- **Write fresh. Never adapt prior prose.**
- **NEVER use em dashes or en dashes in prose.** Use commas, colons, semicolons,
  or separate sentences. Hyphens in compound modifiers stay. Sweep every draft.
  (Official standard titles that contain dashes, such as the ISO/IEC 25010
  title, are quoted as published in the reference list.)
- **The team is the resolution authority.** Flag mismatches, decide them in the
  same session, log the decision (Section 18).
- **Source verification is non-negotiable:** title plus venue, DOI or repository
  page, metadata confirmed. Never fabricate authors or sources.
- Audit every new passage against the five module names.
- Facts only the team holds are marked `[TEAM INPUT: ...]`. Never invented.
- **Describe only what is built.** Every claim about the system must match the
  branch. When the build and the prose disagree, fix one of them and log it.

---

## 18. Decision authority and decision log

### The rule

The team resolves every open item. Claude proposes, the team decides, the
decision is logged with its reasoning, and drafting proceeds. An override is a
new row that points to what it replaces; nothing is deleted.

### Decision log

| # | Item | Decision | Reasoning |
|---|---|---|---|
| 1 | Scope section heading | **"Scope and Limitation"**, verbatim | The outline is the authority for structure and wording. |
| 2 | Chapter 1 page cap | **Cap does not apply.** 8 to 10 pages | The cap governs the Project 2 condensation, not the source. |
| 3 | Theoretical Framework | **DeLone and McLean IS Success Model (2003)** | The study measures delivered quality and acceptability, not adoption intention. |
| 4 | Likert boundaries | **Our four-point scale stands** | Four equal intervals; the outline's table is a fictional example. |
| 5 | Farm-scoped price overrides | **Adopted, tighten-only. Built and verified.** | Farms differ; one Super Admin number still fences them in. |
| 6 | Walk-in sale recording | **Adopted, inside Module B. Built and verified.** | Buyers without the app are real; one ledger, no sixth module. |
| 7 | Scope internal structure | ***Scope of the Study*** and ***Limitations of the Study*** | Italic-bold sub-topics exist in the outline. |
| 8 | Conceptual Framework | **Input-Process-Output**, as a figure | Standard in the program, maps onto the four objectives. |
| 9 | Crop-care read access | **Farmer-sellers only, system-wide, author farm shown** | Buyers do not grow crops; any farm's guidance serves any seller of that crop. |
| 10 | Order chat | **Adopted inside Module B.** One thread per app order, buyer and seller only; Super Admin reads, never posts; read-only once cancelled; none for walk-ins. Delivered through Laravel Reverb with polling fallback. | Handover time and place were being arranged outside the system. Chat keeps that coordination with the order. It is not a bargaining channel, so decision 8 (tawad as a fixed rule) stands. |
| 11 | FAQ helper | **Scripted, keyword-matched, bilingual FAQ, managed in the CMS.** Super Admin writes system answers; a Content Editor may override or add farmer-seller answers for their own farm; buyers always get system answers. Super Admin may hide or delete a farm answer but never rewrite it; only the Super Admin can unhide. | Gives users answers without a human on call, at zero cost. Keyword matching keeps it outside the "no AI" boundary. Moderation mirrors crop-care: govern, never author. |
| 12 | Bilingual scope | **Widened: the app interface switches between English and Filipino; FAQ answers are bilingual.** Replaces the v3.4 "crop labels only" rule. User-written content is not translated; the CMS stays English. | Farmer-sellers and community buyers are more comfortable in Filipino. The widening is bounded to fixed interface text, so no translation service or AI is involved. |
| 13 | Farm page | **Adopted inside Module A.** Public farm profile, pickup point and gallery, reached from a storefront or the farmer's profile. The contact number is private to the farm's farmer-sellers and the Super Admin. No marketplace farm filter. | The Content Editor's profile work had nowhere to appear. Keeping the phone private honours the Data Privacy design; buyers use order chat instead. |
| 14 | Farm announcements | **Adopted inside Module E.** Written by the Content Editor for their own farm (Super Admin for any), members-only or public, scheduled start and end, pinned; notifies active farmer-sellers once when it goes live. | Gives the farm representative a channel to its members without adding account or moderation power. |
| 15 | Farmer-seller analytics | **My Sales in the app, inside Module D**, own completed orders only, one reporting window (7 or 30 calendar days) for every figure. | The manuscript promised farmer-sellers their own figures and there was no surface for it. One window keeps the totals reconcilable. |
| 16 | Stale orders | **12-hour reminder, 48-hour system cancellation with reason "seller unresponsive", inside Module B.** A reason, not a status. | Held stock could otherwise stay locked indefinitely. Using the state machine keeps stock and history correct. |
| 17 | Exports | **Super Admin only**, order ledger CSV (follows filters) and analytics CSV (one file, four sections); no contact data or chat; formula-safe; every export logged. | The spec always gave exports to the Super Admin. Logging and data minimisation keep it consistent with the privacy design. |
| 18 | Data-subject rights | **View, correct, download, request deletion in the app; deletion approved by the Super Admin and executed as anonymisation; refused while orders are open.** | Matches the manuscript's Data Privacy Act commitments while keeping the order ledger intact for analytics and records. |
| 19 | Time zone | **Asia/Manila** for the application, analytics and schedules. | Under UTC, a Philippine day ran 8 AM to 8 AM and daily figures were misattributed. |
| 20 | Real-time stack | **Laravel Reverb added to the locked stack**, self-hosted on the VPS. | Needed for live chat; first-party, free, same server, meets all four justification criteria. |
| 21 | Client scope (closes the v3.4 open item) | **Manggahan Chapter is the sole evaluated partner.** Multi-farm support is a verified design capability, not evaluated scope. | Chapter 1 Scope and Chapter 3 already say so; the build does not depend on it. |
| 22 | Post-turnover administration | **LPU-Cavite ICT Department holds the Super Admin role after handover.** | As drafted in Chapter 3 Respondents and Implementation Plan; recorded here so the spec and the chapters agree. [TEAM INPUT: confirm with the department.] |
| 23 | Organisation name | **"Pag-asa Youth Association of the Philippines"** replaces v3.4's "Pag-asa Youth of the Philippines". | The chapters use the organisation's official name. [TEAM INPUT: confirm the spelling with the chapter.] |
| 24 | Deleted seller's listing photos | **Retained in storage, no longer displayed** (listings are deactivated). | The name and contact data are removed; produce photos are not personal data about the seller. State this in the Chapter 3 privacy text. Reversible by a future decision if the team prefers deletion. |

### Verified sources

DeLone, W. H., & McLean, E. R. (1992). Information systems success: The quest for
the dependent variable. *Information Systems Research*, *3*(1), 60-95.
https://doi.org/10.1287/isre.3.1.60 (page range confirmed; closes the v3.4 note)

DeLone, W. H., & McLean, E. R. (2003). The DeLone and McLean model of information
systems success: A ten-year update. *Journal of Management Information Systems*,
*19*(4), 9-30. https://doi.org/10.1080/07421222.2003.11045748

---

## 19. Drafting order (re-audit pass)

1. **References** (fixes in Section 22, part D) so every later citation is final.
2. **Chapter 1:** Project Description Modules A, B, D, E; Objectives 2a and 2b
   wording; Scope (stack, bilingual, privacy); Limitations.
3. **Chapter 2:** Definition of Terms additions and edits; literature
   positioning of chat against Harvester and Sen et al.
4. **Chapter 3:** Architecture, System Development, Testing Procedure,
   Implementation Plan, Ethical Considerations; then Figures 4 to 8 and Table 2.
5. **Abstract**, last.

All diagrams are drawn fresh from this document.

---

## 20. Communication preferences

- Direct, brief. Flag specific errors without extended explanation.
- Make judgment calls rather than presenting options.
- Stepwise delivery over bulk output; complete deliverables with explicit
  changelogs.
- Shorter, simpler answers as sessions progress.
- Manual control retained for some tasks (for example image insertion into
  slides).
- Slide text: short phrases, three to six words per line.
- Script: English-dominant Taglish; Tagalog only for connectors and politeness
  markers ("po").
- Cue cards: cue-line format, not prose.

---

## 21. Tooling patterns

- **Build work:** feature prompts run in Cursor against the branch; every commit
  is reviewed against this spec before the next prompt.
- **PPTX:** Python zipfile plus direct XML; markitdown for text.
- **Word:** docx.js with section-type XML for multi-column and odd-page starts.
- **PDF and visual QA:** LibreOffice headless, pdftoppm, pdftotext; dash audit via
  pdftotext piped through grep on the Unicode dash byte sequences.
- **Diagrams:** Graphviz grounded in manuscript text.
- **Grounding:** project knowledge search before generating manuscript content.

---

## 22. Re-audit list

Everything below is stale against the v3.5 build and is rewritten in the
re-audit pass. Items from the v3.4 list that were already fixed in the current
drafts are not repeated.

### A. Chapter 1

| Passage | What to change |
|---|---|
| Project Description, Module A | Add the farm page (public profile, pickup point, gallery; contact number private). |
| Project Description, Module B | Add order chat (coordination only, not negotiation; none for walk-ins) and the 12-hour reminder and 48-hour "seller unresponsive" cancellation. Cancellation after Confirmed restores stock. |
| Project Description, Module D | Add My Sales for farmer-sellers (own figures, one reporting window) and Super Admin exports. |
| Project Description, Module E | Add farm announcements, FAQ answer management and moderation, account-deletion processing, export log. Content Editor paragraph: add gallery, announcements, farm FAQ answers. |
| Objectives 2a | "verified farmer-sellers" becomes "approved farmer-sellers". |
| Objectives 2b | Add "and lets the buyer and farmer-seller coordinate each order through an order chat". |
| Scope, stack sentence | Add Laravel Reverb (version-less). |
| Scope, privacy sentence | Now true as written; add that deletion is carried out as anonymisation because order records are retained. |
| Scope or Limitations | Bilingual interface (English and Filipino), user content not translated. |
| Limitations | Add: the FAQ helper answers from a fixed set of questions and is not a conversational AI. |

### B. Chapter 2

| Passage | What to change |
|---|---|
| Digital Marketplaces section | Harvester supports vendor-customer communication; state that AniHow's order chat is scoped to an order and does not carry negotiation. |
| Digital Extension section | Sen et al. call for two-way communication; position announcements and order chat against that without overclaiming. |
| RBAC and Data Privacy section | Now accurate; optionally name the anonymisation approach. |
| Definition of Terms, "Farmer-Seller" | Accounts are created by the Super Admin for approved members; not self-registered in the app. |
| Definition of Terms, "Order Status" | The buyer may cancel while Placed; add "seller unresponsive" auto-cancellation as a reason. |
| Definition of Terms, new entries | **Order Chat**, **Farm Announcement**, **Farm Page**, **FAQ Helper** (scripted, keyword-matched), **Account Deletion Request** / **Anonymisation**, **One-Time Password (OTP)**. |
| Definition of Terms, "Web-based Content Management System" | Mention exports and deletion processing under the Super Admin. |
| Definition of Terms, "Super Admin" / "Content Editor" | Add the new powers listed in Section 5. |

### C. Chapter 3

| Passage | What to change |
|---|---|
| System / Network Architecture | Add Reverb (WSS to the app), the queue worker, and the scheduler as server processes. Figure 4 redrawn. |
| System Development, Table 2 | Fill the versions from Section 14; add the Reverb row. |
| System Development, authentication paragraph | OTP applies to buyers (self-registered); farmer-seller accounts are created by the Super Admin and treated as verified. OTP gates ordering, not login. |
| System Development, functional design | Use case and context diagram gain: chat (buyer, seller), announcements (editor writes, seller reads), FAQ (all app users; editor and Super Admin manage), My Sales (seller), exports and deletion processing (Super Admin), my data (buyer, seller). Figures 5 and 6 redrawn. |
| System Development, data structure | ERD gains farm_photos, farm_announcements, faq_entries, order_messages, export_logs, account_deletion_requests, orders.reminder_sent_at. Figure 7 redrawn. |
| System Development, order lifecycle | Add the system actor's 48-hour cancellation from Placed and stock restore on cancel after Confirmed. Figure 8 redrawn. |
| Testing Procedure | Replace totals with Section 2 figures; the isolation checks are inside the PHP total, not additional; add the Flutter suite and live suites; list the new covered areas. |
| Ethical Considerations | Describe the implemented rights (correct, download, request deletion), anonymisation, private farm contact number, logged exports, and decision 24. |
| Implementation Plan | Add the four server processes, Gmail SMTP app password, Asia/Manila time zone, and building the release APK with the production endpoints. |
| Software Development Methodology | Later iterations now include chat, announcements, FAQ management, farmer analytics, exports and privacy features. |

### D. References

| Item | Fix |
|---|---|
| Cruzate et al. (2024), FarmBill | Author list is wrong. Correct: Cruzate, J., Kam, J. R., Patarata, G. J., Villanueva, I. K., Yabut, I. C., Serrano, E., & San Juan, M. V. |
| ISO/IEC 25010:2023 | Missing from the list; add the ISO entry. |
| Philippine Statistics Authority (2024) | Not cited anywhere; cite it or remove it, and replace the homepage URL with the release URL if kept. |
| Briones et al. (2023) | Confirm the SPIDTECH usability figures appear in the PIDS paper; if not, cite the SPIDTECH article in the *Philippine Journal of Science* for them. |
| DA RFU/RFO 4-A (2022) | Confirm the office name on the document and the 497,000 ha figure; add a URL if one exists. |
| Bar-Gill et al.; Berman and Israeli | Confirm the "over a third" and "both studies observe" claims against the full texts. |
| TAM and UTAUT | Cite Davis (1989) and Venkatesh et al. (2003), or stop naming the models. |
| APA details | "Article" numbers; consistent page-range dash; proceedings pages for all or none; RA 10173 legal format. |

### E. Open team inputs carried in the drafts

Resolved from the previous manuscript and Appendices B to D (see A15): locale,
COSEL's full name, IT-expert count, farmer-seller estimate, instrument and
validators, use period and administration mode, minors and consent, retention,
adviser session dates, college name. Still open: external-buyer recruitment and
target N; total buyer respondents; MySQL version; VPS provider and deployment
date. Appendix D's header fields are still placeholders.

---

## Changelog against v3.4

| # | Change | Reason |
|---|---|---|
| 1 | Order chat added (Module B), with Laravel Reverb in the stack. | Decision 10, 20. |
| 2 | Scripted bilingual FAQ helper added; answers managed in the CMS with farm overrides and Super Admin moderation. | Decision 11. |
| 3 | Bilingual scope widened to the full app interface. | Decision 12; reverses "crop labels only". |
| 4 | Farm page added (Module A); farm contact number private. | Decision 13. |
| 5 | Farm announcements added (Module E), including scheduled delivery. | Decision 14. |
| 6 | My Sales added for farmer-sellers (Module D), one reporting window. | Decision 15; closes a manuscript claim that had no build. |
| 7 | Stale-order reminder and system auto-cancellation added (Module B). | Decision 16. |
| 8 | Super Admin CSV exports and export log added. | Decision 17; closes a manuscript claim that had no build. |
| 9 | Data-subject rights built: correct, download, request deletion, anonymisation. | Decision 18; closes a manuscript claim that had no build. |
| 10 | Change password added for app users. | Account hygiene; no decision needed. |
| 11 | Time zone set to Asia/Manila. | Decision 19. |
| 12 | Release-build endpoints and refusal screen; VPS processes documented. | Deployment readiness. |
| 13 | Farmer-seller account creation described as Super Admin-created; OTP applies to buyers. | Corrects v3.4's description to match the build. |
| 14 | Client scope settled: Manggahan is the sole evaluated partner. | Decision 21; closes the v3.4 open item. |
| 15 | PYAP name corrected; post-turnover administration recorded. | Decisions 22, 23. |
| 16 | Test totals: 182 tests, 1,320 assertions (PHP); 48 Flutter tests plus 3 live suites. Superseded by the addendum totals. | Confirmed runs at `c22e36f`. |
| 17 | DeLone and McLean (1992) page range confirmed as 60-95. | Closes the v3.4 note. |

### Deferred / open (not v3.5 behaviour changes)

- Gmail sending account's app password to be set before the demo.
- No push notifications (FCM); notifications are in-app and email only. Recorded
  as a limitation, not a planned feature.
- Accepted edge: a checkout in the same instant a deletion is approved can leave
  one order attached to "Deleted user"; milliseconds wide, pilot scale.
- The README's older phase sections (Reservations, POS) are outdated and should
  be trimmed in a documentation pass.

---

## Post-v3.5 addendum (hardening pass, 24 September 2026)

None of these changes adds a module, an actor, or a permission, so the five
modules, the golden thread, and the decision log stand as written. Chapter 3
was updated only where noted.

| # | Change | Commit | Manuscript effect |
|---|---|---|---|
| A1 | Shop favorites for buyers (deleted shops drop out of the list); buyer sees the seller's cancellation note on the order. | `36960ae`, `8fe30bc` | None (supporting table already omitted from Figure 7). |
| A2 | DemoSeeder with realistic data; stale pre-rebuild seeders removed; mail, broadcasts, and queued jobs suppressed while seeding. | `c83e82c`, `79f6e41` | None. |
| A3 | Indexes on hot queries; uploaded images stored as a 1600px JPEG (quality 82) plus a 400px thumbnail. | `798ec43` | None. |
| A4 | OTP returned in the API response only in `local`/`testing` with mail unconfigured; production returns 503. | `f5ef3c5` | None. |
| A5 | Image processing: resize before rotate, 40 MP decode cap, white background for transparency, memory limit only raised, all eight EXIF orientations handled. | `f2012b1`, `526ea07` | None. |
| A6 | Unverified-buyer banner no longer overlaps the status bar (closes the v3.4 carry-over). | `8fe30bc` | None. |
| A7 | `storage:prune-orphans` removes image files no row references (listing photos included). | `8fe30bc`, `f57264b` | None. |
| A8 | GitHub Actions CI on every push: Pint, PHPUnit, `flutter analyze`, `flutter test` (Flutter 3.47.4). | `2375443`, `efa1419` | Chapter 3 Testing Procedure mentions automated checks on every push. |
| A9 | Documentation correction: the OTP is sent directly, not through the queue worker. | none (docs) | Chapter 3 architecture paragraph and Figure 4 corrected. |
| A10 | Test totals: 215 tests, 1,538 assertions (PHP); isolation 8 tests, 138 assertions; seeder 26 of 26; Flutter 63 passed plus 3 live suites. | `efa1419` | Chapter 3 Testing Procedure updated. |
| A11 | Table 2 Flutter version set to 3.47.4. | none (docs) | Chapter 3 Table 2 updated; MySQL still team input. |
| A12 | Declaration of AI Use names Cursor (code and tests) and Claude (code review, coding-assistant instructions, manuscript drafting and editing, reference checks, Figures 1 to 8). | none (docs) | Chapter 3 marker resolved; team to confirm. |
| A13 | Table 3, Representative Test Cases (15 cases mapped to the automated suite, all Passed). | none (docs) | Chapter 3 Testing Procedure marker resolved. |
| A14 | Abstract drafted: 296 words, block format, 1.5 spacing, SDG 2 (target 2.3) named, five keywords. | none (docs) | New front-matter page; revise once results exist if the program requires. |
| A15 | Chapter 3 filled from the previous manuscript and Appendices B to D: locale (Barangay Manggahan urban garden, crops, buyers, farm price), COSEL full name, three to five IT experts, farmer-sellers estimated at four or five, one bilingual instrument, five-day window, online and printed administration, minors with parental consent and assent (Appendices H, I), records destroyed after the final defense, sessions of July 8 and July 15, 2026. | none (docs) | Five markers remain. |
