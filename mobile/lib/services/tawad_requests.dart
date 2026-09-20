/// JSON bodies and paths the Flutter client sends for tawad rules.
/// Peso amounts only. Exactly two types: flat and min_quantity.
class TawadRequests {
  static const flat = 'flat';
  static const minQuantity = 'min_quantity';

  static String storePath(int listingId) => '/farmer/listings/$listingId/tawad';

  static String destroyPath(int listingId, int tawadRuleId) =>
      '/farmer/listings/$listingId/tawad/$tawadRuleId';

  static Map<String, dynamic> save({
    required String type,
    required String discountAmount,
    String? minQuantity,
  }) {
    return {
      'type': type,
      'discount_amount': discountAmount,
      if (type == TawadRequests.minQuantity && minQuantity != null && minQuantity.isNotEmpty)
        'min_quantity': minQuantity,
    };
  }
}
