import 'package:anihow/models/models.dart';
import 'package:anihow/screens/chat/order_chats_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

const _me = UserAccount(
  id: 4,
  name: 'Nena',
  email: 'nena@example.com',
  roles: ['farmer_seller'],
);

const _kamatis = OrderItemRow(
  listingName: 'Kamatis',
  quantity: '3',
  unit: 'kg',
  listedPrice: '40',
  lineSubtotal: '120',
);

const _talong = OrderItemRow(
  listingName: 'Talong',
  quantity: '1',
  unit: 'kg',
  listedPrice: '20',
  lineSubtotal: '20',
);

OrderRecord _order({
  required int id,
  required String status,
  required String buyer,
  required String placedAt,
  List<OrderItemRow> items = const [_kamatis],
  String? latestBody,
  int? latestUserId,
  String? latestAt,
}) {
  return OrderRecord(
    id: id,
    status: status,
    total: '120',
    items: items,
    counterpartyName: buyer,
    placedAt: placedAt,
    latestMessageBody: latestBody,
    latestMessageUserId: latestUserId,
    latestMessageAt: latestAt,
  );
}

class _InboxApi extends ApiClient {
  _InboxApi(this.orders, this.stalls) : super(onUnauthorized: () {});

  final List<OrderRecord> orders;
  final List<StallChat> stalls;

  @override
  Future<UserAccount> currentUser() async => _me;

  @override
  Future<List<OrderRecord>> farmerOrders({String? status}) async => orders;

  @override
  Future<List<StallChat>> stallChats() async => stalls;
}

Finder _row(String text) {
  return find.descendant(of: find.byType(Card), matching: find.text(text));
}

Future<void> _pump(WidgetTester tester, _InboxApi api) async {
  final auth = AuthController(api: api)
    ..restoring = false
    ..user = _me;
  await tester.pumpWidget(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(
          create: (_) => PreferencesController()..notificationsEnabled = false,
        ),
        ChangeNotifierProvider.value(value: auth),
      ],
      child: MaterialApp(
        theme: AniHowTheme.light(),
        home: const OrderChatsScreen(forSeller: true),
      ),
    ),
  );
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 300));
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  final orders = [
    _order(
      id: 3,
      status: 'completed',
      buyer: 'Old Buyer',
      placedAt: '2026-09-01T08:00:00',
    ),
    _order(
      id: 8,
      status: 'placed',
      buyer: 'Liza',
      placedAt: '2026-10-08T08:00:00',
    ),
    _order(
      id: 12,
      status: 'confirmed',
      buyer: 'Marco',
      placedAt: '2026-10-09T08:00:00',
      items: const [_talong],
      latestBody: 'On the way',
      latestUserId: 4,
      latestAt: '2026-10-10T09:00:00',
    ),
    _order(
      id: 15,
      status: 'ready',
      buyer: 'Aira',
      placedAt: '2026-10-07T08:00:00',
      latestBody: 'I am outside',
      latestUserId: 9,
      latestAt: '2026-10-11T09:00:00',
    ),
  ];

  const stall = StallChat(
    id: 4,
    sellerId: 4,
    shopName: 'Nena Stall',
    buyerName: 'Questions from Bea',
    latestBody: 'Do you have pechay?',
    latestAt: '2026-10-10T12:00:00',
    latestUserId: 9,
  );

  testWidgets('search filters by buyer and by produce', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await _pump(tester, _InboxApi(orders, const [stall]));

    expect(find.text('Old Buyer'), findsNothing);
    expect(find.text('No messages yet'), findsOneWidget);
    expect(find.text('You: On the way'), findsOneWidget);
    expect(find.text('2'), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'Liza');
    await tester.pump();
    expect(_row('Liza'), findsOneWidget);
    expect(_row('Aira'), findsNothing);
    expect(_row('Marco'), findsNothing);

    await tester.enterText(find.byType(TextField), 'Talong');
    await tester.pump();
    expect(_row('Marco'), findsOneWidget);
    expect(_row('Liza'), findsNothing);
    expect(_row('Aira'), findsNothing);
  });

  testWidgets('needs reply is only the other person, and rows follow the latest message', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1600));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await _pump(tester, _InboxApi(orders, const [stall]));

    expect(
      tester.getTopLeft(find.text('Aira')).dy,
      lessThan(tester.getTopLeft(find.text('Questions from Bea')).dy),
    );
    expect(
      tester.getTopLeft(find.text('Questions from Bea')).dy,
      lessThan(tester.getTopLeft(find.text('Marco')).dy),
    );
    expect(
      tester.getTopLeft(find.text('Marco')).dy,
      lessThan(tester.getTopLeft(find.text('Liza')).dy),
    );

    await tester.tap(find.text('Needs reply'));
    await tester.pump();

    expect(find.text('Aira'), findsOneWidget);
    expect(find.text('Questions from Bea'), findsOneWidget);
    expect(find.text('Marco'), findsNothing);
    expect(find.text('Liza'), findsNothing);
    expect(find.text('I am outside'), findsOneWidget);
  });
}
