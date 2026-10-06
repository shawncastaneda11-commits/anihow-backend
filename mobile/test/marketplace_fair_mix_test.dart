import 'package:anihow/models/models.dart';
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

class _FairApi extends ApiClient {
  _FairApi({required this.listings}) : super(onUnauthorized: () {});

  final List<ListingItem> listings;
  final String mixDay = '2026-10-06';
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
  }) async {
    calls.add({'sort': sort, 'page': page, 'mixDay': mixDay});
    return MarketplaceFeed(items: listings, mixDay: this.mixDay);
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

ListingItem _listing(int id) {
  return ListingItem(
    id: id,
    title: 'Kamatis $id',
    pricePerUnit: '40',
    quantityAvailable: '8',
  );
}

Widget _app(_FairApi api) {
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
  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
  });

  testWidgets('the default request is fair and the next page keeps mix day', (
    tester,
  ) async {
    SharedPreferences.setMockInitialValues({});
    await tester.binding.setSurfaceSize(const Size(400, 640));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _FairApi(
      listings: [for (var id = 1; id <= 15; id++) _listing(id)],
    );

    await tester.pumpWidget(_app(api));
    await tester.pumpAndSettle();

    expect(api.calls.first['sort'], 'fair');
    expect(api.calls.first['mixDay'], isNull);

    final position = tester
        .widget<CustomScrollView>(find.byType(CustomScrollView))
        .controller!
        .position;
    position.jumpTo(position.maxScrollExtent);
    await tester.pumpAndSettle();

    expect(api.calls.length, greaterThan(1));
    expect(api.calls[1]['page'], 2);
    expect(api.calls[1]['mixDay'], '2026-10-06');
  });

  testWidgets('the sort sheet lists fair mix first and newest', (tester) async {
    SharedPreferences.setMockInitialValues({});
    await tester.binding.setSurfaceSize(const Size(400, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _FairApi(listings: [_listing(1)]);

    await tester.pumpWidget(_app(api));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('marketplace-filter')));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('sort-fair')), findsOneWidget);
    expect(
      find.text('Every farm takes turns at the top. Changes daily.'),
      findsOneWidget,
    );
    expect(find.byKey(const Key('sort-newest')), findsOneWidget);
    final fair = tester.getTopLeft(find.byKey(const Key('sort-fair')));
    final newest = tester.getTopLeft(find.byKey(const Key('sort-newest')));
    final fairIsFirst =
        fair.dy < newest.dy - 1 ||
        ((fair.dy - newest.dy).abs() < 1 && fair.dx < newest.dx);
    expect(fairIsFirst, isTrue);
  });
}
