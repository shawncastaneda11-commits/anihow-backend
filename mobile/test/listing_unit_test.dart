import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/listing_form_screen.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/produce_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app(Widget home) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(
        create: (_) {
          final preferences = PreferencesController();
          preferences.notificationsEnabled = false;
          return preferences;
        },
      ),
      ChangeNotifierProvider(
        create: (_) => AuthController()..restoring = false,
      ),
    ],
    child: MaterialApp(
      theme: AniHowTheme.light(),
      home: Scaffold(body: home),
    ),
  );
}

void main() {
  testWidgets('unit picker shows only the units the crop allows', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const crop = CategoryItem(
      id: 7,
      name: 'Kamatis',
      labelEn: 'Tomato',
      unit: 'kg',
      allowedUnits: [
        AllowedListingUnit(value: 'g', family: 'weight'),
        AllowedListingUnit(value: 'kg', family: 'weight'),
      ],
    );

    await tester.pumpWidget(
      _app(ListingFormScreen(cropTypes: Future.value([crop]))),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tomato').last);
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const ValueKey('listing-unit-7-kg')));
    await tester.pumpAndSettle();

    expect(find.text('Gram (g)'), findsOneWidget);
    expect(find.text('Kilogram (kg)'), findsWidgets);
    expect(find.text('Tray'), findsNothing);
    expect(find.text('Bandeha'), findsNothing);
    expect(find.text('Bottle'), findsNothing);
  });

  testWidgets('price label uses the listing unit', (tester) async {
    const listing = ListingItem(
      id: 1,
      title: 'Kamatis',
      pricePerUnit: '60',
      quantityAvailable: '2',
      unit: 'g',
    );

    await tester.pumpWidget(_app(const ProduceCard(listing: listing)));
    await tester.pump();

    expect(find.text('₱60.00 / g'), findsOneWidget);
  });

  testWidgets('a small unit price keeps the extra decimals', (tester) async {
    const listing = ListingItem(
      id: 2,
      title: 'Kamatis',
      pricePerUnit: '0.055',
      quantityAvailable: '500',
      unit: 'g',
    );

    await tester.pumpWidget(_app(const ProduceCard(listing: listing)));
    await tester.pump();

    expect(find.text('₱0.055 / g'), findsOneWidget);
  });
}
