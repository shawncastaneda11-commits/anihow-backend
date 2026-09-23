# AniHow Capstone: Project Instructions (v3.4, Manuscript Drafting)

Supersedes v3.3 and, through it, v3.2, v3.1, v3, and the v2 Marketplace Rebuild
instructions in full. Where this document conflicts with any earlier instruction
set, working note, or prior manuscript section, THIS DOCUMENT WINS. v2, v3, v3.1,
v3.2, and v3.3 are historical reference only.

Status: system built, manuscript in drafting.

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
Filament CMS (eight resources, two roles on one policy-gated panel), and a
Flutter Android application.

**Test totals.** SmokeTestSeeder passes 26 of 26 service-level checks covering
checkout, the per-seller cart split, tawad application, stock held at Placed and
deducted at Confirmed, and review unlocking at Completed. The PHPUnit feature
suite covers the authentication gate (now including OTP email verification), the
client request contract, the crop-care read path (now gated to farmer-sellers),
the buyer order resource, the farmer order state machine, checkout, tawad
creation and validation, seller isolation, farm-scoped price guards, walk-in sale
recording, marketplace search (including storefront-name search), and listing
takedown/restore notification. Multi-farm isolation is a separate group of eight
RefreshDatabase checks, 138 assertions.

The full `php artisan test` suite passes **107 tests, 648 assertions**
(confirmed run). Layers within that total: FarmIsolationTest is 8 checks / 138
assertions; the rest is the feature suite (auth including OTP, checkout, farmer
order state machine, tawad, seller isolation, farm price guards, walk-in,
marketplace search including shop-name, and takedown/restore notification) plus
one unit example. SmokeTestSeeder's 26 service-level checks run separately from
the PHPUnit suite.

The overlap between the smoke and feature layers is deliberate: the seeder tests
the services, the feature suite tests the routes, and a policy or FormRequest can
fail while the service beneath it stays correct.

The Android application was recovered in September 2026. The client had been left
on the pre-rebuild API contract and could not place an order; nine verified
passes restored it, and the full marketplace flow was walked end to end on a
device.

Farm-scoped tighten-only price overrides (Sections 5 and 6) ship as the
`farm_crop_type_overrides` table, an effective-value resolver read across
checkout, tawad, and listing validation, a Filament relation manager, a per-farm
write policy, and a stranded-listing flag for the Super Admin. Walk-in sale
recording (Section 7) ships as three columns on `orders`, a `record_walk_in_sales`
permission, an order-creation path that lands a walk-in directly at Completed, and
a farmer-side app screen. Both carry RefreshDatabase feature coverage and both
were walked on a device.

**Email verification is OTP-based** (see Section 14): a 6-digit code with a
10-minute expiry, replacing the earlier signed-link flow, with an in-app
verification screen.

Not yet done, and outstanding before the system is a system rather than a build:
it has run only against seeder fixtures, never against a real farm's data, and it
is not deployed. The app has run on the emulator only. Section 14 names the VPS as
the deployment target. Mail transport is still on `log`; a real inbox (Mailtrap
for dev, Gmail SMTP for the demo) is a demo-preparation task.

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
  own Content Editor. PYAP (Pag-asa Youth of the Philippines) Manggahan Chapter,
  General Trias, Cavite, adopted through LPU-Cavite COSEL, is the pilot client.
  The multi-farm structure is a design capability. Whether additional farms
  widen the evaluated study population is a manuscript-scope question (Section 18,
  open item / manuscript decision), separate from the build capability.
- **Farmer term:** "member-farmer" is retired along with the communal-plot
  framing. Use **"farmer-seller"** for the selling actor and "farmer" in general
  prose.

---

## 4. Actor model (four actors, locked)

| Actor | Surface | Definition |
|---|---|---|
| **Super Admin** | CMS (web) | System owner. Accounts, approvals, economic guardrails, moderation, analytics, exports. Sole holder of destructive power. |
| **Content Editor** | CMS (web) | Representative of a partner farm, scoped to that farm. Owns crop-care reference content and the farm's catalog participation. No account power, no moderation power, no price-setting power. |
| **Farmer-Seller** | Android app | Approved member of a partner farm. Operates a storefront, creates listings, sets tawad rules, confirms and fulfils orders. |
| **Buyer** | Android app | Open registration. Browses, orders, arranges handover, reviews. |

