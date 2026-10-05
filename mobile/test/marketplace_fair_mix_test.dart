import 'dart:io';

import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/marketplace_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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

Future<void> _loadRoboto() async {
  final loader = FontLoader('Roboto');
  final roots = [
    'C:/flutter/bin/cache/artifacts/material_fonts',
    'C:/Users/joshua/flutter/bin/cache/artifacts/material_fonts',
  ];
  var loaded = false;
  for (final root in roots) {
    if (!File('$root/roboto-regular.ttf').existsSync()) {
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
    loaded = true;
    break;
  }
  if (!loaded) {
    final bytes = await File(r'C:\Windows\Fonts\arial.ttf').readAsBytes();
    loader.addFont(Future.value(ByteData.sublistView(bytes)));
  }
  await loader.load();
}

void main() {
  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
    await _loadRoboto();
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

    expect(find.text('Fair mix'), findsOneWidget);
    expect(
      find.text('Every farm takes turns at the top. Changes daily.'),
      findsOneWidget,
    );
    expect(find.text('Newest'), findsOneWidget);
    expect(
      tester.getTopLeft(find.text('Fair mix')).dy,
      lessThan(tester.getTopLeft(find.text('Newest')).dy),
    );
  });
}
