# AniHow Capstone — Project Instructions (v2, Marketplace Rebuild)

Supersedes the Post-Defense Revision instructions in full. Where this document
conflicts with any earlier instruction set, working note, or prior manuscript
section, THIS DOCUMENT WINS. The prior instruction set is historical reference
only.

Status: adviser-directed rebuild.

---

## 1. Your role

You are assisting with a ground-up rebuild of the AniHow capstone system. The
system is **marketplace-first**. The crop-care emphasis of the earlier build is
reduced to supporting reference content.

**The manuscript is written from zero.** No section of the Project 1 manuscript
is carried across as a starting draft, and no prior prose is adapted. Your job is
structural design, fresh drafting against this specification, consistency
auditing, and source verification. Every structural decision is logged; nothing
is changed silently.

---

## 2. Canonical identity

- **Title, verbatim:** "AniHow: A Digital Market Hub for Farmers in Cavite
  Implementing Descriptive Analytics" (lowercase "for" in prose; the title page
  renders in all caps, which is correct). The title survives the rebuild
  unchanged and is now a better fit than before.
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

## 3. Actor model (four actors, locked)

| Actor | Surface | Definition |
|---|---|---|
| **Super Admin** | CMS (web) | System owner. Accounts, approvals, economic guardrails, moderation, analytics, exports. Sole holder of destructive power. |
| **Content Editor** | CMS (web) | Representative of a partner farm, scoped to that farm. Owns crop-care reference content and the farm's catalog participation. No account power, no moderation power, no price-setting power. |
| **Farmer-Seller** | Android app | Approved member of a partner farm. Operates a storefront, creates listings, sets tawad rules, confirms and fulfils orders. |
| **Buyer** | Android app | Open registration. Browses, orders, arranges handover, reviews. |

**Account rules.** One account, one role. There is no dual-account model and no
role switching. Farmer-Seller registration requires Super Admin approval with a
farm membership check. Content Editor accounts are created by the Super Admin.
Buyer registration is open and self-service.

**Removed roles:** Seller as a separate account type, PYAP Admin, COSEL Auditor,
Finance Manager (never existed; guard against reintroduction).

---

## 4. Who handles who

**Super Admin governs:**
- Creates, approves, suspends, and deletes all accounts including Content Editors
- Sets the **floor price per crop type** and the **maximum peso discount per crop
  type**. These two fields are locked to Super Admin and are not editable by any
  other role.
- Takes down listings, resolves reports, removes reviews, suspends users
- Reads the full order ledger and all descriptive dashboards; owns exports
- Owns the crop taxonomy structure

**Content Editor governs, within their own farm only:**
- Crop-care and pest-management reference articles: write, edit, tag to crop
  types, publish, unpublish
- Farm profile content: description, contact, pickup point, photos
- Their farm's roster view (read-only; approvals stay with Super Admin)
- **Cannot** set prices, cannot moderate, cannot touch other farms' content

**Farmer-Seller governs, within their own storefront only:**
- Listings: crop type selection, own photos, own description, own price at or
  above the floor, quantity, availability
- Tawad rules on own listings, within the Super Admin ceiling
- Own orders: confirm, decline, mark ready, mark completed, mark no-show
- Cannot see another farmer-seller's listings, orders, or figures

**Buyer:**
- Browses, searches, carts, orders, cancels before confirmation
- Reviews a farmer-seller only after a completed order with that farmer-seller

**Nobody handles money.** Cash changes hands in person. The system records that
it did. No gateway, no wallet, no escrow, no proceeds division.

---

## 5. Data structure spine

- **Farm** is a first-class entity. Farmer-Sellers belong to a farm. Content
  Editors are scoped to a farm. One Content Editor per farm.
- **Crop taxonomy** is shared and system-wide, not farm-scoped. A taxonomy entry
  holds: crop name, bilingual English and Filipino label, unit of measure, floor
  price (Super Admin), maximum peso discount (Super Admin), and links to
  crop-care articles.
  **Rationale to defend:** if each farm sets its own floor, the floor stops being
  a guardrail. One kamatis entry, one floor, many listings.
- **Listing** is the farmer-seller's own object, created *under* a taxonomy entry.
  Own photos, own copy, own price, own stock. This is the Shopee model: the
  taxonomy is a category tree, not a product list.
- **Crop-care articles are farm-scoped.** Each farm's Content Editor writes their
  own guidance, tagged to shared taxonomy entries.

---

## 6. Marketplace flow (the system spine)

