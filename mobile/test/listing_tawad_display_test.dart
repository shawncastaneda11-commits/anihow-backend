import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/listing_detail_screen.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_space.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/produce_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

const _listing = ListingItem(
  id: 7,
  title: 'Talong, mahaba',
  pricePerUnit: '40',
  quantityAvailable: '196',
  unit: 'kg',
  sellerId: 2,
  sellerName: 'Aling Nena Produce',
  tawad: TawadRule(
    id: 11,
    type: 'flat',
    discountAmount: '5',
  ),
);

Widget _app({required Widget home}) {
  final auth = AuthController();
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => PreferencesController()),
      ChangeNotifierProvider.value(value: auth),
      ChangeNotifierProvider(create: (_) => CartController(auth)),
    ],
    child: MaterialApp(
      theme: AniHowTheme.light(),
      home: home,
    ),
  );
}

void main() {
  final s = AppStrings(false);

  testWidgets('marketplace poster shows a tawad badge on the listing photo', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const SizedBox(
          width: 158,
          height: 292,
          child: ProduceCard(
            listing: _listing,
            style: ProduceCardStyle.poster,
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.byKey(const ValueKey('produce-tawad-badge')), findsOneWidget);
    expect(find.text(s.tawadMinus(AniHowMoney.peso('5'))), findsOneWidget);
  });

  testWidgets('listing detail shows tawad on the photo and under the price', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const ListingDetailScreen(listingId: 7, preview: _listing),
      ),
    );
    await tester.pump();

    expect(find.byKey(const ValueKey('produce-tawad-badge')), findsOneWidget);
    expect(find.byKey(const ValueKey('listing-tawad-summary')), findsOneWidget);
    expect(find.text(s.tawadOffThisOrder(AniHowMoney.peso('5'))), findsOneWidget);
  });
}
