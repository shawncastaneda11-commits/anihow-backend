# AniHow Capstone: Project Instructions (v3.2, Manuscript Drafting)

Supersedes v3.1 and, through it, v3 and the v2 Marketplace Rebuild instructions in full. Where this document
conflicts with any earlier instruction set, working note, or prior manuscript
section, THIS DOCUMENT WINS. v2, v3, and v3.1 are historical reference only.

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

The system is implemented across three surfaces: a Laravel API (87 routes,
four roles), a Filament CMS (eight resources, two roles on one
policy-gated panel), and a Flutter Android application.

Two test layers cover it. SmokeTestSeeder passes 26 of 26 service-level
checks covering checkout, the per-seller cart split, tawad application,
stock held at Placed and deducted at Confirmed, and review unlocking at
Completed. A PHPUnit feature suite passes 46 of 46 over HTTP, covering the
authentication gate, the client request contract, the crop-care read path,
the buyer order resource, the farmer order state machine, checkout, tawad
creation and validation, and seller isolation. The overlap between the two
layers is deliberate: the seeder tests the services, the feature suite
tests the routes, and a policy or FormRequest can fail while the service
beneath it stays correct.

The Android application was recovered in September 2026. The client had
been left on the pre-rebuild API contract and could not place an order;
nine verified passes restored it, and the full marketplace flow was walked
end to end on a device.

Two changes are specified in this document but not yet built. Farm-scoped
floor overrides (Section 5) and walk-in sale recording (Section 7). Both
are decided, Section 18 items 5 and 6. What is outstanding is
implementation, not approval.

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
  The multi-farm structure is a design capability, not a widened study
  population.
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
  discount per crop type** on the shared taxonomy entry. These two fields are
  locked to Super Admin. No other role can edit them, and no other role can set a
  value that escapes them.
- Takes down listings, resolves reports, removes reviews, suspends users
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

**Buyer:**
- Browses, searches, carts, orders, cancels before confirmation
- Reviews a farmer-seller only after a completed order with that farmer-seller

**Nobody handles money.** Cash changes hands in person. The system records that
it did. No gateway, no wallet, no escrow, no proceeds division.

---

## 6. Data structure spine

- **Farm** is a first-class entity. Farmer-Sellers belong to a farm. Content
  Editors are scoped to a farm. One Content Editor per farm.
- **Crop taxonomy** is shared and system-wide, not farm-scoped. A taxonomy entry
  holds: crop name, bilingual English and Filipino label, unit of measure, system
  floor price (Super Admin), system maximum peso discount (Super Admin), and
  links to crop-care articles.
  **Rationale to defend:** if each farm could set its own floor freely, the floor
  would stop being a guardrail. One kamatis entry, one system floor, many
  listings. Farms may tighten above that floor but never below it.
- **Farm price overrides** are two optional nullable fields on the farm, per
  taxonomy entry: a farm floor and a farm discount ceiling. Null means the system
  value applies. Validation rejects a farm floor below the system floor and a farm
  ceiling above the system maximum.
- **Listing** is the farmer-seller's own object, created *under* a taxonomy entry.
  Own photos, own copy, own price, own stock. This is the Shopee model: the
  taxonomy is a category tree, not a product list.
- **Crop-care articles are farm-scoped.** Each farm's Content Editor writes their
  own guidance, tagged to shared taxonomy entries. Article slugs are unique per
  farm, with the farm slug in the URL path.

---

## 7. Marketplace flow (the system spine)

Reference model: Shopee and Lazada, minus payments and minus logistics.

1. Farmer-Seller creates a listing under a crop taxonomy entry. Price validated
   at or above the farm's effective floor. Auto-publishes.
2. Super Admin holds **takedown** power, not pre-approval power.
3. Buyer browses by crop type, by farm, or by search. Opens a storefront or a
   listing. Adds to cart.
4. Checkout **splits the cart into one order per farmer-seller**, as Shopee does.
   Tawad rules apply automatically where their conditions are met.
5. Buyer selects a fulfillment preference: **buyer pickup** or **seller delivers**.
   Both are text arrangements with a note field. No courier, no tracking number,
   no delivery fee, no route.
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
deducts. The record lands in the same order ledger as every app order, so the
descriptive summaries read it without a separate query.

Three consequences, each of which must be stated in Chapter 3 or the state
diagram will be wrong:

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
implemented as a seller-published peso discount rule rather than live
negotiation, because the system records transactions but does not mediate them.

