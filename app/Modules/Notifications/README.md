# Notifications

In-app records plus queued email. No push provider yet (`TODO(push-notifications)`).

**Implemented**

- Fired on new reservation (farmer-seller), reservation status change (buyer), and low stock (farmer-seller)
- `GET /api/notifications`, `GET /api/notifications/unread-count`
- `PATCH /api/notifications/{notification}/read`, `POST /api/notifications/read-all`
- Queued mail (`ReservationCreatedMail`, `ReservationStatusChangedMail`, `ListingLowStockMail`) for verified addresses
