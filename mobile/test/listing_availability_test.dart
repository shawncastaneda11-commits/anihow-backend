import 'package:anihow/models/models.dart';
import 'package:anihow/services/api_client.dart';
import 'package:dio/dio.dart';
import 'package:anihow/screens/buyer/listing_detail_screen.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
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

  testWidgets('an in-stock listing shows its unit, chips, total and a bottom bar', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final listing = ListingItem(
      id: 5,
      title: 'Kalabasa, pangkare-kare',
      pricePerUnit: '38',
      quantityAvailable: '25',
      unit: 'kg',
      sellerName: 'Kuya Jun Harvest',
      sellerId: 7,
      harvestedOn: DateTime(2026, 10, 22),
      minOrderQuantity: 0.5,
      orderStep: 0.25,
    );

    await tester.pumpWidget(_app(ListingDetailScreen(listingId: listing.id, preview: listing)));
    await tester.pumpAndSettle();

    expect(find.text('In stock'), findsOneWidget);
    expect(find.text('25 kg left'), findsOneWidget);
    expect(find.text('Harvested Oct 22'), findsOneWidget);
    expect(find.text('No reviews yet'), findsOneWidget);
    expect(find.text('= 500 g · min 0.5 kg · steps of 0.25 kg'), findsOneWidget);
    expect(find.text('₱19.00'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('order-qty-plus')));
    await tester.pump();
    expect(find.text('₱28.50'), findsOneWidget);

    final button = tester.getRect(find.text('Add to cart'));
    expect(button.bottom, greaterThan(800 - 90));
  });

  testWidgets('a pre-order listing never says In stock', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final listing = ListingItem(
      id: 6,
      title: 'Kalabas',
      pricePerUnit: '150',
      quantityAvailable: '100',
      unit: 'kg',
      isUpcoming: true,
      availableFrom: DateTime(2026, 11, 6),
    );

    await tester.pumpWidget(_app(ListingDetailScreen(listingId: listing.id, preview: listing)));
    await tester.pumpAndSettle();

    expect(find.text('In stock'), findsNothing);
    expect(find.text('Pre-order'), findsOneWidget);
    expect(find.text('100 kg expected'), findsOneWidget);
    expect(find.text('Reserve now, get it on Nov 6.'), findsOneWidget);
    expect(find.text('Nov 6'), findsOneWidget);
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

    expect(find.text('Ready Oct 20'), findsOneWidget);
    expect(find.byKey(const ValueKey('preorder-note')), findsOneWidget);
    expect(find.text('Reserve'), findsOneWidget);

    await tester.tap(find.text('Reserve'));
    await tester.pumpAndSettle();

    expect(
      find.text("Pay with the seller's QR before the deadline."),
      findsOneWidget,
    );
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
        expect(find.text('Pre-order · ready Oct 20'), findsOneWidget);
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
}