**Account rules.** One account, one role. There is no dual-account model and no
role switching. **A farmer-seller therefore cannot buy.** Farmer-Seller
registration requires Super Admin approval with a farm membership check. Content
Editor accounts are created by the Super Admin. Buyer registration is open and
self-service.

**Removed roles:** Seller as a separate account type, PYAP Admin, COSEL Auditor,
Finance Manager (never existed; guard against reintroduction).

---

## 5. Who handles who

**Super Admin governs:**
- Creates, approves, suspends, and deletes all accounts including Content Editors
- Sets the **system floor price per crop type** and the **system maximum peso
  discount per crop type** on the shared taxonomy entry. These two system fields
  are Super Admin's alone. No other role edits them, and no override any role sets
  can escape them.
- Writes farm price overrides on any farm, held to the same tighten-only rule as
  a Content Editor. This keeps a farm's guards reachable when its Content Editor
  is suspended or absent, and it is why the write is gated by permission and farm
  match rather than by role name.
- Takes down listings (with a required reason recorded and the seller notified),
  restores them (the seller is now notified on restore as well), resolves reports,
  removes reviews, suspends users
- Reads the full order ledger and all descriptive dashboards; owns exports
- Owns the crop taxonomy structure
- **Moderates listings and crop-care content but never writes or edits either.**

**Content Editor governs, within their own farm only:**
- Crop-care and pest-management reference articles: write, edit, tag to crop
  types, publish, unpublish
- Farm profile content: description, contact, pickup point, photos
- Their farm's roster view (read-only; approvals stay with Super Admin)
- **Farm-scoped price guards, tighten-only.** The Content Editor may raise their
  farm's floor above the system floor and may lower their farm's maximum peso
  discount below the system maximum. They can never move either value in the
  loosening direction. A farm's effective floor is therefore the higher of the
  system floor and the farm override; a farm's effective discount ceiling is the
  lower of the system maximum and the farm override. Both overrides are optional
  and default to the system value.
- **Cannot** set or override another farm's values, cannot set a listing price,
  cannot moderate, cannot touch other farms' content

**Why tighten-only.** The taxonomy is shared, so a floor that any farm could
lower would stop being a floor for every other farm using that entry. Bounded
upward, farms differ from each other while one Super Admin number still fences
them all in. This is the answer to a panel question about why pricing is
centralized, and it survives the override.

**Farmer-Seller governs, within their own storefront only:**
- Listings: crop type selection, own photos, own description, own price at or
  above their farm's effective floor, quantity, availability
- Tawad rules on own listings, within their farm's effective discount ceiling
- Recording of walk-in sales, Section 7
- Own orders: confirm, decline, mark ready, mark completed, cancel with reason
- Cannot see another farmer-seller's listings, orders, or figures
- **Sees, read-only, which partner farm they belong to** (shown on their profile)
  **and their crop's effective floor price** (shown on the new-listing form,
  labeled as set by their farm). They cannot edit either; membership is
  admin-assigned at approval.

**Buyer:**
- Browses, searches (by crop type, storefront name, and produce keyword), carts,
  orders, cancels before confirmation
- Browses all storefronts through a shops directory reachable from the marketplace
- Reviews a farmer-seller only after a completed order with that farmer-seller

**Nobody handles money.** Cash changes hands in person. The system records that
it did. No gateway, no wallet, no escrow, no proceeds division.

---

## 6. Data structure spine

- **Farm** is a first-class entity. Farmer-Sellers belong to a farm. Content
  Editors are scoped to a farm. One Content Editor per farm.
- **Crop taxonomy** is shared and system-wide, not farm-scoped. A taxonomy entry
  holds: crop name, bilingual English and Filipino label, unit of measure, system
  floor price (Super Admin), system maximum peso discount (Super Admin), and links
  to crop-care articles.
  **Rationale to defend:** if each farm could set its own floor freely, the floor
  would stop being a guardrail. One kamatis entry, one system floor, many
  listings. Farms may tighten above that floor but never below it.
- **Farm price overrides** live in their own table, `farm_crop_type_overrides`,
  one row per farm per taxonomy entry, holding a nullable farm floor and a nullable
  farm discount ceiling. A missing row, or a row with both columns null, means the
  system value applies. `farm_id` cascades on delete, `crop_type_id` restricts, and
  the pair is unique. There is no CHECK on this table: the `crop_types` CHECK
  compares the system pair only, and tighten-only validation in the application
  preserves the invariant at farm level. Validation rejects a farm floor below the
  system floor and a farm ceiling above the system maximum.
