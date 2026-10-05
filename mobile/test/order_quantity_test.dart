import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/listing_detail_screen.dart';
import 'package:anihow/screens/farmer/listing_form_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/support/order_quantity.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/order_quantity_stepper.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app(Widget home, {AuthController? auth}) {
  final session = auth ?? (AuthController()..restoring = false);
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(
        create: (_) {
          final preferences = PreferencesController();
          preferences.notificationsEnabled = false;
          return preferences;
        },
      ),
      ChangeNotifierProvider.value(value: session),
      ChangeNotifierProvider(create: (_) => CartController(session)),
    ],
    child: MaterialApp(theme: AniHowTheme.light(), home: home),
  );
}

void main() {
  testWidgets('the stepper starts at the minimum and moves by the step', (
    tester,
  ) async {
    final controller = TextEditingController(text: '1');
    addTearDown(controller.dispose);

    await tester.pumpWidget(
      _app(
        Scaffold(
          body: OrderQuantityStepper(
            controller: controller,
            min: 1,
            step: 0.5,
            unit: 'kg',
            max: 10,
          ),
        ),
      ),
    );

    expect(controller.text, '1');
    expect(find.text('Min 1 kg (1,000 g) · steps of 0.5 kg (500 g)'), findsOneWidget);
    expect(find.text('= 1,000 g'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('order-qty-plus')));
    await tester.pump();

    expect(controller.text, '1.5');

    await tester.tap(find.byKey(const ValueKey('order-qty-minus')));
    await tester.pump();

    expect(controller.text, '1');
  });

  testWidgets('a kg listing shows live grams and bracketed min and step', (
    tester,
  ) async {
    final controller = TextEditingController(text: '0.1');
    addTearDown(controller.dispose);

    await tester.pumpWidget(
      _app(
        Scaffold(
          body: OrderQuantityStepper(
            controller: controller,
            min: 0.1,
            step: 0.1,
            unit: 'kg',
            max: 10,
          ),
        ),
      ),
    );

    expect(find.text('= 100 g'), findsOneWidget);
    expect(
      find.text('Min 0.1 kg (100 g) · steps of 0.1 kg (100 g)'),
      findsOneWidget,
    );

    await tester.tap(find.byKey(const ValueKey('order-qty-plus')));
    await tester.pump();

    expect(controller.text, '0.2');
    expect(find.text('= 200 g'), findsOneWidget);
    expect(find.text('= 100 g'), findsNothing);
  });

  testWidgets('the gram equivalent is a green pill and updates as you type', (
    tester,
  ) async {
    final controller = TextEditingController(text: '1');
    addTearDown(controller.dispose);

    await tester.pumpWidget(
      _app(
        Scaffold(
          body: OrderQuantityStepper(
            controller: controller,
            min: 0.01,
            step: 0.01,
            unit: 'kg',
            max: 10,
          ),
        ),
      ),
    );

    expect(find.text('= 1,000 g'), findsOneWidget);
    final equivalent = tester.element(find.text('= 1,000 g'));
    DecoratedBox? pill;
    equivalent.visitAncestorElements((ancestor) {
      final widget = ancestor.widget;
      if (widget is DecoratedBox &&
          widget.decoration is BoxDecoration &&
          (widget.decoration as BoxDecoration).color == AniHowColors.inStockBg) {
        pill = widget;
        return false;
      }
      return true;
    });
    expect(pill, isNotNull);

    await tester.enterText(find.byKey(const ValueKey('order-qty-field')), '2.5');
    await tester.pump();

    expect(find.text('= 2,500 g'), findsOneWidget);
    expect(find.text('= 1,000 g'), findsNothing);
  });

  testWidgets('a piece listing has no gram or millilitre hints', (tester) async {
    final controller = TextEditingController(text: '1');
    addTearDown(controller.dispose);

    await tester.pumpWidget(
      _app(
        Scaffold(
          body: OrderQuantityStepper(
            controller: controller,
            min: 1,
            step: 1,
            unit: 'piece',
            max: 8,
          ),
        ),
      ),
    );

    expect(find.text('Min 1 piece · steps of 1 piece'), findsOneWidget);
    expect(find.byKey(const ValueKey('order-qty-equivalent')), findsNothing);
    expect(find.textContaining(' g)'), findsNothing);
    expect(find.textContaining(' mL)'), findsNothing);
    expect(find.textContaining('= '), findsNothing);
  });

  test('discount nudge uses the listing step', () {
    expect(
      quantityUntilDiscount(
        quantity: 2,
        orderMin: 1,
        step: 1,
        ruleMin: 3,
      ),
      1,
    );
    expect(
      quantityUntilDiscount(
        quantity: 0.5,
        orderMin: 0.25,
        step: 0.25,
        ruleMin: 1,
      ),
      0.5,
    );
    expect(
      quantityUntilDiscount(
        quantity: 3,
        orderMin: 1,
        step: 1,
        ruleMin: 3,
      ),
      isNull,
    );
  });

  test('kg and liter convert to whole grams and millilitres', () {
    expect(orderQuantitySmallUnit(0.01, 'kg'), '10 g');
    expect(orderQuantitySmallUnit(0.1, 'kg'), '100 g');
    expect(orderQuantitySmallUnit(1.5, 'kg'), '1,500 g');
    expect(orderQuantitySmallUnit(0.25, 'liter'), '250 mL');
    expect(orderQuantitySmallUnit(1.25, 'kg'), '1,250 g');
    expect(orderQuantitySmallUnit(1, 'piece'), isNull);
  });

  testWidgets('count units have no decimal entry', (tester) async {
    final controller = TextEditingController(text: '1');
    addTearDown(controller.dispose);

    await tester.pumpWidget(
      _app(
        Scaffold(
          body: OrderQuantityStepper(
            controller: controller,
            min: 1,
            step: 1,
            unit: 'piece',
            max: 8,
          ),
        ),
      ),
    );

    final field = tester.widget<TextField>(
      find.byKey(const ValueKey('order-qty-field')),
    );
    expect(field.keyboardType, TextInputType.number);
    expect(sellsWhole('piece'), isTrue);
    expect(sellsWhole('kg'), isFalse);
  });

  testWidgets('seller chips set the step and the preview', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const crop = CategoryItem(
      id: 7,
      name: 'Kamatis',
      labelEn: 'Tomato',
      unit: 'kg',
      allowedUnits: [AllowedListingUnit(value: 'kg', family: 'weight')],
    );

    await tester.pumpWidget(
      _app(ListingFormScreen(cropTypes: Future.value([crop]))),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tomato').last);
    await tester.pumpAndSettle();

    expect(find.text('Buyers can order 1, 2, 3 kg…'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('order-step-chip-0.5')));
    await tester.pump();

    expect(find.text('Buyers can order 1, 1.5, 2 kg…'), findsOneWidget);
    expect(
      tester
          .widget<TextField>(find.byKey(const ValueKey('listing-order-step')))
          .controller
          ?.text,
      '0.5',
    );
  });

  testWidgets('a refused add shows the server message', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final auth = AuthController(api: _RefusingApi())..restoring = false;
    const listing = ListingItem(
      id: 4,
      title: 'Sitaw',
      pricePerUnit: '30',
      quantityAvailable: '10',
      unit: 'kg',
      minOrderQuantity: 1,
      orderStep: 0.5,
      sellerId: 9,
    );

    await tester.pumpWidget(
      _app(ListingDetailScreen(listingId: 4, preview: listing), auth: auth),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Add to cart'));
    await tester.pumpAndSettle();

    expect(
      find.text('Order at least 1 kg, in steps of 0.5 kg.'),
      findsOneWidget,
    );
  });
}

class _RefusingApi extends ApiClient {
  _RefusingApi() : super(onUnauthorized: () {});

  @override
  Future<CartLine> addCartItem({
    required int listingId,
    required String quantity,
  }) {
    throw ApiException(
      'Order at least 1 kg, in steps of 0.5 kg.',
      statusCode: 422,
    );
  }
}
