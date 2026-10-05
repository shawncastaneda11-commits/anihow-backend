import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/cart_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

CartLine _line({
  required int id,
  required String quantity,
  required String total,
  String title = 'Talong',
}) {
  return CartLine(
    id: id,
    quantity: quantity,
    listedPrice: '40',
    lineSubtotal: total,
    tawadAmount: '0',
    lineTotal: total,
    listing: ListingItem(
      id: id + 10,
      title: title,
      pricePerUnit: '40',
      quantityAvailable: '20',
      unit: 'kg',
      unitLabel: 'kg',
      sellerId: 4,
      sellerName: 'Nena Stall',
      minOrderQuantity: 1,
      orderStep: 1,
      isActive: true,
    ),
  );
}

class _CartApi extends ApiClient {
  _CartApi(this.lines) : super(onUnauthorized: () {});

  List<CartLine> lines;
  int updates = 0;
  String? lastQuantity;
  bool reject = false;

  @override
  Future<List<CartLine>> cartItems() async => lines;

  @override
  Future<CartLine> updateCartItem(int id, {required String quantity}) async {
    updates++;
    lastQuantity = quantity;
    if (reject) {
      throw ApiException('Only 8 kg left.', statusCode: 422);
    }
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
    lines = [
      for (final line in lines) line.id == id ? next : line,
    ];
    return next;
  }
}

Future<void> _pumpCart(WidgetTester tester, _CartApi api) async {
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
      child: MaterialApp(theme: AniHowTheme.light(), home: const CartScreen()),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('each cart line shows a stepper and the line total', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _CartApi([
      _line(id: 1, quantity: '2', total: '80', title: 'Talong'),
      _line(id: 2, quantity: '1', total: '40', title: 'Kamatis'),
    ]);

    await _pumpCart(tester, api);

    expect(find.byKey(const ValueKey('order-qty-plus-1')), findsOneWidget);
    expect(find.byKey(const ValueKey('order-qty-plus-2')), findsOneWidget);
    expect(find.byKey(const ValueKey('cart-line-total-1')), findsOneWidget);
    expect(find.byIcon(Icons.delete_outline), findsNWidgets(2));
  });

  testWidgets('plus updates the line total and one burst saves once', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _CartApi([_line(id: 1, quantity: '2', total: '80')]);
    await _pumpCart(tester, api);

    expect(find.text('₱80.00'), findsWidgets);

    await tester.tap(find.byKey(const ValueKey('order-qty-plus-1')));
    await tester.pump();
    expect(find.text('₱120.00'), findsWidgets);

    await tester.tap(find.byKey(const ValueKey('order-qty-plus-1')));
    await tester.pump(const Duration(milliseconds: 100));
    await tester.tap(find.byKey(const ValueKey('order-qty-plus-1')));
    await tester.pump(const Duration(milliseconds: 700));

    expect(api.updates, 1);
    expect(api.lastQuantity, '5');
    expect(find.text('₱200.00'), findsWidgets);

    await tester.tap(find.byKey(const ValueKey('order-qty-minus-1')));
    await tester.pump();
    expect(find.text('₱160.00'), findsWidgets);
  });

  testWidgets('a rejected quantity comes back and shows the server message', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _CartApi([_line(id: 1, quantity: '2', total: '80')])
      ..reject = true;
    await _pumpCart(tester, api);

    await tester.tap(find.byKey(const ValueKey('order-qty-plus-1')));
    await tester.pump();
    expect(find.text('₱120.00'), findsWidgets);

    await tester.pump(const Duration(milliseconds: 700));
    await tester.pump();

    expect(find.text('Only 8 kg left.'), findsOneWidget);
    expect(find.text('₱80.00'), findsWidgets);
    expect(find.text('₱120.00'), findsNothing);
  });
}