- **Listing** is the farmer-seller's own object, created *under* a taxonomy entry.
  Own photos, own copy, own price, own stock. This is the Shopee model: the
  taxonomy is a category tree, not a product list.
- **Crop-care articles are farm-scoped in authorship, not in read access. Read
  access is farmer-seller-only.** Each farm's Content Editor writes their own
  guidance, tagged to shared taxonomy entries; article slugs are unique per farm,
  with the farm slug in the URL path. A draft is private to its farm. A published
  article is reference content readable by any authenticated **farmer-seller**,
  across farms, regardless of which farm authored it; the author farm is shown so
  provenance is visible. **Buyers have no crop-care access.** "Farm-scoped" governs
  who may write, edit, publish, and unpublish, not which farm's published articles
  a farmer-seller may read. (Reverses v3.3, which allowed buyer reads; see the
  v3.4 changelog row 1 and decision-log row 9.)
- **Multi-farm isolation is verified.** Across two farms, each farm's overrides,
  listings, orders, figures, farm-scoped analytics, article writes, and article
  moderation are private to that farm, and one farm's Content Editor cannot read
  another farm's drafts (a published article remains cross-farm readable to
  farmer-sellers and to content editors in the panel). The Super Admin reads across
  all farms. Eight RefreshDatabase checks, 138 assertions. The build supports many
  farms; the study population is a separate question, Section 18.

---

## 7. Marketplace flow (the system spine)

Reference model: Shopee and Lazada, minus payments and minus logistics.

1. Farmer-Seller creates a listing under a crop taxonomy entry. Price validated at
   or above the farm's effective floor, which is shown on the new-listing form.
   Auto-publishes.
2. Super Admin holds **takedown** power, not pre-approval power. Takedown records a
   required reason and notifies the seller; restore also notifies the seller.
3. Buyer browses by crop type, by storefront (via a shops directory or by tapping a
   seller), or by search (produce keyword, crop label, or storefront name). Opens a
   storefront or a listing. Adds to cart.
4. Checkout **splits the cart into one order per farmer-seller**, as Shopee does.
   Tawad rules apply automatically where their conditions are met. The cart shows a
   breakdown per seller-order plus one cart-wide order summary.
5. Buyer selects a fulfillment preference: **buyer pickup** or **seller delivers**.
   Both are text arrangements with a note field. No courier, no tracking number, no
   delivery fee, no route.
6. Payment method is **cash on handover**, recorded not processed.
7. Order status progression: **Placed, Confirmed, Ready, Completed, Cancelled.**
   Farmer-seller advances the status. **Stock is held at Placed and deducted at
   Confirmed.** Cancellation before Confirmed releases the held quantity
   automatically.
8. **A no-show is a cancellation reason, not a sixth order status.**
9. Handover happens face to face. Farmer-seller marks Completed and records the
   amount received.
10. Review unlocks at Completed, one per order, tied to that order. No order, no
    review.

**Walk-in sales.** A buyer without the app can still buy, and the sale is still
recorded. The Farmer-Seller records it in the Android application as an ordinary
order against an existing listing, with no buyer account attached and an optional
free-text buyer name kept for the seller's own reference. Crop type, unit, and
price come from the catalog as usual, tawad applies on the same rules, and stock
deducts. The record lands in the same order ledger as every app order.

Three consequences, each of which must be stated in Chapter 3 or the state diagram
will be wrong:

- A walk-in order is created directly at **Completed**, because the handover has
  already happened. This is the one exception to stock deducting at Confirmed.
- **No buyer account means no review.** The existing rule is no order, no review;
  this adds no buyer, no review.
- The walk-in path lives in the **Android application**, not the CMS, because the
  farmer-seller is the person holding the cash.

This is a second way into one order ledger. It is not a second sales surface and
not a sixth module.

---

## 8. Tawad (locked as a seller-set discount rule)

**Definition to state once, explicitly, in the Project Description sub-section of
Background and Rationale, and nowhere else in Chapter 1:** tawad in AniHow is
implemented as a seller-published peso discount rule rather than live negotiation,
because the system records transactions but does not mediate them.

- Peso amounts only. **No percentage input anywhere.**
- Exactly two rule types. Do not add a third.
  - Flat peso off per order
  - Peso off at a minimum quantity (for example, twenty pesos off at five kilos and
    above)
