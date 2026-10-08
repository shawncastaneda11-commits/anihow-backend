import 'dart:io';

import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/announcements_feed_screen.dart';
import 'package:anihow/screens/buyer/marketplace_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_space.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/produce_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
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
      'mixDay': mixDay,
    });
    return MarketplaceFeed(items: listings);
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
    return PagedBuyerAnnouncements(items: posts, currentPage: 1, lastPage: 1);
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

Future<void> _loadRoboto() async {
  final loader = FontLoader('Roboto');
  final roots = <String>[
    if (Platform.environment['FLUTTER_ROOT'] case final root?)
      '$root/bin/cache/artifacts/material_fonts',
    'C:/flutter/bin/cache/artifacts/material_fonts',
    'C:/Users/joshua/flutter/bin/cache/artifacts/material_fonts',
  ];
  for (final root in roots) {
    final regular = File('$root/roboto-regular.ttf');
    if (!regular.existsSync()) {
      continue;
    }
    for (final name in [
      'roboto-regular.ttf',
      'roboto-medium.ttf',
      'roboto-bold.ttf',
    ]) {
      final bytes = await File('$root/$name').readAsBytes();
      loader.addFont(Future.value(ByteData.sublistView(bytes)));
    }
    await loader.load();
    return;
  }

  const fallbacks = [
    r'C:\Windows\Fonts\arial.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
  ];
  for (final path in fallbacks) {
    final file = File(path);
    if (!file.existsSync()) {
      continue;
    }
    final bytes = await file.readAsBytes();
    loader.addFont(Future.value(ByteData.sublistView(bytes)));
    await loader.load();
    return;
  }
}

