import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/favorites_screen.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app({required Widget home}) {
  return ChangeNotifierProvider(
    create: (_) => PreferencesController(),
    child: MaterialApp(
      theme: AniHowTheme.light(),
      home: Scaffold(body: home),
    ),
  );
}

const _shop = ShopProfile(
  id: 7,
  shopName: 'Aling Nena Produce',
  name: 'Nena',
  location: 'Manggahan',
);

const _stores = [
  ShopFavoriteRecord(id: 1, sellerId: 7, shop: _shop),
];

void main() {
  testWidgets('Favorites crops tab shows the crop empty state', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(home: const FavoritesScreen(preview: FavoritesPreview(stores: _stores))),
    );
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text(s.favoriteCrops), findsOneWidget);
    expect(find.text(s.favoriteStores), findsOneWidget);
    expect(find.text(s.noFavorites), findsOneWidget);
    expect(find.text('Aling Nena Produce'), findsNothing);
  });

  testWidgets('Favorites stores tab lists saved shops', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const FavoritesScreen(
          preview: FavoritesPreview(kind: FavoriteKind.stores, stores: _stores),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Aling Nena Produce'), findsOneWidget);
    expect(find.text('Manggahan'), findsOneWidget);
    expect(find.text(AppStrings(false).noFavorites), findsNothing);
  });

  testWidgets('Favorites toggle switches from crops to stores', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(home: const FavoritesScreen(preview: FavoritesPreview(stores: _stores))),
    );
    await tester.pump();

    await tester.tap(find.byKey(const ValueKey('favorite-stores-tab')));
    await tester.pumpAndSettle();

    expect(find.text('Aling Nena Produce'), findsOneWidget);
  });
}
