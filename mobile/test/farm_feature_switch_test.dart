import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/checkout_screen.dart';
import 'package:anihow/screens/buyer/listing_detail_screen.dart';
import 'package:anihow/screens/farmer/farmer_orders_screen.dart';
import 'package:anihow/screens/farmer/listing_form_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

ListingItem _listing({
  bool upcoming = false,
  bool? canReserve,
  bool tawadPaused = false,
  TawadRule? tawad,
}) {
  return ListingItem(
    id: 9,
    title: 'Jam',
    pricePerUnit: '40',
    quantityAvailable: '10',
    unit: 'kg',
    sellerId: 4,
    sellerName: 'Nena Stall',
    isUpcoming: upcoming,
    canReserve: canReserve,
    tawadPaused: tawadPaused,
    tawad: tawad,
    minOrderQuantity: 1,
    orderStep: 1,
  );
}

UserAccount _seller({FarmFeatures features = const FarmFeatures()}) {
  return UserAccount(
    id: 4,
    name: 'Nena',
    email: 'nena@example.com',
    roles: const ['farmer_seller'],
    permissions: const ['record_walk_in_sales'],
    farmFeatures: features,
  );
}

class _QuietApi extends ApiClient {
  _QuietApi(this.account, {this.checkoutError}) : super(onUnauthorized: () {});

  final UserAccount account;
  final String? checkoutError;

  @override
  Future<UserAccount> currentUser() async => account;

  @override
  Future<List<OrderRecord>> farmerOrders({String? status}) async => const [];

  @override
  Future<List<CartLine>> cartItems() async => [
    CartLine(
      id: 1,
      quantity: '1',
      listedPrice: '40',
      lineSubtotal: '40',
      tawadAmount: '0',
      lineTotal: '40',
      listing: _listing(),
    ),
  ];

  @override
  Future<List<OrderRecord>> checkout({
    required String fulfillmentPreference,
    String? fulfillmentNote,
    List<Map<String, dynamic>> payments = const [],
  }) async {
    throw ApiException(checkoutError ?? 'ok');
  }
}

Future<void> _pump(WidgetTester tester, Widget home, AuthController auth) async {
  await tester.pumpWidget(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(
          create: (_) => PreferencesController()..notificationsEnabled = false,
        ),
        ChangeNotifierProvider.value(value: auth),
        ChangeNotifierProvider(create: (_) => CartController(auth)),
      ],
      child: MaterialApp(theme: AniHowTheme.light(), home: home),
    ),
  );
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 300));
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('Filipino copy names the paused discount and the coming-soon pill', () {
    const strings = AppStrings(true);
    expect(strings.comingSoon, 'Malapit na');
    expect(strings.discountPaused, 'Naka-pause');
  });

  testWidgets('an upcoming listing with reservations off shows Coming soon', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const buyer = UserAccount(
      id: 1,
      name: 'Buyer',
      email: 'buyer@example.com',
      roles: ['buyer'],
    );
    final auth = AuthController(api: _QuietApi(buyer))
      ..restoring = false
      ..user = buyer;

    await _pump(
      tester,
      ListingDetailScreen(
        listingId: 9,
        preview: _listing(upcoming: true, canReserve: false),
      ),
      auth,
    );

    expect(find.byKey(const ValueKey('coming-soon')), findsOneWidget);
    expect(find.text('Coming soon'), findsWidgets);
    expect(find.byKey(const ValueKey('reserve-harvest')), findsNothing);
  });

  testWidgets('walk-in stays hidden when the farm turns it off', (tester) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final seller = _seller(features: const FarmFeatures(walkIn: false));
    final auth = AuthController(api: _QuietApi(seller))
      ..restoring = false
      ..user = seller;

    await _pump(tester, const FarmerOrdersScreen(), auth);

    expect(find.text('Walk-in'), findsNothing);
  });

  testWidgets('the discount section hides when tawad is off and there is no rule', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(412, 2400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final seller = _seller(features: const FarmFeatures(tawad: false));
    final hidden = AuthController(api: _QuietApi(seller))
      ..restoring = false
      ..user = seller;

    await _pump(
      tester,
      ListingFormScreen(
        listing: _listing(),
        cropTypes: Future.value(const []),
      ),
      hidden,
    );

    expect(find.text('Save listing'), findsOneWidget);
    expect(find.byKey(const ValueKey('discount-section')), findsNothing);
  });

  testWidgets('a paused discount stays visible without an edit button', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(412, 2400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final seller = _seller(features: const FarmFeatures(tawad: false));
    final paused = AuthController(api: _QuietApi(seller))
      ..restoring = false
      ..user = seller;

    await _pump(
      tester,
      ListingFormScreen(
        listing: _listing(
          tawadPaused: true,
          tawad: const TawadRule(id: 3, type: 'flat', discountAmount: '5'),
        ),
        cropTypes: Future.value(const []),
      ),
      paused,
    );

    expect(find.text('Save listing'), findsOneWidget);
    expect(find.text('Paused'), findsWidgets);
    expect(find.byKey(const ValueKey('discount-paused')), findsOneWidget);
    expect(find.text('End'), findsOneWidget);
    expect(find.text('Edit'), findsNothing);
  });

  testWidgets('checkout shows the server refusal and leaves the cart', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(412, 2400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const message = 'Farm A Jam is no longer available.';
    const buyer = UserAccount(
      id: 1,
      name: 'Buyer',
      email: 'buyer@example.com',
      roles: ['buyer'],
    );
    final auth = AuthController(api: _QuietApi(buyer, checkoutError: message))
      ..restoring = false
      ..user = buyer;

    await _pump(tester, const CheckoutScreen(), auth);
    expect(find.text('Place order'), findsOneWidget);
    await tester.tap(find.text('Place order'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text(message), findsWidgets);
    expect(find.text('Back'), findsOneWidget);
  });
}
