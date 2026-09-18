# POS / Sales

Phase 3 farmer-seller walk-in (POS) sales. These records are **separate** from buyer reservations.

**Implemented**

- `POST /api/farmer/sales` — record a walk-in sale and decrement stock in a transaction
- `GET /api/farmer/sales` and `GET /api/farmer/sales/{sale}` — own POS records only

Descriptive-analytics sales summaries are **not** built in this phase. Totals and line snapshots are stored so analytics can be added later without a schema change.
