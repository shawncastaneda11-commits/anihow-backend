import '../models/models.dart';

/// Why a tawad being typed would or would not be accepted.
enum TawadPreviewStatus { empty, ok, over, belowFloor, turnedOff, unit }

/// The live "What the buyer pays" box on the tawad screen.
///
/// Same check as the server and WalkInQuote: the tawad is spread over the
/// quantity it applies to (the minimum for a bulk tawad, the listing's
/// minimum order for an every-order one), then compared per unit with the
/// maximum tawad and, after the tawad, with the lowest price. The server
/// still decides on save; this only warns early.
class TawadPreview {
  const TawadPreview({
    required this.status,
    required this.quantity,
    required this.listed,
    required this.total,
    required this.perUnit,
    required this.unitPrice,
  });

  final TawadPreviewStatus status;

  /// The example order: the minimum for a bulk tawad, else the minimum order.
  final double quantity;
  final double listed;
  final double total;
  final double perUnit;
  final double unitPrice;

  bool get isOk => status == TawadPreviewStatus.ok;

  static const _tolerance = 0.000001;

  static TawadPreview of(
    ListingItem listing, {
    required bool bulk,
    required String amountText,
    required String minQuantityText,
  }) {
    final price = double.tryParse(listing.pricePerUnit) ?? 0;
    final amount = double.tryParse(amountText.trim()) ?? 0;
    final minimum = double.tryParse(minQuantityText.trim()) ?? 0;
    final minOrder =
        listing.minOrderQuantity > 0 ? listing.minOrderQuantity : 1.0;
    final basis = bulk ? minimum : minOrder;
    final quantity = basis > 0 ? basis : minOrder;
    final listed = price * quantity;
    final perUnit = basis > 0 ? amount / basis : 0.0;

    TawadPreviewStatus status;
    final guard = listing.priceGuard;
    if (amount <= 0 || (bulk && minimum <= 0)) {
      status = TawadPreviewStatus.empty;
    } else if (guard != null && !guard.unitAllowed) {
      status = TawadPreviewStatus.unit;
    } else if (guard?.maxTawadPerUnit != null &&
        guard!.maxTawadPerUnit! <= 0) {
      status = TawadPreviewStatus.turnedOff;
    } else if (guard?.maxTawadPerUnit != null &&
        perUnit > guard!.maxTawadPerUnit! + _tolerance) {
      status = TawadPreviewStatus.over;
    } else if (guard?.floorPerUnit != null &&
        price - perUnit < guard!.floorPerUnit! - _tolerance) {
      status = TawadPreviewStatus.belowFloor;
    } else {
      status = TawadPreviewStatus.ok;
    }

    final applied = status == TawadPreviewStatus.ok
        ? (amount < listed ? amount : listed)
        : 0.0;

    return TawadPreview(
      status: status,
      quantity: quantity,
      listed: listed,
      total: listed - applied,
      perUnit: perUnit,
      unitPrice: price,
    );
  }
}
