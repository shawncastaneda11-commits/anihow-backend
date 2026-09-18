# Reservations

Phase 3 buyer ↔ farmer-seller reservations. One reservation holds listings from **one** farmer-seller only.

**Status flow** (no jumps): `pending` → `ready_for_pickup` → `completed`, plus `cancelled`.

**Implemented**

- Buyer: create, list/show own, cancel while `pending` (stock restored)
- Farmer-seller: list/show incoming, mark ready, complete, cancel with reason (stock restored)
- Stock decrement/restore in a DB transaction; overselling is rejected
- Line `unit_price`, listing name/unit snapshots, and reservation `total` stored for later analytics
