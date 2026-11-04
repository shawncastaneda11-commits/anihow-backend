import '../models/models.dart';

/// The three lines shown before a walk-in is recorded.
///
/// Same decision as OrderLinePricer: a tawad that clears the effective
/// ceiling and leaves the unit price at or above the effective floor is
/// applied. One that does not is left off, and the line stays at the listed
/// price. The listed price is never replaced.
class WalkInQuote {
  const WalkInQuote({
    required this.listed,
    required this.tawad,
    required this.total,
  });

  final double listed;
  final double tawad;
  final double total;

  static WalkInQuote? forListing(ListingItem listing, String quantityText) {
    final quantity = double.tryParse(quantityText.trim());
    if (quantity == null || quantity <= 0) {
      return null;
    }

    final unit = double.tryParse(listing.pricePerUnit) ?? 0;
    final listed = unit * quantity;
    final tawad = _tawad(listing, quantity, listed);

    return WalkInQuote(
      listed: listed,
      tawad: tawad,
      total: listed - tawad,
    );
  }

  static double _tawad(ListingItem listing, double quantity, double listed) {
    final rule = listing.tawad;
    if (rule == null || !rule.isActive || !_applies(rule, quantity)) {
      return 0;
    }

    final candidate = double.tryParse(rule.discountAmount) ?? 0;
    if (candidate <= 0) {
      return 0;
    }

    final guard = listing.priceGuard;
    if (guard != null) {
      return _withinListingGuard(listing, rule, guard, candidate)
          ? candidate
          : 0;
    }

    // An older server sends no per-listing guard: the crop's values, as before.
    final ceiling = double.tryParse(listing.category?.sellerMaxDiscount ?? '');
    final floor = double.tryParse(listing.category?.sellerFloorPrice ?? '');
    final discountedUnit = (listed - candidate) / quantity;

    if (ceiling != null && _centavos(candidate) > _centavos(ceiling)) {
      return 0;
    }
    if (floor != null && _centavos(discountedUnit) < _centavos(floor)) {
      return 0;
    }

    return candidate;
  }

  /// The server's check, in the listing's own unit: the tawad spread over
  /// the quantity it applies to (its minimum quantity, or the listing's
  /// minimum order for a flat tawad), then compared per unit with the
  /// maximum tawad and, after the tawad, with the floor.
  static bool _withinListingGuard(
    ListingItem listing,
    TawadRule rule,
    ListingPriceGuard guard,
    double candidate,
  ) {
    if (!guard.unitAllowed) {
      return false;
    }
    final basis = rule.isMinQuantity
        ? double.tryParse(rule.minQuantity ?? '') ?? 0
        : (listing.minOrderQuantity > 0 ? listing.minOrderQuantity : 1);
    if (basis <= 0) {
      return false;
    }
    // Exact amounts, not centavos: per-gram figures are fractions of a
    // centavo, and the server compares in the crop's own unit.
    const tolerance = 0.000001;
    final perUnit = candidate / basis;
    final max = guard.maxTawadPerUnit;
    if (max != null && perUnit > max + tolerance) {
      return false;
    }
    final floor = guard.floorPerUnit;
    final price = double.tryParse(listing.pricePerUnit) ?? 0;
    if (floor != null && price - perUnit < floor - tolerance) {
      return false;
    }
    return true;
  }

  static bool _applies(TawadRule rule, double quantity) {
    if (rule.isFlat) {
      return quantity > 0;
    }
    if (!rule.isMinQuantity) {
      return false;
    }
    final minimum = double.tryParse(rule.minQuantity ?? '');
    return minimum != null && minimum > 0 && quantity >= minimum;
  }

  static int _centavos(double peso) => (peso * 100).round();
}
