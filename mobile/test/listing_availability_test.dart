import 'package:anihow/models/models.dart';
import 'package:anihow/services/api_client.dart';
import 'package:dio/dio.dart';
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
    expect(find.text('Reserve'), findsOneWidget);

    await tester.tap(find.text('Reserve'));
    await tester.pumpAndSettle();

    expect(find.text('Pay cash on handover after harvest'), findsOneWidget);
    expect(find.text('I pick up'), findsOneWidget);
  });

  test('a cleared date is an empty multipart field', () {
    final fields = listingMultipartFields({
      'title': 'Pechay',
      'description': null,
      'available_from': null,
      'available_until': null,
      'harvested_on': null,
    });

    expect(fields['available_from'], '');
    expect(fields['available_until'], '');
    expect(fields['harvested_on'], '');
    expect(fields.containsKey('description'), isFalse);
    expect(fields['title'], 'Pechay');

    final form = FormData.fromMap({
      ...fields,
      'image': MultipartFile.fromString('photo', filename: 'cover.jpg'),
    });
    String? field(String key) {
      for (final entry in form.fields) {
        if (entry.key == key) {
          return entry.value;
        }
      }
      return null;
    }

    expect(field('available_from'), '');
    expect(field('available_until'), '');
    expect(field('harvested_on'), '');
    expect(field('description'), isNull);
  });

  testWidgets(
    'an upcoming card shows the reserve pill with and without the seller',
    (tester) async {
      final listing = ListingItem(
        id: 8,
        title: 'Kalabasa',
        pricePerUnit: '40',
        quantityAvailable: '3',
        isUpcoming: true,
        availableFrom: DateTime(2026, 10, 20),
      );

      for (final showSeller in [false, true]) {
        await tester.pumpWidget(
          _app(ProduceCard(listing: listing, showSeller: showSeller)),
        );
        await tester.pump();

        expect(
          find.byKey(const ValueKey('availability-pill-8')),
          findsOneWidget,
        );
        expect(find.text('Reserve · from Oct 20'), findsOneWidget);
        expect(find.byKey(const ValueKey('upcoming-badge')), findsNothing);
      }
    },
  );

  testWidgets('a normal listing shows Available now', (tester) async {
    final listing = ListingItem(
      id: 9,
      title: 'Pechay',
      pricePerUnit: '30',
      quantityAvailable: '4',
    );

    await tester.pumpWidget(_app(ProduceCard(listing: listing)));
    await tester.pump();

    expect(find.byKey(const ValueKey('availability-pill-9')), findsOneWidget);
    expect(find.text('Available now'), findsOneWidget);
  });

  testWidgets('a shop card with the seller hidden still shows the pill', (
    tester,
  ) async {
    final listing = ListingItem(
      id: 10,
      title: 'Sitaw',
      pricePerUnit: '25',
      quantityAvailable: '6',
    );

    await tester.pumpWidget(
      _app(ProduceCard(listing: listing, showSeller: false)),
    );
    await tester.pump();

    expect(find.byKey(const ValueKey('availability-pill-10')), findsOneWidget);
    expect(find.text('Available now'), findsOneWidget);
  });

  testWidgets('the farmer list chip names the availability state', (
    tester,
  ) async {
    await tester.pumpWidget(_app(const AvailabilityChip(state: 'upcoming')));
    await tester.pump();

    expect(find.text('Upcoming'), findsOneWidget);
  });
}
