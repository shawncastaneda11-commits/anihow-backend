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

const _stores = [
  ShopFavoriteRecord(
    id: 1,
    sellerId: 7,
    shop: ShopProfile(
      id: 7,
      shopName: 'Aling Nena Produce',
      name: 'Nena Villanueva',
      location: 'Manggahan, General Trias',
    ),
  ),
];

void main() {
  testWidgets('Favorites shows the store empty state', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(home: const FavoritesScreen(preview: FavoritesPreview())),
    );
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text(s.favoriteCrops), findsNothing);
    expect(find.text(s.noFavoriteStores), findsOneWidget);
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
    expect(find.text('Nena Villanueva'), findsOneWidget);
    expect(find.text('Manggahan, General Trias'), findsOneWidget);
    expect(find.text('PYAP Manggahan Chapter'), findsNothing);
    expect(find.text(AppStrings(false).noFavoriteStores), findsNothing);
  });
}
