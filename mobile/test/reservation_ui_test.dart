import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/listing_detail_screen.dart';
import 'package:anihow/screens/buyer/order_history_screen.dart';
import 'package:anihow/services/api_client.dart';
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
  testWidgets('the reserve sheet shows the locked price and handover note', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final listing = ListingItem(
      id: 7,
      title: 'Kalabasa',
      pricePerUnit: '40',
      quantityAvailable: '8',
      unit: 'kg',
      isUpcoming: true,
      availableFrom: DateTime(2026, 10, 20),
    );

    await tester.pumpWidget(
      _app(
        ReserveHarvestSheet(
          listing: listing,
          onReserve: (_, _) async {},
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(
      find.text("Pay with the seller's QR before the deadline."),
      findsOneWidget,
    );
    expect(find.text('Locked price: ₱40.00 / kg'), findsOneWidget);
    expect(find.textContaining('Estimated total'), findsOneWidget);
  });

  testWidgets('orders and reservations are tabs, and an active one can be cancelled', (
    tester,
  ) async {
    final auth = AuthController(api: _QuietApi())..restoring = false;
    await tester.pumpWidget(
      MultiProvider(
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
          home: const OrderHistoryScreen(),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('Orders'), findsOneWidget);
    expect(find.text('Reservations'), findsOneWidget);
    final bar = tester.widget<TabBar>(find.byType(TabBar));
    expect(bar.labelColor, Colors.white);
    expect(bar.unselectedLabelColor, Colors.white.withValues(alpha: 0.75));
    expect(bar.labelStyle?.fontWeight, FontWeight.w700);
    final indicator = bar.indicator! as UnderlineTabIndicator;
    expect(indicator.borderSide.color, Colors.white);
    expect(indicator.borderSide.width, 3);

    await tester.pumpWidget(
      _app(
        BuyerReservationsList(
          reservations: const [
            ReservationRecord(
              id: 1,
              listingName: 'Kalabasa',
              quantity: 2,
              lineTotal: 80,
              status: 'active',
              unit: 'kg',
            ),
            ReservationRecord(
              id: 2,
              listingName: 'Sitaw',
              quantity: 1,
              lineTotal: 30,
              status: 'converted',
              orderId: 9,
            ),
          ],
          onCancel: (_) async {},
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Kalabasa'), findsOneWidget);
    expect(find.text('Cancel reservation'), findsOneWidget);
    expect(find.text('Sitaw'), findsOneWidget);
  });

  testWidgets('an upcoming listing shows how much is reserved', (tester) async {
    final listing = ListingItem(
      id: 4,
      title: 'Pechay',
      pricePerUnit: '30',
      quantityAvailable: '20',
      unit: 'kg',
      isUpcoming: true,
      reservedQuantity: 12,
      activeReservationsCount: 4,
    );

    await tester.pumpWidget(_app(ReservedHarvestLabel(listing: listing)));
    await tester.pump();

    expect(find.text('12 kg reserved (4)'), findsOneWidget);
  });
}

class _QuietApi extends ApiClient {
  _QuietApi() : super(onUnauthorized: () {});

  @override
  Future<List<OrderRecord>> buyerOrders() async => const [];

  @override
  Future<List<ReservationRecord>> buyerReservations() async => const [];
}
