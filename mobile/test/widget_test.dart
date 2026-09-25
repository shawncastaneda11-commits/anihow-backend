import 'package:flutter_test/flutter_test.dart';
import 'package:anihow/config/api_config.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/services/cart_requests.dart';
import 'package:anihow/services/tawad_requests.dart';
import 'package:anihow/support/walk_in_quote.dart';
import 'package:anihow/theme/anihow_space.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/status_pill.dart';

void main() {
  test('Android emulator API host is 10.0.2.2', () {
    expect(ApiConfig.emulatorHost, 'http://10.0.2.2:8000');
    expect(ApiConfig.localHost, 'http://127.0.0.1:8000');
    expect(ApiConfig.hasReleaseApi, isFalse);
    expect(ApiConfig.baseUrl.endsWith('/api'), isTrue);
    expect(ApiConfig.broadcastingAuthUrl.endsWith('/broadcasting/auth'), isTrue);
    expect(ApiConfig.reverbPort, 8080);
    expect(ApiConfig.reverbScheme, 'ws');
    expect(ApiConfig.reverbAppKey, 'anihow-reverb-key');
    expect(ApiConfig.reverbHost, anyOf('10.0.2.2', '127.0.0.1'));
  });

  test('media URLs from Laravel localhost are rewritten to the API host', () {
    expect(
      ApiConfig.mediaUrl('http://127.0.0.1:8000/storage/listings/tomato.jpg'),
      '${ApiConfig.host}/storage/listings/tomato.jpg',
    );
    expect(ApiConfig.mediaUrl(null), isNull);
  });

  test('warm agricultural palette tokens', () {
    expect(AniHowColors.brand.toARGB32(), 0xFF1F5A3E);
    expect(AniHowColors.cream.toARGB32(), 0xFFF8F6F0);
    expect(AniHowColors.card.toARGB32(), 0xFFFFFFFF);
    expect(AniHowColors.text.toARGB32(), 0xFF1E2421);
    expect(AniHowColors.muted.toARGB32(), 0xFF6C757D);
    expect(AniHowColors.badge.toARGB32(), 0xFFE63946);
    expect(AniHowColors.inStockAvatar.toARGB32(), 0xFF7CD96C);
    expect(AniHowColors.inStockBg.toARGB32(), 0xFFD9F5DF);
    expect(AniHowColors.inStock.toARGB32(), 0xFF1C5635);
    expect(AniHowColors.lowStockAvatar.toARGB32(), 0xFFE88A83);
    expect(AniHowColors.lowStockBg.toARGB32(), 0xFFFCE3DE);
    expect(AniHowColors.lowStock.toARGB32(), 0xFFB94A3E);
    expect(AniHowColors.switchOn.toARGB32(), 0xFF2E8B57);
    expect(AniHowColors.switchOff.toARGB32(), 0xFFD6D1C7);
    expect(AniHowColors.navActive.toARGB32(), 0xFFD7EADF);
    expect(AniHowColors.navInactive.toARGB32(), 0xFF6F7872);
    expect(AniHowColors.navBar.toARGB32(), 0xFFFDFCFA);
  });

  test('spacing and type scale are locked', () {
    expect(AniHowSpace.screen, 16);
    expect(AniHowSpace.cardGap, 12);
    expect(AniHowSpace.section, 20);
    expect(AniHowSpace.cardPad, 12);
    expect(AniHowSpace.radius, 12);
    expect(AniHowSpace.header, 20);
    expect(AniHowSpace.headline, 20);
    expect(AniHowSpace.title, 16);
    expect(AniHowSpace.name, 16);
    expect(AniHowSpace.body, 14);
    expect(AniHowSpace.meta, 14);
    expect(AniHowSpace.label, 12);
    expect(AniHowSpace.tab, 15);
    expect(AniHowSpace.nav, 13);
    expect(AniHowSpace.tabHeight, 60);
    expect(AniHowSpace.thumb, 64);
    expect(AniHowMoney.peso(65), '₱65.00');
    expect(AniHowMoney.peso(1250), '₱1,250.00');
    expect(AniHowMoney.rating(4), '4.0');
    expect(AniHowMoney.rating('4.50'), '4.5');
  });

  test('notifications with read_at are not unread', () {
    final unread = AppNotification.fromJson({
      'id': 1,
      'title': 'Low stock',
      'body': 'hotdog is down to 2.00 kg.',
      'read_at': null,
    });
    final read = unread.copyWith(readAt: '2026-09-19T07:11:56.000000Z');

    expect(unread.isUnread, isTrue);
    expect(read.isUnread, isFalse);
  });

  test('order history parses live keys and maps all five statuses', () {
    final order = OrderRecord.fromJson({
      'id': 1,
      'order_number': 'AH-260919-TEST',
      'status': 'confirmed',
      'status_label': 'Confirmed',
      'total': 160,
      'placed_at': '2026-09-19T19:49:59.000000Z',
      'can_be_reviewed': false,
      'cancellation_reason': null,
      'fulfillment_note': 'Saturday 7am',
      'seller': {'id': 2, 'name': 'Smoke Seller A', 'shop_name': 'Aling Nena Produce'},
      'farm': {'barangay': 'Manggahan', 'municipality': 'General Trias'},
      'items': [
        {
          'listing_name': 'Fresh kamatis, hand picked',
          'quantity': 6,
          'listed_price': 30,
          'line_subtotal': 180,
          'unit': 'kg',
        },
      ],
    });

    expect(order.placedAt, isNotNull);
    expect(order.canBeReviewed, isFalse);
    expect(order.isConfirmed, isTrue);
    expect(order.isPlaced, isFalse);
    expect(order.location, 'Manggahan, General Trias');
    expect(order.items.single.listedPrice, '30');
    expect(order.itemSummary, isNot(contains('Reservation')));

    const statuses = {
      'placed': AniHowColors.pending,
      'confirmed': AniHowColors.sage,
      'ready': AniHowColors.ready,
      'completed': AniHowColors.completed,
      'cancelled': AniHowColors.cancelled,
    };
    for (final entry in statuses.entries) {
      final pill = StatusPill.order(entry.key);
      expect(pill.color, entry.value, reason: '${entry.key} must not fall through to the default branch');
      expect(pill.label.toLowerCase(), isNot('pending'));
    }
    expect(StatusPill.order('placed').label, 'Placed');
    expect(StatusPill.order('confirmed').label, 'Confirmed');
  });

  test('farmer order parses allowed_next, buyer, and seller cancel reasons', () {
    final placed = OrderRecord.fromJson({
      'id': 9,
      'order_number': 'AH-260920-PLACED',
      'status': 'placed',
      'status_label': 'Placed',
      'allowed_next': ['confirmed', 'cancelled'],
      'total': 30,
      'amount_received': null,
      'can_be_reviewed': false,
      'fulfillment_preference': 'buyer_pickup',
      'fulfillment_label': 'Buyer picks up',
      'buyer': {'id': 4, 'name': 'Smoke Buyer', 'contact': '0917'},
      'items': [
        {
          'listing_name': 'Fresh kamatis, hand picked',
          'quantity': 1,
          'listed_price': 30,
          'line_subtotal': 30,
          'unit': 'kg',
        },
      ],
    });

    expect(placed.buyerName, 'Smoke Buyer');
    expect(placed.contact, '0917');
    expect(placed.canAdvanceTo('confirmed'), isTrue);
    expect(placed.canAdvanceTo('ready'), isFalse);
    expect(placed.canAdvanceTo('completed'), isFalse);
    expect(placed.canAdvanceTo('cancelled'), isTrue);
    expect(placed.canBeReviewed, isFalse);
    expect(placed.fulfillmentLabel, 'Buyer picks up');

    final ready = placed.copyWith(
      status: 'ready',
      statusLabel: 'Ready',
      allowedNext: const ['completed', 'cancelled'],
    );
    expect(ready.canAdvanceTo('completed'), isTrue);
    expect(ready.canAdvanceTo('confirmed'), isFalse);

    final completed = ready.copyWith(
      status: 'completed',
      statusLabel: 'Completed',
      allowedNext: const [],
      canBeReviewed: true,
      amountReceived: '30',
    );
    expect(completed.canAdvanceTo('cancelled'), isFalse);
    expect(completed.canBeReviewed, isTrue);
    expect(completed.amountReceived, '30');
  });

  test('walk-in order uses walk-in buyer name and no advance actions', () {
    final named = OrderRecord.fromJson({
      'id': 12,
      'order_number': 'AH-260921-WALK',
      'status': 'completed',
      'status_label': 'Completed',
      'source': 'walk_in',
      'is_walk_in': true,
      'walk_in_buyer_name': 'Aling Rosa',
      'allowed_next': [],
      'total': 90,
      'subtotal': 100,
      'tawad_total': 10,
      'amount_received': '90',
      'buyer': null,
      'items': [
        {
          'listing_name': 'Fresh kamatis, hand picked',
          'quantity': 3,
          'listed_price': 30,
          'line_subtotal': 90,
          'unit': 'kg',
        },
      ],
    });

    expect(named.isWalkIn, isTrue);
    expect(named.source, 'walk_in');
    expect(named.buyerName, 'Aling Rosa');
    expect(named.canAdvanceTo('confirmed'), isFalse);
    expect(named.canAdvanceTo('ready'), isFalse);
    expect(named.canAdvanceTo('cancelled'), isFalse);
    expect(named.canAdvanceTo('completed'), isFalse);

    final unnamed = named.copyWith();
    final blank = OrderRecord.fromJson({
      'id': 13,
      'status': 'completed',
      'source': 'walk_in',
      'is_walk_in': true,
      'walk_in_buyer_name': '  ',
      'allowed_next': [],
      'total': 30,
      'buyer': null,
      'items': const [],
    });
    expect(unnamed.buyerName, 'Aling Rosa');
    expect(blank.buyerName, 'Walk-in customer');
  });

  test('cart request shapes match the live cart and checkout endpoints', () {
    expect(CartRequests.cartPath, '/buyer/cart');
    expect(CartRequests.checkoutPath, '/buyer/checkout');
    expect(CartRequests.cartItemPath(4), '/buyer/cart/4');

    final add = CartRequests.addItem(listingId: 1, quantity: '6');
    expect(add.keys, unorderedEquals(['listing_id', 'quantity']));
    expect(add['listing_id'], 1);
    expect(add['quantity'], '6');

    final checkout = CartRequests.checkout(
      fulfillmentPreference: CartRequests.buyerPickup,
      fulfillmentNote: 'Saturday 7am at the barangay hall.',
    );
    expect(checkout.keys, unorderedEquals(['fulfillment_preference', 'fulfillment_note']));
    expect(checkout.containsKey('payment_method'), isFalse);
    expect(checkout.containsKey('courier'), isFalse);
    expect(checkout['fulfillment_preference'], 'buyer_pickup');
  });

  test('cart snapshot splits by seller and keeps listed price beside tawad', () {
    final snapshot = CartSnapshot(
      items: [
        CartLine.fromJson({
          'id': 1,
          'quantity': 6,
          'listed_price': 30,
          'line_subtotal': 180,
          'tawad_amount': 20,
          'line_total': 160,
          'listing': {
            'id': 1,
            'title': 'Fresh kamatis, hand picked',
            'price_per_unit': 30,
            'quantity_available': 94,
            'seller': {'id': 2, 'name': 'Smoke Seller A', 'shop_name': 'Aling Nena Produce'},
          },
        }),
        CartLine.fromJson({
          'id': 2,
          'quantity': 2,
          'listed_price': 28,
          'line_subtotal': 56,
          'tawad_amount': 0,
          'line_total': 56,
          'listing': {
            'id': 2,
            'title': 'Kamatis, bagong ani',
            'price_per_unit': 28,
            'quantity_available': 50,
            'seller': {'id': 3, 'name': 'Smoke Seller B', 'shop_name': 'Mang Tonyo Farm'},
          },
        }),
      ],
    );

    expect(snapshot.upcomingOrderCount, 2);
    expect(snapshot.splitMessage, contains('2 orders'));
    expect(snapshot.splitMessage.toLowerCase(), isNot(contains('after')));
    expect(snapshot.groupsBySeller.map((group) => group.sellerName), [
      'Aling Nena Produce',
      'Mang Tonyo Farm',
    ]);
    expect(snapshot.items.first.listedPrice, '30');
    expect(snapshot.items.first.lineTotal, isNot(snapshot.items.first.listedPrice));
    expect(snapshot.groupsBySeller.first.listedSubtotal, 180);
    expect(snapshot.groupsBySeller.first.tawadTotal, 20);
    expect(snapshot.groupsBySeller.first.total, 160);

    final placed = OrderRecord.fromJson({
      'id': 10,
      'status': 'placed',
      'subtotal': 180,
      'tawad_total': 20,
      'total': 160,
      'payment_method': 'cash_on_handover',
      'seller': {'id': 2, 'shop_name': 'Aling Nena Produce'},
      'items': [
        {
          'listing_name': 'Fresh kamatis, hand picked',
          'quantity': 6,
          'listed_price': 30,
          'line_subtotal': 180,
          'tawad_amount': 20,
          'line_total': 160,
        },
      ],
    });
    expect(placed.listedTotal, '180');
    expect(placed.tawadDisplay, '20');
    expect(placed.total, '160');
    expect(placed.paymentLabel, 'Cash on handover');
    expect(placed.items.single.listedPrice, '30');
    expect(
      placed.chatPeerTitle(viewingAsSeller: false),
      'Aling Nena Produce',
    );
  });

  test('order chat title is the stall for a buyer and the buyer for a seller', () {
    final order = OrderRecord.fromJson({
      'id': 11,
      'order_number': 'AH-260925-CY7JUU',
      'status': 'placed',
      'total': 80,
      'seller': {'id': 4, 'shop_name': 'Kuya Jun Harvest'},
      'buyer': {'id': 9, 'name': 'Carla Santos'},
    });

    expect(order.chatPeerTitle(viewingAsSeller: false), 'Kuya Jun Harvest');
    expect(order.chatPeerTitle(viewingAsSeller: true), 'Carla Santos');
  });

  test('a taken-down listing is not purchasable from the cart', () {
    const line = CartLine(
      id: 1,
      quantity: '2',
      listedPrice: '40',
      lineSubtotal: '80',
      tawadAmount: '0',
      lineTotal: '80',
      listing: ListingItem(
        id: 7,
        title: 'Talong, mahaba',
        pricePerUnit: '40',
        quantityAvailable: '10',
        isActive: true,
        status: 'taken_down',
      ),
    );

    expect(line.isPurchasable, isFalse);
  });

  test('tawad request shapes are peso-only with exactly two types', () {
    expect(TawadRequests.flat, 'flat');
    expect(TawadRequests.minQuantity, 'min_quantity');
    expect(TawadRequests.storePath(1), '/farmer/listings/1/tawad');
    expect(TawadRequests.destroyPath(1, 9), '/farmer/listings/1/tawad/9');

    final flat = TawadRequests.save(type: TawadRequests.flat, discountAmount: '5');
    expect(flat.keys, unorderedEquals(['type', 'discount_amount']));
    expect(flat.containsKey('percent'), isFalse);
    expect(flat.containsKey('percentage'), isFalse);
    expect(flat['type'], 'flat');

    final minimum = TawadRequests.save(
      type: TawadRequests.minQuantity,
      discountAmount: '12',
      minQuantity: '4',
    );
    expect(minimum.keys, unorderedEquals(['type', 'discount_amount', 'min_quantity']));
    expect(minimum['type'], 'min_quantity');
  });

  test('listing parses an active tawad rule for the seller to see', () {
    final listing = ListingItem.fromJson({
      'id': 1,
      'title': 'Fresh kamatis, hand picked',
      'price_per_unit': 30,
      'quantity_available': 94,
      'crop_type': {
        'id': 1,
        'name': 'Tomato',
        'floor_price': 25,
        'max_discount': 20,
        'unit_of_measure': 'kg',
        'unit_label': 'kg',
      },
      'tawad': {
        'id': 3,
        'type': 'min_quantity',
        'type_label': 'Peso discount at minimum quantity',
        'discount_amount': 12,
        'min_quantity': 4,
        'is_active': true,
      },
    });

    expect(listing.tawad, isNotNull);
    expect(listing.tawad!.id, 3);
    expect(listing.tawad!.isMinQuantity, isTrue);
    expect(listing.tawad!.summary, contains('₱12.00'));
    expect(listing.tawad!.summary.toLowerCase(), isNot(contains('percent')));
    expect(listing.category?.maxDiscount, '20');
    expect(listing.category?.floorPrice, '25');
  });

  test('walk-in quote applies tawad that clears the ceiling and the floor', () {
    final quote = WalkInQuote.forListing(_quotedListing(discount: 20, minimum: 5), '6');

    expect(quote, isNotNull);
    expect(quote!.listed, 180);
    expect(quote.tawad, 20);
    expect(quote.total, 160);
  });

  test('walk-in quote skips tawad below the minimum quantity', () {
    final quote = WalkInQuote.forListing(_quotedListing(discount: 20, minimum: 5), '4');

    expect(quote!.listed, 120);
    expect(quote.tawad, 0);
    expect(quote.total, 120);
  });

  test('walk-in quote skips tawad that breaches the ceiling or the floor', () {
    final overCeiling = WalkInQuote.forListing(
      _quotedListing(discount: 10, ceiling: '5'),
      '1',
    );
    final underFloor = WalkInQuote.forListing(
      _quotedListing(price: '40', discount: 10, floor: '35', type: 'flat'),
      '1',
    );

    expect(overCeiling!.tawad, 0);
    expect(overCeiling.total, 30);
    expect(underFloor!.tawad, 0);
    expect(underFloor.total, 40);
  });

  test('a buyer permission set cannot record a walk-in', () {
    final buyer = UserAccount.fromJson({
      'id': 1,
      'name': 'Buyer',
      'email': 'buyer@example.com',
      'roles': ['buyer'],
      'permissions': ['place_orders'],
    });
    final seller = UserAccount.fromJson({
      'id': 2,
      'name': 'Seller',
      'email': 'seller@example.com',
      'roles': ['farmer_seller'],
      'permissions': ['record_walk_in_sales'],
    });

    expect(buyer.canRecordWalkInSales, isFalse);
    expect(seller.canRecordWalkInSales, isTrue);
  });
}

ListingItem _quotedListing({
  String price = '30',
  required num discount,
  num? minimum,
  String floor = '25',
  String ceiling = '20',
  String type = 'min_quantity',
}) {
  return ListingItem.fromJson({
    'id': 1,
    'title': 'Kamatis',
    'price_per_unit': price,
    'quantity_available': 20,
    'crop_type': {
      'id': 1,
      'name': 'Kamatis',
      'floor_price': floor,
      'max_discount': ceiling,
      'effective_floor_price': floor,
      'effective_max_discount': ceiling,
    },
    'tawad': {
      'id': 1,
      'type': type,
      'discount_amount': discount,
      'min_quantity': minimum,
      'is_active': true,
    },
  });
}
