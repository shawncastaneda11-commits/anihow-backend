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