void main() {
  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
    await _loadRoboto();
  });

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

  testWidgets(
    'the updates banner is hidden with no posts and after dismissal',
    (tester) async {
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
    },
  );

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
    await tester.tap(find.widgetWithText(ChoiceChip, 'Price low-high'));
    await tester.tap(find.widgetWithText(ChoiceChip, 'Certified organic'));
    await tester.tap(find.text('Apply'));
    await tester.pumpAndSettle();

    expect(api.sort, 'price_asc');
    expect(api.growingMethod, 'certified_organic');
    expect(api.nearLat, isNull);
    expect(api.nearLng, isNull);
    expect(api.calls.last['page'], isNull);
  });

  testWidgets('sort chips stay on screen and none is highlighted by default', (
    tester,
  ) async {
    for (final size in [const Size(360, 640), const Size(411, 800)]) {
      await tester.binding.setSurfaceSize(size);
      addTearDown(() => tester.binding.setSurfaceSize(null));
      SharedPreferences.setMockInitialValues({});

      await tester.pumpWidget(_app(_MarketApi(listings: [_listing(1)])));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('marketplace-filter')));
      await tester.pumpAndSettle();

      expect(find.text('Fair mix'), findsNothing);
      expect(tester.takeException(), isNull);

      final scroll = tester.state<ScrollableState>(
        find.descendant(
          of: find.byType(SingleChildScrollView),
          matching: find.byType(Scrollable),
        ),
      );
      expect(scroll.position.pixels, 0);

      void expectVisible(Finder finder) {
        expect(finder.hitTestable(), findsOneWidget);
        final rect = tester.getRect(finder);
        expect(rect.top, greaterThanOrEqualTo(0));
        expect(rect.bottom, lessThanOrEqualTo(size.height));
      }

      expectVisible(find.text('Sort'));
      expect(find.byKey(const Key('sort-fair')), findsNothing);
      expectVisible(find.byKey(const Key('sort-newest')));
      expectVisible(find.widgetWithText(ChoiceChip, 'Price low-high'));
      expectVisible(find.widgetWithText(ChoiceChip, 'Price high-low'));
      expectVisible(find.widgetWithText(ChoiceChip, 'Availability'));
      expectVisible(find.widgetWithText(ChoiceChip, 'Nearest'));
      expectVisible(find.text('Growing method'));
      expectVisible(find.widgetWithText(ChoiceChip, 'Certified organic'));
      expectVisible(find.widgetWithText(ChoiceChip, 'Naturally grown'));
      expectVisible(find.text('Clear'));
      expectVisible(find.text('Apply'));

      final newest = tester.widget<ChoiceChip>(
        find.byKey(const Key('sort-newest')),
      );
      expect(newest.selected, isFalse);
      expect(newest.materialTapTargetSize, MaterialTapTargetSize.padded);

      await tester.tap(find.byKey(const Key('sort-newest')));
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<ChoiceChip>(find.byKey(const Key('sort-newest')))
            .selected,
        isTrue,
      );
      expect(scroll.position.pixels, 0);

      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pumpAndSettle();
    }
  });

  testWidgets('category and crop chips combine', (tester) async {
    await tester.binding.setSurfaceSize(const Size(800, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _MarketApi();

    await tester.pumpWidget(_app(api));
    await tester.pumpAndSettle();

    expect(find.byKey(const ValueKey('crop-3')), findsNothing);

    await tester.tap(find.byKey(const ValueKey('category-value_added')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('marketplace-filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('crop-3')));
    await tester.tap(find.text('Apply'));
    await tester.pumpAndSettle();

    expect(api.category, 'value_added');
    expect(api.cropTypeId, 3);
    final badge = tester.widget<Badge>(
      find.descendant(
        of: find.byKey(const Key('marketplace-filter')),
        matching: find.byType(Badge),
      ),
    );
    expect(badge.isLabelVisible, isTrue);
  });

  ListingItem upcomingListing() {
    return ListingItem(
      id: 12,
      title: 'Kalabasa from the morning harvest at Manggahan',
      pricePerUnit: '40',
      quantityAvailable: '8',
      unit: 'kg',
      sellerId: 3,
      sellerName: 'Aling Nena Produce of Manggahan General Trias',
      category: const CategoryItem(id: 1, name: 'Squash', labelEn: 'Squash'),
      isUpcoming: true,
      availableFrom: DateTime(2026, 10, 12),
      harvestedOn: DateTime(2026, 10, 3),
      organicBadge: 'naturally_grown',
      averageRating: '4.5',
      reviewsCount: 3,
      tawad: const TawadRule(id: 1, type: 'flat', discountAmount: '5'),
    );
  }

  Future<void> pumpMarket(WidgetTester tester, Size size) async {
    await tester.binding.setSurfaceSize(size);
    addTearDown(() => tester.binding.setSurfaceSize(null));
    SharedPreferences.setMockInitialValues({});
    final api = _MarketApi(listings: [upcomingListing(), _listing(2)]);
    await tester.pumpWidget(_app(api));
    await tester.pumpAndSettle();
  }

  testWidgets('phones show one full-width feed card per line', (tester) async {
    const shopName = 'Aling Nena Produce of Manggahan General Trias';
    for (final width in [360.0, 411.0]) {
      await pumpMarket(tester, Size(width, 800));

      expect(tester.takeException(), isNull);
      final cards = tester.widgetList<ProduceCard>(find.byType(ProduceCard));
      expect(cards.length, 2);
      expect(
        cards.every((card) => card.style == ProduceCardStyle.feed),
        isTrue,
      );
      expect(cards.every((card) => card.showSeller), isTrue);

      final first = tester.getRect(find.byType(ProduceCard).at(0));
      final second = tester.getRect(find.byType(ProduceCard).at(1));
      expect(second.top, greaterThan(first.bottom - 1));
      expect(second.left, closeTo(first.left, 1));
      expect((second.top - first.bottom).round(), AniHowSpace.cardGap.round());

      final photo = tester.getRect(find.byType(AspectRatio).first);
      expect(photo.height, greaterThanOrEqualTo(180));

      final name = tester.getRect(
        find.text('Kalabasa from the morning harvest at Manggahan'),
      );
      final priceBox = tester.getRect(find.text('₱40.00 / kg'));
      expect((name.center.dy - priceBox.center.dy).abs(), lessThan(4));
      expect(priceBox.left, greaterThan(name.left));
      expect(photo.bottom, lessThanOrEqualTo(name.top));

      final pill = tester.getRect(
        find.byKey(const ValueKey('availability-pill-12')),
      );
      final promo = tester.getRect(find.byKey(const ValueKey('promo-badge')));
      expect(pill.center.dy, lessThan(photo.bottom));
      expect(pill.top, greaterThanOrEqualTo(photo.top));
      expect(promo.center.dy, lessThan(photo.bottom));
      expect(promo.bottom, lessThanOrEqualTo(photo.bottom + 1));

      final reserve = tester.renderObject<RenderParagraph>(
        find.text('Reserve · from Oct 12'),
      );
      expect(reserve.didExceedMaxLines, isFalse);
      expect(find.byTooltip('Naturally grown (self-declared)'), findsOneWidget);
      expect(find.text('Harvested Oct 3'), findsOneWidget);
      expect(find.text('₱5.00 off'), findsOneWidget);
      expect(find.text('Squash · '), findsOneWidget);
      final price = tester.widget<Text>(find.text('₱40.00 / kg'));
      expect(price.style?.color, AniHowColors.brand);
      expect(find.textContaining('4.5'), findsWidgets);

      final shop = tester.widget<Text>(find.text(shopName));
      expect(shop.maxLines, 1);
      expect(shop.overflow, TextOverflow.ellipsis);

      var sellerTaps = 0;
      await tester.pumpWidget(
        ChangeNotifierProvider(
          create: (_) => PreferencesController()..notificationsEnabled = false,
          child: MaterialApp(
            theme: AniHowTheme.dark(),
            home: Scaffold(
              body: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: ProduceCard(
                  listing: upcomingListing(),
                  style: ProduceCardStyle.feed,
                  onSellerTap: () => sellerTaps++,
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pump();
      expect(tester.takeException(), isNull);
      final darkPrice = tester.widget<Text>(find.text('₱40.00 / kg'));
      expect(darkPrice.style?.color, AniHowColors.sage);
      await tester.tap(find.text(shopName));
      await tester.pump();
      expect(sellerTaps, 1);
    }
  });

  testWidgets('wide screens keep the two-column poster grid', (tester) async {
    await pumpMarket(tester, const Size(800, 1200));

    expect(tester.takeException(), isNull);
    final cards = tester.widgetList<ProduceCard>(find.byType(ProduceCard));
    expect(cards.length, 2);
    expect(
      cards.every((card) => card.style == ProduceCardStyle.poster),
      isTrue,
    );

    final first = tester.getRect(find.byType(ProduceCard).at(0));
    final second = tester.getRect(find.byType(ProduceCard).at(1));
    expect(second.top, closeTo(first.top, 1));
    expect(second.left, greaterThan(first.right - 1));
  });

  testWidgets('the price uses sage text on a dark card', (tester) async {
    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => PreferencesController()..notificationsEnabled = false,
        child: MaterialApp(
          theme: AniHowTheme.dark(),
          home: Scaffold(
            body: ProduceCard(
              listing: ListingItem(
                id: 1,
                title: 'Kalabasa',
                pricePerUnit: '40',
                quantityAvailable: '8',
                unit: 'kg',
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pump();

    final price = tester.widget<Text>(find.text('₱40.00 / kg'));
    expect(price.style?.color, AniHowColors.sage);
  });
}
