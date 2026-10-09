import 'package:anihow/models/models.dart';
import 'package:anihow/push/firebase_push.dart';
import 'package:anihow/push/push_open.dart';
import 'package:anihow/push/push_permission.dart';
import 'package:anihow/push/push_preferences.dart';
import 'package:anihow/push/push_runtime.dart';
import 'package:anihow/screens/buyer/buyer_order_detail_screen.dart';
import 'package:anihow/screens/buyer/checkout_screen.dart';
import 'package:anihow/screens/buyer/listing_detail_screen.dart';
import 'package:anihow/screens/buyer/pay_now_screen.dart';
import 'package:anihow/screens/chat/order_chats_screen.dart';
import 'package:anihow/screens/chat/stall_chat_screen.dart';
import 'package:anihow/screens/profile/settings_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/state/theme_controller.dart';
import 'package:anihow/support/crop_language.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/notification_bell.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _FakePush implements DevicePush {
  _FakePush({this.token, this.permissionDenied = false});

  String? token;

  @override
  bool permissionDenied;

  int permissionRequests = 0;
  int settingsOpens = 0;

  @override
  String? get rememberedToken => token;

  @override
  Future<bool> requestPermission() async {
    permissionRequests++;
    return !permissionDenied;
  }

  @override
  Future<String?> currentToken() async => token;

  @override
  void remember(String? next) => token = next;

  @override
  Future<PushPayload?> initialMessage() async => null;

  @override
  Future<void> showLocal(PushPayload message) async {}

  @override
  Future<void> openSystemSettings() async {
    settingsOpens++;
  }

  @override
  void listen({
    required void Function(String token) onToken,
    required void Function(PushPayload message) onForeground,
    required void Function(PushPayload message) onOpened,
  }) {}
}

class _Api extends ApiClient {
  _Api() : super(onUnauthorized: () {});

  String? registered;
  String? logoutToken;
  String? deleted;
  Map<String, bool>? patched;
  int? markedRead;
  bool sawBuyerReservations = false;

  @override
  Future<({UserAccount user, String token})> login({
    required String email,
    required String password,
    bool remember = true,
  }) async {
    return (
      user: const UserAccount(
        id: 1,
        name: 'Buyer',
        email: 'buyer@example.com',
        roles: ['buyer'],
      ),
      token: 'session',
    );
  }

  @override
  Future<void> registerDeviceToken(String token) async {
    registered = token;
  }

  @override
  Future<void> logout({String? deviceToken}) async {
    logoutToken = deviceToken;
  }

  @override
  Future<void> deleteDeviceToken(String token) async {
    deleted = token;
  }

  @override
  Future<Map<String, bool>> pushPreferences() async => defaultPushPreferences();

  @override
  Future<Map<String, bool>> updatePushPreferences(Map<String, bool> patch) async {
    patched = patch;
    return {...defaultPushPreferences(), ...patch};
  }

  @override
  Future<int> unreadNotificationCount() async => 4;

  @override
  Future<void> markNotificationRead(int id) async {
    markedRead = id;
  }

  @override
  Future<List<CartLine>> cartItems() async => [_line];

  @override
  Future<List<OrderRecord>> checkout({
    required String fulfillmentPreference,
    String? fulfillmentNote,
    List<Map<String, dynamic>> payments = const [],
  }) async {
    return [
      const OrderRecord(id: 9, status: 'placed', total: '30', items: []),
    ];
  }

  @override
  Future<List<OrderRecord>> buyerOrders() async => const [];

  @override
  Future<List<OrderRecord>> farmerOrders({String? status}) async => const [];

  @override
  Future<OrderRecord> buyerOrder(int id) async {
    return OrderRecord(id: id, status: 'placed', total: '30', items: const []);
  }

  @override
  Future<List<ReservationRecord>> buyerReservations() async {
    sawBuyerReservations = true;
    return const [
      ReservationRecord(
        id: 4,
        listingName: 'Pechay',
        quantity: 1,
        lineTotal: 40,
        status: 'active',
        paymentStatus: 'awaiting_payment',
      ),
    ];
  }

  @override
  Future<ReservationRecord> reserveListing({
    required int listingId,
    required String quantity,
    required String fulfillmentPreference,
    String? fulfillmentNote,
  }) async {
    return const ReservationRecord(
      id: 4,
      listingName: 'Pechay',
      quantity: 1,
      lineTotal: 40,
      status: 'active',
      paymentStatus: 'awaiting_payment',
    );
  }

  @override
  Future<StallChat> openStallChat(int sellerId) async {
    return StallChat(
      id: 3,
      sellerId: sellerId,
      shopName: 'Nena Stall',
      buyerName: 'Ana',
    );
  }

