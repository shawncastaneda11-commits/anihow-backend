# Marketplace

Buyer catalog and reservations for produce listed in General Trias, Cavite.

**Implemented**

- `GET /api/buyer/marketplace` — active listings, optional `category_id` and `search`
- `GET /api/buyer/marketplace/{listing}` — product detail with seller name + location
- `GET /api/categories` — active categories (authenticated)
- `GET /api/buyer/shops` — browse farmer-seller shops
- `GET /api/buyer/shops/{farmerSeller}` — shop profile, active listings, average rating
- `GET/POST /api/buyer/favorites` and `DELETE /api/buyer/favorites/{listing}`
- `POST /api/buyer/reservations` — reserve listings from **one** farmer-seller
- `GET /api/buyer/reservations` and `GET /api/buyer/reservations/{reservation}`
- `PATCH /api/buyer/reservations/{reservation}/cancel` — pending only; stock restored
- `GET /api/buyer/orders` — completed and cancelled reservations
- `GET /api/buyer/orders/{reservation}/receipt` — receipt-style breakdown
