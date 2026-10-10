import 'dart:async';

import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/buyer_shell.dart';
import 'package:anihow/screens/buyer/marketplace_screen.dart';
import 'package:anihow/screens/buyer/order_history_screen.dart';
import 'package:anihow/screens/farmer/farmer_shell.dart';
import 'package:anihow/screens/profile/profile_screen.dart';
import 'package:anihow/screens/profile/settings_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/state/theme_controller.dart';
import 'package:anihow/support/crop_language.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/account_menu_button.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _MenuApi extends ApiClient {
  _MenuApi(this.account) : super(onUnauthorized: () {});

  final UserAccount account;
  int logouts = 0;

  @override
  Future<void> logout({String? deviceToken}) async {
    logouts++;
  }

  @override
  Future<UserAccount> currentUser() async => account;

  @override
  Future<MarketplaceFeed> marketplace({
    String? search,
    int? cropTypeId,
    String? sort,
    String? category,
    double? nearLat,
    double? nearLng,
    String? growingMethod,
    int? page,
    String? mixDay,
    int? farmId,
  }) async {
    return const MarketplaceFeed(items: []);
  }

  @override
  Future<List<CategoryItem>> cropTypes() async => const [];

  @override
  Future<PagedBuyerAnnouncements> buyerAnnouncements({
    bool following = false,
    int? farmId,
    int page = 1,
  }) async {
    return const PagedBuyerAnnouncements(
      items: [],
      currentPage: 1,
      lastPage: 1,
    );
  }

  @override
  Future<List<ShopProfile>> buyerShops({
    String? sort,
    double? nearLat,
    double? nearLng,
  }) async => const [];

  @override
  Future<List<OrderRecord>> buyerOrders() async => const [];

  @override
  Future<List<ReservationRecord>> buyerReservations() async => const [];

  @override
  Future<List<ShopFavoriteRecord>> shopFavorites() async => const [];

  @override
  Future<List<FarmFavoriteRecord>> farmFavorites() async => const [];

  @override
  Future<List<CartLine>> cartItems() async => const [];

  @override
  Future<int> unreadNotificationCount() async => 0;

  @override
  Future<List<AppNotification>> notifications() async => const [];

  @override
  Future<Map<String, bool>> pushPreferences() async => const {};

  @override
  Future<ShopProfile> farmerShop() async {
    return const ShopProfile(
      id: 4,
      shopName: 'Cruz Morning Greens',
      name: 'Liza Cruz',
    );
  }

  @override
  Future<PagedItems<ListingItem>> farmerListingsPaged() async {
    return const PagedItems(items: [], complete: true);
  }
}

UserAccount _buyer() {
  return const UserAccount(
    id: 1,
    name: 'Maria Santos',
    email: 'maria@example.com',
    roles: ['buyer'],
  );
}

UserAccount _seller() {
  return const UserAccount(
    id: 4,
    name: 'Liza Cruz',
    email: 'liza@example.com',
    roles: ['farmer_seller'],
    shopName: 'Cruz Morning Greens',
    farmName: 'PYAP Manggahan',
  );
}

Widget _app({
  required AuthController auth,
  required Widget home,
  ThemeData? theme,
  PreferencesController? preferences,
}) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider.value(
        value: preferences ?? PreferencesController()
          ..notificationsEnabled = false,
      ),
      ChangeNotifierProvider(create: (_) => ThemeController()),
      ChangeNotifierProvider.value(value: auth),
      ChangeNotifierProvider(create: (_) => CartController(auth)),
    ],
    child: MaterialApp(theme: theme ?? AniHowTheme.light(), home: home),
  );
}

Future<void> _systemBack(WidgetTester tester) async {
  await tester.binding.defaultBinaryMessenger.handlePlatformMessage(
    'flutter/navigation',
    const JSONMethodCodec().encodeMethodCall(const MethodCall('popRoute')),
    (_) {},
  );
  await tester.pumpAndSettle();
}

