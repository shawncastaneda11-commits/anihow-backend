import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/checkout_screen.dart';
import 'package:anihow/screens/buyer/pay_now_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/services/cart_requests.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

CartLine _line({
  required int id,
  required int sellerId,
  required String seller,
  required bool acceptsOnline,
}) {
  return CartLine(
    id: id,
    quantity: '1',
    listedPrice: '30',
    lineSubtotal: '30',
    tawadAmount: '0',
    lineTotal: '30',
    listing: ListingItem(
      id: id + 20,
      title: 'Sitaw',
      pricePerUnit: '30',
      quantityAvailable: '10',
      unit: 'kg',
      sellerId: sellerId,
      sellerName: seller,
      acceptsOnlinePayment: acceptsOnline,
      isActive: true,
    ),
  );
}

class _CheckoutApi extends ApiClient {
  _CheckoutApi(this.lines) : super(onUnauthorized: () {});

  final List<CartLine> lines;
  List<Map<String, dynamic>>? payments;
  List<OrderRecord> placed = const [];

  @override
  Future<List<CartLine>> cartItems() async => lines;

  @override
  Future<List<OrderRecord>> checkout({
    required String fulfillmentPreference,
    String? fulfillmentNote,
    List<Map<String, dynamic>> payments = const [],
  }) async {
    this.payments = payments;
    return placed;
  }

  @override
  Future<List<OrderRecord>> buyerOrders() async => placed;

  @override
  Future<StallChat> openStallChat(int sellerId) {
    throw ApiException('offline', statusCode: 500);
  }
}

Future<void> _pump(WidgetTester tester, _CheckoutApi api) async {
  final auth = AuthController(api: api)
    ..restoring = false
    ..user = const UserAccount(
      id: 1,
      name: 'Buyer',
      email: 'buyer@example.com',
      roles: ['buyer'],
    );
  await tester.pumpWidget(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(
          create: (_) => PreferencesController()..notificationsEnabled = false,
        ),
        ChangeNotifierProvider.value(value: auth),
        ChangeNotifierProvider(create: (_) => CartController(auth)),
      ],
      child: MaterialApp(
        theme: AniHowTheme.light(),
        home: const CheckoutScreen(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('each seller group chooses a payment and cash-only hides online', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _CheckoutApi([
      _line(id: 1, sellerId: 4, seller: 'Nena Stall', acceptsOnline: true),
      _line(id: 2, sellerId: 5, seller: 'Tonyo Stall', acceptsOnline: false),
    ]);
    await _pump(tester, api);

    expect(find.text('Cash on handover'), findsNWidgets(2));
    expect(find.text("Online payment (seller's QR)"), findsOneWidget);
    expect(find.text('This seller accepts cash only'), findsOneWidget);

    await tester.tap(find.text("Online payment (seller's QR)"));
    await tester.pump();
    await tester.scrollUntilVisible(
      find.text('Place 2 orders'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Place 2 orders'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(api.payments, isNotNull);
    expect(api.payments, contains(containsPair('seller_id', 4)));
    expect(
      api.payments!.firstWhere((row) => row['seller_id'] == 4)['method'],
      CartRequests.onlineTransfer,
    );
    expect(
      api.payments!.firstWhere((row) => row['seller_id'] == 5)['method'],
      CartRequests.cashOnHandover,
    );
  });

  test('checkout body always sends payment_flow proof', () {
    final body = CartRequests.checkout(
      fulfillmentPreference: CartRequests.buyerPickup,
    );
    expect(body['payment_flow'], 'proof');
  });

  testWidgets('an online order opens the pay screen', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1000));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _CheckoutApi([
      _line(id: 1, sellerId: 4, seller: 'Nena Stall', acceptsOnline: true),
    ]);
    api.placed = const [
      OrderRecord(
        id: 9,
        status: 'placed',
        total: '30',
        items: [],
        sellerId: 4,
        paymentMethod: 'online_transfer',
        orderNumber: 'AH-1',
      ),
    ];
    await _pump(tester, api);

    await tester.tap(find.text("Online payment (seller's QR)"));
    await tester.pump();
    expect(
      find.text("You'll pay with the seller's QR after placing the order."),
      findsOneWidget,
    );
    await tester.scrollUntilVisible(
      find.text('Place order'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Place order'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.byType(PayNowScreen), findsOneWidget);
  });
}
