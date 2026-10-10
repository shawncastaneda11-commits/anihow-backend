import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/cart_screen.dart';
import 'package:anihow/screens/buyer/checkout_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/chat_with_stall_button.dart';
import 'package:anihow/widgets/hint_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

ListingItem _listing({
  required int id,
  required String title,
  int sellerId = 4,
  String sellerName = 'Nena Stall',
  TawadRule? tawad,
  String? thumbnailUrl,
  String? imageUrl,
}) {
  return ListingItem(
    id: id,
    title: title,
    pricePerUnit: '40',
    quantityAvailable: '20',
    unit: 'kg',
    unitLabel: 'kg',
    sellerId: sellerId,
    sellerName: sellerName,
    minOrderQuantity: 1,
    orderStep: 1,
    isActive: true,
    tawad: tawad,
    thumbnailUrl: thumbnailUrl,
    imageUrl: imageUrl,
  );
}

CartLine _line({
  required int id,
  required String quantity,
  required String total,
  String title = 'Talong',
  int sellerId = 4,
  String sellerName = 'Nena Stall',
  TawadRule? tawad,
  String tawadAmount = '0',
}) {
  return CartLine(
    id: id,
    quantity: quantity,
    listedPrice: '40',
    lineSubtotal: total,
    tawadAmount: tawadAmount,
    lineTotal: total,
    listing: _listing(
      id: id + 10,
      title: title,
      sellerId: sellerId,
      sellerName: sellerName,
      tawad: tawad,
    ),
  );
}

class _CartApi extends ApiClient {
  _CartApi(this.lines) : super(onUnauthorized: () {});

  List<CartLine> lines;

  @override
  Future<List<CartLine>> cartItems() async => lines;

  @override
  Future<CartLine> updateCartItem(int id, {required String quantity}) async {
    final current = lines.firstWhere((line) => line.id == id);
    final amount = (double.parse(quantity) * 40).toString();
    final next = CartLine(
      id: current.id,
      quantity: quantity,
      listedPrice: current.listedPrice,
      lineSubtotal: amount,
      tawadAmount: '0',
      lineTotal: amount,
      listing: current.listing,
    );
    lines = [for (final line in lines) line.id == id ? next : line];
    return next;
  }
}

Future<void> _pumpCart(
  WidgetTester tester,
  _CartApi api, {
  List<String> roles = const ['buyer'],
}) async {
  final auth = AuthController(api: api)
    ..restoring = false
    ..user = UserAccount(
      id: 1,
      name: 'Buyer',
      email: 'buyer@example.com',
      roles: roles,
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
      child: MaterialApp(theme: AniHowTheme.light(), home: const CartScreen()),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('the cart title counts items and sellers', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pumpCart(
      tester,
      _CartApi([
        _line(id: 1, quantity: '2', total: '80', title: 'Talong'),
        _line(
          id: 2,
          quantity: '1',
          total: '40',
          title: 'Kamatis',
          sellerId: 9,
          sellerName: 'Rosa Farm',
        ),
      ]),
    );

    expect(find.text('Cart'), findsOneWidget);
    expect(find.text('2 items · 2 sellers'), findsOneWidget);
    expect(find.text('This cart will become 2 orders, one per seller.'), findsOneWidget);
    expect(find.byType(AniHowHintCard), findsNothing);
  });

  testWidgets('buyers get an icon-only stall chat button', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pumpCart(tester, _CartApi([_line(id: 1, quantity: '2', total: '80')]));

    expect(find.byType(ChatWithStallButton), findsOneWidget);
    expect(find.byTooltip('Chat with stall'), findsOneWidget);
    expect(find.text('Chat with stall'), findsNothing);
    expect(tester.getSize(find.byTooltip('Chat with stall')), const Size(40, 40));
  });

  testWidgets('a non-buyer does not get the stall chat control', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pumpCart(
      tester,
      _CartApi([_line(id: 1, quantity: '2', total: '80')]),
      roles: const ['farmer_seller'],
    );

    expect(find.byType(ChatWithStallButton), findsNothing);
    expect(find.byTooltip('Chat with stall'), findsNothing);
    expect(find.text('Your cart is empty.'), findsOneWidget);
  });

  testWidgets('a listing without a photo shows the leaf thumbnail', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pumpCart(tester, _CartApi([_line(id: 1, quantity: '2', total: '80')]));

    expect(find.byKey(const ValueKey('cart-thumb-fallback-1')), findsOneWidget);
    expect(find.byIcon(Icons.eco_outlined), findsOneWidget);
  });

  testWidgets('the stepper changes quantity and the value opens the dialog', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pumpCart(tester, _CartApi([_line(id: 1, quantity: '2', total: '80')]));

    expect(
      find.text('Min 1 kg · steps of 1 kg · 1 kg = 1,000 g'),
      findsOneWidget,
    );
    expect(find.textContaining('(1,000 g)'), findsNothing);
    expect(find.text('= 1,000 g'), findsNothing);

    await tester.tap(find.byKey(const ValueKey('order-qty-plus-1')));
    await tester.pump();
    expect(find.text('₱120.00'), findsWidgets);

    await tester.tap(find.byKey(const ValueKey('order-qty-field-1')));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('cart-quantity-update')), findsOneWidget);
  });

  testWidgets('tawad shows a nudge until the discount applies', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pumpCart(
      tester,
      _CartApi([
        _line(
          id: 1,
          quantity: '2',
          total: '80',
          tawad: const TawadRule(
            id: 3,
            type: 'min_quantity',
            discountAmount: '5',
            minQuantity: '3',
          ),
        ),
      ]),
    );

    expect(find.byKey(const ValueKey('cart-discount-nudge')), findsOneWidget);
    expect(find.textContaining('Tawad applied:'), findsNothing);

    await tester.tap(find.byKey(const ValueKey('order-qty-plus-1')));
    await tester.pump();

    expect(find.byKey(const ValueKey('cart-discount-nudge')), findsNothing);
    expect(find.text('Tawad applied: ₱5.00 off'), findsOneWidget);
  });

  testWidgets('the checkout bar matches the summary and opens checkout', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pumpCart(
      tester,
      _CartApi([
        _line(id: 1, quantity: '2', total: '80'),
        _line(id: 2, quantity: '1', total: '40', title: 'Kamatis'),
      ]),
    );

    final summary = tester.widget<Text>(
      find.byKey(const ValueKey('cart-summary-total')),
    );
    final bar = tester.widget<Text>(
      find.byKey(const ValueKey('cart-bottom-total')),
    );
    expect(summary.data, '₱120.00');
    expect(bar.data, summary.data);
    expect(find.text('Total · 1 order'), findsOneWidget);
    expect(find.text('Each seller is paid separately at checkout.'), findsOneWidget);
    expect(find.byType(AniHowHintCard), findsNothing);

    await tester.tap(find.widgetWithText(FilledButton, 'Checkout'));
    await tester.pumpAndSettle();
    expect(find.byType(CheckoutScreen), findsOneWidget);
  });
}