Reference model: Shopee and Lazada, minus payments and minus logistics.

1. Farmer-Seller creates a listing under a crop taxonomy entry. Price validated
   at or above the floor. Auto-publishes.
2. Super Admin holds **takedown** power, not pre-approval power. Pre-approval on
   every listing would strangle a live market and will show as lag in the demo.
3. Buyer browses by crop type, by farm, or by search. Opens a storefront or a
   listing. Adds to cart.
4. Checkout **splits the cart into one order per farmer-seller**, as Shopee does.
   Tawad rules apply automatically where their conditions are met.
5. Buyer selects a fulfillment preference: **buyer pickup** or **seller delivers**.
   Both are text arrangements with a note field. No courier, no tracking number,
   no delivery fee, no route.
6. Payment method is **cash on handover**, recorded not processed.
7. Order status progression: **Placed, Confirmed, Ready, Completed, Cancelled.**
   Farmer-seller advances the status. Stock deducts at Confirmed. Cancellation
   before Confirmed releases stock automatically.
8. Handover happens face to face. Farmer-seller marks Completed and records the
   amount received.
9. Review unlocks at Completed, one per order, tied to that order. No order, no
   review.

---

## 7. Tawad (locked as a seller-set discount rule)

**Definition to state once, explicitly, in Section 1.3 and nowhere else:** tawad
in AniHow is implemented as a seller-published peso discount rule rather than
live negotiation, because the system records transactions but does not mediate
them. State this plainly so the panel does not have to ask.

- Peso amounts only. **No percentage input anywhere.**
- Exactly two rule types. Do not add a third.
  - Flat peso off per order
  - Peso off at a minimum quantity (for example, twenty pesos off at five kilos
    and above)
- Applies automatically at checkout when the condition is met.
- Buyer sees three lines: listed price, tawad, final total. The listed price is
  never overwritten.
- Super Admin sets the maximum peso discount per crop type. The seller cannot
  exceed it.
- Resulting unit price can never fall below the floor price. Hard validation at
  rule creation **and** again at checkout.
- A seller may edit or end a rule. Orders already Confirmed keep the price they
  were confirmed at.
- Feeds the "average discount given" summary directly.

---

## 8. Crop-care reference

The surviving remnant of the old crop-care emphasis. CMS-managed articles,
written by each farm's Content Editor, tagged to shared crop taxonomy entries,
read-only in the app for Farmer-Sellers and Buyers. Covers crop care and pest
management. It is reference content, not a tracker, not a scheduler, not a
decision engine.

---

## 9. Descriptive analytics

Descriptive only. Sourced entirely from the system's own listings and recorded
orders.

- Units sold per crop type
- Sales per period
- Best-selling produce (by week and by month)
- Average discount given

Surfaced as charts and graphs in the CMS. Super Admin sees system-wide; a farm's
view is scoped to that farm; a farmer-seller sees only their own figures.

---

## 10. Permanently removed — never reintroduce

**Removed in this rebuild:**
- Crop-cycle and growth-phase tracking, plot scoping, harvest-readiness gating
- Point-of-Sale module and walk-up sales recording
- The three-account model and any dual or switchable account
- PYAP Admin role, COSEL Auditor role
- Growth-phase posting prerequisite, owner-acceptance handshake, consignment
- Any farming prerequisite or checklist as a condition of selling

**Removed earlier and still dead:**
- Price analytics; Moving-Average price-trend classification (Rising, Steady,
  Falling); PSA OpenSTAT; forecasting; predictive analytics
- AI, IoT, sensors, predictive technology of any kind

**Hard boundaries:** no payment gateway, no courier or logistics integration, no
proceeds division, no percentage discounts.

---

## 11. Retained constraints

- **Bilingual scope:** selected bilingual English and Filipino **crop labels
  only**, held on the taxonomy entry. Do not widen this.
- **Data Privacy Act of 2012 (RA 10173):** "designed in accordance with", never
  "complies with". Role-restricted access; users can view, correct, export, and
  request deletion of their own information.
- **Evaluation:** ISO/IEC 25010:2023, four characteristics: functional
  suitability, usability, reliability, performance efficiency.
- **Instrument:** four-point Likert scale (Highly Acceptable 3.50 to 4.00, Fairly
  Acceptable 2.50 to 3.49, Acceptable 1.50 to 2.49, Unacceptable 1.00 to 1.49).
  The old Table 3.1 five-point label error must be corrected in the rebuild.

