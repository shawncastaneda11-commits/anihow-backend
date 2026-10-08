import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/farm_page_screen.dart';
import 'package:anihow/screens/buyer/listing_detail_screen.dart';
import 'package:anihow/widgets/farm_map_card.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

const _buyer = UserAccount(
  id: 3,
  name: 'Ana Buyer',
  email: 'ana@example.com',
  roles: ['buyer'],
);

const _farm = FarmProfile(
  id: 7,
  name: 'Manggahan Farm',
  barangay: 'Manggahan',
  municipality: 'General Trias',
  farmerSellersCount: 2,
  favoritesCount: 5,
);

const _certified = FarmProfile(
  id: 8,
  name: 'Harvest Farm',
  barangay: 'San Francisco',
  municipality: 'General Trias',
  isOrganicCertified: true,
  organicCertifier: 'OCCP',
  organicCertifiedUntil: '2027-01-01',
  announcements: [
    FarmAnnouncement(
      id: 8,
      title: 'Harvest morning',
      body: 'Tomatoes are ready.',
      createdAt: '2026-10-05T08:00:00Z',
    ),
  ],
);

ListingItem _crop(int id) {
  return ListingItem(
    id: id,
    title: 'Pechay $id',
    pricePerUnit: '30',
    quantityAvailable: '8',
    unit: 'kg',
    sellerName: 'Nena Stall',
    sellerId: 4,
  );
}

class _ProductsApi extends ApiClient {
  _ProductsApi({
    this.listings = const [],
    this.profile = _farm,
    this.fail = false,
    this.crash = false,
    this.pageTotal,
  }) : super(onUnauthorized: () {});

  List<ListingItem> listings;
  FarmProfile profile;
  bool fail;
  bool crash;
  int? pageTotal;
  final calls = <Map<String, Object?>>[];

  @override
  Future<FarmProfile> farm(int farmId) async => profile;

  @override
  Future<List<ShopProfile>> buyerShops({
    String? sort,
    double? nearLat,
    double? nearLng,
  }) async => const [];

  @override
  Future<List<ShopFavoriteRecord>> shopFavorites() async => const [];

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
    calls.add({'farmId': farmId, 'page': page, 'mixDay': mixDay, 'sort': sort});
    if (crash) {
      throw StateError('socket closed');
    }
    if (fail) {
      throw ApiException('Could not load produce.');
    }
    final requested = page ?? 1;
    if (requested > 1) {
      return MarketplaceFeed(items: requested == 2 ? [_crop(90)] : const []);
    }
    return MarketplaceFeed(items: listings, total: pageTotal);
  }

  @override
  Future<ListingItem> marketplaceShow(int id) async => _crop(id);
}

Widget _app(_ProductsApi api, {double textScale = 1}) {
  final auth = AuthController(api: api)..restoring = false;
  auth.user = _buyer;
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => PreferencesController()),
      ChangeNotifierProvider<AuthController>.value(value: auth),
      ChangeNotifierProvider(create: (_) => CartController(auth)),
    ],
    child: MaterialApp(
      theme: AniHowTheme.light(),
      builder: (context, child) {
        return MediaQuery(
          data: MediaQuery.of(context)
              .copyWith(textScaler: TextScaler.linear(textScale)),
          child: child!,
        );
      },
      home: FarmPageScreen(key: ObjectKey(api), farmId: api.profile.id),
    ),
  );
}

