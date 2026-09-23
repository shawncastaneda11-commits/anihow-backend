# AniHow Manuscript, Chapter 1

## Sections 1.3 and 1.7, Draft 1

Written from zero against the Project Instructions. Crop-care access, farm
visibility, listing-floor helper, restore notify, and shops directory are
aligned to `docs/spec/AniHow_Project_Instructions_v3.4.md`. No prose adapted
from the Project 1 manuscript. No em dashes or en dashes. Version-less per
Section 12.

---

# 1.3 Proposed Research Project

AniHow is a digital market hub for farmers in Cavite. It consists of an Android
application and a separate web-based content management system reached through a
browser. The application serves two actors, the Farmer-Seller and the Buyer. The
content management system serves two more, the Super Admin and the Content
Editor. One account carries one role. There is no dual account and no role
switching.

The system gives farmers in Cavite a direct selling channel to buyers, records
each transaction that results, and produces descriptive summaries from those
records. Money is never held or moved by the system. Cash changes hands in
person and the system records that it did.

A farm is a first-class entity in the system. Every Farmer-Seller belongs to a
partner farm, and every partner farm is represented in the content management
system by one Content Editor. The pilot client is the Pag-asa Youth of the
Philippines (PYAP) Manggahan Chapter of General Trias, Cavite, adopted through
the LPU-Cavite Community Outreach and Service Learning office. The multi-farm
structure is a design capability of the system and not a widening of the study
population.

The proposed system is organized into five modules.

## A. Marketplace and Storefront Module

This module holds the produce catalog and the selling surface. The catalog is
structured as a shared crop taxonomy rather than as a list of products. One
taxonomy entry represents one crop type and holds the crop name, an English
label and a Filipino label, the unit of measure, the floor price, the maximum
peso discount, and links to the crop-care articles tagged to it. The floor price
and the maximum peso discount are set by the Super Admin and cannot be edited by
any other actor. The taxonomy is system-wide, so a single kamatis entry governs
every kamatis listing regardless of which farm posted it.

A listing is the Farmer-Seller's own object, created under a taxonomy entry. The
Farmer-Seller supplies the photographs, the description, the price, the available
quantity, and the availability state. The price is validated at or above the
farm's effective floor for that crop. On the new-listing form the Farmer-Seller
sees that floor, labeled as set by their farm. A listing publishes on creation.
The Super Admin holds takedown power over a published listing rather than
approval power over a pending one, so a new listing reaches buyers without an
administrative wait. Takedown and restore each notify the seller in-app.

Each Farmer-Seller operates a storefront that gathers their listings under their
name. Their shop profile shows the partner farm they belong to. Buyers reach
listings by crop type, by storefront, or by keyword search, including a shops
directory of storefronts, and add them to a cart. A Farmer-Seller cannot view
another Farmer-Seller's listings, orders, or figures.

Tawad in AniHow is implemented as a seller-published peso discount rule rather
than as live negotiation, because the system records transactions but does not
mediate them. A Farmer-Seller may attach one of exactly two rule types to a
listing: a flat peso amount off an order, or a peso amount off once a minimum
quantity is reached, for example twenty pesos off at five kilograms and above.
Rules are expressed in pesos only, and the system accepts no percentage input
anywhere. A rule may not exceed the maximum peso discount set by the Super Admin
for that crop type, and a rule that would bring the unit price below the floor
price is rejected when the rule is created and rejected again at checkout.

## B. Order and Fulfillment Coordination Module

This module carries a transaction from cart to completed handover. At checkout
the cart is split into one order per Farmer-Seller, so a buyer who selects
produce from three sellers places three separate orders. Tawad rules apply
automatically to an order whose conditions they meet. The buyer sees three
lines, the listed price, the tawad amount, and the final total. The listed price
is never overwritten.

The buyer records a fulfillment preference, either buyer pickup or seller
delivers, together with a note field for the agreed time and place. The
preference is a text arrangement between the two parties. The system does not
book couriers, compute delivery fees, issue tracking numbers, or plan routes.
The payment method is cash on handover, recorded by the system and not processed
by it.

An order moves through five states: Placed, Confirmed, Ready, Completed, and
Cancelled. The Farmer-Seller advances the state. Stock is deducted from the
listing at Confirmed, and a cancellation before Confirmed releases the held
quantity automatically. An order that has reached Confirmed keeps the price and
the tawad amount it was confirmed at, even if the Farmer-Seller later edits or
ends the rule. At Completed the Farmer-Seller records the amount of cash
received. A buyer may leave one review per completed order, tied to that order
and to that Farmer-Seller. A buyer with no completed order with a given
Farmer-Seller cannot review that Farmer-Seller.

## C. Crop-Care and Pest-Management Reference Module

This module holds reference articles on crop care and pest management. Each
partner farm's Content Editor writes and maintains that farm's own articles in
the content management system, tags each article to entries in the shared crop
taxonomy, and publishes or unpublishes them. Articles are read-only inside the
Android application for Farmer-Sellers. A Farmer-Seller sees published articles
from every farm, and each article names its author farm. Buyers do not read
crop-care articles in the app.

