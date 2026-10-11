import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/farmer_orders_screen.dart';
import 'package:anihow/screens/farmer/farmer_payments_screen.dart';
import 'package:anihow/screens/farmer/walk_in_sale_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/status_pill.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _OrdersApi extends ApiClient {
  _OrdersApi(this.account, this.orders) : super(onUnauthorized: () {});

  final UserAccount account;
  final List<OrderRecord> orders;
  int confirms = 0;

  @override
  Future<UserAccount> currentUser() async => account;

  @override
  Future<List<OrderRecord>> farmerOrders({String? status}) async => orders;

  @override
  Future<OrderRecord> confirmOrder(int id) async {
    confirms++;
    return orders.firstWhere((order) => order.id == id).copyWith(
      status: 'confirmed',
      allowedNext: const ['ready', 'cancelled'],
    );
  }

  @override
  Future<PagedItems<PaymentListItem>> farmerPayments({
    required String status,
    int page = 1,
  }) async {
    return const PagedItems(items: [], complete: true);
  }

  @override
  Future<List<ListingItem>> farmerListings() async => const [];
}

UserAccount _seller({
  bool walkIn = true,
  bool canRecord = true,
}) {
  return UserAccount(
    id: 4,
    name: 'Nena',
    email: 'nena@example.com',
    roles: const ['farmer_seller'],
    permissions: canRecord ? const ['record_walk_in_sales'] : const [],
    farmFeatures: FarmFeatures(walkIn: walkIn),
  );
}

const _kamatis = OrderItemRow(
  listingName: 'Kamatis',
  quantity: '3',
  unit: 'kg',
  listedPrice: '40',
  lineSubtotal: '120',
);

OrderRecord _order({
  required int id,
  required String status,
  String total = '120',
  String buyer = 'Liza',
  String placedAt = '2026-10-10T08:00:00',
  List<OrderItemRow> items = const [_kamatis],
  String? paymentMethod,
  String? paymentStatus,
  String? fulfillmentLabel,
  bool isWalkIn = false,
  String? cancellationReason,
  String? cancellationNote,
}) {
  return OrderRecord(
    id: id,
    status: status,
    total: total,
    items: items,
    counterpartyName: buyer,
    placedAt: placedAt,
    paymentMethod: paymentMethod,
    paymentStatus: paymentStatus,
    fulfillmentLabel: fulfillmentLabel,
    isWalkIn: isWalkIn,
    cancellationReason: cancellationReason,
    cancellationNote: cancellationNote,
  );
}

