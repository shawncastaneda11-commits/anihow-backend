import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/farm_page_screen.dart';
import 'package:anihow/screens/buyer/shop_profile_screen.dart';
import 'package:anihow/screens/buyer/shops_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/farm_map_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

const _farm = FarmProfile(
  id: 1,
  name: 'Manggahan Farm',
  barangay: 'Manggahan',
  municipality: 'General Trias',
  pickupPoint: 'Barangay hall',
  storefronts: [FarmStorefront(id: 99, shopName: 'Plain List Stall')],
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
  _FarmApi() : super(onUnauthorized: () {});

  @override
  Future<List<ShopProfile>> buyerShops({
    String? sort,
    double? nearLat,
    double? nearLng,
  }) async => _shops;

  @override
  Future<FarmProfile> farm(int farmId) async => _farm;

  @override
  Future<List<ShopFavoriteRecord>> shopFavorites() async => const [];

  @override
  Future<ShopProfile> buyerShop(int sellerId) async =>
      _shops.firstWhere((shop) => shop.id == sellerId);
}

Widget _app(Widget home) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => PreferencesController()),
      ChangeNotifierProvider(
        create: (_) => AuthController(api: _FarmApi())..restoring = false,
      ),
    ],
    child: MaterialApp(theme: AniHowTheme.light(), home: home),
  );
}

void main() {
  testWidgets('tapping a farm card opens the profile and its shops', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(_app(const ShopsScreen()));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Manggahan Farm'));
    await tester.pumpAndSettle();

    final s = AppStrings(false);
    expect(find.text('Manggahan Farm'), findsWidgets);
    expect(find.text('Manggahan, General Trias'), findsOneWidget);
    await tester.tap(find.byKey(const Key('farm-tab-shops')));
    await tester.pumpAndSettle();
    expect(find.text('Nena Stall'), findsOneWidget);
    expect(find.text('Tonyo Stall'), findsOneWidget);
    expect(find.text(s.shopsAtThisFarm(2)), findsOneWidget);
    expect(find.text(s.farmStorefronts), findsNothing);
    expect(find.text('Plain List Stall'), findsNothing);
    expect(find.byType(FarmMapCard), findsNothing);
    expect(find.byIcon(Icons.favorite_outline), findsWidgets);
    await tester.tap(find.byKey(const Key('farm-tab-about')));
    await tester.pumpAndSettle();
    expect(find.text('Barangay hall'), findsOneWidget);
    await tester.tap(find.byKey(const Key('farm-tab-shops')));
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byWidgetPredicate(
        (widget) =>
            widget is TextField && widget.decoration?.hintText == s.searchShops,
      ),
      'Nena',
    );
    await tester.pump();

    expect(find.text('Nena Stall'), findsOneWidget);
    expect(find.text('Tonyo Stall'), findsNothing);
  });

  testWidgets('a storefront farm chip opens the same farm page', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(ShopProfileScreen(sellerId: 4, preview: _shops[0])),
    );
    await tester.pumpAndSettle();

    final s = AppStrings(false);
    await tester.ensureVisible(find.text(s.farmLine('Manggahan Farm')));
    await tester.tap(find.text(s.farmLine('Manggahan Farm')));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('farm-tab-about')));
    await tester.pumpAndSettle();
    expect(find.text('Barangay hall'), findsOneWidget);
    await tester.tap(find.byKey(const Key('farm-tab-shops')));
    await tester.pumpAndSettle();
    expect(find.text('Nena Stall'), findsWidgets);
    expect(find.text('Tonyo Stall'), findsOneWidget);
    expect(find.text(s.farmStorefronts), findsNothing);
    expect(find.byType(FarmPageScreen), findsOneWidget);
  });
}
