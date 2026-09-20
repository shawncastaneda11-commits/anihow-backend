# Manuscript re-audit list

Extracted from Section 22 of `AniHow_Project_Instructions_v3.2.md`.

Twelve drafted passages describe the system as it was before farm-scoped price
overrides and walk-in sale recording. Each one goes stale the moment the
matching feature lands.

**Do not fix these while building.** They are prose, not code, and they get
re-audited in one drafting session after the smoke test passes. This file exists
so the debt is visible while the work is happening, not discovered a month later.

---

## Goes stale when Change A lands (farm-scoped price overrides)

| Passage | What is now wrong |
|---|---|
| Project Description, Module A | Describes the floor and maximum discount as Super Admin's alone, with no farm layer |
| Project Description, Module E | Content Editor paragraph says the role cannot set prices, full stop |
| Definition of Terms, "Floor Price" | Defines one floor, set by Super Admin, with no farm override |
| Definition of Terms, "Maximum Peso Discount" | Same problem |
| Definition of Terms, "Content Editor" | Says the role holds no price-setting power |

## Goes stale when Change B lands (walk-in sale recording)

| Passage | What is now wrong |
|---|---|
| Project Description, Module B | Has no walk-in path; describes checkout as the only way an order is created |
| Project Description, Module D | Analytics paragraph does not account for walk-in orders |
| Definition of Terms, "Order" | Defines an order as between one Buyer and one Farmer-Seller; a walk-in order has no Buyer |
| Definition of Terms, "Order Status" | Does not note that walk-in orders enter at Completed |
| Definition of Terms, "Review" | Needs the no-buyer case |
| Definition of Terms | Needs a new entry: **Walk-in Sale** |
| Background and Rationale, boundaries paragraph | Says the system records payment at handover; still true, but now covers two entry paths |

---

## Diagrams blocked by the same two changes

Not prose, but stale for the same reason. Listed here so they are not drawn
twice.

| Diagram | Blocked by |
|---|---|
| Order state diagram | Walk-in orders enter at Completed, the one exception to stock-at-Confirmed |
| Use case and data flow diagrams | Content Editor gains two capabilities, Farmer-Seller gains one |
| Entity relationship diagram | Four new columns |

Theoretical Framework, Conceptual Framework, and the system architecture diagram
are not blocked and can be drawn now.

---

## Not affected

Background and Rationale's statistical and locale paragraphs, the four-objective
pattern, and every source citation stand as drafted.

---

## Trigger

Re-audit runs in a single drafting session once the extended smoke test passes.
Record the new passing count when it does; Section 2 of the spec and Chapter 3
System Development both quote it.

Current: 26 of 26, before either change.
New count: ____
