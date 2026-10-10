import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/farm_announcements_screen.dart';
import 'package:anihow/screens/farmer/listings_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

ListingItem _listing({
  required int id,
  required String title,
  String quantity = '10',
  bool isActive = true,
  String? status,
  bool isUpcoming = false,
  bool expiredWithStock = false,
  bool needsActualHarvest = false,
  DateTime? availableFrom,
  TawadRule? tawad,
  int? reservations,
}) {
  return ListingItem(
    id: id,
    title: title,
    pricePerUnit: '40',
    quantityAvailable: quantity,
    unit: 'kg',
    unitLabel: 'kg',
    isActive: isActive,
    status: status,
    isUpcoming: isUpcoming,
    expiredWithStock: expiredWithStock,
    needsActualHarvest: needsActualHarvest,
    availableFrom: availableFrom,
    category: const CategoryItem(id: 3, name: 'Talong', labelEn: 'Eggplant'),
    tawad: tawad,
    activeReservationsCount: reservations,
  );
}

class _ListingsApi extends ApiClient {
  _ListingsApi(this.items, {this.announcements = const []})
    : super(onUnauthorized: () {});

  List<ListingItem> items;
  List<FarmAnnouncement> announcements;
  UserAccount? account;
  int deletes = 0;

  @override
  Future<UserAccount> currentUser() async {
    final account = this.account;
    if (account == null) {
      throw ApiException('signed out');
    }
    return account;
  }

  @override
  Future<List<ListingItem>> farmerListings() async => items;

  @override
  Future<List<FarmAnnouncement>> farmerAnnouncements() async => announcements;

  @override
  Future<void> deleteListing(
    int id, {
    bool confirmCancelReservations = false,
  }) async {
    deletes++;
    items = [for (final item in items) if (item.id != id) item];
  }
}

