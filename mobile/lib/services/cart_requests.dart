/// JSON bodies and paths the Flutter client sends for cart and checkout.
/// Live verification must use these maps, not a parallel API script.
class CartRequests {
  static const cartPath = '/buyer/cart';
  static const checkoutPath = '/buyer/checkout';

  static const buyerPickup = 'buyer_pickup';
  static const sellerDelivers = 'seller_delivers';

  static String cartItemPath(int id) => '/buyer/cart/$id';

  static Map<String, dynamic> addItem({
    required int listingId,
    required String quantity,
  }) {
    return {
      'listing_id': listingId,
      'quantity': quantity,
    };
  }

  static Map<String, dynamic> updateItem({required String quantity}) {
    return {
      'quantity': quantity,
    };
  }

  static Map<String, dynamic> checkout({
    required String fulfillmentPreference,
    String? fulfillmentNote,
  }) {
    return {
      'fulfillment_preference': fulfillmentPreference,
      if (fulfillmentNote != null && fulfillmentNote.isNotEmpty) 'fulfillment_note': fulfillmentNote,
    };
  }
}