  @override
  Future<List<StallChat>> stallChats() async => const [];
}

final _line = CartLine(
  id: 1,
  quantity: '1',
  listedPrice: '30',
  lineSubtotal: '30',
  tawadAmount: '0',
  lineTotal: '30',
  listing: ListingItem(
    id: 21,
    title: 'Sitaw',
    pricePerUnit: '30',
    quantityAvailable: '10',
    unit: 'kg',
    sellerId: 4,
    sellerName: 'Nena Stall',
    isActive: true,
  ),
);

const _buyer = UserAccount(
  id: 1,
  name: 'Buyer',
  email: 'buyer@example.com',
  roles: ['buyer'],
);

const _seller = UserAccount(
  id: 4,
  name: 'Nena',
  email: 'nena@example.com',
  roles: ['farmer_seller'],
);

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    PushRuntime.reset();
  });

  tearDown(PushRuntime.reset);

  test('firebase init failure does not crash', () async {
    await startPush();
    expect(PushRuntime.ready, isFalse);
    await pushBackgroundHandler(RemoteMessage(data: const {}));
    expect(PushRuntime.ready, isFalse);
  });

  testWidgets('permission is not asked on launch, then once on checkout', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final device = _FakePush();
    PushRuntime.device = device;
    final api = _Api();
    await _pumpCheckout(tester, api);

    expect(find.text('Get notified about orders and payments'), findsNothing);
    expect(device.permissionRequests, 0);

    await tester.scrollUntilVisible(
      find.text('Place order'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();
    expect(find.text('Get notified about orders and payments'), findsOneWidget);

    await tester.tap(find.text('Not now'));
    await tester.pumpAndSettle();
    expect(device.permissionRequests, 0);

    final placed = tester.element(find.byType(CheckoutScreen));
    await offerPushPermission(placed);
    await tester.pumpAndSettle();
    expect(find.text('Get notified about orders and payments'), findsNothing);
    expect(device.permissionRequests, 0);
  });

  testWidgets('allowing the explainer asks the phone for permission', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final device = _FakePush(token: 'phone-token');
    PushRuntime.device = device;
    final api = _Api();
    await _pumpCheckout(tester, api);
    await tester.scrollUntilVisible(
      find.text('Place order'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Allow'));
    await tester.pumpAndSettle();
    expect(device.permissionRequests, 1);
    expect(api.registered, 'phone-token');
  });

  testWidgets('a reservation asks for permission and launch does not', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final device = _FakePush();
    PushRuntime.device = device;
    final api = _Api();
    await _pump(
      tester,
      api,
      ListingDetailScreen(
        listingId: 8,
        preview: ListingItem(
          id: 8,
          title: 'Pechay',
          pricePerUnit: '40',
          quantityAvailable: '20',
          unit: 'kg',
          isUpcoming: true,
          canReserve: true,
          availableFrom: DateTime.now().add(const Duration(days: 3)),
        ),
      ),
    );
    expect(find.text('Get notified about orders and payments'), findsNothing);
    expect(device.permissionRequests, 0);

    await tester.tap(find.byKey(const ValueKey('reserve-harvest')));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Reserve').last);
    await tester.pumpAndSettle();
    expect(find.text('Get notified about orders and payments'), findsOneWidget);
    expect(find.byType(PayNowScreen), findsNothing);
  });

  testWidgets('login registers the token and logout sends it', (tester) async {
    final api = _Api();
    final auth = AuthController(api: api);
    PushRuntime.device = _FakePush(token: 'phone-token');
    final signedIn = await auth.login('buyer@example.com', 'secret');
    expect(signedIn, isTrue);
    expect(api.registered, 'phone-token');
    await auth.logout();
    expect(api.logoutToken, 'phone-token');
  });

  testWidgets('a push tap opens the order and a reservation', (tester) async {
    final api = _Api();
    await _pump(tester, api, const SizedBox(key: ValueKey('nav-host')));
    final context = tester.element(find.byKey(const ValueKey('nav-host')));
    final orderNav = openPushData(context, {
      'notification_id': '9',
      'type': 'order_placed',
      'related_type': 'App\\Models\\Order',
      'related_id': '9',
    });
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(find.byType(BuyerOrderDetailScreen, skipOffstage: false), findsOneWidget);
    Navigator.of(
      tester.element(find.byType(BuyerOrderDetailScreen, skipOffstage: false)),
    ).pop();
    await tester.pumpAndSettle();
    await orderNav;
    expect(api.markedRead, 9);

    final reservationNav = openPushData(context, {
      'notification_id': '4',
      'type': 'payment_confirmed',
      'related_type': 'App\\Models\\Reservation',
      'related_id': '4',
    });
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(api.sawBuyerReservations, isTrue);
    expect(find.byType(PayNowScreen, skipOffstage: false), findsOneWidget);
    Navigator.of(
      tester.element(find.byType(PayNowScreen, skipOffstage: false)),
    ).pop();
    await tester.pumpAndSettle();
    await reservationNav;
  });

  testWidgets('a test push opens the app without a notification screen', (
    tester,
  ) async {
    final api = _Api();
    await _pump(tester, api, const SizedBox(key: ValueKey('nav-host')));
    final context = tester.element(find.byKey(const ValueKey('nav-host')));
    await openPushData(context, {
      'type': 'test',
      'notification_id': '',
      'related_type': '',
      'related_id': '',
    });
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('nav-host')), findsOneWidget);
    expect(find.byType(BuyerOrderDetailScreen), findsNothing);
    expect(api.markedRead, isNull);
  });

  testWidgets('a stall message opens the buyer chat or the seller list', (
    tester,
  ) async {
    final api = _Api();
    await _pump(tester, api, const SizedBox(key: ValueKey('nav-host')));
    var context = tester.element(find.byKey(const ValueKey('nav-host')));
    final buyerNav = openPushData(context, {
      'type': 'stall_message',
      'conversation_id': '3',
      'seller_id': '4',
    });
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(find.byType(StallChatScreen, skipOffstage: false), findsOneWidget);
    Navigator.of(
      tester.element(find.byType(StallChatScreen, skipOffstage: false)),
    ).pop();
    await tester.pumpAndSettle();
    await buyerNav;

    await _pump(
      tester,
      api,
      const SizedBox(key: ValueKey('nav-host')),
      user: _seller,
    );
    context = tester.element(find.byKey(const ValueKey('nav-host')));
    final sellerNav = openPushData(context, {
      'type': 'stall_message',
      'conversation_id': '3',
      'seller_id': '4',
    });
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(find.byType(OrderChatsScreen, skipOffstage: false), findsOneWidget);
    Navigator.of(
      tester.element(find.byType(OrderChatsScreen, skipOffstage: false)),
    ).pop();
    await tester.pumpAndSettle();
    await sellerNav;
  });

  testWidgets('the bell shows the unread count while push is off', (tester) async {
    final prefs = PreferencesController()..notificationsEnabled = false;
    final auth = AuthController(api: _Api())
      ..restoring = false
      ..user = _buyer;
    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider.value(value: prefs),
          ChangeNotifierProvider.value(value: auth),
        ],
        child: MaterialApp(
          theme: AniHowTheme.light(),
          home: Scaffold(
            appBar: AppBar(actions: const [NotificationBellButton()]),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('4'), findsOneWidget);
  });

  testWidgets('settings switches call the api and the master disables them', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1600));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _Api();
    final device = _FakePush(token: 'phone-token', permissionDenied: true);
    PushRuntime.device = device;
    final prefs = PreferencesController()..language = CropLanguage.filipino;
    final auth = AuthController(api: api)
      ..restoring = false
      ..user = _buyer;
    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider.value(value: prefs),
          ChangeNotifierProvider(create: (_) => ThemeController()),
          ChangeNotifierProvider.value(value: auth),
        ],
        child: MaterialApp(
          theme: AniHowTheme.light(),
          home: const SettingsScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      find.text('Ang mga abiso sa account at kaligtasan ay laging dumarating.'),
      findsOneWidget,
    );
    expect(find.text('I-on sa settings ng phone'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('push-orders')));
    await tester.pumpAndSettle();
    expect(api.patched, {'orders': false});

    await tester.tap(find.byKey(const ValueKey('push-master')));
    await tester.pumpAndSettle();
    expect(api.deleted, 'phone-token');
    for (final key in ['orders', 'payments', 'chats', 'farm_updates']) {
      final tile = tester.widget<SwitchListTile>(find.byKey(ValueKey('push-$key')));
      expect(tile.onChanged, isNull);
    }
  });
}

Future<void> _pumpCheckout(WidgetTester tester, _Api api) {
  return _pump(tester, api, const CheckoutScreen());
}

Future<void> _pump(
  WidgetTester tester,
  _Api api,
  Widget home, {
  UserAccount user = _buyer,
}) async {
  final auth = AuthController(api: api)
    ..restoring = false
    ..user = user;
  await tester.pumpWidget(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => PreferencesController()),
        ChangeNotifierProvider.value(value: auth),
        ChangeNotifierProvider(create: (_) => CartController(auth)),
      ],
      child: MaterialApp(theme: AniHowTheme.light(), home: home),
    ),
  );
  await tester.pumpAndSettle();
}