- Applies automatically at checkout when the condition is met.
- Buyer sees three lines: listed price, tawad, final total. The listed price is
  never overwritten.
- Super Admin sets the system maximum peso discount per crop type, and a farm may
  tighten it downward. The seller cannot exceed their farm's effective ceiling.
  `max_discount < floor_price` is enforced as a database CHECK constraint.
- Resulting unit price can never fall below the farm's effective floor price. Hard
  validation at rule creation **and** again at checkout, and again on a walk-in
  sale.
- A seller may edit or end a rule. Orders already Confirmed keep the price they
  were confirmed at.
- Feeds the "average discount given" summary directly.

**Definition of Terms carries a plain operational definition of tawad with no
rationale sentence.** The rationale appears only in Chapter 1.

---

## 9. Crop-care reference

CMS-managed articles, written by each farm's Content Editor, tagged to shared crop
taxonomy entries, **read-only in the app for Farmer-Sellers only (buyers have no
crop-care access)**. A farmer-seller reads published articles across all farms, and
each article shows its author farm. Covers crop care and pest management. It is
reference content, not a tracker, not a scheduler, not a decision engine, and never
a prerequisite for selling. (Reads restricted to farmer-sellers per the v3.4
changelog row 1 and decision-log row 9; reversed the earlier buyer-readable
position.)

---

## 10. Descriptive analytics

Descriptive only. Sourced entirely from the system's own listings and recorded
orders. **Analytics count completed orders only**, which includes walk-in orders
since those are recorded at Completed.

- Units sold per crop type
- Sales per period
- Best-selling produce (by week and by month)
- Average discount given

Surfaced as charts and graphs in the CMS. Super Admin sees system-wide; a farm's
view is scoped to that farm; a farmer-seller sees only their own figures.

---

## 11. Permanently removed, never reintroduce

- Crop-cycle and growth-phase tracking, plot scoping, harvest-readiness gating
- Point-of-Sale as a separate module, and a separate Sales Summary module. The
  summary half was absorbed by Descriptive Analytics. The recording half returns as
  the walk-in sale path inside Module B, Section 7. There is no POS surface, no
  second ledger, and no sixth module.
- The three-account model and any dual or switchable account
- PYAP Admin role, COSEL Auditor role
- Growth-phase posting prerequisite, owner-acceptance handshake, consignment
- Any farming prerequisite or checklist as a condition of selling
- Price analytics; Moving-Average price-trend classification (Rising, Steady,
  Falling); PSA OpenSTAT; forecasting; predictive analytics
- AI, IoT, sensors, predictive technology of any kind

**Hard boundaries:** no payment gateway, no courier or logistics integration, no
proceeds division, no percentage discounts.

---

## 12. Retained constraints

- **Bilingual scope:** selected bilingual English and Filipino **crop labels
  only**, held on the taxonomy entry. Do not widen this.
- **Data Privacy Act of 2012 (RA 10173):** "designed in accordance with", never
  "complies with". Role-restricted access; users can view, correct, export, and
  request deletion of their own information.
- **Evaluation:** ISO/IEC 25010:2023, four characteristics: functional
  suitability, usability, reliability, performance efficiency.
- **Instrument:** four-point Likert scale (Highly Acceptable 3.50 to 4.00, Fairly
  Acceptable 2.50 to 3.49, Acceptable 1.50 to 2.49, Unacceptable 1.00 to 1.49). The
  outline's sample results table shows different boundaries and reverses the two
  middle labels. That table is an illustrative artifact of a fictional study, not a
  mandated instrument. Our scale stands, and it divides the range into four equal
  intervals where the outline's does not. Section 18, decision 4.

---

## 13. Manuscript scope and structure

**This manuscript is Chapters 1 to 3 plus the abstract and front matter.** There is
no Chapter 4 and no Chapter 5. The outline document runs to five chapters because
it is the Capstone Project 2 full-manuscript template; Chapters 4 and 5 are out of
scope and are not drafted.

**Headings are unnumbered, bold, and left-aligned**, under a centered chapter label
and centered chapter title. Section numbers such as 1.4 are retained in working
filenames and in conversation only. They never appear in the manuscript.

### Chapter 1: INTRODUCTION

```
Background and Rationale of the Study
Objectives of the Study
Significance of the Study
Scope and Limitation
```