- Peso amounts only. **No percentage input anywhere.**
- Exactly two rule types. Do not add a third.
  - Flat peso off per order
  - Peso off at a minimum quantity (for example, twenty pesos off at five kilos
    and above)
- Applies automatically at checkout when the condition is met.
- Buyer sees three lines: listed price, tawad, final total. The listed price is
  never overwritten.
- Super Admin sets the system maximum peso discount per crop type, and a farm may
  tighten it downward. The seller cannot exceed their farm's effective ceiling.
  `max_discount < floor_price` is enforced as a database CHECK constraint.
- Resulting unit price can never fall below the farm's effective floor price.
  Hard validation at rule creation **and** again at checkout, and again on a
  walk-in sale.
- A seller may edit or end a rule. Orders already Confirmed keep the price they
  were confirmed at.
- Feeds the "average discount given" summary directly.

**Definition of Terms carries a plain operational definition of tawad with no
rationale sentence.** The rationale appears only in Chapter 1.

---

## 9. Crop-care reference

CMS-managed articles, written by each farm's Content Editor, tagged to shared
crop taxonomy entries, read-only in the app for Farmer-Sellers and Buyers. Covers
crop care and pest management. It is reference content, not a tracker, not a
scheduler, not a decision engine, and never a prerequisite for selling.

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
  summary half was absorbed by Descriptive Analytics. The recording half returns
  as the walk-in sale path inside Module B, Section 7. There is no POS surface,
  no second ledger, and no sixth module.
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
  Acceptable 2.50 to 3.49, Acceptable 1.50 to 2.49, Unacceptable 1.00 to 1.49).
  The outline's sample results table shows different boundaries and reverses the
  two middle labels. That table is an illustrative artifact of a fictional study,
  not a mandated instrument. Our scale stands, and it divides the range into four
  equal intervals where the outline's does not. Section 18, decision 4.

---

## 13. Manuscript scope and structure

**This manuscript is Chapters 1 to 3 plus the abstract and front matter.** There
is no Chapter 4 and no Chapter 5. The outline document runs to five chapters
because it is the Capstone Project 2 full-manuscript template; Chapters 4 and 5
are out of scope and are not drafted.

**Headings are unnumbered, bold, and left-aligned**, under a centered chapter
label and centered chapter title. Section numbers such as 1.4 are retained in
working filenames and in conversation only. They never appear in the manuscript.

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

**Objectives of the Study** keeps the FOUR-objective pattern: Design, Create,
Test, Evaluate, each with lettered sub-items. Do not reintroduce six objectives.
The two research designs, descriptive and developmental, map one-to-one to these
four; this is the Chapter 3 anchor.

**Significance precedes Scope**, per the outline. This reverses the old 1.5 and
1.6 order.

**Scope and Limitation** is the outline's heading, singular, used verbatim. It
carries two italic-bold sub-headings, ***Scope of the Study*** and ***Limitations
of the Study***. Deliberate boundaries are written inside Limitations and
labelled in prose as chosen rather than imposed. Section 18, decisions 1 and 7.

The outline's five-page Chapter 1 cap does not apply to this manuscript.
Chapter 1 targets 8 to 10 pages. Section 18, decision 2.

### Chapter 2: REVIEW OF RELATED LITERATURE

```
Main topics (as many as the literature warrants)
Theoretical Framework          (requires a figure)
Conceptual Framework           (requires a figure)
Definition of Terms
```

**Definition of Terms lives in Chapter 2, not Chapter 1.** The existing 23-term
draft transplants without a rewrite; the outline uses the same bold-term-period
format.

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

1. **This document**, the governing spec. The system is defined here and
   nowhere else.
2. **`2026-2027-CRD-FULL-MANUSCRIPT-CCS.docx`**, authority for **structure and
   format**, Chapters 1 to 3 only. This single document serves as both outline
   and format reference. Where it conflicts with this spec on system behaviour,
   this spec wins. Where it conflicts on structure or format, it wins.
3. **Workbook PDFs**, section checklists and expected length.

**Archived. Never a source of prose, structure, or system behaviour:**
MANUSCRIPT-FINAL.docx, SRS v1.3, the Project 1 diagram set, the Project 1 defense
assets, the separate "Capstone-Project-1-Manuscript-Outline-and-Format" document
if it resurfaces. Consult only to check a format convention or a fixed fact such
as a name or date.

