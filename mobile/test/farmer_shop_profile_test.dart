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

    expect(find.byKey(const ValueKey('shop-checklist')), findsOneWidget);
    expect(find.text(s.finishProgress(3, 5)), findsOneWidget);
    expect(find.text(s.onlinePaymentIsOff), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('shop-edit')));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextField, 'malaki ako'), 'sariwa ang ani');
    await tester.ensureVisible(find.text(s.saveShopProfile));
    await tester.pumpAndSettle();
    await tester.tap(find.text(s.saveShopProfile));
    await tester.pumpAndSettle();

    expect(find.text('sariwa ang ani'), findsOneWidget);
    expect(find.text('malaki ako'), findsNothing);
    expect(find.text('No bio yet'), findsNothing);
  });

  testWidgets('a finished shop shows About and the payment status, not the checklist', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    const full = ShopProfile(
      id: 5,
      shopName: 'Kuya Jun Harvest',
      name: 'Jun',
      bio: 'Fresh vegetables from our family farm.',
      location: 'Pooc II, Silang, Cavite',
      contact: '0917 123 4567',
      avatarUrl: 'https://example.test/jun.jpg',
      acceptsOnlinePayment: true,
      paymentTimeLimitHours: 24,
      paymentQrs: [
        PaymentQrCode(id: 1, wallet: 'gcash', accountName: 'Jun', accountLast4: '1234'),
        PaymentQrCode(id: 2, wallet: 'maya', accountName: 'Jun', accountLast4: '5678'),
      ],
    );

    await tester.pumpWidget(
      _app(
        home: const FarmerProfileScreen(preview: full),
        language: CropLanguage.english,
      ),
    );
    await tester.pump();
    // The photo URL does not load in tests; only the layout matters here.
    tester.takeException();

    final s = AppStrings(false);
    expect(find.byKey(const ValueKey('shop-checklist')), findsNothing);
    expect(find.byKey(const ValueKey('shop-about')), findsOneWidget);
    expect(find.text('0917 123 4567'), findsOneWidget);
    expect(find.text(s.onlinePaymentIsOn), findsOneWidget);
    expect(find.text(s.onlinePaymentDetail(2, 24)), findsOneWidget);
    expect(find.text(s.changeCover), findsNothing);
    expect(find.text(s.addCover), findsOneWidget);
  });
}
