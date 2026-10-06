import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/marketplace_screen.dart';
import 'package:anihow/screens/buyer/shops_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/services/buyer_location.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/support/crop_language.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/produce_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _MarketplaceApi extends ApiClient {
  _MarketplaceApi() : super(onUnauthorized: () {});

  String? sort;
  double? nearLat;
  double? nearLng;
  int calls = 0;

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
  }) async {
    calls++;
    this.sort = sort;
    this.nearLat = nearLat;
    this.nearLng = nearLng;
    return const MarketplaceFeed(
      items: [
        ListingItem(
          id: 7,
          title: 'Kamatis',
          pricePerUnit: '40',
          quantityAvailable: '10',
          distanceKm: 2.4,
        ),
      ],
    );
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
}

class _ShopsApi extends ApiClient {
  _ShopsApi() : super(onUnauthorized: () {});

  String? sort;
  double? nearLat;
  double? nearLng;

  @override
  Future<List<ShopProfile>> buyerShops({
    String? sort,
    double? nearLat,
    double? nearLng,
  }) async {
    this.sort = sort;
    this.nearLat = nearLat;
    this.nearLng = nearLng;
    return const [
      ShopProfile(
        id: 4,
        shopName: 'Nena Stall',
        name: 'Nena',
        farmId: 1,
        farmName: 'Manggahan Farm',
        farmMunicipality: 'General Trias',
        farmIsActive: true,
        distanceKm: 2.4,
      ),
    ];
  }
}

Widget _app(
  Widget home, {
  required AuthController auth,
  CropLanguage? language,
  bool dark = false,
}) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(
        create: (_) {
          final preferences = PreferencesController()
            ..notificationsEnabled = false;
          if (language != null) {
            preferences.language = language;
          }
          return preferences;
        },
      ),
      ChangeNotifierProvider.value(value: auth),
      ChangeNotifierProvider(create: (_) => CartController(auth)),
    ],
    child: MaterialApp(
      theme: dark ? AniHowTheme.dark() : AniHowTheme.light(),
      home: Scaffold(body: home),
    ),
  );
}

void main() {
  tearDown(() {
    BuyerLocation.read = BuyerLocation.device;
  });

  testWidgets('denied location still loads nearest with a short message', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    BuyerLocation.read = () async => null;
    final api = _MarketplaceApi();
    final auth = AuthController(api: api)..restoring = false;

    await tester.pumpWidget(_app(const MarketplaceScreen(), auth: auth));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('marketplace-filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Nearest'));
    await tester.tap(find.text('Apply'));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('location-unavailable')), findsOneWidget);
    expect(find.text('Kamatis'), findsWidgets);
    expect(api.sort, 'nearest');
    expect(api.nearLat, isNull);
    expect(api.nearLng, isNull);
    expect(api.calls, greaterThan(1));
  });

  testWidgets('distance labels format in English and Filipino', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const listing = ListingItem(
      id: 1,
      title: 'Kamatis',
      pricePerUnit: '40',
      quantityAvailable: '10',
      distanceKm: 2.4,
    );

    await tester.pumpWidget(
      _app(
        const ProduceCard(listing: listing),
        auth: AuthController()..restoring = false,
      ),
    );
    await tester.pump();
    expect(find.text('2.4 km away'), findsOneWidget);
    expect(AppStrings(true).kilometersAway(2.4), '2.4 km ang layo');
  });

  testWidgets('distance labels use Filipino when that language is selected', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const listing = ListingItem(
      id: 1,
      title: 'Kamatis',
      pricePerUnit: '40',
      quantityAvailable: '10',
      distanceKm: 2.4,
    );

    await tester.pumpWidget(
      _app(
        const ProduceCard(listing: listing),
        auth: AuthController()..restoring = false,
        language: CropLanguage.filipino,
      ),
    );
    await tester.pump();
    expect(find.text('2.4 km ang layo'), findsOneWidget);
  });

  testWidgets('shop cards show the distance from the farm pin', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _ShopsApi();
    final auth = AuthController(api: api)..restoring = false;

    await tester.pumpWidget(_app(const ShopsScreen(), auth: auth));
    await tester.pumpAndSettle();

    expect(find.text('Manggahan Farm'), findsOneWidget);
    expect(find.text('2.4 km away'), findsOneWidget);
    expect(api.nearLat, isNull);
    expect(api.sort, isNull);
  });

  testWidgets('the farm search and nearest chip share a row', (tester) async {
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    for (final dark in [false, true]) {
      for (final size in const [Size(360, 640), Size(411, 800)]) {
        tester.view.devicePixelRatio = 1;
        tester.view.physicalSize = size;
        final api = _ShopsApi();
        final auth = AuthController(api: api)..restoring = false;

        await tester.pumpWidget(
          _app(const ShopsScreen(), auth: auth, dark: dark),
        );
        await tester.pumpAndSettle();

        final search = tester.getRect(find.byType(TextField));
        final narrow = size.width < 380;
        final chipFinder = find.byType(FilterChip);
        final chip = tester.getRect(chipFinder);
        final nearest = tester.widget<FilterChip>(chipFinder);

        expect(nearest.tooltip, narrow ? 'Nearest' : isNull);
        expect(
          find.descendant(of: chipFinder, matching: find.text('Nearest')),
          narrow ? findsNothing : findsOneWidget,
        );
        expect((search.center.dy - chip.center.dy).abs(), lessThan(1));
        expect(chip.left, greaterThanOrEqualTo(search.right));
        expect(tester.takeException(), isNull);
      }
    }
  });
}
