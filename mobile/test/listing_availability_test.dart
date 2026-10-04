import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/listing_detail_screen.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/availability_chip.dart';
import 'package:anihow/widgets/produce_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app(Widget home) {
  final auth = AuthController()..restoring = false;
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
      ChangeNotifierProvider(create: (_) => CartController(auth)),
    ],
    child: MaterialApp(
      theme: AniHowTheme.light(),
      home: Scaffold(body: home),
    ),
  );
}

void main() {
  testWidgets('a harvested listing shows the harvest date', (tester) async {
    final listing = ListingItem(
      id: 1,
      title: 'Pechay',
      pricePerUnit: '30',
      quantityAvailable: '4',
      harvestedOn: DateTime(2026, 10, 3),
    );

    await tester.pumpWidget(_app(ProduceCard(listing: listing)));
    await tester.pump();

    expect(find.text('Harvested Oct 3'), findsOneWidget);
  });

  testWidgets('an upcoming listing disables add to cart', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final listing = ListingItem(
      id: 2,
      title: 'Kalabasa',
      pricePerUnit: '40',
      quantityAvailable: '3',
      isUpcoming: true,
      availableFrom: DateTime(2026, 10, 20),
    );

    await tester.pumpWidget(
      _app(ListingDetailScreen(listingId: listing.id, preview: listing)),
    );
    await tester.pumpAndSettle();

    expect(find.text('Available from Oct 20'), findsOneWidget);
    final button = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Add to cart'),
    );
    expect(button.onPressed, isNull);
  });

  testWidgets('the farmer list chip names the availability state', (
    tester,
  ) async {
    await tester.pumpWidget(_app(const AvailabilityChip(state: 'upcoming')));
    await tester.pump();

    expect(find.text('Upcoming'), findsOneWidget);
  });
}
