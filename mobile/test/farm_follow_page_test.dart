import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/farm_page_screen.dart';
import 'package:anihow/screens/buyer/favorites_screen.dart';
import 'package:anihow/screens/buyer/shops_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
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

const _plain = FarmProfile(
  id: 1,
  name: 'Manggahan Farm',
  barangay: 'Manggahan',
  municipality: 'General Trias',
  farmerSellersCount: 2,
);

const _rich = FarmProfile(
  id: 2,
  name: 'Harvest Farm',
  description: 'Morning harvest.',
  contactPerson: 'Aling Nena',
  contactNumber: '09171230001',
  barangay: 'San Francisco',
  municipality: 'General Trias',
  pickupPoint: 'Barangay hall',
  latitude: 14.28,
  longitude: 120.88,
  farmerSellersCount: 1,
  announcements: [
    FarmAnnouncement(
      id: 8,
      title: 'Harvest morning',
      body: 'Tomatoes are ready.',
      createdAt: '2026-10-05T08:00:00Z',
    ),
  ],
  photos: [
    FarmPhotoItem(id: 4, url: 'https://example.test/tomato.jpg', caption: 'Tomatoes'),
  ],
);

const _empty = FarmProfile(
  id: 3,
  name: 'Quiet Farm',
  barangay: 'Biclatan',
  municipality: 'General Trias',
);

const _shops = [
  ShopProfile(
    id: 4,
    shopName: 'Nena Stall',
    name: 'Nena',
    location: 'Manggahan',
    farmId: 1,
    farmName: 'Manggahan Farm',
    farmBarangay: 'Manggahan',
    farmMunicipality: 'General Trias',
    farmIsActive: true,
    averageRating: '4.8',
    reviewsCount: 3,
  ),
  ShopProfile(
    id: 5,
    shopName: 'Tonyo Stall',
    name: 'Tonyo',
    location: 'Manggahan',
    farmId: 1,
    farmName: 'Manggahan Farm',
    farmBarangay: 'Manggahan',
    farmMunicipality: 'General Trias',
    farmIsActive: true,
  ),
];

class _FarmApi extends ApiClient {
  _FarmApi(this.profile) : super(onUnauthorized: () {});

  FarmProfile profile;
  int adds = 0;
  int removes = 0;

  @override
  Future<FarmProfile> farm(int farmId) async => profile;

  @override
  Future<List<ShopProfile>> buyerShops({
    String? sort,
    double? nearLat,
    double? nearLng,
  }) async => _shops;

  @override
  Future<List<ShopFavoriteRecord>> shopFavorites() async => const [];

  @override
  Future<void> addFarmFavorite(int farmId) async {
    adds += 1;
  }

  @override
  Future<void> removeFarmFavorite(int farmId) async {
    removes += 1;
  }
}

Widget _app(Widget home, _FarmApi api) {
  final auth = AuthController(api: api)..restoring = false;
  auth.user = _buyer;
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => PreferencesController()),
      ChangeNotifierProvider<AuthController>.value(value: auth),
    ],
    child: MaterialApp(theme: AniHowTheme.light(), home: home),
  );
}

void main() {
  testWidgets('header shows the farm name and location', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _FarmApi(_plain);

    await tester.pumpWidget(_app(const FarmPageScreen(farmId: 1), api));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('farm-name')), findsOneWidget);
    expect(find.text('Manggahan Farm'), findsWidgets);
    expect(find.byKey(const Key('farm-place')), findsOneWidget);
    expect(find.text('Manggahan, General Trias'), findsOneWidget);
    expect(find.byKey(const Key('farm-cover-fallback')), findsOneWidget);
    expect(find.text('2 shops'), findsOneWidget);
    expect(find.text('4.8'), findsWidgets);
  });

  testWidgets('Follow toggles and calls the farm favorite API', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _FarmApi(_plain);
    final s = AppStrings(false);

    await tester.pumpWidget(_app(const FarmPageScreen(farmId: 1), api));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('farm-directions')), findsNothing);
    await tester.tap(find.byKey(const Key('farm-follow')));
    await tester.pumpAndSettle();

    expect(api.adds, 1);
    expect(find.text(s.followingFarm), findsOneWidget);

    await tester.tap(find.byKey(const Key('farm-follow')));
    await tester.pumpAndSettle();

    expect(api.removes, 1);
    expect(find.text(s.followFarm), findsWidgets);
  });

  testWidgets('Directions shows only when the farm has a pin', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(const FarmPageScreen(farmId: 2), _FarmApi(_rich)),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(find.byKey(const Key('farm-directions')), findsOneWidget);
  });

  testWidgets('tabs show farm content', (tester) async {
    await tester.binding.setSurfaceSize(const Size(800, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final s = AppStrings(false);

    await tester.pumpWidget(
      _app(const FarmPageScreen(farmId: 2), _FarmApi(_rich)),
    );
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.byKey(const Key('farm-tab-about')));
    await tester.tap(find.byKey(const Key('farm-tab-about')));
    await tester.pumpAndSettle();
    expect(find.text('Morning harvest.'), findsOneWidget);
    expect(find.text('Barangay hall'), findsOneWidget);
    expect(find.text('Aling Nena'), findsOneWidget);
    expect(find.text(s.farmContactBuyerHint), findsOneWidget);
    expect(find.text('09171230001'), findsNothing);

    await tester.ensureVisible(find.byKey(const Key('farm-tab-updates')));
    await tester.tap(find.byKey(const Key('farm-tab-updates')));
    await tester.pumpAndSettle();
    expect(find.text('Harvest morning'), findsOneWidget);
    expect(find.text('2026-10-05'), findsOneWidget);
    expect(find.text('Tomatoes are ready.'), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('farm-tab-photos')));
    await tester.tap(find.byKey(const Key('farm-tab-photos')));
    await tester.pumpAndSettle();
    expect(find.text('Tomatoes'), findsOneWidget);
  });

  testWidgets('empty updates and photos', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(800, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final s = AppStrings(false);

    await tester.pumpWidget(
      _app(const FarmPageScreen(farmId: 3), _FarmApi(_empty)),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('farm-updates-action')));
    await tester.pumpAndSettle();
    expect(find.text(s.noUpdatesYet), findsOneWidget);

    await tester.tap(find.byKey(const Key('farm-tab-photos')));
    await tester.pumpAndSettle();
    expect(find.text(s.noFarmPhotos), findsOneWidget);
  });

  testWidgets('a farm card heart follows the farm', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _FarmApi(_plain);

    await tester.pumpWidget(_app(const ShopsScreen(), api));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('farm-follow-1')));
    await tester.pumpAndSettle();

    expect(api.adds, 1);
    expect(find.byIcon(Icons.favorite), findsWidgets);
  });

  testWidgets('Favorites lists a followed farm and opens it', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final s = AppStrings(false);

    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => PreferencesController(),
        child: MaterialApp(
          theme: AniHowTheme.light(),
          home: const Scaffold(
            body: FavoritesScreen(
              preview: FavoritesPreview(
                farms: [
                  FarmFavoriteRecord(
                    id: 9,
                    farmId: 1,
                    name: 'Manggahan Farm',
                    place: 'Manggahan, General Trias',
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pump();

    await tester.tap(find.byKey(const Key('favorite-farms-tab')));
    await tester.pumpAndSettle();

    expect(find.text('Manggahan Farm'), findsOneWidget);
    expect(find.text('Manggahan, General Trias'), findsOneWidget);
    expect(find.text(s.noFavoriteFarms), findsNothing);
  });
}