**Background and Rationale of the Study** integrates what were previously
Introduction, Project Context, and Project Description into one section, per the
outline's own instruction. Project Description runs as an italic-bold sub-section
inside it and carries the five module names verbatim. Those five names are the
golden thread; every later section audits against them.

- A. Marketplace and Storefront Module
- B. Order and Fulfillment Coordination Module
- C. Crop-Care and Pest-Management Reference Module
- D. Descriptive Analytics and Reporting Module
- E. Web-based Content Management System

**Manuscript follow-up (v3.4):** the Module C paragraph currently says crop-care is
"read-only in the app for Farmer-Sellers and Buyers." The buyer half is now
incorrect: crop-care is farmer-sellers only. This is the one factual error in the
current draft and is fixed first. Reflect the same in Definition of Terms if any
entry describes buyer access to articles.

**Objectives of the Study** keeps the FOUR-objective pattern: Design, Create, Test,
Evaluate, each with lettered sub-items. Do not reintroduce six objectives. The two
research designs, descriptive and developmental, map one-to-one to these four; this
is the Chapter 3 anchor.

**Significance precedes Scope**, per the outline. This reverses the old 1.5 and 1.6
order.

**Scope and Limitation** is the outline's heading, singular, used verbatim. It
carries two italic-bold sub-headings, ***Scope of the Study*** and ***Limitations
of the Study***. Deliberate boundaries are written inside Limitations and labelled
in prose as chosen rather than imposed. Section 18, decisions 1 and 7.

The outline's five-page Chapter 1 cap does not apply to this manuscript. Chapter 1
targets 8 to 10 pages. Section 18, decision 2.

### Chapter 2: REVIEW OF RELATED LITERATURE

```
Main topics (as many as the literature warrants)
Theoretical Framework          (requires a figure)
Conceptual Framework           (requires a figure)
Definition of Terms
```

**Definition of Terms lives in Chapter 2, not Chapter 1.** The existing 23-term
draft (24 with Walk-in Sale) transplants; the outline uses the same
bold-term-period format. Update any entry that describes buyer access to crop-care
(now farmer-sellers only) and, if the verification method is defined, note OTP
rather than email link.

**Theoretical Framework: the DeLone and McLean Information Systems Success Model
(2003).** Its System Quality, Information Quality, and Service Quality dimensions
carry the four ISO/IEC 25010 characteristics; User Satisfaction carries the
four-point acceptability instrument. Do not substitute TAM or UTAUT: both measure
adoption intention, which this study does not instrument for.

**Conceptual Framework: the Input-Process-Output model**, drawn as a figure.

Section 18, decisions 3 and 8.

All Chapter 2 sources are verified from scratch.

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

**Participants.** Internal actors (Farmer-Sellers, Content Editors, Super Admin)
remain total enumeration. Buyers are a **mixed pool**: community members and
external buyers. The external buyer group needs a stated recruitment method and a
target N. A separate IT-expert group is retained.

**Declaration of AI Use** appears as a highlighted insert in the template, which
means it is to be filled in rather than deleted.

**Manuscript follow-up (v3.4):** if System Development or the SRS names the
verification method, it is OTP (6-digit code), not an email link.

### Abstract

Project 1 style: **no Results, Discussion, or Conclusions**, because a Project 1
manuscript has none to report. Everything else in the outline's abstract
specification is honoured: 250 to 300 words, block format with no paragraph
indentation, **1.50 line spacing** (the one place the body's double spacing does
not apply), three to five keywords, and the relevant UN Sustainable Development
Goal named in both the abstract and the keywords using its official numbering and
title. Written last.

---

## 14. Tech stack (locked)

Flutter (Android only), Laravel REST API, Laravel Sanctum, Spatie Permission,
MySQL, Filament CMS (web, separate from the app). Deployment to VPS; Railway is
testing only.

**Versions: Filament 5, Laravel 13.32.0, PHP 8.4.25.**

**Email verification:** OTP-only, a 6-digit numeric code stored hashed in cache
(keyed by user id) with a 10-minute expiry, sent by email, verified in an in-app
screen. The earlier signed-link verification is removed. Mail transport is `log`
in dev; Mailtrap for dev testing and Gmail SMTP for the demo are the planned real
transports (Gmail SMTP needs 2FA and an app password on the sending account), and
they are a demo-preparation task, not a build blocker. The automated test suite
does not send mail.

