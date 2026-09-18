# Listings

Phase 2 farmer-seller produce listings.

**Implemented**

- Farmer API under `/api/farmer/listings` (own records only)
- Single listing image on the local `public` disk (`storage/app/public/listings`)
- Super-admin Filament resource to oversee all listings

Quantity is decremented by Phase 3 reservations and POS sales. Images use `LISTING_DISK` (`public` locally, `s3` on Railway). Crop-cycle / growth tracking will not be added.
