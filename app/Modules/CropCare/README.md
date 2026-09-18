# Crop-care

Descriptive crop-care articles (title, body, optional category). Super admin authors them in Filament. Farmer-sellers read them on mobile.

**Implemented**

- Filament Crop care resource (`super_admin`)
- `GET /api/farmer/crop-care` — list, `category_id` + `search` (title/body)
- `GET /api/farmer/crop-care/{article}` — one active article

No create/edit from mobile. **Not** crop-cycle / growth tracking.
