import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/farm_page_screen.dart';
import 'package:anihow/screens/buyer/shops_screen.dart';
import 'package:anihow/screens/farm/farm_profile_screen.dart';
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
);

const _logoFarm = FarmProfile(
  id: 1,
  name: 'Manggahan Farm',
  barangay: 'Manggahan',
  municipality: 'General Trias',
  logoUrl: 'https://example.test/logo.jpg',
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
  ),
];

const _logoShops = [
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
    farmLogoUrl: 'https://example.test/logo.jpg',
  ),
];

class _FarmApi extends ApiClient {
  _FarmApi(this.profile, this.shops) : super(onUnauthorized: () {});

  final FarmProfile profile;
  final List<ShopProfile> shops;

  @override
  Future<FarmProfile> farm(int farmId) async => profile;

  @override
  Future<List<ShopProfile>> buyerShops({
    String? sort,
    double? nearLat,
    double? nearLng,
  }) async => shops;

  @override
  Future<List<ShopFavoriteRecord>> shopFavorites() async => const [];
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
  void ignoreMissingImages(WidgetTester tester) {
    final previous = FlutterError.onError;
    FlutterError.onError = (details) {
      if (details.library == 'image resource service') {
        return;
      }
      previous?.call(details);
    };
    addTearDown(() => FlutterError.onError = previous);
  }

  testWidgets('the farm header uses initials when there is no logo', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(const FarmPageScreen(farmId: 1), _FarmApi(_plain, _shops)),
    );
    await tester.pumpAndSettle();

    final avatar = tester.widget<CircleAvatar>(
      find.descendant(
        of: find.byKey(const Key('farm-logo')),
        matching: find.byType(CircleAvatar),
      ),
    );
    expect(avatar.backgroundImage, isNull);
    expect(
      find.descendant(
        of: find.byKey(const Key('farm-logo')),
        matching: find.text('MF'),
      ),
      findsOneWidget,
    );
  });

  testWidgets('the farm header shows the logo when one is set', (tester) async {
    ignoreMissingImages(tester);
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(const FarmPageScreen(farmId: 1), _FarmApi(_logoFarm, _shops)),
    );
    await tester.pumpAndSettle();

    final avatar = tester.widget<CircleAvatar>(
      find.descendant(
        of: find.byKey(const Key('farm-logo')),
        matching: find.byType(CircleAvatar),
      ),
    );
    expect(avatar.backgroundImage, isA<NetworkImage>());
    expect(
      find.descendant(
        of: find.byKey(const Key('farm-logo')),
        matching: find.text('MF'),
      ),
      findsNothing,
    );
  });

  testWidgets('farm cards and chips show the logo or the fallback', (
    tester,
  ) async {
    ignoreMissingImages(tester);
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(const ShopsScreen(), _FarmApi(_plain, _shops)),
    );
    await tester.pumpAndSettle();

    final plain = tester.widget<CircleAvatar>(
      find.descendant(
        of: find.byKey(const Key('farm-card-logo-1')),
        matching: find.byType(CircleAvatar),
      ),
    );
    expect(plain.backgroundImage, isNull);
    expect(find.text('MF'), findsWidgets);

    await tester.pumpWidget(
      _app(
        const ShopsScreen(key: ValueKey('with-logo')),
        _FarmApi(_plain, _logoShops),
      ),
    );
    await tester.pumpAndSettle();

    final withLogo = tester.widget<CircleAvatar>(
      find.descendant(
        of: find.byKey(const Key('farm-card-logo-1')),
        matching: find.byType(CircleAvatar),
      ),
    );
    expect(withLogo.backgroundImage, isA<NetworkImage>());

    await tester.pumpWidget(
      MaterialApp(
        theme: AniHowTheme.light(),
        home: const Scaffold(
          body: FarmLinkChip(farmId: 1, label: 'Manggahan Farm'),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.byIcon(Icons.agriculture_outlined), findsOneWidget);
    expect(find.byKey(const Key('farm-chip-logo')), findsNothing);

    await tester.pumpWidget(
      MaterialApp(
        theme: AniHowTheme.light(),
        home: const Scaffold(
          body: FarmLinkChip(
            farmId: 1,
            label: 'Manggahan Farm',
            logoUrl: 'https://example.test/logo.jpg',
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('farm-chip-logo')), findsOneWidget);
  });
}