The module is reference material and nothing further. It does not track crop
cycles or growth phases, does not schedule farm activities, does not gate any
selling action behind a farming prerequisite, and does not recommend a course of
action to the reader.

## D. Descriptive Analytics and Reporting Module

This module summarizes what the system has already recorded. It produces four
outputs: units sold per crop type, sales per period, best-selling produce by
week and by month, and average discount given. All four are computed from the
system's own listings and recorded orders. The module uses no external data
source and projects no future value. It reports what happened. It does not
forecast, classify price movement, or recommend a price.

Output is presented as charts and graphs in the content management system. The
Super Admin sees system-wide figures and owns the exports. A farm's view is
scoped to that farm. A Farmer-Seller sees only their own figures.

## E. Web-based Content Management System

This is the administrative surface, separate from the Android application and
reached through a browser. It hosts the Super Admin and the Content Editor on a
single panel, with access restricted per resource according to the role held.

The Super Admin creates, approves, suspends, and deletes all accounts, including
Content Editor accounts. The Super Admin sets the floor price and the maximum
peso discount on each taxonomy entry, owns the structure of the crop taxonomy,
takes down listings, restores a taken-down listing, resolves reports, removes
reviews, suspends users, reads the full order ledger, and reads every
descriptive dashboard. Takedown and restore each notify the seller. Destructive
actions are held by the Super Admin alone.

The Content Editor works inside one partner farm. The Content Editor writes,
edits, tags, publishes, and unpublishes that farm's crop-care articles, maintains
the farm profile including its description, contact details, pickup point, and
photographs, and views the farm's roster of approved Farmer-Sellers without
acting on it. A Content Editor cannot set prices, cannot moderate, and cannot
reach another farm's content.

Farmer-Seller registration requires Super Admin approval together with a check of
the applicant's farm membership. Content Editor accounts are created by the Super
Admin. Buyer registration is open and self-service.

## Boundaries of the proposed system

The system is designed in accordance with the Data Privacy Act of 2012 (Republic
Act No. 10173). Access to records is restricted by role, and a user can view,
correct, export, and request the deletion of their own information.

The system does not integrate a payment gateway, does not integrate a courier or
logistics service, does not divide proceeds between any parties, and does not
apply percentage-based discounts. It does not use artificial intelligence, the
internet of things, sensors, or any predictive technology.

---

# 1.7 Definition of Terms

The following terms are defined as they are used in this study. Where a term
carries a meaning specific to AniHow, the definition given is operational.

**Android Application.** The mobile surface of AniHow, installed on an Android
device. It is the only surface available to Farmer-Sellers and Buyers.

**Buyer.** An actor who browses listings, places orders, arranges handover, and
leaves reviews. Buyer registration is open and self-service, and a Buyer holds no
administrative or selling capability. A Buyer does not read crop-care articles.

**Cash on Handover.** The payment method used in AniHow. The buyer pays the
Farmer-Seller in cash when the produce changes hands in person, and the
Farmer-Seller records the amount received in the system. The system records the
payment and does not process, hold, or transfer it.

**Content Editor.** An actor in the content management system who represents one
partner farm. A Content Editor maintains that farm's crop-care articles and farm
profile. One Content Editor is assigned per farm. The role holds no account
power, no moderation power, and no price-setting power.

**Crop Taxonomy.** The shared, system-wide catalog of crop types. A taxonomy
entry holds the crop name, an English label and a Filipino label, the unit of
measure, the floor price, the maximum peso discount, and links to crop-care
articles. The taxonomy is a category structure, not a list of items for sale.

**Descriptive Analytics.** The summarization of data that has already been
recorded in order to describe what has happened. In this study it excludes
prediction, forecasting, and prescriptive recommendation.

**Farm.** A partner agricultural organization registered in the system as a
first-class entity. Farmer-Sellers belong to a farm, a Content Editor is scoped
to a farm, and crop-care articles are owned by a farm.

**Farmer-Seller.** An approved member of a partner farm who operates a storefront
in the Android application, creates listings, sets tawad rules, and confirms and
fulfils orders. Their profile shows the partner farm they belong to. They read
published crop-care articles from every farm. Registration requires Super Admin
approval and a farm membership check.

**Floor Price.** The minimum price per unit allowed for a crop type. The Super
Admin sets the system floor on the taxonomy entry. A farm may raise that floor
for its own sellers. No listing price and no discounted unit price may fall
below the farm's effective floor. The new-listing form shows that effective
floor to the Farmer-Seller.

**Fulfillment Preference.** The buyer's stated arrangement for receiving an
order, recorded as either buyer pickup or seller delivers, with a note field for
the agreed time and place. It is a text arrangement and carries no courier
booking, delivery fee, tracking number, or route.

**Handover.** The face-to-face meeting at which the produce is given to the buyer
and cash is paid to the Farmer-Seller.

**ISO/IEC 25010:2023.** The international standard for system and software
quality models, used in this study to evaluate the system across four
characteristics: functional suitability, usability, reliability, and performance
efficiency.

**Likert Scale, Four-Point.** The rating instrument used in evaluating the
system, interpreted as Highly Acceptable (3.50 to 4.00), Fairly Acceptable (2.50
to 3.49), Acceptable (1.50 to 2.49), and Unacceptable (1.00 to 1.49).