**Never use at all:** Capstone_Capsule.pdf. Outdated price-analytics version;
has carried fabricated authors and predatory sources.

---

## 16. Format spec

Times New Roman 12pt; double spacing (abstract at 1.50); one-inch margins all
sides; page numbers bottom right; APA v7; **bold, left-aligned, unnumbered**
section headings under centered chapter labels; ring-bound; deliver .docx;
diagrams SVG or PNG. Table and figure notes use the label "Note" at 9pt,
italicized, single spaced.

---

## 17. Working rules

- **Write fresh. Never adapt prior prose.** If a passage is needed, it is
  drafted new against this document.
- **NEVER use em dashes or en dashes in prose.** Use commas, colons, semicolons,
  or separate sentences. Hyphens in compound modifiers (role-based, peso-based,
  farm-scoped) are correct and kept. Sweep every draft for them.
- **The team is the resolution authority.** Flag mismatches early, then decide
  them in the same session. Nothing is parked for the adviser and nothing is left
  open across sessions. See Section 18 for how a decision is recorded.
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

The team resolves every open item. Claude proposes, the team decides, the
decision is logged in the table below with its reasoning, and drafting proceeds
on it immediately. No item is parked, deferred, or sent out for a ruling.

Three constraints on that rule, so it stays honest:

1. **A decision is logged, not just made.** Every entry carries the reasoning
   that would be given if a panelist asked. An undefended decision is not closed.
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
| 2 | Chapter 1 page cap | **Cap does not apply.** Chapter 1 targets 8 to 10 pages | The five-page cap governs the Project 2 template's condensation of this manuscript, which its own note describes as a synthesis of the approved Project 1 proposal. A cap on the summary is not a cap on the source. |
| 3 | Theoretical Framework | **DeLone and McLean Information Systems Success Model (2003)** | The study measures delivered system quality and user acceptability. It does not measure adoption intention, which is what TAM and UTAUT measure and what this study has no instrument for. The updated D&M model's System Quality, Information Quality, and Service Quality dimensions take the four ISO/IEC 25010 characteristics directly, and User Satisfaction takes the four-point acceptability instrument. The fit is structural, not decorative. |
| 4 | Likert boundaries | **Our four-point scale stands**: 3.50 to 4.00 Highly Acceptable, 2.50 to 3.49 Fairly Acceptable, 1.50 to 2.49 Acceptable, 1.00 to 1.49 Unacceptable | The outline's differing table sits inside a worked example from a fictional rice-genetics study. It illustrates table formatting, not a mandated instrument. Our boundaries divide the 1.00 to 4.00 range into four equal intervals, which the outline's do not. |
| 5 | Farm-scoped price overrides | **Adopted, tighten-only** | Farms differ in quality and cost and a single national floor flattens that. Bounding the override upward keeps one Super Admin number fencing every farm in, so the guardrail argument survives intact. Reverses a v3 lock; that reversal is the point of this row. |
| 6 | Walk-in sale recording | **Adopted, inside Module B** | Buyers without the app are real and their sales were going uncounted, which would have made the analytics describe only half the trade. Routing them into the existing order ledger avoids a sixth module and avoids the second ledger that got POS cut in the first place. Reverses part of the permanently-removed list; scoped to the recording half only. |
| 7 | Scope section internal structure | Two italic-bold sub-headings: ***Scope of the Study*** and ***Limitations of the Study*** | The outline's own Chapter 2 uses italic-bold sub-topics, so the convention exists in the document. Deliberate boundaries (no payment gateway, no logistics, Android only, one pilot farm) are written inside Limitations and labelled in prose as chosen rather than imposed, which covers delimitations without a heading the outline does not have. |
| 8 | Conceptual Framework | **Input-Process-Output model**, drawn as a figure | The outline requires a Conceptual Framework figure and gives no form. IPO is the standard form in this program, maps cleanly onto the four-objective Design, Create, Test, Evaluate pattern, and does not require a second theory. |

### Verified source for decision 3

DeLone, W. H., & McLean, E. R. (2003). The DeLone and McLean model of
information systems success: A ten-year update. *Journal of Management
Information Systems*, *19*(4), 9-30.
https://doi.org/10.1080/07421222.2003.11045748

The 1992 original in *Information Systems Research* is cited in Chapter 2
alongside it. Its page range needs verification before use; two page ranges are
in circulation.

---

## 19. Drafting order

Definition of Terms and the Project Description content are already drafted.