Future<void> _pump(
  WidgetTester tester,
  _ListingsApi api, {
  UserAccount? user,
}) async {
  api.account = user;
  final auth = AuthController(api: api)
    ..restoring = false
    ..user = user;
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
        home: const FarmerListingsScreen(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(DismissedAnnouncementStore.reset);

  testWidgets('filter chips count and hide the right listings', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pump(
      tester,
      _ListingsApi([
        _listing(id: 1, title: 'Market'),
        _listing(id: 2, title: 'Running low', quantity: '2'),
        _listing(id: 3, title: 'Paused', isActive: false),
        _listing(id: 4, title: 'Removed', status: 'taken_down'),
      ]),
    );

    expect(find.text('All 4'), findsOneWidget);
    expect(find.text('In stock 1'), findsOneWidget);
    expect(find.text('Low 1'), findsOneWidget);
    expect(find.text('Hidden 2'), findsOneWidget);
    expect(find.byKey(const ValueKey('listing-status-1')), findsOneWidget);
    expect(find.text('On the market'), findsOneWidget);
    expect(find.text('Low stock'), findsOneWidget);
    expect(find.text('Hidden'), findsWidgets);
    expect(find.text('Taken down'), findsOneWidget);

    await tester.drag(
      find.byKey(const ValueKey('listing-filters')),
      const Offset(-160, 0),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('listing-filter-hidden')));
    await tester.pumpAndSettle();
    expect(find.text('Paused'), findsOneWidget);
    expect(find.text('Removed'), findsOneWidget);
    expect(find.text('Market'), findsNothing);
    expect(find.text('Running low'), findsNothing);

    await tester.tap(find.byKey(const ValueKey('listing-filter-low')));
    await tester.pumpAndSettle();
    expect(find.text('Running low'), findsOneWidget);
    expect(find.text('Add stock'), findsOneWidget);
  });

  testWidgets('upcoming status and the menu omit reservations otherwise', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _ListingsApi([
      _listing(id: 1, title: 'Market'),
      _listing(id: 8, title: 'Coming', isUpcoming: true),
    ]);
    await _pump(tester, api);

    expect(find.text('Upcoming'), findsOneWidget);
    expect(find.byKey(const ValueKey('listing-status-8')), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('listing-menu-1')));
    await tester.pumpAndSettle();
    expect(find.text('Edit listing'), findsOneWidget);
    expect(find.text('Add stock'), findsWidgets);
    expect(find.text('Remove stock'), findsOneWidget);
    expect(find.text('Stock history'), findsOneWidget);
    expect(find.text('Tawad discount'), findsOneWidget);
    expect(find.text('Reservations'), findsNothing);
    expect(find.text('Delete listing'), findsOneWidget);

    await tester.tap(find.text('Delete listing'));
    await tester.pumpAndSettle();
    expect(find.text('Delete this listing?'), findsOneWidget);
    await tester.tap(find.text('Delete'));
    await tester.pumpAndSettle();
    expect(api.deletes, 1);
    expect(find.text('Market'), findsNothing);
  });

  testWidgets('an upcoming listing offers reservations in the menu', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pump(
      tester,
      _ListingsApi([_listing(id: 8, title: 'Coming', isUpcoming: true)]),
    );

    await tester.tap(find.byKey(const ValueKey('listing-menu-8')));
    await tester.pumpAndSettle();
    expect(find.text('Reservations'), findsOneWidget);
  });

  testWidgets('a taken-down switch stays off', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pump(
      tester,
      _ListingsApi([_listing(id: 4, title: 'Removed', status: 'taken_down')]),
    );

    final toggle = tester.widget<Switch>(
      find.byKey(const ValueKey('listing-visible-4')),
    );
    expect(toggle.onChanged, isNull);
    expect(find.text('Taken down by admin'), findsOneWidget);
  });

  testWidgets('farm news counts an unseen notice and opens the page', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pump(
      tester,
      _ListingsApi(
        [_listing(id: 1, title: 'Market')],
        announcements: const [
          FarmAnnouncement(id: 9, title: 'Market day', body: 'Saturday'),
        ],
      ),
      user: const UserAccount(
        id: 3,
        name: 'Nena',
        email: 'nena@example.com',
        roles: ['farmer_seller'],
      ),
    );

    expect(find.text('1 new'), findsOneWidget);
    expect(find.text('Market day'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('farm-news-row')));
    await tester.pumpAndSettle();
    expect(find.byType(FarmAnnouncementsScreen), findsOneWidget);

    navigator() => tester.state<NavigatorState>(find.byType(Navigator));
    navigator().pop();
    await tester.pumpAndSettle();
    expect(find.text('1 new'), findsNothing);
    expect(find.text('No farm news yet'), findsNothing);
  });

  testWidgets('harvest due and expired stock show their own actions', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1000));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pump(
      tester,
      _ListingsApi([
        _listing(
          id: 5,
          title: 'Due',
          needsActualHarvest: true,
          availableFrom: DateTime.now().add(const Duration(hours: 2)),
        ),
        _listing(
          id: 6,
          title: 'Old stock',
          quantity: '3',
          expiredWithStock: true,
        ),
      ]),
    );

    expect(find.byKey(const ValueKey('record-harvest-5')), findsOneWidget);
    expect(find.text('Record harvest'), findsOneWidget);
    expect(find.byKey(const ValueKey('expired-left-6')), findsOneWidget);
    expect(find.byKey(const ValueKey('remove-spoiled-6')), findsOneWidget);
    expect(find.byKey(const ValueKey('extend-6')), findsOneWidget);
    expect(find.text('Add stock'), findsNothing);
  });

  testWidgets('a pending harvest offers its harvest, sold out offers stock', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pump(
      tester,
      _ListingsApi([
        _listing(id: 5, title: 'Pending', quantity: '0', needsActualHarvest: true),
        _listing(id: 7, title: 'Sold out', quantity: '0'),
      ]),
    );

    expect(find.text('Add stock'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('listing-menu-5')));
    await tester.pumpAndSettle();
    expect(find.text('Record actual harvest'), findsOneWidget);
    expect(find.text('Remove stock'), findsNothing);
  });
}
