import 'dart:io';

import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/farm_announcements_screen.dart';
import 'package:anihow/screens/farmer/listings_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/support/crop_language.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

ListingItem _listing({
  required int id,
  required String title,
  String quantity = '10',
  String unit = 'kg',
  double? stockBaseline,
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
    unit: unit,
    unitLabel: unit,
    isActive: isActive,
    status: status,
    isUpcoming: isUpcoming,
    expiredWithStock: expiredWithStock,
    needsActualHarvest: needsActualHarvest,
    availableFrom: availableFrom,
    category: const CategoryItem(id: 3, name: 'Talong', labelEn: 'Eggplant'),
    tawad: tawad,
    activeReservationsCount: reservations,
    stockBaseline: stockBaseline,
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
    items = [
      for (final item in items)
        if (item.id != id) item,
    ];
  }
}

Future<void> _pump(
  WidgetTester tester,
  _ListingsApi api, {
  UserAccount? user,
  CropLanguage language = CropLanguage.english,
}) async {
  api.account = user;
  final auth = AuthController(api: api)
    ..restoring = false
    ..user = user;
  await tester.pumpWidget(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(
          create: (_) => PreferencesController()
            ..notificationsEnabled = false
            ..language = language,
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

  testWidgets('expired stock buttons stay one line high in Filipino on a narrow phone', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(360, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.runAsync(_loadRoboto);
    await _pump(
      tester,
      _ListingsApi([
        _listing(id: 6, title: 'Old stock', quantity: '13', expiredWithStock: true),
      ]),
      language: CropLanguage.filipino,
    );

    final remove = tester.getSize(find.byKey(const ValueKey('remove-spoiled-6')));
    final extend = tester.getSize(find.byKey(const ValueKey('extend-6')));

    expect(remove.height, 44);
    expect(extend.height, 44);
    expect(find.text('Alisin ang sira'), findsOneWidget);
    expect(find.text('Ano ang gagawin sa natitirang 13 kg?'), findsOneWidget);
  });

  testWidgets('a pending harvest offers its harvest, sold out offers stock', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pump(
      tester,
      _ListingsApi([
        _listing(
          id: 5,
          title: 'Pending',
          quantity: '0',
          needsActualHarvest: true,
        ),
        _listing(id: 7, title: 'Sold out', quantity: '0'),
      ]),
    );

    expect(find.text('Add stock'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('listing-menu-5')));
    await tester.pumpAndSettle();
    expect(find.text('Record actual harvest'), findsOneWidget);
    expect(find.text('Remove stock'), findsNothing);
  });

  testWidgets('stock line uses the plural unit word', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pump(
      tester,
      _ListingsApi([
        _listing(id: 1, title: 'Sitaw', quantity: '12', unit: 'bundle'),
        _listing(id: 2, title: 'Pechay', quantity: '1', unit: 'bundle'),
      ]),
    );

    expect(find.text('12 bundles left'), findsOneWidget);
    expect(find.text('1 bundle left'), findsOneWidget);
  });

  testWidgets('the New listing button hides while scrolling down', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pump(
      tester,
      _ListingsApi([
        for (var id = 1; id <= 8; id++) _listing(id: id, title: 'Item $id'),
      ]),
    );

    Offset fabOffset() =>
        tester.widget<AnimatedSlide>(find.byType(AnimatedSlide)).offset;
    expect(fabOffset(), Offset.zero);

    await tester.drag(find.byType(ListView).last, const Offset(0, -300));
    await tester.pumpAndSettle();
    expect(fabOffset(), isNot(Offset.zero));

    await tester.drag(find.byType(ListView).last, const Offset(0, 200));
    await tester.pumpAndSettle();
    expect(fabOffset(), Offset.zero);
  });

  testWidgets('filters share the row equally and scroll away with the cards', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pump(
      tester,
      _ListingsApi([
        for (var id = 1; id <= 8; id++) _listing(id: id, title: 'Item $id'),
      ]),
    );

    final widths = [
      for (final key in ['all', 'stock', 'low', 'hidden'])
        tester.getSize(find.byKey(ValueKey('listing-filter-$key'))).width,
    ];
    for (final width in widths) {
      expect(width, moreOrLessEquals(widths.first, epsilon: 0.5));
    }

    final newsTop = tester
        .getTopLeft(find.byKey(const ValueKey('farm-news-row')))
        .dy;
    await tester.drag(find.byType(ListView).last, const Offset(0, -150));
    await tester.pumpAndSettle();
    final moved = find.byKey(const ValueKey('farm-news-row'));
    expect(
      moved.evaluate().isEmpty || tester.getTopLeft(moved).dy < newsTop - 100,
      isTrue,
    );
  });

  testWidgets('the stock bar shows what is left since the last restock', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1000));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pump(
      tester,
      _ListingsApi([
        _listing(id: 1, title: 'Half', quantity: '6', stockBaseline: 12),
        _listing(id: 2, title: 'Low', quantity: '2', stockBaseline: 10),
        _listing(id: 3, title: 'Coming', isUpcoming: true, stockBaseline: 9),
        _listing(id: 4, title: 'Legacy', quantity: '7'),
      ]),
    );

    expect(find.byKey(const ValueKey('stock-bar-1')), findsOneWidget);
    expect(find.text('6 of 12 kg left'), findsOneWidget);
    expect(find.text('50%'), findsOneWidget);
    expect(find.text('2 of 10 kg left'), findsOneWidget);
    expect(
      find.descendant(
        of: find.byKey(const ValueKey('stock-bar-2')),
        matching: find.text('Low stock'),
      ),
      findsOneWidget,
    );
    final fill = tester.widget<FractionallySizedBox>(
      find.descendant(
        of: find.byKey(const ValueKey('stock-bar-1')),
        matching: find.byType(FractionallySizedBox),
      ),
    );
    expect(fill.widthFactor, 0.5);
    expect(find.byKey(const ValueKey('stock-bar-3')), findsNothing);
    expect(find.byKey(const ValueKey('stock-bar-4')), findsNothing);
    expect(find.text('7 kg left'), findsOneWidget);
  });
}

/// Test text is drawn as squares unless a real font is loaded, which would make
/// every label look too wide. Load the Roboto the app uses from the Flutter SDK.
Future<void> _loadRoboto() async {
  final root = Platform.environment['FLUTTER_ROOT'];
  final folder = root == null ? null : Directory('$root/bin/cache/artifacts/material_fonts');
  if (folder == null || !folder.existsSync()) {
    return;
  }
  final loader = FontLoader('Roboto');
  for (final file in folder.listSync().whereType<File>()) {
    final name = file.uri.pathSegments.last.toLowerCase();
    if (name == 'roboto-regular.ttf' || name == 'roboto-medium.ttf' || name == 'roboto-bold.ttf') {
      loader.addFont(file.readAsBytes().then(ByteData.sublistView));
    }
  }
  await loader.load();
}