**Role implementation:** Super Admin and Content Editor are two Spatie roles on
**one** Filament panel, policy-gated per resource. Not two separate panels.

**Version handling:** Chapter 1 and Chapter 2 prose is version-less. Versions are
stated in exactly one canonical place, Chapter 3 System Development; the SRS
mirrors it. Do not add version numbers elsewhere.

**Justification framework.** Every tool choice defends against four criteria:
Android-first on low-cost phones, zero cost, well-documented for a student team,
maintainable after handover. Development process is Iterative and Incremental,
anchored by two documented adviser validation sessions.

---

## 15. Sources of truth (priority order)

1. **This document**, the governing spec. The system is defined here and nowhere
   else.
2. **`2026-2027-CRD-FULL-MANUSCRIPT-CCS.docx`**, authority for **structure and
   format**, Chapters 1 to 3 only. This single document serves as both outline and
   format reference. Where it conflicts with this spec on system behaviour, this
   spec wins. Where it conflicts on structure or format, it wins.
3. **Workbook PDFs**, section checklists and expected length.

**Archived. Never a source of prose, structure, or system behaviour:**
MANUSCRIPT-FINAL.docx, SRS v1.3, the Project 1 diagram set, the Project 1 defense
assets, the separate "Capstone-Project-1-Manuscript-Outline-and-Format" document if
it resurfaces. Consult only to check a format convention or a fixed fact such as a
name or date.

**Never use at all:** Capstone_Capsule.pdf. Outdated price-analytics version; has
carried fabricated authors and predatory sources.

---

## 16. Format spec

Times New Roman 12pt; double spacing (abstract at 1.50); one-inch margins all
sides; page numbers bottom right; APA v7; **bold, left-aligned, unnumbered** section
headings under centered chapter labels; ring-bound; deliver .docx; diagrams SVG or
PNG. Table and figure notes use the label "Note" at 9pt, italicized, single spaced.

---

## 17. Working rules

- **Write fresh. Never adapt prior prose.** If a passage is needed, it is drafted
  new against this document.
- **NEVER use em dashes or en dashes in prose.** Use commas, colons, semicolons, or
  separate sentences. Hyphens in compound modifiers (role-based, peso-based,
  farm-scoped) are correct and kept. Sweep every draft for them.
- **The team is the resolution authority.** Flag mismatches early, then decide them
  in the same session. Nothing is parked for the adviser and nothing is left open
  across sessions. See Section 18 for how a decision is recorded.
- Make judgment calls on ambiguous items, then log the call and its reasoning.
- **Source verification is non-negotiable:** search title plus journal, fetch the
  DOI or repository page, confirm metadata before using any citation. Never
  fabricate authors or sources. Author and date corrections cascade to every
  in-text citation.
- Audit every newly drafted passage against the five verbatim module names before
  treating it as final.
- Facts only the team holds (client detail, current selling practice, farmer
  statements) are marked `[TEAM INPUT: ...]` in the draft. Never invented.

---

## 18. Decision authority and decision log

### The rule

The team resolves every open item. Claude proposes, the team decides, the decision
is logged in the table below with its reasoning, and drafting proceeds on it
immediately. No item is parked, deferred, or sent out for a ruling.

Three constraints on that rule, so it stays honest:

1. **A decision is logged, not just made.** Every entry carries the reasoning that
   would be given if a panelist asked. An undefended decision is not closed.
2. **The adviser still signs the approval sheet and the panel still grades.**
   Deciding an item here removes it from our queue, not from their review. He is
   informed of what was decided, not consulted on what to decide.
3. **An override is a new decision, not a reopening.** If Cajigas or the panel
   rejects a logged decision, it gets a new row with the new reasoning and a
   pointer to what it replaced. The old row stays. Nothing is deleted from this
   log.

### Decision log

