import 'package:anihow/models/models.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/produce_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app({required Widget home, required double textScale}) {
  return ChangeNotifierProvider(
    create: (_) => PreferencesController(),
    child: MaterialApp(
      theme: AniHowTheme.light(),
      builder: (context, child) {
        return MediaQuery(
          data: MediaQuery.of(context).copyWith(
            textScaler: TextScaler.linear(textScale),
          ),
          child: child!,
        );
      },
      home: Scaffold(body: home),
    ),
  );
}

const _listing = ListingItem(
  id: 1,
  title: 'Fresh kamatis, hand picked',
  pricePerUnit: '30',
  quantityAvailable: '20',
  unit: 'kg',
  sellerName: 'Juan Farm Stall',
  category: CategoryItem(id: 1, name: 'Tomato', labelEn: 'Tomato'),
  tawad: TawadRule(
    id: 3,
    type: 'flat',
    discountAmount: '5',
  ),
);

void main() {
  for (final scale in [1.3, 2.0]) {
    testWidgets('marketplace poster card does not overflow at textScaler $scale', (tester) async {
      await tester.binding.setSurfaceSize(const Size(360, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));

      await tester.pumpWidget(
        _app(
          textScale: scale,
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

      expect(tester.takeException(), isNull);
      expect(find.text('Fresh kamatis, hand picked'), findsOneWidget);
    });
  }
}
