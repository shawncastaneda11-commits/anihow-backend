import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/shop_profile_screen.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app({required Widget home}) {
  return ChangeNotifierProvider(
    create: (_) => PreferencesController(),
    child: MaterialApp(
      theme: AniHowTheme.dark(),
      home: home,
    ),
  );
}

const _shop = ShopProfile(
  id: 7,
  shopName: 'Mang Tonyo Farm',
  name: 'Tonyo',
  location: 'Manggahan, General Trias',
);

void main() {
  testWidgets('Shop page has a Save store control', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const ShopProfileScreen(sellerId: 7, preview: _shop),
      ),
    );
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text(s.addStoreToFavorites), findsOneWidget);
    expect(find.byIcon(Icons.favorite_outline), findsWidgets);

    await tester.ensureVisible(find.text(s.addStoreToFavorites));
    await tester.tap(find.text(s.addStoreToFavorites));
    await tester.pumpAndSettle();

    expect(find.text(s.removeStoreFromFavorites), findsOneWidget);
    expect(find.text(s.addStoreToFavorites), findsNothing);
  });
}