1. **Background and Rationale of the Study**: drafted, awaiting three team
   inputs and the Project Description sub-section condensation
2. **Objectives of the Study**, **Significance of the Study**, **Scope and
   Limitation**: one pass, closes Chapter 1
3. **Chapter 2**, all sources verified from scratch, including both frameworks
   and their figures, with Definition of Terms transplanted in
4. **Chapter 3**, diagrams included
5. **Abstract**

All system diagrams are drawn fresh from this document. The Project 1 diagram set
is void.

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

## 22. Re-audit list

Changes 1 to 13 above contradict text already drafted. These passages are stale
until re-audited:

| Passage | What is now wrong |
|---|---|
| Project Description sub-section, Module A | Describes the floor and maximum discount as Super Admin's alone, with no farm layer |
| Project Description sub-section, Module B | Has no walk-in path; describes checkout as the only way an order is created |
| Project Description sub-section, Module D | Analytics paragraph does not account for walk-in orders |
| Project Description sub-section, Module E | Content Editor paragraph says the role cannot set prices, full stop |
| Definition of Terms, "Floor Price" | Defines one floor, set by Super Admin, with no farm override |
| Definition of Terms, "Maximum Peso Discount" | Same problem |
| Definition of Terms, "Content Editor" | Says the role holds no price-setting power |
| Definition of Terms, "Order" | Defines an order as between one Buyer and one Farmer-Seller; a walk-in order has no Buyer |
| Definition of Terms, "Order Status" | Does not note that walk-in orders enter at Completed |
| Definition of Terms, "Review" | Needs the no-buyer case |
| Definition of Terms | Needs a new entry: **Walk-in Sale** |
| Background and Rationale, boundaries paragraph | Says the system records payment at handover; still true, but now covers two entry paths |

Nothing else drafted is affected. Background and Rationale's statistical and
locale paragraphs, the objectives pattern, and every source citation stand.

---

## Changelog against v2

| # | Change | Reason |
|---|---|---|
| 1 | Build state recorded; prose now present tense | System is implemented and smoke-tested |
| 2 | Versions corrected to Filament 5, Laravel 13.32.0, PHP 8.4.25 | v2 Section 12 was stale |
| 3 | The CRD full-manuscript document promoted to sole structure and format authority | User directive, this session |
| 4 | The separate Project 1 outline document dropped from sources of truth | Superseded by item 3 |
| 5 | Chapter 1 reduced from seven numbered sections to four unnumbered ones | Outline structure |
| 6 | Introduction, Project Context, and Project Description merged into Background and Rationale | The outline's own instruction on that section |
| 7 | Project Description demoted to an italic-bold sub-section; five module names still verbatim | Consequence of item 6 |
| 8 | Significance now precedes Scope | Outline order; reverses old 1.5 and 1.6 |
| 9 | Definition of Terms relocated from Chapter 1 to Chapter 2 | Outline places it after Conceptual Framework |
| 10 | Section numbering removed from manuscript headings; retained in filenames only | Outline uses unnumbered headings |
| 11 | Scope heading recorded as "Scope and Limitation", singular, as a third variant | Outline's actual wording |
| 12 | Theoretical Framework and Conceptual Framework added to Chapter 2 as required sections | Outline; absent from v2 |
| 13 | Chapter 3 expanded to fourteen named sections; Implementation Plan and Declaration of AI Use added | Outline; absent from v2 |
| 14 | Manuscript scope stated explicitly as Chapters 1 to 3 plus abstract | The outline is a five-chapter Project 2 template |
| 15 | Abstract spec reconciled: Project 1 content, outline formatting, 1.50 spacing, SDG keywords | Direct conflict resolved in favour of each document's own authority |
| 16 | Chapter 1 five-page cap set aside as a judgment call | The cap governs the Project 2 condensation of this document |
| 17 | Likert conflict logged; our four-point scale retained as a judgment call | The outline's table is an illustrative artifact, not an instrument |
| 18 | Open adviser items section reintroduced with four entries | v2 removed it; four real open items now exist |
| 19 | One account one role clarified: a farmer-seller cannot buy | Settled this session |
| 20 | Super Admin moderates listings and crop-care content but never writes or edits either | Settled this session |
| 21 | No-show recorded as a cancellation reason, not a sixth status | Settled this session |
| 22 | Stock held at Placed, deducted at Confirmed | Settled in the build; v2 said only "deducts at Confirmed" |
| 23 | Analytics count completed orders only | Settled this session |
| 24 | `max_discount < floor_price` recorded as a CHECK constraint; article slugs unique per farm | From the build |
| 25 | Tawad definition placement updated to the Project Description sub-section | Consequence of item 6 |
| 26 | Tawad given a rationale-free operational entry in Definition of Terms | Settled; a glossary omitting the study's coined term invites a panel question |
| 27 | `[TEAM INPUT]` convention added to working rules | Three such gaps exist in Background and Rationale |
| 28 | Drafting order rewritten to reflect what is done and what the new structure requires | Consequence of items 5 to 9 |
| 29 | Project 1 assets section folded into Sections 15 and 19 | Redundant as a standalone section |