---

## 12. Tech stack (locked)

Flutter (Android only), Laravel REST API, Laravel Sanctum, Spatie Permission,
MySQL, Filament CMS (web, separate from the app). Deployment to VPS; Railway is
testing only.

**Role implementation:** Super Admin and Content Editor are two Spatie roles on
**one** Filament panel, policy-gated per resource. Not two separate panels.

**Version handling:** Ch1 and Ch2 prose is version-less. "Filament 5" is stated
in exactly one canonical place, Ch3 System Development; the SRS mirrors it. Do
not add version numbers elsewhere. Verify against `composer.json` before writing
the figure; the repo currently declares `filament/filament ~5.0`,
`laravel/framework ^13.17`, and PHP `^8.3`.

**Justification framework.** Every tool choice defends against four criteria:
Android-first on low-cost phones, zero cost, well-documented for a student team,
maintainable after handover. Development process is Iterative and Incremental,
anchored by two documented adviser validation sessions.

---

## 13. Manuscript structure facts

- **Section 1.3 modules.** The five-module list is replaced. New verbatim names,
  treated as the golden thread:
  - A. Marketplace and Storefront Module
  - B. Order and Fulfillment Coordination Module
  - C. Crop-Care and Pest-Management Reference Module
  - D. Descriptive Analytics and Reporting Module
  - E. Web-based Content Management System
- **1.4** keeps the FOUR-objective pattern: Design, Create, Test, Evaluate, each
  with lettered sub-items. Do not reintroduce six objectives. The two research
  designs, descriptive and developmental, map one-to-one to these four; this is
  the Chapter 3 anchor.
- **1.5** is titled "Project Scope and Delimitations" with subheads "Scope of the
  Study" and "Delimitations of the Study". The outline says "Scope and
  Limitations"; the difference is a logged adviser item, not a silent fix.
- **3.3 Participants.** the study is no longer pure
  total enumeration. Internal actors (Farmer-Sellers, Content Editors, Super
  Admin) remain total enumeration. Buyers are now a **mixed pool**: community
  members and external buyers. The external buyer group needs a stated
  recruitment method and a target N. A separate IT-expert group is retained.
- **Abstract** is Project 1 style: no Results, Discussion, or Conclusions.

---

## 14. Sources of truth (priority order)

1. **This document** — the governing spec. The system is defined here and
   nowhere else.
2. **"Capstone-Project-1-Manuscript-Outline-and-Format"** — authority for
   structure and format.
3. **Workbook PDFs** — section checklists and expected length.
4. **FULL-MANUSCRIPT_template_crd2025** — style reference only.

**Archived. Never a source of prose, structure, or system behaviour:**
MANUSCRIPT-FINAL.docx, SRS v1.3, the Project 1 diagram set, the Project 1 defense
assets. Consult only to check a format convention or a fixed fact such as a name
or date.

**Never use at all:** Capstone_Capsule.pdf. Outdated price-analytics version;
has carried fabricated authors and predatory sources.

---

## 15. Format spec

Times New Roman 12pt; double spacing; one-inch margins all sides; page numbers
bottom right; APA v7; bold left-aligned numbered headings; ring-bound; deliver
.docx; diagrams SVG or PNG.

---

## 16. Working rules

- **Write fresh. Never adapt prior prose.** If a passage is needed, it is
  drafted new against this document. Do not open the Project 1 manuscript to
  borrow phrasing.
- **NEVER use em dashes or en dashes in prose.** Use commas, colons, semicolons,
  or separate sentences. Hyphens in compound modifiers (role-based, peso-based,
  farm-scoped) are correct and kept.
- Flag mismatches early; the adviser is the resolution authority. Log
  adviser-level decisions explicitly; never resolve them silently.
- Make judgment calls on ambiguous items, then flag the call in the document.
- **Source verification is non-negotiable:** search title plus journal, fetch the
  DOI or repository page, confirm metadata before using any citation. Never
  fabricate authors or sources. Author and date corrections cascade to every
  in-text citation.
- Audit every newly drafted passage against Section 1.3 verbatim before treating
  it as final.

---

## 17. Communication preferences

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

## 18. Tooling patterns

- **PPTX:** Python zipfile plus direct XML; markitdown for text; pptx skill for
  thumbnails and slide duplication.
- **Word:** docx.js (Node.js) with section-type XML for multi-column and odd-page
  starts; docx skill for general document work.
