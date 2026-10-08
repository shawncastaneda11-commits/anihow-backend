import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/farm_page_screen.dart';
import 'package:anihow/screens/buyer/listing_detail_screen.dart';
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
  }) : super(onUnauthorized: () {});

  List<ListingItem> listings;
  FarmProfile profile;
  bool fail;
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
    calls.add({'farmId': farmId, 'page': page, 'mixDay': mixDay});
    if (fail) {
      throw ApiException('Could not load produce.');
    }
    final requested = page ?? 1;
    if (requested > 1) {
      return MarketplaceFeed(items: requested == 2 ? [_crop(90)] : const []);
    }
    return MarketplaceFeed(items: listings);
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
    expect(find.byKey(const Key('farm-directions')), findsNothing);

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
}