**Listing.** A single item offered for sale by one Farmer-Seller, created under a
crop taxonomy entry. The Farmer-Seller owns its photographs, description, price,
quantity, and availability.

**Maximum Peso Discount.** The largest peso amount a tawad rule may deduct for a
given crop type, set by the Super Admin on the taxonomy entry.

**Order.** A record of a transaction between one Buyer and one Farmer-Seller. A
cart containing listings from several Farmer-Sellers is split at checkout into
one order for each of them.

**Order Status.** The state of an order, one of Placed, Confirmed, Ready,
Completed, or Cancelled. The Farmer-Seller advances the status.

**Review.** A rating and comment left by a Buyer about a Farmer-Seller. A review
unlocks only after an order between the two reaches Completed, and one review is
allowed per completed order.

**Storefront.** The page that gathers one Farmer-Seller's listings under their
name and their farm, and through which buyers browse that seller's produce.

**Super Admin.** The system owner. The Super Admin manages all accounts, sets
floor prices and maximum peso discounts, owns the crop taxonomy structure,
moderates listings, reviews, and users, and reads the full order ledger and all
descriptive dashboards.

**Takedown.** The Super Admin's removal of an already published listing.
Takedown is exercised after publication, in place of approval before it. The
seller is notified on takedown and again if the listing is restored.

**Tawad.** A peso discount rule published by a Farmer-Seller on their own
listing, applied automatically at checkout when its condition is met. Two rule
types exist: a flat peso amount off an order, and a peso amount off once a
minimum quantity is reached.

**Web-based Content Management System.** The browser-based administrative
surface of AniHow, separate from the Android application, used by the Super Admin
and by Content Editors.

---

# Changelog

| # | Item | Note |
|---|---|---|
| 1 | 1.3 drafted from zero | No Project 1 prose consulted or adapted |
| 2 | Five module names reproduced verbatim from spec Section 13 | Treated as the golden thread for every later section |
| 3 | Tawad rationale stated once, in Module A | Per spec Section 7, it appears in 1.3 and nowhere else |
| 4 | 1.7 drafted with 23 terms, alphabetical | Covers every actor, object, and state named in 1.3 |
| 5 | Tawad entry in 1.7 gives the operational definition only | The "rather than live negotiation" rationale is not repeated; see Flag 3 |
| 6 | No tool or framework versions in either section | Spec Section 12: Ch1 prose is version-less |
| 7 | No em dashes or en dashes | Verified by character sweep |
| 8 | "farmer-seller" used throughout; "member-farmer" absent | Spec Section 2 |
| 9 | Communal-plot, crop-cycle, POS, and reservation vocabulary absent | Spec Section 10 |
| 10 | RA 10173 phrased "designed in accordance with" | Spec Section 11 |
| 11 | Module C: crop-care reads are Farmer-Sellers only | v3.4. Buyer half of the old sentence was wrong. |
| 12 | Buyer and Farmer-Seller terms: crop-care access | Buyer does not read articles. Farmer-Seller reads all published articles. |
| 13 | Module A: farm name on profile, floor on listing form, shops directory | Additive. |
| 14 | Module A, Module E, Takedown: restore also notifies the seller | Additive. |

# Chapter 3 note (not yet drafted)

Chapter 3 System Development, and the SRS if either names the auth flow, must
describe email verification as OTP-only: a six-digit code, ten-minute expiry,
no signed-link GET. There is no Chapter 3 draft in this repository yet.

# Flags for adviser or your decision

**Flag 1. Heading wording for 1.3.** The spec names the section by number only. I
have titled it "Proposed Research Project". The outline document is the authority
here and I do not have it in this session. If the outline says "Proposed
Solution", "Project Description", or similar, the heading changes and the body
does not.

**Flag 2. No-show has no state.** Spec Section 4 gives the Farmer-Seller power to
"mark no-show", but Section 6 locks the status list to five values and no-show is
not among them. I have not invented a sixth state. Judgment call: a no-show
resolves as Cancelled carrying a reason code. This needs a one-line confirmation
before Chapter 3 diagrams are drawn, because it changes the state machine.

**Flag 3. Tawad in the glossary.** Section 7 says the tawad definition appears in
1.3 "and nowhere else", but a Definition of Terms section that omits the study's
one coined term will be questioned by the panel. I have split it: the rationale
sentence appears only in 1.3, and 1.7 carries a plain operational definition with
no rationale. Say the word if you want tawad struck from 1.7 entirely.

**Flag 4. Ready state visibility.** I have written Ready as a seller-advanced
state without saying whether the buyer is notified. Notification behaviour is a
Chapter 3 concern, so 1.3 stays silent on it. Noting it so it is not forgotten.

**Flag 5. Still outstanding from the last message.** The spec file in circulation
still reads "Filament 4" in Section 12, and the changelog in the copy I hold ends
at 23 rows against the 24 reported.

# Next

1.1 and 1.2, per the spec's drafting order. Both audit against the 1.3 above, so
resolve Flag 1 first if the outline is to hand.
