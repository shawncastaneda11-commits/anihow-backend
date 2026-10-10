import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/order_history_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/status_pill.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _OrdersApi extends ApiClient {
  _OrdersApi(this.orders) : super(onUnauthorized: () {});

  final List<OrderRecord> orders;
  int cancels = 0;

  @override
  Future<List<OrderRecord>> buyerOrders() async => orders;

  @override
  Future<List<ReservationRecord>> buyerReservations() async => const [];

  @override
  Future<List<AppNotification>> notifications() async => const [];

  @override
  Future<OrderRecord> cancelBuyerOrder(int id, {String? note}) async {
    cancels++;
    return orders.firstWhere((order) => order.id == id).copyWith(
      status: 'cancelled',
      cancellationReason: 'buyer_cancelled',
    );
  }
}

OrderRecord _order({
  required int id,
  required String status,
  required String shop,
  required String placedAt,
  List<OrderItemRow> items = const [],
  String? paymentMethod,
  String? paymentStatus,
  String? location,
  bool canBeReviewed = false,
  int? reviewRating,
}) {
  return OrderRecord(
    id: id,
    status: status,
    total: '80',
    items: items,
    shopName: shop,
    placedAt: placedAt,
    paymentMethod: paymentMethod,
    paymentStatus: paymentStatus,
    location: location,
    canBeReviewed: canBeReviewed,
    reviewRating: reviewRating,
  );
}

const _pechay = OrderItemRow(
  listingName: 'Pechay, sariwa',
  quantity: '2',
  unit: 'bundle',
  listedPrice: '40',
  lineSubtotal: '80',
);

const _extra = OrderItemRow(
  listingName: 'Talong',
  quantity: '1',
  unit: 'piece',
  listedPrice: '20',
  lineSubtotal: '20',
);

void main() {
  testWidgets('orders split into Active and Past with one pill each', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 2000));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final orders = [
      _order(
        id: 1,
        status: 'placed',
        shop: 'Older active',
        placedAt: '2026-10-01T08:00:00',
        items: const [_pechay, _extra],
        location: 'Manggahan',
      ),
      _order(
        id: 2,
        status: 'ready',
        shop: 'Newest active',
        placedAt: '2026-10-09T08:00:00',
        items: const [_pechay],
        paymentMethod: 'online_transfer',
        paymentStatus: 'awaiting_payment',
      ),
      _order(
        id: 3,
        status: 'completed',
        shop: 'Older past',
        placedAt: '2026-09-01T08:00:00',
        reviewRating: 5,
      ),
      _order(
        id: 4,
        status: 'cancelled',
        shop: 'Newest past',
        placedAt: '2026-09-20T08:00:00',
      ),
    ];
    final api = _OrdersApi(orders);
    final auth = AuthController(api: api)
      ..restoring = false
      ..user = const UserAccount(
        id: 1,
        name: 'Maria',
        email: 'maria@example.com',
        roles: ['buyer'],
        emailVerifiedAt: '2026-01-01T00:00:00Z',
      );

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => PreferencesController()),
          ChangeNotifierProvider.value(value: auth),
        ],
        child: MaterialApp(
          theme: AniHowTheme.light(),
          home: const OrderHistoryScreen(),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text(s.activeReservations.toUpperCase()), findsOneWidget);
    expect(find.text(s.pastOrders.toUpperCase()), findsOneWidget);

    final newestActive = tester.getTopLeft(find.text('Newest active'));
    final olderActive = tester.getTopLeft(find.text('Older active'));
    final newestPast = tester.getTopLeft(find.text('Newest past'));
    final olderPast = tester.getTopLeft(find.text('Older past'));
    expect(newestActive.dy, lessThan(olderActive.dy));
    expect(olderActive.dy, lessThan(newestPast.dy));
    expect(newestPast.dy, lessThan(olderPast.dy));

    expect(find.byType(StatusPill), findsNWidgets(4));
    expect(find.text('Pechay, sariwa · 2 bundles'), findsOneWidget);
    expect(find.text('Pechay, sariwa +1 more'), findsOneWidget);
    expect(find.textContaining('Online payment, Awaiting payment'), findsOneWidget);
    expect(find.textContaining('Step 3 of 4'), findsOneWidget);
    expect(find.text('2026-10-09 08:00'), findsNothing);
    expect(find.textContaining('You rated 5'), findsOneWidget);

    final cancel = tester.widget<TextButton>(
      find.widgetWithText(TextButton, s.cancelOrder),
    );
    expect(cancel, isNotNull);
  });

  testWidgets('cancel on the card is a text button and still cancels', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final order = _order(
      id: 12,
      status: 'placed',
      shop: 'Aling Nena Produce',
      placedAt: '2026-10-10T08:00:00',
      items: const [_pechay],
    );
    final api = _OrdersApi([order]);
    final auth = AuthController(api: api)..restoring = false;

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => PreferencesController()),
          ChangeNotifierProvider.value(value: auth),
        ],
        child: MaterialApp(
          theme: AniHowTheme.dark(),
          home: Scaffold(body: BuyerOrderCard(order: order)),
        ),
      ),
    );
    await tester.pump();

    final button = tester.widget<TextButton>(
      find.byKey(const Key('cancel-buyer-order')),
    );
    expect(button.style?.foregroundColor?.resolve({}), const Color(0xFFFF8A80));

    await tester.tap(find.byKey(const Key('cancel-buyer-order')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('confirm-cancel-order')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));

    expect(api.cancels, 1);
    expect(find.text('Order cancelled.'), findsOneWidget);
  });
}