void main() {
  test('marketplace query sends farm_id only when it is set', () {
    final filtered = marketplaceQuery(farmId: 7, page: 1);
    expect(filtered['farm_id'], 7);
    expect(filtered.containsKey('mix_day'), isFalse);

    final open = marketplaceQuery(page: 2, mixDay: '2026-10-09');
    expect(open.containsKey('farm_id'), isFalse);
    expect(open['page'], 2);
    expect(open['mix_day'], '2026-10-09');
  });

  testWidgets('products is the default tab and lists this farm', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _ProductsApi(listings: [_crop(11), _crop(12)]);
    final s = AppStrings(false);

    await tester.pumpWidget(_app(api));
    await tester.pumpAndSettle();

    final tabs = tester.widget<TabBar>(find.byType(TabBar));
    expect(tabs.controller!.index, 0);
    expect(find.byKey(const Key('farm-tab-products')), findsOneWidget);
    expect(api.calls, isNotEmpty);
    expect(api.calls.first['farmId'], 7);
    expect(api.calls.first['sort'], 'freshest');
    expect(api.calls.first['mixDay'], isNull);
    expect(api.calls.every((call) => call['mixDay'] == null), isTrue);
    expect(find.text(s.productsFromFarm(2)), findsOneWidget);
    expect(find.text('Pechay 11'), findsOneWidget);
    expect(find.text('5'), findsWidgets);
    expect(find.text(s.followers), findsOneWidget);

    await tester.tap(find.text('Pechay 11'));
    await tester.pumpAndSettle();

    expect(find.byType(ListingDetailScreen), findsOneWidget);
  });

  testWidgets('scrolling the products grid requests the next page', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(360, 740));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _ProductsApi(
      listings: [for (var id = 1; id <= 8; id++) _crop(id)],
    );

    await tester.pumpWidget(_app(api));
    await tester.pumpAndSettle();

    expect(api.calls.map((call) => call['page']), [1]);

    final grid = find.byKey(const PageStorageKey<String>('farm-products'));
    await tester.fling(grid, const Offset(0, -1600), 3000);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pumpAndSettle();

    expect(api.calls.any((call) => call['page'] == 2), isTrue);
    expect(api.calls.every((call) => call['mixDay'] == null), isTrue);
    expect(find.text('Pechay 90'), findsOneWidget);
  });

  testWidgets('an empty product list and a failed load can be retried', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final s = AppStrings(false);
    final empty = _ProductsApi();

    await tester.pumpWidget(_app(empty));
    await tester.pumpAndSettle();

    expect(find.text(s.noProduceListed), findsOneWidget);
    expect(find.text(s.productsFromFarm(0)), findsOneWidget);

    final failed = _ProductsApi(fail: true, listings: [_crop(11)]);
    await tester.pumpWidget(_app(failed));
    await tester.pumpAndSettle();

    expect(find.text('Could not load produce.'), findsOneWidget);
    expect(find.byKey(const Key('farm-products-retry')), findsOneWidget);

    failed.fail = false;
    await tester.tap(find.byKey(const Key('farm-products-retry')));
    await tester.pumpAndSettle();

    expect(find.text('Pechay 11'), findsOneWidget);
    expect(find.text('Could not load produce.'), findsNothing);
  });

  testWidgets('latest update and organic certification follow the farm data', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final s = AppStrings(false);
    final plain = _ProductsApi();

    await tester.pumpWidget(_app(plain));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('farm-latest-update')), findsNothing);
    await tester.tap(find.byKey(const Key('farm-tab-about')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('farm-organic')), findsNothing);
    expect(find.byKey(const Key('farm-directions')), findsOneWidget);

    final certified = _ProductsApi(profile: _certified);
    await tester.pumpWidget(_app(certified));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('farm-latest-update')), findsOneWidget);
    expect(find.text(s.organicCertified), findsWidgets);
    await tester.tap(find.byKey(const Key('farm-updates-see-all')));
    await tester.pumpAndSettle();

    final tabs = tester.widget<TabBar>(find.byType(TabBar));
    expect(tabs.controller!.index, 3);
    expect(find.text('Tomatoes are ready.'), findsWidgets);

    await tester.tap(find.byKey(const Key('farm-tab-about')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('farm-organic')), findsOneWidget);
    expect(find.text(s.certifiedBy('OCCP')), findsOneWidget);
    expect(find.text(s.validUntil('2027-01-01')), findsOneWidget);
  });

  testWidgets('the farm page does not overflow at 1.3 text scale', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(360, 740));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _ProductsApi(listings: [_crop(11)], profile: _certified);

    await tester.pumpWidget(_app(api, textScale: 1.3));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('farm-tab-products')),
      200,
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('farm-tab-products')), findsOneWidget);
    expect(find.text('Pechay 11'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  test('FarmProfile.isCertified follows is_organic_certified only', () {
    final stale = FarmProfile.fromJson({
      'id': 1,
      'name': 'Quiet Farm',
      'is_organic_certified': false,
      'organic_certifier': 'OCCP',
      'organic_certified_until': '2027-01-01',
    });
    expect(stale.isCertified, isFalse);
    expect(stale.organicCertifier, 'OCCP');

    final certified = FarmProfile.fromJson({
      'id': 2,
      'name': 'Harvest Farm',
      'is_organic_certified': true,
      'organic_certifier': 'OCCP',
      'organic_certified_until': '2027-01-01',
    });
    expect(certified.isCertified, isTrue);
  });

  test('directions use a pin when there is one, otherwise a place search', () {
    const pinned = FarmProfile(
      id: 4,
      name: 'Pinned Farm',
      latitude: 14.28,
      longitude: 120.88,
    );
    expect(
      pinned.directionsUri.toString(),
      'https://www.google.com/maps/dir/?api=1&destination=14.28,120.88',
    );

    const placed = FarmProfile(
      id: 5,
      name: 'PYAP Manggahan',
      barangay: 'Manggahan',
      municipality: 'General Trias',
    );
    expect(
      placed.directionsUri.toString(),
      'https://www.google.com/maps/search/?api=1&query='
      '${Uri.encodeComponent('PYAP Manggahan, Manggahan, General Trias, Cavite')}',
    );

    const barangayOnly = FarmProfile(
      id: 6,
      name: 'Ka Rosa',
      barangay: 'Manggahan',
    );
    expect(
      barangayOnly.directionsUri.toString(),
      'https://www.google.com/maps/search/?api=1&query='
      '${Uri.encodeComponent('Ka Rosa, Manggahan, Cavite')}',
    );

    const nowhere = FarmProfile(id: 7, name: 'Nowhere Farm');
    expect(nowhere.canGetDirections, isFalse);
    expect(nowhere.directionsUri, isNull);
  });

  testWidgets('the product total comes from page 1 and freshest order', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final s = AppStrings(false);
    final api = _ProductsApi(
      listings: [for (var id = 1; id <= 15; id++) _crop(id)],
      pageTotal: 23,
    );

    await tester.pumpWidget(_app(api));
    await tester.pumpAndSettle();

    expect(api.calls, isNotEmpty);
    expect(api.calls.first['sort'], 'freshest');
    expect(api.calls.first['farmId'], 7);
    expect(api.calls.first['mixDay'], isNull);
    expect(find.text(s.productsFromFarm(23)), findsOneWidget);
    expect(find.text(s.productsFromFarm(15)), findsNothing);
  });

  testWidgets('a non-API product error shows Retry and Retry reloads', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final s = AppStrings(false);
    final api = _ProductsApi(crash: true, listings: [_crop(11)]);

    await tester.pumpWidget(_app(api));
    await tester.pumpAndSettle();

    expect(find.text(s.somethingWentWrong), findsOneWidget);
    expect(find.byKey(const Key('farm-products-retry')), findsOneWidget);

    api.crash = false;
    await tester.tap(find.byKey(const Key('farm-products-retry')));
    await tester.pumpAndSettle();

    expect(find.text('Pechay 11'), findsOneWidget);
    expect(find.text(s.somethingWentWrong), findsNothing);
  });

  testWidgets('directions follow the pin, the place, or stay hidden', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final previous = launchFarmDirections;
    final opened = <Uri>[];
    launchFarmDirections = (uri) async {
      opened.add(uri);
      return true;
    };
    addTearDown(() => launchFarmDirections = previous);

    const pinned = FarmProfile(
      id: 4,
      name: 'Pinned Farm',
      latitude: 14.28,
      longitude: 120.88,
    );
    await tester.pumpWidget(_app(_ProductsApi(profile: pinned)));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('farm-directions')), findsOneWidget);
    await tester.tap(find.byKey(const Key('farm-directions')));
    await tester.pump();
    expect(opened.single.toString(), pinned.directionsUri.toString());

    opened.clear();
    const placed = FarmProfile(
      id: 5,
      name: 'PYAP Manggahan',
      barangay: 'Manggahan',
      municipality: 'General Trias',
    );
    await tester.pumpWidget(_app(_ProductsApi(profile: placed)));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('farm-directions')), findsOneWidget);
    await tester.ensureVisible(find.byKey(const Key('farm-directions')));
    await tester.tap(find.byKey(const Key('farm-directions')));
    await tester.pump();
    expect(opened.single.toString(), placed.directionsUri.toString());
    expect(opened.single.toString(), contains('maps/search'));
    await tester.tap(find.byKey(const Key('farm-tab-about')));
    await tester.pumpAndSettle();
    expect(find.byType(FarmMapCard), findsNothing);
    expect(find.text('Manggahan, General Trias'), findsWidgets);
    expect(find.text(AppStrings(false).directions), findsWidgets);
    expect(
      opened.single.toString(),
      contains(
        Uri.encodeComponent('PYAP Manggahan, Manggahan, General Trias, Cavite'),
      ),
    );

    const nowhere = FarmProfile(id: 9, name: 'Nowhere Farm');
    await tester.pumpWidget(_app(_ProductsApi(profile: nowhere)));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('farm-directions')), findsNothing);
  });

  for (final scale in [1.0, 1.3]) {
    testWidgets(
      'farm product cards end within 16px of the availability pill at $scale',
      (tester) async {
        tester.view.devicePixelRatio = 1;
        tester.view.physicalSize = const Size(390, 844);
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.binding.setSurfaceSize(const Size(390, 844));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        const listing = ListingItem(
          id: 21,
          title: 'Sitaw',
          pricePerUnit: '40',
          quantityAvailable: '12',
          unit: 'kg',
          sellerName: 'Ka Rosa Garden',
          sellerId: 4,
          category: CategoryItem(
            id: 3,
            name: 'String beans',
            labelEn: 'String beans',
          ),
        );

        await tester.pumpWidget(
          _app(_ProductsApi(listings: [listing]), textScale: scale),
        );
        await tester.pumpAndSettle();

        expect(find.text('String beans'), findsOneWidget);
        expect(find.text('Ka Rosa Garden'), findsOneWidget);
        final pill = find.byKey(const ValueKey('availability-pill-21'));
        await tester.scrollUntilVisible(
          pill,
          400,
          scrollable: find.descendant(
            of: find.byKey(const PageStorageKey<String>('farm-products')),
            matching: find.byType(Scrollable),
          ),
        );
        await tester.pumpAndSettle();
        expect(pill, findsOneWidget);
        final card = find.ancestor(of: pill, matching: find.byType(Card)).first;
        final gap = tester.getRect(card).bottom - tester.getRect(pill).bottom;
        expect(gap, inInclusiveRange(0, 16));
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets('tabs stay even until the labels cannot fit', (tester) async {
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    addTearDown(() => tester.binding.setSurfaceSize(null));

    Future<TabBar> bar(Size size, double scale) async {
      tester.view.physicalSize = size;
      await tester.binding.setSurfaceSize(size);
      await tester.pumpWidget(_app(_ProductsApi(), textScale: scale));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      return tester.widget<TabBar>(find.byType(TabBar));
    }

    final wide = await bar(const Size(390, 844), 1);
    expect(wide.isScrollable, isFalse);
    expect(tester.getSize(find.byType(TabBar)).width, 390);

    final narrow = await bar(const Size(340, 844), 1);
    expect(narrow.isScrollable, isTrue);
    expect(narrow.tabAlignment, TabAlignment.center);
    expect(tester.getSize(find.byType(TabBar)).width, 340);

    final scaled = await bar(const Size(390, 844), 1.3);
    expect(scaled.isScrollable, isTrue);
    expect(scaled.tabAlignment, TabAlignment.center);
  });

  testWidgets('the name and the first tab line sit 12px under their anchors', (
    tester,
  ) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(390, 844);
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.binding.setSurfaceSize(const Size(390, 844));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(_app(_ProductsApi()));
    await tester.pumpAndSettle();

    final logo = tester.getRect(find.byKey(const Key('farm-logo')));
    final name = tester.getRect(find.byKey(const Key('farm-name')));
    expect(name.top - logo.bottom, closeTo(12, 1));

    final tabs = tester.getRect(find.byType(TabBar));
    final count = tester.getRect(find.byKey(const Key('farm-products-count')));
    expect(count.top - tabs.bottom, closeTo(12, 2));

    final nested = tester.state<NestedScrollViewState>(
      find.byType(NestedScrollView),
    );
    nested.outerController.jumpTo(
      nested.outerController.position.maxScrollExtent,
    );
    await tester.pumpAndSettle();
    // ignore: avoid_print
    final pinnedTabs = tester.getRect(find.byType(TabBar));
    final pinnedCount = tester.getRect(
      find.byKey(const Key('farm-products-count')),
    );
    expect(pinnedCount.top - pinnedTabs.bottom, closeTo(12, 2));
    expect(tester.takeException(), isNull);
  });
}