Future<void> _openMenu(WidgetTester tester, AppStrings s) async {
  await tester.tap(find.byTooltip(s.accountMenu).hitTestable());
  await tester.pumpAndSettle();
}

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('buyer shell has four tabs and the account button on each', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final api = _MenuApi(_buyer());
    final auth = AuthController(api: api)
      ..user = _buyer()
      ..restoring = false;
    await tester.pumpWidget(_app(auth: auth, home: const BuyerShell()));
    await tester.pump();

    final s = AppStrings(false);
    expect(find.byType(NavigationDestination), findsNWidgets(4));
    expect(find.text(s.profile), findsNothing);
    Finder tab(String label) => find.descendant(
      of: find.byType(NavigationBar),
      matching: find.text(label),
    );
    expect(tab(s.shops), findsOneWidget);
    expect(tab(s.market), findsOneWidget);
    expect(tab(s.orders), findsOneWidget);
    expect(tab(s.favorites), findsOneWidget);

    expect(find.byType(AccountMenuButton).hitTestable(), findsOneWidget);

    await tester.tap(find.byIcon(Icons.agriculture_outlined));
    await tester.pump();
    expect(find.byType(AccountMenuButton).hitTestable(), findsOneWidget);

    await tester.tap(find.byIcon(Icons.receipt_long_outlined));
    await tester.pump();
    expect(find.byType(AccountMenuButton).hitTestable(), findsOneWidget);

    await tester.tap(find.byIcon(Icons.favorite_outline));
    await tester.pump();
    expect(find.byType(AccountMenuButton).hitTestable(), findsOneWidget);
  });

  testWidgets('a pushed market or orders screen has no account button', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final auth = AuthController(api: _MenuApi(_buyer()))
      ..user = _buyer()
      ..restoring = false;

    await tester.pumpWidget(
      _app(
        auth: auth,
        home: const Scaffold(body: MarketplaceScreen()),
      ),
    );
    await tester.pump();
    expect(find.byType(AccountMenuButton), findsNothing);

    await tester.pumpWidget(_app(auth: auth, home: const OrderHistoryScreen()));
    await tester.pump();
    expect(find.byType(AccountMenuButton), findsNothing);
  });

  testWidgets('buyer menu opens, shows the account, and closes outside', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final auth = AuthController(api: _MenuApi(_buyer()))
      ..user = _buyer()
      ..restoring = false;
    await tester.pumpWidget(
      _app(
        auth: auth,
        home: Scaffold(appBar: AppBar(actions: const [AccountMenuButton()])),
      ),
    );
    await tester.pump();

    final s = AppStrings(false);
    await _openMenu(tester, s);

    expect(find.text('Maria Santos'), findsWidgets);
    expect(find.text('maria@example.com'), findsOneWidget);
    expect(find.text(s.viewYourProfile), findsOneWidget);
    expect(find.text(s.settings), findsOneWidget);
    expect(find.text(s.helpAndFaq), findsOneWidget);
    expect(find.text(s.wantToBeASeller), findsOneWidget);
    expect(find.text(s.logOut), findsOneWidget);

    final panel = tester.getRect(find.byKey(const Key('account-menu-panel')));
    expect(panel.left, greaterThanOrEqualTo(0));
    expect(panel.right, lessThanOrEqualTo(400));
    expect(panel.width, lessThanOrEqualTo(320));

    await tester.tap(find.text(s.viewYourProfile));
    await tester.pumpAndSettle();
    expect(find.byType(ProfileScreen), findsOneWidget);
    expect(find.widgetWithText(AppBar, s.profile), findsOneWidget);

    await tester.pageBack();
    await tester.pumpAndSettle();
    await _openMenu(tester, s);
    await tester.tapAt(const Offset(8, 500));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('account-menu-panel')), findsNothing);
  });

  testWidgets('the system back button closes the menu, not the screen', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final auth = AuthController(api: _MenuApi(_buyer()))
      ..user = _buyer()
      ..restoring = false;
    await tester.pumpWidget(
      _app(auth: auth, home: const Scaffold(body: Text('home'))),
    );
    await tester.pump();
    unawaited(
      tester
          .state<NavigatorState>(find.byType(Navigator).first)
          .push(
            MaterialPageRoute<void>(
              builder: (_) => Scaffold(
                appBar: AppBar(actions: const [AccountMenuButton()]),
              ),
            ),
          ),
    );
    await tester.pumpAndSettle();

    final s = AppStrings(false);
    await _openMenu(tester, s);
    expect(find.byKey(const Key('account-menu-panel')), findsOneWidget);

    await _systemBack(tester);
    expect(find.byKey(const Key('account-menu-panel')), findsNothing);
    expect(find.byType(AccountMenuButton), findsOneWidget);

    await _systemBack(tester);
    expect(find.byType(AccountMenuButton), findsNothing);
    expect(find.text('home'), findsOneWidget);
  });

  testWidgets('seller menu replaces the app-bar FAQ icon', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final seller = _seller();
    final auth = AuthController(api: _MenuApi(seller))
      ..user = seller
      ..restoring = false;
    await tester.pumpWidget(
      _app(
        auth: auth,
        theme: AniHowTheme.dark(),
        home: FarmerShell(
          preview: FarmerShellPreview(
            pages: const [
              SizedBox.shrink(),
              SizedBox.shrink(),
              SizedBox.shrink(),
              SizedBox.shrink(),
              SizedBox.shrink(),
            ],
          ),
        ),
      ),
    );
    await tester.pump();

    final s = AppStrings(false);
    expect(find.byIcon(Icons.help_outline), findsNothing);

    await _openMenu(tester, s);
    expect(find.text('Cruz Morning Greens'), findsOneWidget);
    expect(find.text('Liza Cruz · PYAP Manggahan'), findsOneWidget);
    expect(find.text(s.viewYourShopProfile), findsOneWidget);
    expect(find.text(s.settings), findsOneWidget);
    expect(find.text(s.askTheFaqBot), findsOneWidget);
    expect(find.text(s.howAnihowWorks), findsOneWidget);
    expect(find.text(s.logOut), findsOneWidget);
    expect(
      tester.widget<Text>(find.text(s.logOut)).style?.color,
      const Color(0xFFFF8A80),
    );

    await tester.tap(find.text(s.viewYourShopProfile));
    await tester.pumpAndSettle();
    expect(find.byType(FarmerProfileScreen), findsOneWidget);
  });

  testWidgets('cancel keeps the session and log out runs once', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final api = _MenuApi(_buyer());
    final auth = AuthController(api: api)
      ..user = _buyer()
      ..restoring = false;
    await tester.pumpWidget(
      _app(
        auth: auth,
        home: const Scaffold(body: Center(child: AccountMenuButton())),
      ),
    );
    await tester.pump();

    final s = AppStrings(false);
    await _openMenu(tester, s);
    await tester.tap(find.text(s.logOut));
    await tester.pumpAndSettle();
    expect(find.text(s.logOutConfirmTitle), findsOneWidget);
    expect(find.text(s.logOutConfirmBody), findsOneWidget);

    await tester.tap(find.widgetWithText(FilledButton, s.cancel));
    await tester.pumpAndSettle();
    expect(auth.user, isNotNull);
    expect(api.logouts, 0);
    expect(find.text(s.logOutConfirmTitle), findsNothing);

    await _openMenu(tester, s);
    await tester.tap(find.text(s.logOut));
    await tester.pumpAndSettle();
    final button = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, s.logOut),
    );
    button.onPressed!.call();
    button.onPressed!.call();
    await tester.pump();
    await tester.pump();

    expect(api.logouts, 1);
    expect(auth.user, isNull);
  });

  testWidgets('settings log out uses the same confirmation', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final api = _MenuApi(_buyer());
    final auth = AuthController(api: api)
      ..user = _buyer()
      ..restoring = false;
    await tester.pumpWidget(_app(auth: auth, home: const SettingsScreen()));
    await tester.pump();

    final s = AppStrings(false);
    await tester.scrollUntilVisible(find.text(s.logOut), 300);
    await tester.tap(find.text(s.logOut));
    await tester.pumpAndSettle();
    expect(find.text(s.logOutConfirmTitle), findsOneWidget);

    await tester.tap(find.widgetWithText(FilledButton, s.cancel));
    await tester.pumpAndSettle();
    expect(auth.user, isNotNull);
    expect(api.logouts, 0);
  });

  testWidgets('new account menu strings render in Filipino', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final preferences = PreferencesController()
      ..language = CropLanguage.filipino
      ..notificationsEnabled = false;
    final auth = AuthController(api: _MenuApi(_buyer()))
      ..user = _buyer()
      ..restoring = false;
    await tester.pumpWidget(
      _app(
        auth: auth,
        preferences: preferences,
        home: Scaffold(appBar: AppBar(actions: const [AccountMenuButton()])),
      ),
    );
    await tester.pump();

    final s = AppStrings(true);
    expect(find.byTooltip(s.accountMenu), findsOneWidget);
    await _openMenu(tester, s);
    expect(find.text(s.viewYourProfile), findsOneWidget);
    expect(find.text(s.helpAndFaq), findsOneWidget);
    expect(find.text('Tingnan ang profile mo'), findsOneWidget);
    expect(find.text('Tulong at FAQ'), findsOneWidget);

    await tester.tap(find.text(s.logOut));
    await tester.pumpAndSettle();
    expect(find.text('Mag-log out sa AniHow?'), findsOneWidget);
    expect(
      find.text(
        'Kailangan mo ang email at password mo para makapag-sign in ulit. Walang mabubura sa account mo.',
      ),
      findsOneWidget,
    );
  });
}
