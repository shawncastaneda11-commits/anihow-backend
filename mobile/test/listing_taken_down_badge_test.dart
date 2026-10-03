import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/listing_active_badge.dart';
import 'package:anihow/widgets/produce_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

const _takenDown = ListingItem(
  id: 8,
  title: 'Talong, mahaba',
  pricePerUnit: '40',
  quantityAvailable: '196',
  unit: 'kg',
  isActive: true,
  status: 'taken_down',
  takedownReason: 'Item not allowed to be sold.',
);

void main() {
  testWidgets('a taken-down listing shows inactive and cannot be toggled', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    var tapped = false;
    final s = AppStrings(false);

    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => PreferencesController(),
        child: MaterialApp(
          theme: AniHowTheme.light(),
          home: Scaffold(
            body: ProduceCard(
              listing: _takenDown,
              showSeller: false,
              showStock: true,
              trailing: ListingActiveBadge(
                isActive: _takenDown.isSellerActive,
                onTap: _takenDown.isTakenDown ? null : () => tapped = true,
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.text(s.listingInactive), findsOneWidget);
    expect(find.text(s.takenDown), findsOneWidget);
    expect(find.text(s.listingActive), findsNothing);

    await tester.tap(find.text(s.listingInactive));
    await tester.pump();
    expect(tapped, isFalse);
  });
}
