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
    child: MaterialApp(theme: AniHowTheme.dark(), home: home),
  );
}

const _shop = ShopProfile(
  id: 7,
  shopName: 'Mang Tonyo Farm',
  name: 'Tonyo',
  location: 'Manggahan, General Trias',
  farmId: 2,
  farmName: 'PYAP Manggahan Chapter',
  farmIsActive: true,
);

void main() {
  testWidgets('Shop page saves that store', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(home: const ShopProfileScreen(sellerId: 7, preview: _shop)),
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

  testWidgets('shop page does not show a contact number', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const ShopProfileScreen(
          sellerId: 7,
          preview: ShopProfile(
            id: 7,
            shopName: 'Mang Tonyo Farm',
            name: 'Tonyo',
            contact: '09171234567',
            location: 'Manggahan, General Trias',
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('09171234567'), findsNothing);
    expect(find.byIcon(Icons.phone_outlined), findsNothing);
    expect(find.text('Mang Tonyo Farm'), findsOneWidget);
  });

  testWidgets('buyers see the seller profile layout without the seller-only parts', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const ShopProfileScreen(
          sellerId: 7,
          preview: ShopProfile(
            id: 7,
            shopName: 'Kuya Jun Harvest',
            name: 'Jun',
            bio: 'Fresh squash every week.',
            location: 'Manggahan, General Trias',
            averageRating: '4.0',
            reviewsCount: 5,
            acceptsOnlinePayment: true,
            paymentTimeLimitHours: 24,
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.byKey(const ValueKey('shop-profile-name')), findsOneWidget);
    expect(find.byKey(const ValueKey('farmer-shop-reviews')), findsOneWidget);
    expect(find.byKey(const ValueKey('shop-about')), findsOneWidget);
    expect(find.text('Fresh squash every week.'), findsOneWidget);
    expect(find.byKey(const ValueKey('shop-payments')), findsOneWidget);
    expect(find.text('Online payment or cash'), findsOneWidget);
    expect(find.text('Pickup only'), findsOneWidget);

    expect(find.byKey(const ValueKey('shop-cover-button')), findsNothing);
    expect(find.byKey(const ValueKey('shop-payments-manage')), findsNothing);
    expect(find.byKey(const ValueKey('shop-checklist')), findsNothing);
    expect(find.byKey(const ValueKey('shop-edit')), findsNothing);
  });

  testWidgets('a cash-only shop says so to buyers', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const ShopProfileScreen(
          sellerId: 8,
          preview: ShopProfile(id: 8, shopName: 'Aling Nena', name: 'Nena', acceptsOnlinePayment: false),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Cash only'), findsOneWidget);
    expect(find.text('Online payment is off'), findsNothing);
  });
}
