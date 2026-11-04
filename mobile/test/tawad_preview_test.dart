import 'package:anihow/models/models.dart';
import 'package:anihow/support/tawad_preview.dart';
import 'package:flutter_test/flutter_test.dart';

/// The tawad screen's live box must agree with what the server accepts:
/// judged per unit, against the maximum tawad and then the lowest price.
ListingItem _bundle({num? max = 5, num minOrder = 1}) => ListingItem.fromJson({
  'id': 7,
  'title': 'Sitaw',
  'unit': 'bundle',
  'price_per_unit': 20,
  'quantity_available': 40,
  'min_order_quantity': minOrder,
  'order_step': 1,
  'price_guard': {
    'unit': 'bundle',
    'floor_per_unit': 15,
    'max_tawad_per_unit': max,
    'unit_allowed': true,
  },
});

void main() {
  test('PHP 15 off 3 bundles fits and the buyer pays PHP 45', () {
    final preview = TawadPreview.of(
      _bundle(),
      bulk: true,
      amountText: '15',
      minQuantityText: '3',
    );

    expect(preview.status, TawadPreviewStatus.ok);
    expect(preview.quantity, 3);
    expect(preview.listed, 60);
    expect(preview.total, 45);
    expect(preview.perUnit, 5);
  });

  test('PHP 18 off 3 bundles is over the PHP 5 maximum', () {
    final preview = TawadPreview.of(
      _bundle(),
      bulk: true,
      amountText: '18',
      minQuantityText: '3',
    );

    expect(preview.status, TawadPreviewStatus.over);
    expect(preview.total, 60);
  });

  test('with no maximum, the lowest price is what stops it', () {
    final preview = TawadPreview.of(
      _bundle(max: null),
      bulk: true,
      amountText: '18',
      minQuantityText: '3',
    );

    expect(preview.status, TawadPreviewStatus.belowFloor);
  });

  test('a maximum of zero means tawad is turned off', () {
    final preview = TawadPreview.of(
      _bundle(max: 0),
      bulk: false,
      amountText: '1',
      minQuantityText: '',
    );

    expect(preview.status, TawadPreviewStatus.turnedOff);
  });

  test('an every-order tawad is spread over the minimum order', () {
    final preview = TawadPreview.of(
      _bundle(minOrder: 2),
      bulk: false,
      amountText: '10',
      minQuantityText: '',
    );

    expect(preview.quantity, 2);
    expect(preview.perUnit, 5);
    expect(preview.status, TawadPreviewStatus.ok);
  });

  test('nothing typed yet is neither accepted nor refused', () {
    final preview = TawadPreview.of(
      _bundle(),
      bulk: true,
      amountText: '',
      minQuantityText: '',
    );

    expect(preview.status, TawadPreviewStatus.empty);
    expect(preview.isOk, isFalse);
  });
}
