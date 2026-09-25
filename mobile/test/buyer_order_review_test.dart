import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/buyer_order_detail_screen.dart';
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

const _order = OrderRecord(
  id: 9,
  status: 'completed',
  statusLabel: 'Completed',
  total: '1480',
  items: [],
  shopName: 'Aling Nena Produce',
  canBeReviewed: true,
);

void main() {
  testWidgets('completed order shows a review form the buyer can submit', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(home: const BuyerOrderDetailScreen(order: _order, preview: true)),
    );
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text(s.writeReview), findsOneWidget);
    expect(find.text(s.submitReview), findsOneWidget);
    expect(find.text(s.readyToReview), findsNothing);

    await tester.ensureVisible(find.byKey(const ValueKey('review-star-5')));
    await tester.tap(find.byKey(const ValueKey('review-star-5')));
    await tester.pump();
    await tester.ensureVisible(find.text(s.submitReview));
    await tester.tap(find.text(s.submitReview));
    await tester.pumpAndSettle();

    expect(find.text(s.youRated(5)), findsOneWidget);
    expect(find.text(s.submitReview), findsNothing);
  });
}