---

## Changelog against v3

| # | Change | Reason |
|---|---|---|
| 1 | Floor price and maximum peso discount renamed **system floor** and **system maximum** on the taxonomy entry | Two levels now exist and the prose has to distinguish them |
| 2 | Content Editor gains tighten-only farm price overrides | User directive, this session; pending adviser, item 5 |
| 3 | Farm price overrides added to the data spine as two optional nullable fields per taxonomy entry | Required by change 2; null means the system value applies |
| 4 | "Effective floor" and "effective discount ceiling" introduced as the values validation actually uses | Listing price, tawad rules, and checkout all resolve against the farm, not the taxonomy |
| 5 | Guardrail rationale rewritten to survive the override | The old sentence argued against per-farm floors outright; the new one argues against *lowering* them |
| 6 | Content Editor's "cannot set prices" narrowed to "cannot set a listing price" and "cannot override another farm" | The blanket denial is no longer accurate |
| 7 | Walk-in sale path added to Section 7 inside Module B | Buyers without the app were going uncounted; user's stated reason for the original POS |
| 8 | Walk-in orders created directly at Completed, recorded as the one exception to stock-at-Confirmed | The handover has already happened |
| 9 | Review rule extended: no buyer, no review | A walk-in order has no buyer account to attach a review to |
| 10 | Walk-in path placed in the Android application, not the CMS | The farmer-seller is the one holding the cash |
| 11 | Section 11's POS line rewritten rather than deleted | The separate module and separate sales summary stay dead; only the recording half returns |
| 12 | Analytics note clarified to include walk-in orders | They are recorded at Completed, so they already qualify |
| 13 | Tawad validation extended to walk-in sales | Third validation point, alongside rule creation and checkout |
| 14 | Section 2 now names both changes as specified but not built | The smoke test does not cover either yet |
| 15 | Open adviser items grown from four to six | Both changes reverse previously locked text |
| 16 | Re-audit list added as Section 22 | Drafted passages now contain statements this document contradicts |

---

## Changelog against v3.1

| # | Change | Reason |
|---|---|---|
| 1 | Section 18 renamed from "Open adviser items" to "Decision authority and decision log" | The team is now the resolution authority |
| 2 | Section 17 working rule rewritten: items are decided in-session, not parked | Same |
| 3 | All six open items closed, plus two that had been implicit | Nothing is left open across sessions |
| 4 | Three constraints attached to the rule: log the reasoning, the adviser still signs, an override is a new row | A decision the team cannot defend is not closed, and the adviser's sign-off is not ours to remove |
| 5 | Decision 1: Scope heading fixed as "Scope and Limitation", singular | Outline wording, verbatim |
| 6 | Decision 2: five-page Chapter 1 cap set aside; target 8 to 10 pages | The cap governs the Project 2 condensation |
| 7 | Decision 3: Theoretical Framework fixed as DeLone and McLean (2003), source verified to DOI | The study measures delivered quality, not adoption intention |
| 8 | Decision 4: four-point Likert boundaries confirmed | Equal intervals; the outline's table is an illustrative artifact |
| 9 | Decision 7 added: Scope section takes two italic-bold sub-headings | Delimitations covered without a heading the outline lacks |
| 10 | Decision 8 added: Conceptual Framework fixed as Input-Process-Output | Required figure, standard form, maps to the four objectives |
| 11 | Section 2 reworded: the two unbuilt changes are pending implementation, not approval | Consequence of changes 1 to 3 |
| 12 | Section 13 updated with all four structural decisions in place | Chapter 1 and Chapter 2 are now fully specified |
| 13 | TAM and UTAUT explicitly ruled out in Section 13 | Guard against substitution during Chapter 2 drafting |

---
