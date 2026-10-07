import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/cart_screen.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

const _item = CartLine(
  id: 1,
  quantity: '9',
  listedPrice: '40',
  lineSubtotal: '360',
  tawadAmount: '10',
  lineTotal: '350',
  listing: ListingItem(
    id: 7,
    title: 'Talong, mahaba',
    pricePerUnit: '40',
    quantityAvailable: '196',
    unit: 'kg',
    unitLabel: 'Kilogram (kg)',
  ),
);

void main() {
  testWidgets('updating cart quantity does not dispose the field during pop', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    String? result;
    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => PreferencesController(),
        child: MaterialApp(
          theme: AniHowTheme.light(),
          home: Builder(
            builder: (context) {
              return Scaffold(
                body: TextButton(
                  onPressed: () async {
                    result = await showDialog<String>(
                      context: context,
                      builder: (_) => const CartQuantityDialog(item: _item),
                    );
                  },
                  child: const Text('open'),
                ),
              );
            },
          ),
        ),
      ),
    );

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), '5');
    await tester.tap(find.byKey(const ValueKey('cart-quantity-update')));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(result, '5');
  });
}