| # | Item | Decision | Reasoning |
|---|---|---|---|
| 1 | Scope section heading | **"Scope and Limitation"**, verbatim, singular | Section 15 makes the outline the authority for structure and wording. Matching it exactly is the defensible position; "Project Scope and Delimitations" was a v2 invention with no source behind it. |
| 2 | Chapter 1 page cap | **Cap does not apply.** Chapter 1 targets 8 to 10 pages | The five-page cap governs the Project 2 template's condensation of this manuscript. A cap on the summary is not a cap on the source. |
| 3 | Theoretical Framework | **DeLone and McLean Information Systems Success Model (2003)** | The study measures delivered system quality and user acceptability, not adoption intention (TAM/UTAUT). The updated D&M model's quality dimensions take the four ISO/IEC 25010 characteristics; User Satisfaction takes the four-point instrument. Structural fit. |
| 4 | Likert boundaries | **Our four-point scale stands**: 3.50–4.00 Highly Acceptable, 2.50–3.49 Fairly Acceptable, 1.50–2.49 Acceptable, 1.00–1.49 Unacceptable | The outline's differing table sits inside a fictional worked example. Our boundaries divide the range into four equal intervals; the outline's do not. |
| 5 | Farm-scoped price overrides | **Adopted, tighten-only. Built and verified.** | Farms differ in quality and cost; a single national floor flattens that. Bounding the override upward keeps one Super Admin number fencing every farm in. |
| 6 | Walk-in sale recording | **Adopted, inside Module B. Built and verified.** | Buyers without the app are real and their sales were going uncounted. Routing them into the existing order ledger avoids a sixth module and a second ledger. |
| 7 | Scope section internal structure | Two italic-bold sub-headings: ***Scope of the Study*** and ***Limitations of the Study*** | The outline's Chapter 2 uses italic-bold sub-topics, so the convention exists. Deliberate boundaries are written inside Limitations and labelled as chosen. |
| 8 | Conceptual Framework | **Input-Process-Output model**, drawn as a figure | Required figure, standard form in this program, maps onto the four-objective pattern, needs no second theory. |
| 9 | Crop-care read access | **Buyers lose crop-care access. Reads are farmer-seller-only and system-wide; each article shows its author farm.** Reverses the v3.3 position that buyers may read published articles (Section 6, changelog row 9 against v3.2). | Buyers do not grow crops, so crop-care and pest-management reference is not theirs to act on; the team and adviser cut it. Reads stay system-wide for farmer-sellers because articles are tagged to the shared taxonomy, so any farm's kamatis guidance serves any kamatis seller, and the author farm is shown so provenance is visible. Gated in the policy layer (`viewAny` and `view`); the Filament CMS path is unaffected and multi-farm isolation still passes 8/8. |

### Open / manuscript-scope, not yet a logged behaviour decision

**Client scope.** Whether the study *evaluates* across additional partner farms or
keeps Manggahan as the sole evaluated pilot is a manuscript-scope question, not a
build question (the build supports many farms; multi-farm isolation is verified).
The manuscript context tracks this; the build treats Manggahan as the pilot. When
settled it becomes a decision row and cascades to Section 3, Chapter 1, and
Chapter 3.

### Verified source for decision 3

DeLone, W. H., & McLean, E. R. (2003). The DeLone and McLean model of information
systems success: A ten-year update. *Journal of Management Information Systems*,
*19*(4), 9-30. https://doi.org/10.1080/07421222.2003.11045748

The 1992 original in *Information Systems Research* is cited in Chapter 2 alongside
it. Its page range needs verification before use; two page ranges are in
circulation.

---

## 19. Drafting order

Definition of Terms and the Project Description content are already drafted.

1. **Background and Rationale of the Study**: drafted, awaiting remaining team
   inputs and the Project Description sub-section condensation
2. **Objectives of the Study**, **Significance of the Study**, **Scope and
   Limitation**: one pass, closes Chapter 1
3. **Chapter 2**, all sources verified from scratch, including both frameworks and
   their figures, with Definition of Terms transplanted in
4. **Chapter 3**, diagrams included
5. **Abstract**

All system diagrams are drawn fresh from this document. The Project 1 diagram set is
void.

---

## 20. Communication preferences

- Direct, brief. Flag specific errors without extended explanation.
- Make judgment calls rather than presenting options.
- Stepwise delivery over bulk output. Complete, ready-to-use deliverables with
  explicit changelogs.
- Shorter, simpler answers as sessions progress.
- Manual control retained for some tasks, for example image insertion into slides.
- Slide text: short phrases, three to six words per line, not bare keywords.
- Script: English-dominant Taglish; Tagalog only for connectors and politeness
  markers ("po").
- Cue cards: cue-line format, not prose.

---

## 21. Tooling patterns

- **PPTX:** Python zipfile plus direct XML; markitdown for text; pptx skill for
  thumbnails and slide duplication.
- **Word:** docx.js (Node.js) with section-type XML for multi-column and odd-page
  starts; docx skill for general document work.