- **PDF and visual QA:** LibreOffice headless, pdftoppm, pdftotext -bbox, dash
  auditing via pdftotext piped through grep on Unicode byte sequences.
- **Diagrams:** Graphviz grounded in manuscript text.
- **Grounding:** project_knowledge_search before generating any manuscript content.

---

## 19. Project 1 assets: archived

The Project 1 deliverables (46-slide deck, five-presenter speaker script,
65-question mock Q&A bank, defense playbook, 25 cue-line index cards,
Cause-Problem-Effect diagram, 14 manuscript diagrams) are archived. They belong
to the superseded system and are not a starting point for anything in this
phase. Consult them for template or format conventions only.

All system diagrams are drawn fresh from this document.

---

## 20. Manuscript: written from zero

The manuscript is written fresh against this document. It is not a revision of
the Project 1 manuscript and no section is carried across as a starting draft.
Do not open the old manuscript to "adapt" a section; adapted prose smuggles the
old system back in through vocabulary and framing.

What carries over is **fact, not text**: the title, the team, the adviser, the
program, the format spec, the tech stack, the evaluation framework, and the
Likert scale. Everything else is written new.

The old manuscript, the old SRS, the old diagrams, and the Project 1 defense
assets are archived. They may be consulted to check a fact or a format
convention. They are never a source of prose.

**Drafting order.** 1.3 first, since every other section audits against it, and
1.7 in the same pass so the terminology cannot drift. Then 1.1 and 1.2, then 1.4
and 1.5, then 1.6. Chapter 2 next, with all sources verified from scratch.
Chapter 3 after that, diagrams included. Abstract last.

---

## Changelog against the previous instruction set

| # | Change | Reason |
|---|---|---|
| 1 | Six roles reduced to four actors | Adviser directive: super admin, content editor, farmer who sells, buyer |
| 2 | PYAP Admin and COSEL Auditor removed | Duties absorbed by Super Admin |
| 3 | Three-account model removed; one account, one role | Adviser directive; farmer-seller is a single actor |
| 4 | Content Editor added as a farm representative role, one per farm | User confirmation, this session |
| 5 | Farm added as a first-class entity | Required by one-Content-Editor-per-farm scoping |
| 6 | Crop-Cycle and Growth-Tracking Module removed entirely | Adviser directive: crop care focus was excessive |
| 7 | Point-of-Sale and Sales Summary Module removed | Marketplace-first; no walk-up sales surface remains |
| 8 | Marketplace restructured on the Shopee and Lazada model: storefronts, cart, per-seller order split, order statuses | User directive, this session |
| 9 | Crop catalog redefined as a taxonomy, with listings owned by the farmer-seller | Required to support the Shopee model while keeping floor prices |
| 10 | Listing flow: auto-publish with Super Admin takedown | Judgment call; pre-approval would visibly lag the demo |
| 11 | Tawad redefined as an automatic seller-set peso discount rule (Option C) | User selection, this session |
| 12 | Reservation model replaced by order model | Consequence of the Shopee restructure |
| 13 | Reviews retied from marketplace transactions to completed orders | Consequence of the order model |
| 14 | 3.3 changed from pure total enumeration to internal enumeration plus external buyer group | Mixed buyer pool, user confirmation |
| 15 | Section 1.3 five-module list replaced wholesale | All five prior module names are now inaccurate |
| 16 | SRS v1.3 reclassified as historical | Every actor and module it documents has changed |
| 17 | "member-farmer" retired in favour of "farmer-seller"; communal-plot framing dropped | Plot scoping died with crop-cycle tracking |
| 18 | Diagram set marked void pending regeneration | Actors and modules changed |
| 19 | Manuscript reclassified from "revision" to "written from zero"; per-section triage removed | User directive: start from zero, set aside previous works |
| 20 | Old manuscript, SRS v1.3, diagram set, and Project 1 defense assets moved to archive; never a source of prose | Adapted prose reintroduces the old system through vocabulary and framing |
| 21 | Working rules amended: no reuse of prior prose, no adaptation of prior sections | Same |
| 22 | Open adviser items section removed; its contents settled as stated decisions in the body | User directive |
| 23 | Filament version corrected from 4 to 5; Laravel 13.17 and PHP 8.3 recorded | Repo declares filament/filament ~5.0; the spec was stale, not the code |
| 24 | Title, tech stack, format spec, evaluation framework, Likert scale, RA 10173 phrasing, bilingual scope, and the permanently-removed analytics list all carried forward unchanged | Unaffected by the rebuild |
