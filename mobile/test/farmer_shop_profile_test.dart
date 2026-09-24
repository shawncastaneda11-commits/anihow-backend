import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/profile/profile_screen.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/support/crop_language.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app({required Widget home, CropLanguage language = CropLanguage.filipino}) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => AuthController()..restoring = false),
      ChangeNotifierProvider(
        create: (_) => PreferencesController()..language = language,
      ),
    ],
    child: MaterialApp(
      theme: AniHowTheme.dark(),
      home: home,
    ),
  );
}

const _shop = ShopProfile(
  id: 4,
  shopName: 'Aling Nena Produce',
  name: 'Nena',
  bio: 'malaki ako',
  location: 'Manggahan, General Trias',
);

void main() {
  testWidgets('saved Tagalog bio shows on the shop profile without switching language', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(home: const FarmerProfileScreen(preview: _shop)),
    );
    await tester.pump();

    final s = AppStrings(true);
    expect(find.text('malaki ako'), findsOneWidget);

    await tester.tap(find.text(s.editShopProfile));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextField, 'malaki ako'), 'sariwa ang ani');
    await tester.tap(find.text(s.saveShopProfile));
    await tester.pumpAndSettle();

    expect(find.text('sariwa ang ani'), findsOneWidget);
    expect(find.text('malaki ako'), findsNothing);
    expect(find.text(s.noBioYet), findsNothing);
  });
}