- **PDF and visual QA:** LibreOffice headless, pdftoppm, pdftotext -bbox, dash
  auditing via pdftotext piped through grep on Unicode byte sequences.
- **Diagrams:** Graphviz grounded in manuscript text.
- **Grounding:** project_knowledge_search before generating any manuscript content.

---

## 22. Re-audit list

The features these passages describe are now built and verified, so the re-audit is
a rewrite against a settled system. Stale passages until re-audited:

| Passage | What is now wrong / to update |
|---|---|
| Project Description sub-section, Module A | Confirm floor and maximum discount describe the two-level system (Super Admin system value + tighten-only farm override) |
| Project Description sub-section, Module B | Walk-in path present; checkout not the only way an order is created |
| Project Description sub-section, Module C | **Crop-care is read-only for Farmer-Sellers only, not Buyers. The "and Buyers" is incorrect and is fixed first.** |
| Project Description sub-section, Module D | Analytics account for walk-in orders |
| Project Description sub-section, Module E | Content Editor tighten-only price guards; Super Admin two system fields |
| Definition of Terms, "Floor Price" / "Maximum Peso Discount" / "Content Editor" | Two-level system floor + farm override; Content Editor tightens |
| Definition of Terms, "Order" / "Order Status" / "Review" | Walk-in order has no buyer; walk-in enters at Completed; no buyer no review |
| Definition of Terms | Walk-in Sale entry present; crop-care access is farmer-sellers only; verification is OTP if defined |
| Background and Rationale, boundaries paragraph | Cash recorded at handover across two entry paths (app order and walk-in) |

Nothing else drafted is affected. Background and Rationale's statistical and locale
paragraphs, the objectives pattern, and every source citation stand.

---

## Changelog against v3.3

| # | Change | Reason |
|---|---|---|
| 1 | Crop-care reads restricted to farmer-sellers, system-wide. Buyers lose crop-care access. Each article shows its author farm. Sections 6 and 9 edited; new decision-log row 9 added. | Team and adviser decided buyers do not need crop-care/pest reference. Reads stay system-wide for sellers because articles tag the shared taxonomy, so any farm's guidance serves any seller of that crop, with the author farm shown. Reverses v3.3's buyer-readable position. Gated in the policy layer; Filament CMS path unaffected; isolation still 8/8. |
| 2 | Email verification is OTP-only (6-digit, 10-minute expiry, in-app screen), replacing the signed-link flow. | The signed-link path was unreachable from the app and fragile on mobile. OTP is self-contained, zero-dependency, inside the zero-cost boundary. |
| 3 | Farmer-Seller sees, read-only, their partner farm (on their profile) and the crop's effective floor price (on the new-listing form, labeled as set by their farm). | The farmer had no way to see which farm they belonged to or the floor before pricing. Membership stays admin-assigned. Closes the view-only half of the earlier Flag A. |
| 4 | Section 2 test totals: the full `php artisan test` suite passes **107 tests, 648 assertions** (confirmed run). | The running count drifted across passes during the session; a confirmed full run replaces the drifted estimate. |
| 5 | Restore action notifies the seller. | Takedown notified the seller with a reason; restore was silent. Symmetric notification added, with test coverage. |
| 6 | Marketplace search matches the seller storefront name (shop_name), not only farms.name. | The name buyers see and type is the storefront shop_name; farms.name differs, so farm-name searches returned nothing. Both are searchable now. |
| 7 | Buyer shops directory added (browse all storefronts), from the marketplace header. The farm_id marketplace filter was deliberately not built. | Buyers browse by storefront, the name they see; a partner-farm filter is a browse axis buyers do not perceive. Shops directory loads first page only (pilot-scale). |

### Deferred / open (not v3.4 behaviour changes)

- Mail transport still on `log`; Mailtrap-dev / Gmail-SMTP-demo deferred to demo
  prep (Gmail SMTP needs 2FA + app password).
- Banner and status-bar overlap on the unverified-buyer `MaterialBanner`: a
  `SafeArea` fix was attempted and reverted; needs a different approach. Deferred to
  the UI polish track.
- `cart_checkout_live_test` is the one known Flutter failure (fails on live DB
  state, not code).

---

## Historical changelogs (v3.3 and earlier)

The v3.3, v3.2, v3.1, v3, and v2 changelogs are retained in the v3.3 document and
its predecessors. They are not reproduced here; consult the v3.3 file for the full
change history prior to this version.
