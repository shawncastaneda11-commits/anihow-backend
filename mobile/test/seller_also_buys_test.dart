import 'package:anihow/models/models.dart';
import 'package:anihow/screens/profile/start_buying_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/side_chip.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

UserAccount _seller({bool alsoBuys = false}) => UserAccount(
  id: 7,
  name: 'Juan Dela Cruz',
  email: 'juan@example.com',
  roles: alsoBuys ? const ['farmer_seller', 'buyer'] : const ['farmer_seller'],
  alsoBuys: alsoBuys,
);

class _BuyingApi extends ApiClient {
  _BuyingApi() : super(onUnauthorized: () {});

  int calls = 0;

  @override
  Future<UserAccount> startBuying() async {
    calls++;
    return _seller(alsoBuys: true);
  }
}

void main() {
  test('reads also_buys from the account', () {
    final user = UserAccount.fromJson({
      'id': 7,
      'name': 'Juan Dela Cruz',
      'email': 'juan@example.com',
      'roles': ['farmer_seller', 'buyer'],
      'also_buys': true,
    });

    expect(user.alsoBuys, isTrue);
    expect(user.isFarmerSeller, isTrue);
    expect(user.roleLabel, 'farmer seller');
  });

  test('the side follows the switch and is sent to the server', () {
    final api = _BuyingApi();
    final auth = AuthController(api: api)..user = _seller(alsoBuys: true);

    auth.showSide(buying: false);
    expect(auth.actingAsBuyer, isFalse);
    expect(api.side, 'selling');

    auth.showSide(buying: true);
    expect(auth.actingAsBuyer, isTrue);
    expect(api.side, 'buying');
  });

  test('a seller who does not buy never switches side', () {
    final api = _BuyingApi();
    final auth = AuthController(api: api)..user = _seller();

    auth.showSide(buying: true);
    expect(auth.buying, isFalse);
    expect(auth.actingAsBuyer, isFalse);
    expect(api.side, isNull);
  });

  testWidgets('Start buying explains the change, then turns buying on', (tester) async {
    final api = _BuyingApi();
    final auth = AuthController(api: api)
      ..user = _seller()
      ..restoring = false;

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider<AuthController>.value(value: auth),
          ChangeNotifierProvider(create: (_) => PreferencesController()),
        ],
        child: MaterialApp(theme: AniHowTheme.light(), home: const StartBuyingScreen()),
      ),
    );

    expect(find.text('ID check before your first order'), findsOneWidget);
    expect(find.text('Not your own listings'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('turn-on-buying')));
    await tester.pumpAndSettle();

    expect(api.calls, 1);
    expect(auth.user?.alsoBuys, isTrue);
    expect(find.text('Buying is on'), findsOneWidget);
  });

  testWidgets('the header chip flips a seller who also buys between sides', (tester) async {
    final auth = AuthController(api: _BuyingApi())
      ..user = _seller(alsoBuys: true)
      ..restoring = false;

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider<AuthController>.value(value: auth),
          ChangeNotifierProvider(create: (_) => PreferencesController()),
        ],
        child: MaterialApp(
          theme: AniHowTheme.light(),
          home: Scaffold(appBar: AppBar(title: const Text('My listings'), actions: const [SideChip()])),
        ),
      ),
    );

    expect(find.text('Selling'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('side-chip')));
    await tester.pump();

    expect(auth.buying, isTrue);
    expect(find.text('Buying'), findsOneWidget);
  });

  testWidgets('no chip for a seller who only sells', (tester) async {
    final auth = AuthController(api: _BuyingApi())
      ..user = _seller()
      ..restoring = false;

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider<AuthController>.value(value: auth),
          ChangeNotifierProvider(create: (_) => PreferencesController()),
        ],
        child: MaterialApp(
          theme: AniHowTheme.light(),
          home: Scaffold(appBar: AppBar(title: const Text('My listings'), actions: const [SideChip()])),
        ),
      ),
    );

    expect(find.byKey(const ValueKey('side-chip')), findsNothing);
  });
}
