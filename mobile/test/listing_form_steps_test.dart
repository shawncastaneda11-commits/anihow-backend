import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/listing_form_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/dashed_photo_box.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _RecordingApi extends ApiClient {
  _RecordingApi() : super(onUnauthorized: () {});

  Map<String, dynamic>? created;
  Map<String, Object?>? tawad;

  @override
  Future<TawadRule> saveTawad({
    required int listingId,
    required String type,
    required String discountAmount,
    String? minQuantity,
  }) async {
    tawad = {
      'listing': listingId,
      'type': type,
      'amount': discountAmount,
      'min': minQuantity,
    };
    return TawadRule(id: 3, type: type, discountAmount: discountAmount);
  }

  @override
  Future<ListingItem> createListing(
    Map<String, dynamic> body, {
    String? imagePath,
  }) async {
    created = body;
    return const ListingItem(
      id: 1,
      title: 'Tomato',
      pricePerUnit: '40',
      quantityAvailable: '1',
    );
  }
}

Widget _app(Widget home, {ApiClient? api, UserAccount? user}) {
  final auth = AuthController(api: api)..restoring = false;
  auth.user = user;
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(
        create: (_) {
          final preferences = PreferencesController();
          preferences.notificationsEnabled = false;
          return preferences;
        },
      ),
      ChangeNotifierProvider.value(value: auth),
    ],
    child: MaterialApp(theme: AniHowTheme.light(), home: home),
  );
}

UserAccount _seller({required bool certified}) {
  return UserAccount(
    id: 4,
    name: 'Farm',
    email: 'farm@example.test',
    roles: const ['farmer'],
    farmIsOrganicCertified: certified,
  );
}

const _crop = CategoryItem(
  id: 7,
  name: 'Kamatis',
  labelEn: 'Tomato',
  unit: 'kg',
);

const _bundleCrop = CategoryItem(
  id: 8,
  name: 'Pechay',
  labelEn: 'Pechay',
  unit: 'bundle',
);

Finder _stepCheck(int index) {
  return find.descendant(
    of: find.byKey(ValueKey('form-step-$index'), skipOffstage: false),
    matching: find.byIcon(Icons.check),
    skipOffstage: false,
  );
}

