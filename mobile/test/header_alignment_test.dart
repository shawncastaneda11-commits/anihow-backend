import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/buyer_shell.dart';
import 'package:anihow/screens/farmer/farmer_shell.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/account_menu_button.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _HeaderApi extends ApiClient {
  _HeaderApi() : super(onUnauthorized: () {});

  @override
  Future<UserAccount> currentUser() async => _buyer;

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
  }) async => const MarketplaceFeed(items: []);

  @override
  Future<List<CategoryItem>> cropTypes() async => const [];

  @override
  Future<PagedBuyerAnnouncements> buyerAnnouncements({
    bool following = false,
    int? farmId,
    int page = 1,
  }) async => const PagedBuyerAnnouncements(
    items: [],
    currentPage: 1,
    lastPage: 1,
  );

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
  Future<List<ListingItem>> farmerListings() async => const [];

  @override
  Future<List<FarmAnnouncement>> farmerAnnouncements() async => const [];

  @override
  Future<List<OrderRecord>> farmerOrders({String? status}) async => const [];

  @override
  Future<List<CropCareArticle>> cropCare({
    String? search,
    String? category,
    int? cropTypeId,
    int? farmId,
  }) async => const [];

  @override
  Future<List<StallChat>> stallChats() async => const [];

  @override
  Future<FarmerAnalytics> farmerAnalytics({
    String range = 'month',
    String? from,
    String? to,
    int? year,
    String category = 'all',
  }) async {
    return const FarmerAnalytics(
      period: 'week',
      windowStart: '2026-09-18',
      windowEnd: '2026-09-24',
      summary: FarmerAnalyticsSummary(
        completedOrders: 0,
        unitsSold: 0,
        grossSales: 0,
        averageDiscount: 0,
      ),
      salesPerPeriod: [],
      unitsPerCropType: [],
      bestSelling: [],
      walkInShare: FarmerWalkInShare(
        walkInOrders: 0,
        walkInSales: 0,
        appOrders: 0,
        appSales: 0,
      ),
    );
  }
}

const _buyer = UserAccount(
  id: 1,
  name: 'Maria Santos',
  email: 'maria@example.com',
  roles: ['buyer', 'farmer_seller'],
  shopName: 'Cruz Morning Greens',
  emailVerifiedAt: '2026-01-01T00:00:00Z',
);

class _TabHeader {
  const _TabHeader(this.label, this.title);

  final String label;
  final String title;
}

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('every main-tab header lines up', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final auth = AuthController(api: _HeaderApi())
      ..user = _buyer
      ..restoring = false;
    final s = AppStrings(false);

    Widget app(Widget home) {
      return MultiProvider(
        providers: [
          ChangeNotifierProvider(
            create: (_) => PreferencesController()..notificationsEnabled = false,
          ),
          ChangeNotifierProvider.value(value: auth),
          ChangeNotifierProvider(create: (_) => CartController(auth)),
        ],
        child: MaterialApp(theme: AniHowTheme.light(), home: home),
      );
    }

    final buyerTabs = [
      _TabHeader(s.market, s.marketplace),
      _TabHeader(s.shops, s.shops),
      _TabHeader(s.orders, s.orderHistory),
      _TabHeader(s.favorites, s.favorites),
    ];
    final sellerTabs = [
      _TabHeader(s.listings, s.myListings),
      _TabHeader(s.orders, s.incomingOrders),
      _TabHeader(s.mySales, s.mySales),
      _TabHeader(s.cropCare, s.cropCare),
      _TabHeader(s.chats, s.chats),
    ];

    final measurements = <_TabHeader, ({double left, double centerY, Offset button, double toolbar})>{};

    Future<void> collect(Widget home, List<_TabHeader> tabs) async {
      await tester.pumpWidget(app(home));
      await tester.pump();
      for (final tab in tabs) {
        await tester.tap(find.text(tab.label).hitTestable());
        await tester.pump();
        final appBar = find.byType(AppBar).hitTestable();
        final title = tester.getRect(
          find.descendant(of: appBar, matching: find.text(tab.title)),
        );
        final button = tester.getRect(
          find.byType(AccountMenuButton).hitTestable(),
        );
        final toolbar = tester.getSize(
          find.descendant(
            of: appBar,
            matching: find.byType(NavigationToolbar),
          ),
        );
        measurements[tab] = (
          left: title.left,
          centerY: title.center.dy,
          button: button.center,
          toolbar: toolbar.height,
        );
      }
    }

    await collect(const BuyerShell(), buyerTabs);
    await collect(const FarmerShell(), sellerTabs);

    final first = measurements.values.first;
    for (final entry in measurements.entries) {
      final measured = entry.value;
      expect(measured.left, closeTo(first.left, 0.5), reason: entry.key.title);
      expect(
        measured.centerY,
        closeTo(first.centerY, 0.5),
        reason: entry.key.title,
      );
      expect(
        measured.button.dx,
        closeTo(first.button.dx, 0.5),
        reason: entry.key.title,
      );
      expect(
        measured.button.dy,
        closeTo(first.button.dy, 0.5),
        reason: entry.key.title,
      );
      expect(
        measured.toolbar,
        closeTo(first.toolbar, 0.5),
        reason: entry.key.title,
      );
      expect(measured.toolbar, closeTo(kToolbarHeight, 0.5));
      expect(measured.left, closeTo(16, 0.5), reason: entry.key.title);
    }
  });
}
