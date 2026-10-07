import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/farm_page_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

ShopProfile _shop({
  required int id,
  String? rating,
  int reviews = 0,
}) {
  return ShopProfile(
    id: id,
    shopName: 'Stall $id',
    name: 'Seller $id',
    farmId: 1,
    farmName: 'Manggahan Farm',
    farmIsActive: true,
    averageRating: rating,
    reviewsCount: reviews,
  );
}

class _Api extends ApiClient {
  _Api(this.shops) : super(onUnauthorized: () {});

  final List<ShopProfile> shops;

  @override
  Future<FarmProfile> farm(int farmId) async {
    return const FarmProfile(
      id: 1,
      name: 'Manggahan Farm',
      farmerSellersCount: 2,
    );
  }

  @override
  Future<List<ShopProfile>> buyerShops({
    String? sort,
    double? nearLat,
    double? nearLng,
  }) async => shops;

  @override
  Future<List<ShopFavoriteRecord>> shopFavorites() async => const [];
}

Widget _app(List<ShopProfile> shops, {required Key pageKey}) {
  final api = _Api(shops);
  final auth = AuthController(api: api)..restoring = false;
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => PreferencesController()),
      ChangeNotifierProvider<AuthController>.value(value: auth),
    ],
    child: MaterialApp(
      theme: AniHowTheme.light(),
      home: FarmPageScreen(key: pageKey, farmId: 1),
    ),
  );
}

void main() {
  test('weights the farm rating by review count', () {
    final summary = weightedFarmRating([
      _shop(id: 1, rating: '4.0', reviews: 3),
      _shop(id: 2, rating: '1.0', reviews: 1),
      _shop(id: 3),
    ]);

    expect(summary, isNotNull);
    expect(summary!.rating, '3.3');
    expect(summary.reviewsCount, 4);

    final single = weightedFarmRating([_shop(id: 4, rating: '5.0')]);
    expect(single!.rating, '5.0');
    expect(single.reviewsCount, 1);
    expect(weightedFarmRating([_shop(id: 5)]), isNull);
  });

  testWidgets(
    'the farm rating chip shows the star and review count, or stays hidden',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(400, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final s = AppStrings(false);

      await tester.pumpWidget(
        _app(
          [
            _shop(id: 1, rating: '4.0', reviews: 3),
            _shop(id: 2, rating: '1.0', reviews: 1),
          ],
          pageKey: const Key('rated-farm'),
        ),
      );
      await tester.pumpAndSettle();

      final chip = find.byKey(const Key('farm-rating'));
      expect(chip, findsOneWidget);
      expect(find.text('2 shops'), findsOneWidget);
      expect(
        find.descendant(of: chip, matching: find.byIcon(Icons.star_rounded)),
        findsOneWidget,
      );
      final star = tester.widget<Icon>(
        find.descendant(of: chip, matching: find.byIcon(Icons.star_rounded)),
      );
      expect(star.color, AniHowColors.pending);
      expect(find.descendant(of: chip, matching: find.text('3.3')), findsOneWidget);
      expect(
        find.descendant(
          of: chip,
          matching: find.text(' · ${s.reviewsCount(4)}'),
        ),
        findsOneWidget,
      );
      expect(find.byTooltip(s.farmRating), findsOneWidget);

      await tester.pumpWidget(
        _app([_shop(id: 1), _shop(id: 2)], pageKey: const Key('unrated-farm')),
      );
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('farm-rating')), findsNothing);
      expect(find.text('2 shops'), findsOneWidget);
    },
  );
}