void main() {
  Future<void> pumpForm(
    WidgetTester tester, {
    ListingItem? listing,
    UserAccount? user,
    ApiClient? api,
    List<CategoryItem> crops = const [_crop],
  }) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(
      _app(
        ListingFormScreen(listing: listing, cropTypes: Future.value(crops)),
        user: user,
        api: api,
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('step chips turn done as the fields are filled', (tester) async {
    await pumpForm(tester);

    expect(find.text('Photo'), findsWidgets);
    expect(find.text('Details'), findsWidgets);
    expect(find.text('Price'), findsWidgets);
    expect(find.text('Harvest'), findsWidgets);
    expect(find.text('Expected'), findsNothing);
    expect(_stepCheck(0), findsNothing);
    expect(
      find.text('Not filled in yet: photo, details, price, harvest'),
      findsOneWidget,
    );

    await tester.enterText(find.byType(TextField).first, 'Garden tomato');
    await tester.pump();
    expect(_stepCheck(1), findsNothing);

    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tomato').last);
    await tester.pumpAndSettle();
    expect(_stepCheck(1), findsOneWidget);

    await tester.enterText(
      find.byWidgetPredicate(
        (widget) =>
            widget is TextField && widget.decoration?.hintText == '0.00',
      ),
      '40',
    );
    await tester.pump();
    expect(_stepCheck(2), findsOneWidget);

    final harvested = find.byKey(
      const ValueKey('harvest-quantity'),
      skipOffstage: false,
    );
    final rejected = find.byKey(
      const ValueKey('rejected-quantity'),
      skipOffstage: false,
    );
    await tester.ensureVisible(harvested);
    await tester.enterText(harvested, '12');
    await tester.ensureVisible(rejected);
    await tester.enterText(rejected, '2');
    await tester.pump();
    expect(_stepCheck(3), findsOneWidget);
    expect(find.text('Good to sell: 10 kg'), findsOneWidget);
    expect(find.text('Not filled in yet: photo'), findsOneWidget);
  });

  testWidgets('an upcoming date uses the Expected step', (tester) async {
    await pumpForm(tester);

    final list = find.byWidgetPredicate(
      (widget) => widget is ListView && widget.scrollDirection == Axis.vertical,
    );
    await tester.dragUntilVisible(
      find.text('Available from'),
      list,
      const Offset(0, -240),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(OutlinedButton, 'Not set').first);
    await tester.pumpAndSettle();
    // A day next month is always upcoming, whatever day the test runs.
    await tester.tap(find.byTooltip('Next month'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('15').hitTestable().first);
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();

    expect(find.text('Expected', skipOffstage: false), findsWidgets);
    expect(find.text('Harvest', skipOffstage: false), findsNothing);
    final quantity = find.byKey(
      const ValueKey('listing-quantity'),
      skipOffstage: false,
    );
    expect(quantity, findsOneWidget);

    await tester.ensureVisible(quantity);
    await tester.enterText(quantity, '8');
    await tester.pump();
    expect(_stepCheck(3), findsOneWidget);
  });

  testWidgets('editing a listing uses the Stock step and a complete hint', (
    tester,
  ) async {
    await pumpForm(
      tester,
      listing: const ListingItem(
        id: 9,
        title: 'Pechay',
        pricePerUnit: '40',
        quantityAvailable: '50',
        unit: 'kg',
        imageUrl: 'https://example.test/pechay.jpg',
        category: _crop,
      ),
    );

    expect(find.text('Stock'), findsWidgets);
    expect(find.text('Expected'), findsNothing);
    expect(find.text('Harvest'), findsNothing);
    expect(_stepCheck(0), findsOneWidget);
    expect(_stepCheck(3), findsOneWidget);
    expect(find.text('Everything is filled in.'), findsOneWidget);
    final addStock = find.byKey(
      const ValueKey('add-stock'),
      skipOffstage: false,
    );
    await tester.ensureVisible(addStock);
    expect(addStock, findsOneWidget);
    expect(
      find.byKey(const ValueKey('remove-stock'), skipOffstage: false),
      findsOneWidget,
    );
  });

  testWidgets('the crop type is locked when editing, not when creating', (
    tester,
  ) async {
    await pumpForm(tester);
    final newPicker = tester.widget<DropdownButton<int>>(
      find.descendant(
        of: find.byKey(const ValueKey('listing-crop-type')),
        matching: find.byType(DropdownButton<int>),
      ),
    );
    expect(newPicker.onChanged, isNotNull);

    await tester.pumpWidget(const SizedBox());
    await pumpForm(
      tester,
      listing: const ListingItem(
        id: 9,
        title: 'Kamatis',
        pricePerUnit: '40',
        quantityAvailable: '50',
        unit: 'kg',
        category: _crop,
      ),
    );
    final editPicker = tester.widget<DropdownButton<int>>(
      find.descendant(
        of: find.byKey(const ValueKey('listing-crop-type')),
        matching: find.byType(DropdownButton<int>),
      ),
    );
    expect(editPicker.onChanged, isNull);
    expect(
      find.text(
        "The crop type can't be changed. Make a new listing for a different crop.",
      ),
      findsOneWidget,
    );
  });

  testWidgets('editing works when the listing crop left the crop list', (
    tester,
  ) async {
    await pumpForm(
      tester,
      crops: const [_bundleCrop],
      listing: const ListingItem(
        id: 9,
        title: 'Kamatis',
        pricePerUnit: '40',
        quantityAvailable: '50',
        unit: 'kg',
        category: _crop,
      ),
    );

    expect(tester.takeException(), isNull);
    final picker = tester.widget<DropdownButton<int>>(
      find.descendant(
        of: find.byKey(const ValueKey('listing-crop-type')),
        matching: find.byType(DropdownButton<int>),
      ),
    );
    expect(picker.value, _crop.id);
    expect(picker.items!.where((item) => item.value == _crop.id), hasLength(1));
  });

  testWidgets('tapping a step chip scrolls to that card', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 640));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(
      _app(ListingFormScreen(cropTypes: Future.value(const [_crop]))),
    );
    await tester.pumpAndSettle();

    final list = find.byWidgetPredicate(
      (widget) => widget is ListView && widget.scrollDirection == Axis.vertical,
    );
    await tester.drag(list, const Offset(0, -900));
    await tester.pumpAndSettle();
    final hidden = find.byType(DashedPhotoBox, skipOffstage: false);
    expect(tester.getRect(hidden).bottom, lessThan(80));

    await tester.tap(find.byKey(const ValueKey('form-step-0')));
    await tester.pumpAndSettle();

    final photoTop = tester.getTopLeft(find.byType(DashedPhotoBox)).dy;
    expect(photoTop, greaterThan(0));
    expect(photoTop, lessThan(400));
  });

  testWidgets('growing method chips save the same values', (tester) async {
    final api = _RecordingApi();
    await pumpForm(tester, api: api, user: _seller(certified: false));

    expect(find.text('Certified Organic'), findsNothing);
    await tester.enterText(find.byType(TextField).first, 'Tomato');
    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tomato').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Naturally grown'));
    await tester.pump();
    await tester.scrollUntilVisible(
      find.byKey(const ValueKey('harvest-quantity')),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.enterText(
      find.byKey(const ValueKey('harvest-quantity')),
      '10',
    );
    await tester.pump();
    await tester.tap(find.text('Save listing'));
    await tester.pumpAndSettle();

    expect(api.created?['growing_method'], 'naturally_grown');
  });

  testWidgets('a new listing can set its tawad when it is created', (
    tester,
  ) async {
    final api = _RecordingApi();
    await pumpForm(tester, api: api, user: _seller(certified: false));

    await tester.enterText(find.byType(TextField).first, 'Tomato');
    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tomato').last);
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const ValueKey('harvest-quantity')),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.enterText(
      find.byKey(const ValueKey('harvest-quantity')),
      '10',
    );
    final section = find.byKey(const ValueKey('new-discount-section'));
    await tester.scrollUntilVisible(
      section,
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(section);
    await tester.pumpAndSettle();
    final minimumRule = find.text('Peso off at a minimum quantity');
    await tester.ensureVisible(minimumRule);
    await tester.pumpAndSettle();
    await tester.tap(minimumRule);
    await tester.pumpAndSettle();
    final amount = find.byKey(const ValueKey('new-tawad-amount'));
    await tester.ensureVisible(amount);
    await tester.pumpAndSettle();
    await tester.enterText(amount, '5');

    await tester.tap(find.text('Save listing'));
    await tester.pumpAndSettle();
    expect(api.created, isNull);
    expect(find.byKey(const ValueKey('new-tawad-error')), findsOneWidget);

    final minimum = find.byKey(const ValueKey('new-tawad-min-quantity'));
    await tester.ensureVisible(minimum);
    await tester.pumpAndSettle();
    await tester.enterText(minimum, '3');
    await tester.tap(find.text('Save listing'));
    await tester.pumpAndSettle();

    expect(api.created, isNotNull);
    expect(api.tawad, {
      'listing': 1,
      'type': 'min_quantity',
      'amount': '5',
      'min': '3',
    });
  });

  testWidgets('a certified farm can save certified organic', (tester) async {
    final api = _RecordingApi();
    await pumpForm(tester, api: api, user: _seller(certified: true));

    expect(find.text('Certified Organic'), findsOneWidget);
    await tester.enterText(find.byType(TextField).first, 'Tomato');
    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tomato').last);
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Certified Organic'));
    await tester.tap(find.text('Certified Organic'));
    await tester.pump();
    await tester.scrollUntilVisible(
      find.byKey(const ValueKey('harvest-quantity')),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.enterText(
      find.byKey(const ValueKey('harvest-quantity')),
      '10',
    );
    await tester.pump();
    await tester.tap(find.text('Save listing'));
    await tester.pumpAndSettle();

    expect(api.created?['growing_method'], 'certified_organic');
  });

  Future<void> scrollTo(WidgetTester tester, Key key) {
    return tester.scrollUntilVisible(
      find.byKey(key),
      200,
      scrollable: find.byType(Scrollable).first,
    );
  }

  testWidgets(
    'a half bundle minimum shows the rule under the fields, not a pop-up',
    (tester) async {
      final api = _RecordingApi();
      await pumpForm(
        tester,
        api: api,
        user: _seller(certified: false),
        crops: const [_bundleCrop],
      );

      await tester.enterText(find.byType(TextField).first, 'Pechay, sariwa');
      await tester.tap(find.byType(DropdownButtonFormField<int>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Pechay').last);
      await tester.pumpAndSettle();
      await scrollTo(tester, const ValueKey('listing-min-order'));
      await tester.enterText(
        find.byKey(const ValueKey('listing-min-order')),
        '0.5',
      );
      await tester.pump();

      final rule = find.byKey(const ValueKey('listing-order-rule-error'));
      expect(rule, findsOneWidget);
      expect(find.byKey(const ValueKey('listing-order-preview')), findsNothing);
      final ruleText = tester.widget<Text>(rule).data;
      expect(ruleText, contains('1'));

      await scrollTo(tester, const ValueKey('harvest-quantity'));
      await tester.enterText(
        find.byKey(const ValueKey('harvest-quantity')),
        '10',
      );
      await tester.tap(find.text('Save listing'));
      await tester.pumpAndSettle();

      expect(api.created, isNull);
      expect(find.byType(SnackBar), findsNothing);
      expect(
        tester
            .widget<Text>(find.byKey(const ValueKey('listing-form-error')))
            .data,
        ruleText,
      );
    },
  );

  testWidgets('saving without a harvested quantity says so under the field', (
    tester,
  ) async {
    final api = _RecordingApi();
    await pumpForm(tester, api: api, user: _seller(certified: false));

    await tester.enterText(find.byType(TextField).first, 'Tomato');
    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tomato').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save listing'));
    await tester.pumpAndSettle();

    expect(api.created, isNull);
    expect(find.byType(SnackBar), findsNothing);
    expect(find.text('Enter how much you harvested.'), findsWidgets);
    await scrollTo(tester, const ValueKey('harvest-quantity'));
    expect(
      find.byKey(const ValueKey('harvest-quantity-error')),
      findsOneWidget,
    );
  });
}
