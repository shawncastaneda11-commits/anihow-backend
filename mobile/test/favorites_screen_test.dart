import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/favorites_screen.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app({required Widget home}) {
  final auth = AuthController()
    ..restoring = false
    ..user = const UserAccount(
      id: 1,
      name: 'Maria',
      email: 'maria@example.com',
      roles: ['buyer'],
      emailVerifiedAt: '2026-01-01T00:00:00Z',
    );
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => PreferencesController()),
      ChangeNotifierProvider.value(value: auth),
    ],
    child: MaterialApp(
      theme: AniHowTheme.light(),
      home: Scaffold(body: home),
    ),
  );
}

const _stores = [
  ShopFavoriteRecord(
    id: 1,
    sellerId: 7,
    inStockCount: 6,
    shop: ShopProfile(
      id: 7,
      shopName: 'Aling Nena Produce',
      name: 'Nena Villanueva',
      location: 'Manggahan, General Trias',
    ),
  ),
];

void main() {
  testWidgets('Favorites with nothing saved explains how to add some', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(home: const FavoritesScreen(preview: FavoritesPreview())),
    );
    await tester.pump();

    expect(find.text('Crops'), findsNothing);
    expect(find.text('No favorites yet'), findsOneWidget);
    expect(find.byKey(const ValueKey('favorites-empty')), findsOneWidget);
  });

  testWidgets('Favorites lists one saved store', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const FavoritesScreen(preview: FavoritesPreview(stores: _stores)),
      ),
    );
    await tester.pump();

    expect(find.text('Aling Nena Produce'), findsOneWidget);
    expect(
      find.textContaining('Manggahan, General Trias · 6 in stock', findRichText: true),
      findsOneWidget,
    );
    expect(find.text('PYAP Manggahan Chapter'), findsNothing);
    expect(find.text(AppStrings(false).noFavoriteStores), findsNothing);
    expect(find.text(AppStrings(false).noFavoriteFarms), findsOneWidget);
    expect(find.byKey(const ValueKey('unfavorite-store-7')), findsOneWidget);
    expect(find.byIcon(Icons.delete_outline), findsNothing);
  });

  testWidgets('Favorites is one page: fresh produce, stores, then farms', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const FavoritesScreen(
          preview: FavoritesPreview(
            stores: _stores,
            farms: [
              FarmFavoriteRecord(id: 2, farmId: 3, name: 'PYAP Manggahan', place: 'General Trias', sellersCount: 4),
            ],
            fresh: [
              ListingItem(id: 11, title: 'Kalabasa', pricePerUnit: '36', quantityAvailable: '25', unit: 'kg', sellerName: 'Bautista Morning Pick'),
            ],
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.byType(TabBar), findsNothing);
    expect(find.textContaining('Fresh from your favorites', findRichText: true), findsOneWidget);
    expect(find.byKey(const ValueKey('favorites-fresh-11')), findsOneWidget);
    expect(find.textContaining('General Trias · 4 stores', findRichText: true), findsOneWidget);

    final fresh = tester.getTopLeft(find.textContaining('Fresh from your favorites', findRichText: true)).dy;
    final stores = tester.getTopLeft(find.textContaining('Stores  1', findRichText: true)).dy;
    final farms = tester.getTopLeft(find.textContaining('Farms  1', findRichText: true)).dy;
    expect(fresh, lessThan(stores));
    expect(stores, lessThan(farms));
  });
}
