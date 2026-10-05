import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/announcements_feed_screen.dart';
import 'package:anihow/screens/buyer/marketplace_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _MarketApi extends ApiClient {
  _MarketApi({this.posts = const [], this.listings = const []})
    : super(onUnauthorized: () {});

  final List<BuyerFarmAnnouncement> posts;
  final List<ListingItem> listings;
  String? sort;
  String? category;
  String? growingMethod;
  int? cropTypeId;
  double? nearLat;
  double? nearLng;
  final calls = <Map<String, Object?>>[];

  @override
  Future<List<ListingItem>> marketplace({
    String? search,
    int? cropTypeId,
    String? sort,
    String? category,
    double? nearLat,
    double? nearLng,
    String? growingMethod,
    int? page,
  }) async {
    this.sort = sort;
    this.category = category;
    this.growingMethod = growingMethod;
    this.cropTypeId = cropTypeId;
    this.nearLat = nearLat;
    this.nearLng = nearLng;
    calls.add({
      'sort': sort,
      'category': category,
      'growingMethod': growingMethod,
      'cropTypeId': cropTypeId,
      'nearLat': nearLat,
      'nearLng': nearLng,
      'page': page,
    });
    return listings;
  }

  @override
  Future<List<CategoryItem>> cropTypes() async => const [
    CategoryItem(id: 3, name: 'Chili', labelEn: 'Chili', labelFil: 'Sili'),
  ];

  @override
  Future<PagedBuyerAnnouncements> buyerAnnouncements({
    bool following = false,
    int? farmId,
    int page = 1,
  }) async {
    return PagedBuyerAnnouncements(
      items: posts,
      currentPage: 1,
      lastPage: 1,
    );
  }
}

ListingItem _listing(int id) {
  return ListingItem(
    id: id,
    title: 'Kamatis $id',
    pricePerUnit: '40',
    quantityAvailable: '8',
  );
}

BuyerFarmAnnouncement _post() {
  return const BuyerFarmAnnouncement(
    id: 9,
    title: 'Harvest morning',
    body: 'Fresh',
    farmId: 2,
    farmName: 'Ka Rosa',
  );
}

Widget _app(_MarketApi api) {
  final auth = AuthController(api: api)..restoring = false;
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(
        create: (_) => PreferencesController()..notificationsEnabled = false,
      ),
      ChangeNotifierProvider.value(value: auth),
      ChangeNotifierProvider(create: (_) => CartController(auth)),
    ],
    child: MaterialApp(
      theme: AniHowTheme.light(),
      home: const Scaffold(body: MarketplaceScreen()),
    ),
  );
}

void main() {
  testWidgets('search stays pinned while chips and the banner scroll away', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 640));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _MarketApi(
      posts: [_post(), _post()],
      listings: [for (var id = 1; id <= 8; id++) _listing(id)],
    );

    await tester.pumpWidget(_app(api));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('marketplace-search')), findsOneWidget);
    expect(find.byKey(const Key('marketplace-chips')), findsOneWidget);
    expect(find.byKey(const Key('marketplace-updates')), findsOneWidget);

    await tester.drag(find.byType(CustomScrollView), const Offset(0, -900));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('marketplace-search')), findsOneWidget);
    expect(find.byKey(const Key('marketplace-chips')), findsNothing);
    expect(find.byKey(const Key('marketplace-updates')), findsNothing);
  });

  testWidgets('the updates banner shows the count and opens the feed', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _MarketApi(posts: [_post(), _post()]);

    await tester.pumpWidget(_app(api));
    await tester.pumpAndSettle();

    expect(find.text('2 updates from farms'), findsOneWidget);
    expect(find.text('Ka Rosa · Harvest morning'), findsOneWidget);

    await tester.tap(find.byKey(const Key('marketplace-updates')));
    await tester.pumpAndSettle();

    expect(find.byType(AnnouncementsFeedScreen), findsOneWidget);
  });

  testWidgets('the updates banner is hidden with no posts and after dismissal', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    SharedPreferences.setMockInitialValues({});

    await tester.pumpWidget(_app(_MarketApi()));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('marketplace-updates')), findsNothing);

    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpWidget(_app(_MarketApi(posts: [_post()])));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('marketplace-updates')), findsOneWidget);

    await tester.tap(find.byKey(const Key('marketplace-updates-close')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('marketplace-updates')), findsNothing);

    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpWidget(_app(_MarketApi(posts: [_post()])));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('marketplace-updates')), findsNothing);
  });

  testWidgets('the filter sheet applies sort and growing method', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _MarketApi(listings: [_listing(1)]);

    await tester.pumpWidget(_app(api));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('marketplace-filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Price low-high'));
    await tester.tap(find.text('Certified organic'));
    await tester.tap(find.text('Apply'));
    await tester.pumpAndSettle();

    expect(api.sort, 'price_asc');
    expect(api.growingMethod, 'certified_organic');
    expect(api.nearLat, isNull);
    expect(api.nearLng, isNull);
    expect(api.calls.last['page'], isNull);
  });

  testWidgets('category and crop chips combine', (tester) async {
    await tester.binding.setSurfaceSize(const Size(800, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _MarketApi();

    await tester.pumpWidget(_app(api));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const ValueKey('category-value_added')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('crop-3')));
    await tester.pumpAndSettle();

    expect(api.category, 'value_added');
    expect(api.cropTypeId, 3);
  });
}
