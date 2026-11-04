import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/tawad_form_screen.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/primary_button.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

final _listing = ListingItem.fromJson({
  'id': 7,
  'title': 'Sitaw, bagong pitas',
  'unit': 'bundle',
  'price_per_unit': 20,
  'quantity_available': 40,
  'min_order_quantity': 1,
  'order_step': 1,
  'price_guard': {
    'unit': 'bundle',
    'floor_per_unit': 15,
    'max_tawad_per_unit': 5,
    'unit_allowed': true,
  },
});

Future<void> _pump(WidgetTester tester) async {
  await tester.binding.setSurfaceSize(const Size(390, 1400));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => PreferencesController()),
        ChangeNotifierProvider(create: (_) => AuthController()),
      ],
      child: MaterialApp(
        theme: AniHowTheme.light(),
        home: TawadFormScreen(listing: _listing),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

bool _saveEnabled(WidgetTester tester) =>
    tester
        .widget<PrimaryButton>(find.byKey(const ValueKey('save-tawad')))
        .onPressed !=
    null;

void main() {
  testWidgets('the limits show per bundle in one card', (tester) async {
    await _pump(tester);

    expect(find.text('Lowest price'), findsOneWidget);
    expect(find.text('Max tawad'), findsOneWidget);
    expect(
      tester
          .widget<RichText>(
            find.descendant(
              of: find.byKey(const ValueKey('tawad-max-per-unit')),
              matching: find.byType(RichText),
            ),
          )
          .text
          .toPlainText(),
      '₱5.00 / bundle',
    );
  });

  testWidgets('a bulk tawad over the maximum is flagged before saving', (tester) async {
    await _pump(tester);

    await tester.tap(find.text('Bulk orders'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const ValueKey('tawad-amount')), '18');
    await tester.enterText(
      find.byKey(const ValueKey('tawad-min-quantity')),
      '3',
    );
    await tester.pumpAndSettle();

    expect(
      find.text('That is ₱6.00 off each bundle. The most is ₱5.00.'),
      findsOneWidget,
    );
    expect(_saveEnabled(tester), isFalse);

    await tester.enterText(find.byKey(const ValueKey('tawad-amount')), '15');
    await tester.pumpAndSettle();

    expect(
      find.text('Within your limits: ₱5.00 off each bundle.'),
      findsOneWidget,
    );
    expect(find.text('₱45.00'), findsOneWidget);
    expect(_saveEnabled(tester), isTrue);
  });
}
