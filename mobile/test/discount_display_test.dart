import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/cart_screen.dart';
import 'package:anihow/screens/farmer/listing_form_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_space.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/price_breakdown.dart';
import 'package:anihow/widgets/produce_card.dart';
import 'package:anihow/widgets/promo_badge.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

const _crop = CategoryItem(
  id: 7,
  name: 'Kamatis',
  labelEn: 'Tomato',
  unit: 'kg',
  allowedUnits: [AllowedListingUnit(value: 'kg', family: 'weight')],
);

ListingItem _listing({TawadRule? tawad}) {
  return ListingItem(
    id: 4,
    title: 'Kamatis',
    pricePerUnit: '40',
    quantityAvailable: '20',
    unit: 'kg',
    sellerId: 9,
    sellerName: 'Nena Stall',
    category: _crop,
    minOrderQuantity: 1,
    orderStep: 1,
    tawad: tawad,
  );
}

Widget _app(Widget home) {
  final auth = AuthController()..restoring = false;
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(
        create: (_) => PreferencesController()..notificationsEnabled = false,
      ),
      ChangeNotifierProvider.value(value: auth),
      ChangeNotifierProvider(create: (_) => CartController(auth)),
    ],
    child: MaterialApp(theme: AniHowTheme.light(), home: home),
  );
}

void main() {
  final s = AppStrings(false);

  testWidgets('discount section is collapsed when the listing has no rule', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1600));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        ListingFormScreen(
          listing: _listing(),
          cropTypes: Future.value(const [_crop]),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView).last, const Offset(0, -2400));
    await tester.pump();

    expect(find.text(s.discountOptionalTitle), findsOneWidget);
    expect(find.text(s.discountOptionalHelp), findsOneWidget);
    expect(find.byKey(const ValueKey('discount-summary')), findsNothing);
    expect(find.text(s.editDiscount), findsNothing);
    expect(find.text(s.endDiscount), findsNothing);
  });

  testWidgets('discount section shows the rule summary with edit and end', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1600));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        ListingFormScreen(
          listing: _listing(
            tawad: const TawadRule(
              id: 3,
              type: 'min_quantity',
              discountAmount: '5',
              minQuantity: '3',
            ),
          ),
          cropTypes: Future.value(const [_crop]),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView).last, const Offset(0, -2400));
    await tester.pump();

    expect(
      find.text(s.sellerDiscountMin(AniHowMoney.peso('5'), '3', 'kg')),
      findsOneWidget,
    );
    expect(find.text(s.editDiscount), findsOneWidget);
    expect(find.text(s.endDiscount), findsOneWidget);
  });

  testWidgets('promo badge wording follows the tawad type', (tester) async {
    await tester.pumpWidget(
      _app(
        const Column(
          children: [
            PromoBadge(
              rule: TawadRule(
                id: 1,
                type: 'flat',
                discountAmount: '5',
              ),
              unit: 'kg',
            ),
            PromoBadge(
              rule: TawadRule(
                id: 2,
                type: 'min_quantity',
                discountAmount: '5',
                minQuantity: '3',
              ),
              unit: 'kg',
            ),
            PromoBadge(rule: null, unit: 'kg'),
            ProduceCard(
              listing: ListingItem(
                id: 8,
                title: 'Sitaw',
                pricePerUnit: '20',
                quantityAvailable: '4',
                unit: 'piece',
              ),
            ),
          ],
        ),
      ),
    );
    await tester.pump();

    expect(find.text(s.promoFlatOff(AniHowMoney.peso('5'))), findsOneWidget);
    expect(
      find.text(s.promoMinOff(AniHowMoney.peso('5'), '3', 'kg')),
      findsOneWidget,
    );
    expect(find.byKey(const ValueKey('promo-badge')), findsNWidgets(2));
    expect(find.textContaining('Tawad'), findsNothing);
  });

  testWidgets('cart nudge appears below the minimum and leaves at it', (
    tester,
  ) async {
    final api = _CartApi(
      CartLine(
        id: 1,
        quantity: '2',
        listedPrice: '40',
        lineSubtotal: '80',
        tawadAmount: '0',
        lineTotal: '80',
        listing: _listing(
          tawad: const TawadRule(
            id: 3,
            type: 'min_quantity',
            discountAmount: '5',
            minQuantity: '3',
          ),
        ),
      ),
    );
    await _pumpCart(tester, api);

    expect(
      find.text(s.cartDiscountNudge('1', 'kg', AniHowMoney.peso('5'))),
      findsOneWidget,
    );

    await tester.tap(find.byKey(const ValueKey('order-qty-plus-1')));
    await tester.pump();

    expect(find.byKey(const ValueKey('cart-discount-nudge')), findsNothing);
  });

  testWidgets('cart and receipt label the discount without changing amounts', (
    tester,
  ) async {
    final api = _CartApi(
      CartLine(
        id: 1,
        quantity: '2',
        listedPrice: '40',
        lineSubtotal: '80',
        tawadAmount: '5',
        lineTotal: '75',
        listing: _listing(
          tawad: const TawadRule(
            id: 9,
            type: 'flat',
            discountAmount: '5',
          ),
        ),
      ),
    );
    await _pumpCart(tester, api);

    expect(
      find.text(s.discountTawadMinus(AniHowMoney.peso(5))),
      findsWidgets,
    );
    expect(find.text(AniHowMoney.peso('75')), findsWidgets);
    expect(find.text('Tawad −${AniHowMoney.peso(5)}'), findsNothing);

    await tester.pumpWidget(
      _app(
        const Scaffold(
          body: PriceBreakdown(listed: 80, tawad: 5, total: 75),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('${s.discountTawad} ${AniHowMoney.peso(5)}'), findsOneWidget);
    expect(find.text('${s.listed} ${AniHowMoney.peso(80)}'), findsOneWidget);
    expect(find.text('${s.total} ${AniHowMoney.peso(75)}'), findsOneWidget);
  });
}

class _CartApi extends ApiClient {
  _CartApi(this.line) : super(onUnauthorized: () {});

  CartLine line;

  @override
  Future<List<CartLine>> cartItems() async => [line];

  @override
  Future<CartLine> updateCartItem(int id, {required String quantity}) async {
    final amount = (double.parse(quantity) * 40).toStringAsFixed(0);
    line = CartLine(
      id: line.id,
      quantity: quantity,
      listedPrice: line.listedPrice,
      lineSubtotal: amount,
      tawadAmount: line.tawadAmount,
      lineTotal: line.lineTotal,
      listing: line.listing,
    );
    return line;
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