Future<void> _pump(
  WidgetTester tester,
  _OrdersApi api, {
  bool showAccountMenu = true,
}) async {
  final auth = AuthController(api: api)
    ..restoring = false
    ..user = api.account;
  await tester.pumpWidget(
    ChangeNotifierProvider(
      create: (_) => PreferencesController()..notificationsEnabled = false,
      child: ChangeNotifierProvider.value(
        value: auth,
        child: MaterialApp(
          theme: AniHowTheme.light(),
          home: FarmerOrdersScreen(showAccountMenu: showAccountMenu),
        ),
      ),
    ),
  );
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 300));
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('the header has three equal tabs and no scrolling bar', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await _pump(
      tester,
      _OrdersApi(_seller(), [
        _order(id: 1, status: 'placed'),
        _order(id: 2, status: 'confirmed', placedAt: '2026-10-09T08:00:00'),
        _order(id: 3, status: 'ready', placedAt: '2026-10-08T08:00:00'),
      ]),
    );

    expect(find.text('Orders'), findsOneWidget);
    expect(find.text('Active'), findsOneWidget);
    expect(find.text('3'), findsOneWidget);
    expect(find.text('Completed'), findsOneWidget);
    expect(find.text('Cancelled'), findsOneWidget);
    expect(find.text('Placed'), findsNothing);
    expect(tester.widget<TabBar>(find.byType(TabBar)).isScrollable, isFalse);
    expect(find.widgetWithText(OutlinedButton, 'Record walk-in sale'), findsNothing);
  });

  testWidgets('payments to check opens the payments screen', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1600));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await _pump(
      tester,
      _OrdersApi(_seller(), [
        _order(
          id: 1,
          status: 'placed',
          paymentMethod: 'online_transfer',
          paymentStatus: 'payment_sent',
        ),
        _order(
          id: 2,
          status: 'confirmed',
          paymentMethod: 'online_transfer',
          paymentStatus: 'payment_sent',
        ),
        _order(
          id: 3,
          status: 'ready',
          paymentMethod: 'online_transfer',
          paymentStatus: 'refund_due',
        ),
      ]),
    );

    expect(find.text('2 payments to check'), findsOneWidget);
    expect(find.text('Buyers sent proof of payment'), findsOneWidget);
    expect(find.text('Review'), findsOneWidget);
    expect(find.text('1 refund due'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('open-payments')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.byType(FarmerPaymentsScreen), findsOneWidget);
  });

  testWidgets('a quiet payments card says everything is checked', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await _pump(
      tester,
      _OrdersApi(_seller(), [
        _order(id: 1, status: 'placed', paymentMethod: 'cash_on_handover'),
      ]),
    );

    expect(find.text('Payments'), findsOneWidget);
    expect(find.text('All payments checked'), findsOneWidget);
    expect(find.text('payments to check'), findsNothing);
    expect(find.byIcon(Icons.chevron_right), findsOneWidget);
  });

  testWidgets('active sections show counts and hide empty ones', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 2000));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await _pump(
      tester,
      _OrdersApi(_seller(), [
        _order(id: 1, status: 'placed', buyer: 'Older', placedAt: '2026-10-08T08:00:00'),
        _order(id: 2, status: 'placed', buyer: 'Newer', placedAt: '2026-10-10T08:00:00'),
        _order(id: 3, status: 'ready', buyer: 'Ready one', placedAt: '2026-10-07T08:00:00'),
        _order(id: 4, status: 'ready', buyer: 'Ready two', placedAt: '2026-10-09T08:00:00'),
      ]),
    );

    expect(find.text('NEW · 2'), findsOneWidget);
    expect(find.text('READY FOR PICKUP · 2'), findsOneWidget);
    expect(find.textContaining('CONFIRMED'), findsNothing);
    expect(
      tester.getTopLeft(find.text('Newer')).dy,
      lessThan(tester.getTopLeft(find.text('Older')).dy),
    );
  });

  testWidgets('a card shows the item, payment chip, and total without a status pill', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await _pump(
      tester,
      _OrdersApi(_seller(), [
        _order(
          id: 18,
          status: 'placed',
          paymentMethod: 'online_transfer',
          paymentStatus: 'payment_sent',
          fulfillmentLabel: 'Pickup at the stall',
        ),
      ]),
    );

    expect(find.byType(StatusPill), findsNothing);
    expect(find.text('Liza'), findsOneWidget);
    expect(find.textContaining('#18'), findsOneWidget);
    expect(find.text('Kamatis · 3 kg'), findsOneWidget);
    expect(find.text('Online payment, Payment sent'), findsOneWidget);
    expect(find.text('Pickup at the stall'), findsOneWidget);
    expect(find.text('₱120.00'), findsOneWidget);
  });

  testWidgets('Confirm calls confirm', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final api = _OrdersApi(_seller(), [
      _order(id: 7, status: 'placed', paymentMethod: 'cash_on_handover'),
    ]);
    await _pump(tester, api);

    await tester.tap(find.widgetWithText(FilledButton, 'Confirm'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));

    expect(api.confirms, 1);
  });

  testWidgets('an unpaid ready order waits and cannot be completed', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await _pump(
      tester,
      _OrdersApi(_seller(), [
        _order(
          id: 9,
          status: 'ready',
          paymentMethod: 'online_transfer',
          paymentStatus: 'awaiting_payment',
        ),
      ]),
    );

    expect(find.text('Waiting for payment'), findsOneWidget);
    final complete = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Complete'),
    );
    expect(complete.onPressed, isNull);
    expect(find.byKey(const ValueKey('complete-order')), findsOneWidget);
  });

  testWidgets('more actions opens the cancel reason dialog', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await _pump(
      tester,
      _OrdersApi(_seller(), [
        _order(id: 4, status: 'placed'),
      ]),
    );

    await tester.tap(find.byTooltip('More actions'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    await tester.tap(find.text('Cancel order').last);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));

    expect(
      find.text('A no-show is a cancellation reason, not a separate status.'),
      findsOneWidget,
    );
  });

  testWidgets('the walk-in button shows only when allowed', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final walkIn = _order(
      id: 11,
      status: 'placed',
      isWalkIn: true,
      buyer: 'Walk-in customer',
    );
    await _pump(tester, _OrdersApi(_seller(), [walkIn]));

    expect(find.text('Walk-in sale'), findsWidgets);
    expect(find.widgetWithText(TextButton, 'Chat'), findsNothing);
    expect(find.byType(FloatingActionButton), findsOneWidget);

    await tester.tap(find.byType(FloatingActionButton));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    expect(find.byType(WalkInSaleScreen), findsOneWidget);
  });

  testWidgets('the walk-in button hides when the farm turns it off', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await _pump(
      tester,
      _OrdersApi(_seller(walkIn: false), const []),
      showAccountMenu: false,
    );

    expect(find.byType(FloatingActionButton), findsNothing);
    expect(find.text('Walk-in'), findsNothing);
    expect(find.byType(AppBar), findsNothing);
    expect(tester.widget<TabBar>(find.byType(TabBar)).isScrollable, isFalse);
  });
}
